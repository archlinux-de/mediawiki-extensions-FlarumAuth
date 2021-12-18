<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use MediaWiki\Extensions\FlarumAuth\FlarumAuthenticationProvider;
use PHPUnit\Framework\TestCase;

class FlarumAuthenticationProviderTest extends TestCase
{
    /**
     * @dataProvider providePasswords
     */
    public function testIsValidPassword(string $password, bool $valid): void
    {
        $this->assertEquals($valid, FlarumAuthenticationProvider::isValidPassword($password));
    }

    public function providePasswords(): array
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
        $flarumAuthenticationProvider = new FlarumAuthenticationProvider();
        $this->assertFalse($flarumAuthenticationProvider->testUserExists(''));
    }
}
