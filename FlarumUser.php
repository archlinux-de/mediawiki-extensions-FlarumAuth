<?php

namespace MediaWiki\Extensions\FlarumAuth;

readonly class FlarumUser
{
    public function __construct(
        private int $id,
        private string $userName,
        private string $displayName,
        private string $email,
        private bool $isEmailConfirmed,
        private \DateTime $joinTime
    ) {
    }

    public static function crateFromResponse(string $response): self
    {
        /** @var array $data */
        $data = json_decode($response, true);
        return new FlarumUser(
            $data['data']['id'],
            $data['data']['attributes']['username'],
            $data['data']['attributes']['displayName'],
            $data['data']['attributes']['email'],
            $data['data']['attributes']['isEmailConfirmed'],
            new \DateTime($data['data']['attributes']['joinTime'])
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isEmailConfirmed(): bool
    {
        return $this->isEmailConfirmed;
    }

    public function getJoinTime(): \DateTime
    {
        return $this->joinTime;
    }
}
