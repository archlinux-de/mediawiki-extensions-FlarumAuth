<?php

namespace MediaWiki\Extensions\FlarumAuth;

class FlarumUser
{
    private int $id;
    private string $userName;
    private string $displayName;
    private string $email;
    private bool $isEmailConfirmed;
    private \DateTime $joinTime;

    public function __construct(
        int $id,
        string $userName,
        string $displayName,
        string $email,
        bool $isEmailConfirmed,
        \DateTime $joinTime
    ) {
        $this->id = $id;
        $this->userName = $userName;
        $this->displayName = $displayName;
        $this->email = $email;
        $this->isEmailConfirmed = $isEmailConfirmed;
        $this->joinTime = $joinTime;
    }


    public static function crateFromResponse(string $response): self
    {
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
