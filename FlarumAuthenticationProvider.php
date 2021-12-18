<?php

namespace MediaWiki\Extensions\FlarumAuth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\MediaWikiServices;
use StatusValue;
use User;

class FlarumAuthenticationProvider extends AbstractPasswordPrimaryAuthenticationProvider
{
    private ?FlarumUser $flarumUser = null;

    public static function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8;
    }

    private static function getFlarumUrl(): string
    {
        $url = MediaWikiServices::getInstance()
            ->getConfigFactory()
            ->makeConfig('FlarumAuth')
            ->get('FlarumUrl');

        return is_string($url) ? $url : '';
    }

    /**
     * @param PasswordAuthenticationRequest[] $reqs
     * @return AuthenticationResponse
     */
    public function beginPrimaryAuthentication(array $reqs): AuthenticationResponse
    {
        $req = AuthenticationRequest::getRequestByClass($reqs, PasswordAuthenticationRequest::class);
        if (!$req) {
            return AuthenticationResponse::newAbstain();
        }

        if (!($req instanceof PasswordAuthenticationRequest) || $req->username === null || $req->password === null) {
            return AuthenticationResponse::newAbstain();
        }

        $client = MediaWikiServices::getInstance()->getHttpRequestFactory()->createGuzzleClient();
        try {
            $res = $client->request(
                'POST',
                self::getFlarumUrl() . '/api/token',
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
                self::getFlarumUrl() . '/api/users/' . $flarumUserToken->getId(),
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

    /**
     * @param string $username
     * @param int $flags
     * @return bool
     */
    public function testUserExists($username, $flags = User::READ_NORMAL): bool
    {
        return false;
    }

    /**
     * @param AuthenticationRequest $req
     * @param bool $checkData
     * @return StatusValue
     */
    public function providerAllowsAuthenticationDataChange(AuthenticationRequest $req, $checkData = true): StatusValue
    {
        return StatusValue::newFatal('authentication data cannot be changed');
    }

    public function providerChangeAuthenticationData(AuthenticationRequest $req): void
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not implemented.');
    }

    public function accountCreationType(): string
    {
        return self::TYPE_CREATE;
    }

    /**
     * @param User $user
     * @param User $creator
     * @param AuthenticationRequest[] $reqs
     * @return AuthenticationResponse
     */
    public function beginPrimaryAccountCreation($user, $creator, array $reqs): AuthenticationResponse
    {
        return AuthenticationResponse::newAbstain();
    }

    /**
     * @param string $property
     * @return bool
     */
    public function providerAllowsPropertyChange($property): bool
    {
        return false;
    }

    /**
     * @param User|null $user
     * @param AuthenticationResponse $response
     */
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
