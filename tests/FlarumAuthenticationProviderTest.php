<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use BadMethodCallException;
use ConfigFactory;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HashConfig;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Extensions\FlarumAuth\FlarumAuthenticationProvider;
use MediaWiki\Http\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use User;

class FlarumAuthenticationProviderTest extends TestCase
{
    private FlarumAuthenticationProvider $flarumAuthenticationProvider;

    /** @var ConfigFactory|MockObject */
    private MockObject $configFactory;

    /** @var HttpRequestFactory|MockObject */
    private MockObject $httpRequestFactory;

    public function setUp(): void
    {
        $this->configFactory = $this->createMock(ConfigFactory::class);
        $this->configFactory
            ->expects($this->any())
            ->method('makeConfig')
            ->with('FlarumAuth')
            ->willReturn(new HashConfig(['FlarumUrl' => 'http://localhost']));


        $this->httpRequestFactory = $this->createMock(HttpRequestFactory::class);

        $this->flarumAuthenticationProvider = new FlarumAuthenticationProvider(
            $this->configFactory,
            $this->httpRequestFactory,
            ['authoritative' => true]
        );
    }

    #[DataProvider('providePasswords')]
    public function testIsValidPassword(string $password, bool $valid): void
    {
        $this->assertEquals($valid, FlarumAuthenticationProvider::isValidPassword($password));
    }

    public static function providePasswords(): array
    {
        return [
            ['', false],
            ['abc', false],
            ['12345678', true],
            ['123456789', true]
        ];
    }

    public function testTestUserExists(): void
    {
        $this->assertFalse($this->flarumAuthenticationProvider->testUserExists(''));
    }

    public function testBeginPrimaryAuthentication(): void
    {
        $this->configureSuccessfulMockhandler();

        $request = new PasswordAuthenticationRequest();
        $request->username = 'bob';
        $request->password = 'foobar';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);

        $this->assertEquals(AuthenticationResponse::PASS, $response->status);
        $this->assertEquals('Bob', $response->username);
    }

    private function configureSuccessfulMockhandler(): void
    {
        $mockHandler = new MockHandler([
                                           new Response(
                                               body: (string)json_encode([
                                                                             'userId' => 1234,
                                                                             'token' => 'abcdef'
                                                                         ])
                                           ),
                                           new Response(
                                               body: (string)json_encode([
                                                                             'data' => [
                                                                                 'id' => 1234,
                                                                                 'attributes' => [
                                                                                     'username' => 'bob',
                                                                                     'displayName' => 'Mr. Bob',
                                                                                     'email' => 'bob@localhost',
                                                                                     'isEmailConfirmed' => true,
                                                                                     'joinTime' => '2021-12-01'
                                                                                 ]
                                                                             ]
                                                                         ])
                                           )
                                       ]);

        $this->configureMockHandler($mockHandler);
    }

    private function configureMockHandler(MockHandler $mockHandler): void
    {
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $this->httpRequestFactory
            ->expects($this->any())
            ->method('createGuzzleClient')
            ->willReturn($client);
    }

    public function testAbstainWhenNoPasswordRequestIsProvided(): void
    {
        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([]);

        $this->assertEquals(AuthenticationResponse::ABSTAIN, $response->status);
    }

    public function testAbstainWhenPasswordIsEmpty(): void
    {
        $request = new PasswordAuthenticationRequest();
        $request->username = 'bob';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);

        $this->assertEquals(AuthenticationResponse::ABSTAIN, $response->status);
    }

    public function testAbstainWhenUsernameIsEmpty(): void
    {
        $request = new PasswordAuthenticationRequest();
        $request->password = 'foobar';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);

        $this->assertEquals(AuthenticationResponse::ABSTAIN, $response->status);
    }

    public function testFailWithIncorrectPassword(): void
    {
        $this->configureUnsuccessfulMockhandler();

        $request = new PasswordAuthenticationRequest();
        $request->username = 'bob';
        $request->password = 'foobar';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);
        $this->assertEquals(AuthenticationResponse::FAIL, $response->status);
    }

    private function configureUnsuccessfulMockhandler(): void
    {
        $mockHandler = new MockHandler([
                                           new Response(
                                               status: 401
                                           )
                                       ]);

        $this->configureMockHandler($mockHandler);
    }

    public function testPostAuthentication(): void
    {
        $this->configureSuccessfulMockhandler();

        $user = $this->createMock(User::class);
        $user
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('Bob');
        $user
            ->expects($this->atLeastOnce())
            ->method('getEmail')
            ->willReturn('bob@localhost');
        $user
            ->expects($this->atLeastOnce())
            ->method('getRealName')
            ->willReturn('Mr. Bob');

        $request = new PasswordAuthenticationRequest();
        $request->username = 'bob';
        $request->password = 'foobar';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);
        $this->flarumAuthenticationProvider->postAuthentication($user, $response);
    }

    public function testPostAuthenticationSavesUpdatedUserData(): void
    {
        $this->configureSuccessfulMockhandler();

        $user = $this->createMock(User::class);
        $user
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('Bob');
        $user
            ->expects($this->atLeastOnce())
            ->method('getEmail')
            ->willReturn('bob.smith@localhost');
        $user
            ->expects($this->atLeastOnce())
            ->method('getRealName')
            ->willReturn('Mr. Bob Smith');

        $user
            ->expects($this->atLeastOnce())
            ->method('setRealName')
            ->with('Mr. Bob');
        $user
            ->expects($this->atLeastOnce())
            ->method('setEmail')
            ->with('bob@localhost');
        $user
            ->expects($this->atLeastOnce())
            ->method('setEmailAuthenticationTimestamp')
            ->with((new DateTime('2021-12-01'))->getTimestamp());
        $user
            ->expects($this->atLeastOnce())
            ->method('saveSettings');

        $request = new PasswordAuthenticationRequest();
        $request->username = 'bob';
        $request->password = 'foobar';

        $response = $this->flarumAuthenticationProvider->beginPrimaryAuthentication([$request]);
        $this->flarumAuthenticationProvider->postAuthentication($user, $response);
    }

    public function testProviderAllowsAuthenticationDataChange(): void
    {
        $this->assertFalse(
            $this->flarumAuthenticationProvider->providerAllowsAuthenticationDataChange(
                new PasswordAuthenticationRequest()
            )->isOK()
        );
    }

    public function testProviderChangeAuthenticationData(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->flarumAuthenticationProvider->providerChangeAuthenticationData(new PasswordAuthenticationRequest());
    }

    public function testAccountCreationType(): void
    {
        $this->assertEquals('create', $this->flarumAuthenticationProvider->accountCreationType());
    }

    public function testBeginPrimaryAccountCreation(): void
    {
        $this->assertEquals(
            AuthenticationResponse::ABSTAIN,
            $this->flarumAuthenticationProvider->beginPrimaryAccountCreation(
                $this->createMock(User::class),
                $this->createMock(User::class),
                [new PasswordAuthenticationRequest()]
            )->status
        );
    }

    public function testProviderAllowsPropertyChange(): void
    {
        $this->assertFalse($this->flarumAuthenticationProvider->providerAllowsPropertyChange(''));
    }
}
