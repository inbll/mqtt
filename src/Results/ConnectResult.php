<?php

namespace Inbll\Mqtt\Results;

/**
 * 连接数据实体类
 *
 * Class ConnectResult
 */
class ConnectResult extends Result
{
    /**
     * 协议名称
     *
     * @var string
     */
    protected $protocolName;

    /**
     * 协议版本
     *
     * @var int
     */
    protected $protocolVersion;

    /**
     * 连接时长（秒）
     *
     * @var int
     */
    protected $keepAlive;

    /**
     * 是否清理会话
     *
     * @var bool
     */
    protected $cleanSession;

    /**
     * 遗嘱标志
     *
     * @var bool
     */
    protected $willFlag;

    /**
     * 遗嘱QoS
     *
     * @var int
     */
    protected $willQos;

    /**
     * 遗嘱保留
     *
     * @var bool
     */
    protected $willRetain;

    /**
     * 用户名标志
     *
     * @var bool
     */
    protected $usernameFlag;

    /**
     * 密码标志
     *
     * @var bool
     */
    protected $passwordFlag;

    /**
     * 客户端ID
     *
     * @var string
     */
    protected $clientId;

    /**
     * 遗嘱主题
     *
     * @var string
     */
    protected $willTopic;

    /**
     * 遗嘱内容
     *
     * @var string
     */
    protected $willMessage;

    /**
     * 用户名
     *
     * @var string
     */
    protected $username;

    /**
     * 密码
     *
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
