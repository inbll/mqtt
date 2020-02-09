<?php

namespace Inbll\Mqtt\Results;

/**
 * Class ConnectResult
 * @package Inbll\Mqtt\Results
 */
class ConnectResult extends Result
{
    /**
     * @var string
     */
    protected $protocolName;

    /**
     * @var int
     */
    protected $protocolVersion;

    /**
     * @var int
     */
    protected $keepAlive;

    /**
     * @var bool
     */
    protected $cleanSession;

    /**
     * @var bool
     */
    protected $willFlag;

    /**
     * @var int
     */
    protected $willQos;

    /**
     * @var bool
     */
    protected $willRetain;

    /**
     * @var bool
     */
    protected $usernameFlag;

    /**
     * @var bool
     */
    protected $passwordFlag;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $willTopic;

    /**
     * @var string
     */
    protected $willMessage;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;


    /**
     * @return string
     */
    public function getProtocolName(): string
    {
        return $this->protocolName;
    }

    /**
     * @param string $protocolName
     */
    public function setProtocolName(string $protocolName): void
    {
        $this->protocolName = $protocolName;
    }

    /**
     * @return int
     */
    public function getProtocolVersion(): int
    {
        return $this->protocolVersion;
    }

    /**
     * @param int $protocolVersion
     */
    public function setProtocolVersion(int $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * @return int
     */
    public function getKeepAlive(): int
    {
        return $this->keepAlive;
    }

    /**
     * @param int $keepAlive
     */
    public function setKeepAlive(int $keepAlive): void
    {
        $this->keepAlive = $keepAlive;
    }

    /**
     * @return bool
     */
    public function isCleanSession(): bool
    {
        return $this->cleanSession;
    }

    /**
     * @param bool $cleanSession
     */
    public function setCleanSession(bool $cleanSession): void
    {
        $this->cleanSession = $cleanSession;
    }

    /**
     * @return bool
     */
    public function isWillFlag(): bool
    {
        return $this->willFlag;
    }

    /**
     * @param bool $willFlag
     */
    public function setWillFlag(bool $willFlag): void
    {
        $this->willFlag = $willFlag;
    }

    /**
     * @return int
     */
    public function getWillQos(): int
    {
        return $this->willQos;
    }

    /**
     * @param int $willQos
     */
    public function setWillQos(int $willQos): void
    {
        $this->willQos = $willQos;
    }

    /**
     * @return bool
     */
    public function isWillRetain(): bool
    {
        return $this->willRetain;
    }

    /**
     * @param bool $willRetain
     */
    public function setWillRetain(bool $willRetain): void
    {
        $this->willRetain = $willRetain;
    }

    /**
     * @return bool
     */
    public function isUsernameFlag(): bool
    {
        return $this->usernameFlag;
    }

    /**
     * @param bool $usernameFlag
     */
    public function setUsernameFlag(bool $usernameFlag): void
    {
        $this->usernameFlag = $usernameFlag;
    }

    /**
     * @return bool
     */
    public function isPasswordFlag(): bool
    {
        return $this->passwordFlag;
    }

    /**
     * @param bool $passwordFlag
     */
    public function setPasswordFlag(bool $passwordFlag): void
    {
        $this->passwordFlag = $passwordFlag;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return string
     */
    public function getWillTopic(): ?string
    {
        return $this->willTopic;
    }

    /**
     * @param string $willTopic
     */
    public function setWillTopic(string $willTopic): void
    {
        $this->willTopic = $willTopic;
    }

    /**
     * @return string
     */
    public function getWillMessage(): ?string
    {
        return $this->willMessage;
    }

    /**
     * @param string $willMessage
     */
    public function setWillMessage(string $willMessage): void
    {
        $this->willMessage = $willMessage;
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
