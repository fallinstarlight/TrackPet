<?php
namespace TrackPet\utils;

use RuntimeException;
use TrackPet\model\response\Response;

/** Expected domain failure; messages and data are safe to return to the client. */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $flag = Response::FLAG_USER_ERROR, public readonly array $data = [])
    {
        parent::__construct($message);
    }
}
