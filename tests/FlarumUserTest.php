<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use DateTime;
use MediaWiki\Extensions\FlarumAuth\FlarumUser;
use PHPUnit\Framework\TestCase;

use const DATE_ISO8601;

class FlarumUserTest extends TestCase
{
    public function testCrateFromResponse(): void
    {
        $flarumUser = FlarumUser::crateFromResponse(
            (string)json_encode([
                                    'data' => [
                                        'id' => 1,
                                        'attributes' => [
                                            'username' => 'name',
                                            'displayName' => 'Mr. Name',
                                            'email' => 'foo@localhost',
                                            'isEmailConfirmed' => true,
                                            'joinTime' => (new DateTime('2021-01-01'))->format(DATE_ISO8601)
                                        ]
                                    ]
                                ])
        );

        $this->assertEquals(1, $flarumUser->getId());
        $this->assertEquals('name', $flarumUser->getUserName());
        $this->assertEquals('Mr. Name', $flarumUser->getDisplayName());
        $this->assertEquals('foo@localhost', $flarumUser->getEmail());
        $this->assertEquals(true, $flarumUser->isEmailConfirmed());
        $this->assertEquals(new DateTime('2021-01-01'), $flarumUser->getJoinTime());
    }
}