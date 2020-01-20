<?php

namespace Inbll\Mqtt\Exceptions;

use Throwable;

/**
 * MQTT连接异常类
 *
 * Class ConnectException
 */
class ConnectException extends \Exception
{
    /**
     * 返回码-服务端不支持客户端请求的MQTT协议级别
     */
    const RETURN_CODE_PROTOCOL_NOT_SUPPORT = 1;

    /**
     * 返回码-客户端标识符是正确的UTF-8编码，但服务端不允许使用
     */
    const RETURN_CODE_CLIENT_ID_INVALID = 2;

    /**
     * 返回码-网络连接已建立，但MQTT服务不可用
     */
    const RETURN_CODE_ERROR = 3;

    /**
     * 返回码-用户名或密码的数据格式无效
     */
    const RETURN_CODE_USER_INVALID = 4;

    /**
     * 返回码-客户端未被授权连接到此服务器
     */
    const RETURN_CODE_UNAUTHORIZED = 5;


    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
