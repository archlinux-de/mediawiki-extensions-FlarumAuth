<?php

namespace MediaWiki\Extensions\FlarumAuth;

use MediaWiki\Config\ConfigFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Http\HttpRequestFactory;

class FlarumAuthenticationProvider extends AbstractPasswordPrimaryAuthenticationProvider
{
    private ?FlarumUser $flarumUser = null;

    public function __construct(
        private readonly ConfigFactory $configFactory,
        private readonly HttpRequestFactory $httpRequestFactory,
        array $params = []
    ) {
        parent::__construct($params);
    }

    public static function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8;
    }

    private function getFlarumUrl(): string
    {
        $url = $this->configFactory
            ->makeConfig('FlarumAuth')
            ->get('FlarumUrl');

        return is_string($url) ? $url : '';
    }

    private function createClient(): Client
    {
        return $this->httpRequestFactory->createGuzzleClient(['base_uri' => $this->getFlarumUrl()]);
    }

    public function beginPrimaryAuthentication(array $reqs): AuthenticationResponse
    {
        $req = AuthenticationRequest::getRequestByClass($reqs, PasswordAuthenticationRequest::class);
        if (!$req) {
            return AuthenticationResponse::newAbstain();
        }

        if (!($req instanceof PasswordAuthenticationRequest) || $req->username === null || $req->password === null) {
            return AuthenticationResponse::newAbstain();
        }

        $client = $this->createClient();
        try {
            $res = $client->request(
                'POST',
                '/api/token',
                [
                    'json' => ['identification' => $req->username, 'password' => $req->password]
                ]
            );
        } catch (ClientException $e) {
            return $this->failResponse($req);
        } catch (GuzzleException $e) {
            return AuthenticationResponse::newAbstain();
        }
        $flarumUserToken = FlarumUserToken::crateFromResponse($res->getBody()->getContents());

        try {
            $res = $client->request(
                'GET',
                '/api/users/' . $flarumUserToken->getId(),
                [
                    'headers' => [
                        'accept' => 'application/json',
                        'authorization' => 'Token ' . $flarumUserToken->getToken()
                    ]
                ]
            );
        } catch (ClientException $e) {
            return $this->failResponse($req);
        } catch (GuzzleException $e) {
            return AuthenticationResponse::newAbstain();
        }
        $this->flarumUser = FlarumUser::crateFromResponse($res->getBody()->getContents());

        $username = $this->createCanonicalName($this->flarumUser->getUserName());
        if (!$username) {
            return AuthenticationResponse::newAbstain();
        }

        return AuthenticationResponse::newPass($username);
    }

    private function createCanonicalName(string $username): string
    {
        return strtoupper(substr($username, 0, 1)) . substr($username, 1);
    }

    public function testUserExists($username, $flags = \IDBAccessObject::READ_NORMAL): bool
    {
        return false;
    }

    public function providerAllowsAuthenticationDataChange(AuthenticationRequest $req, $checkData = true): \StatusValue
    {
        return \StatusValue::newFatal('authentication data cannot be changed');
    }

    public function providerChangeAuthenticationData(AuthenticationRequest $req): void
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not implemented.');
    }

    public function accountCreationType(): string
    {
        return self::TYPE_CREATE;
    }

    public function beginPrimaryAccountCreation($user, $creator, array $reqs): AuthenticationResponse
    {
        return AuthenticationResponse::newAbstain();
    }

    public function providerAllowsPropertyChange($property): bool
    {
        return false;
    }

    public function postAuthentication($user, AuthenticationResponse $response): void
    {
        if (
            $user
            && $response->status === AuthenticationResponse::PASS
            && $this->flarumUser
            && $this->flarumUser->isEmailConfirmed()
            && $response->username == $user->getName()
        ) {
            $userUpdated = false;

            if ($user->getEmail() != $this->flarumUser->getEmail()) {
                $userUpdated = true;
                $user->setEmail($this->flarumUser->getEmail());
                $user->setEmailAuthenticationTimestamp((string)$this->flarumUser->getJoinTime()->getTimestamp());
            }

            if ($user->getRealName() != $this->flarumUser->getDisplayName()) {
                $userUpdated = true;
                $user->setRealName($this->flarumUser->getDisplayName());
            }

            if ($userUpdated) {
                $user->saveSettings();
            }
        }
    }
}
