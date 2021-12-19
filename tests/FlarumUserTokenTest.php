<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use MediaWiki\Extensions\FlarumAuth\FlarumUserToken;
use PHPUnit\Framework\TestCase;

class FlarumUserTokenTest extends TestCase
{
    public function testCrateFromResponse(): void
    {
        $flarumUserToken = FlarumUserToken::crateFromResponse(
            (string)json_encode([
                                    'userId' => 1,
                                    'token' => 'abc'
                                ])
        );

        $this->assertEquals(1, $flarumUserToken->getId());
        $this->assertEquals('abc', $flarumUserToken->getToken());
    }
}
