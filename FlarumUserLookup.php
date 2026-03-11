<?php

namespace MediaWiki\Extensions\FlarumAuth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class FlarumUserLookup
{
    private const FLARUM_CONTENT_TYPE = 'application/vnd.api+json';

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Check if a user exists in Flarum.
     *
     * @return bool|null true if exists, false if not found, null on unexpected response
     */
    public function exists(string $username): ?bool
    {
        try {
            $response = $this->client->request(
                'GET',
                '/api/users/' . rawurlencode($username),
                [
                    'query' => ['bySlug' => 1, 'fields[users]' => ''],
                    'headers' => [
                        'accept' => 'application/json',
                    ],
                ]
            );

            $contentType = $response->getHeader('Content-Type')[0] ?? '';
            if (!str_starts_with($contentType, self::FLARUM_CONTENT_TYPE)) {
                return null;
            }

            /** @var array<string, mixed>|null $data */
            $data = json_decode($response->getBody()->getContents(), true);
            if (
                !is_array($data)
                || !is_array($data['data'] ?? null)
                || ($data['data']['type'] ?? null) !== 'users'
            ) {
                return null;
            }

            return true;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $contentType = $response->getHeader('Content-Type')[0] ?? '';

            if ($response->getStatusCode() !== 404) {
                return null;
            }

            if (!str_starts_with($contentType, self::FLARUM_CONTENT_TYPE)) {
                return null;
            }

            /** @var array<string, mixed>|null $data */
            $data = json_decode($response->getBody()->getContents(), true);
            if (
                !is_array($data)
                || !is_array($data['errors'] ?? null)
                || !is_array($data['errors'][0] ?? null)
            ) {
                return null;
            }
            $error = $data['errors'][0];
            if (($error['status'] ?? null) !== '404' || ($error['code'] ?? null) !== 'not_found') {
                return null;
            }

            return false;
        } catch (GuzzleException) {
            return null;
        }
    }
}
