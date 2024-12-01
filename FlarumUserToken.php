<?php

namespace MediaWiki\Extensions\FlarumAuth;

readonly class FlarumUserToken
{
    public function __construct(private int $id, private string $token)
    {
    }

    public static function crateFromResponse(string $response): self
    {
        /** @var array{userId: int, token: string} $data */
        $data = json_decode($response, true);
        return new FlarumUserToken($data['userId'], $data['token']);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
