<?php

namespace Tests\MediaWiki\Extensions\FlarumAuth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MediaWiki\Extensions\FlarumAuth\FlarumUserLookup;
use PHPUnit\Framework\TestCase;

class FlarumUserLookupTest extends TestCase
{
    private const FLARUM_CONTENT_TYPE = 'application/vnd.api+json';

    private function createLookup(MockHandler $mockHandler): FlarumUserLookup
    {
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);
        return new FlarumUserLookup($client);
    }

    public function testUserExists(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 200,
                headers: ['Content-Type' => self::FLARUM_CONTENT_TYPE],
                body: (string)json_encode([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [],
                    ],
                ])
            ),
        ]));

        $this->assertTrue($lookup->exists('bob'));
    }

    public function testUserNotFound(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 404,
                headers: ['Content-Type' => self::FLARUM_CONTENT_TYPE],
                body: (string)json_encode([
                    'errors' => [
                        [
                            'status' => '404',
                            'code' => 'not_found',
                        ],
                    ],
                ])
            ),
        ]));

        $this->assertFalse($lookup->exists('nobody'));
    }

    public function testNginx404ReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 404,
                headers: ['Content-Type' => 'text/html'],
                body: '<html>Not Found</html>'
            ),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }

    public function test404WithWrongErrorCodeReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 404,
                headers: ['Content-Type' => self::FLARUM_CONTENT_TYPE],
                body: (string)json_encode([
                    'errors' => [
                        [
                            'status' => '404',
                            'code' => 'something_else',
                        ],
                    ],
                ])
            ),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }

    public function test200WithWrongContentTypeReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 200,
                headers: ['Content-Type' => 'text/html'],
                body: '<html>OK</html>'
            ),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }

    public function test200WithWrongJsonStructureReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(
                status: 200,
                headers: ['Content-Type' => self::FLARUM_CONTENT_TYPE],
                body: (string)json_encode(['unexpected' => 'structure'])
            ),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }

    public function testServerErrorReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new Response(status: 500),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }

    public function testConnectionErrorReturnsNull(): void
    {
        $lookup = $this->createLookup(new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/api/users/bob')),
        ]));

        $this->assertNull($lookup->exists('bob'));
    }
}
