<?php

namespace Inbll\Mqtt\Exceptions;

/**
 * Class ConnectException
 * @package Inbll\Mqtt\Exceptions
 */
class ConnectException extends \Exception
{
    /**
     * The Server does not support the level of the MQTT protocol requested by the Client
     */
    const PROTOCOL_NOT_SUPPORT = 1;

    /**
     * The Client identifier is correct UTF-8 but not allowed by the Server
     */
    const CLIENT_ID_INVALID = 2;

    /**
     * The Network Connection has been made but the MQTT service is unavailable
     */
    const ERROR = 3;

    /**
     * The data in the user name or password is malformed
     */
    const USER_INVALID = 4;

    /**
     * The Client is not authorized to connect
     */
    const UNAUTHORIZED = 5;
}
