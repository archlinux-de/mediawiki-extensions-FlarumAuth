<?php

namespace MediaWiki\Extensions\FlarumAuth;

class FlarumUserToken
{
    private int $id;
    private string $token;

    public function __construct(int $id, string $token)
    {
        $this->id = $id;
        $this->token = $token;
    }

    public static function crateFromResponse(string $response): self
    {
        /** @var array $data */
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
