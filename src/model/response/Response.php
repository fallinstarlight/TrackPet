<?php
namespace TrackPet\model\response;

/**
 * Class to handle responses to the controller, which then encodes them and sends them to the user
 * 
 * The object may be initialized without any parameters and its attributes shall be set after based on the needs of the response
 * 
 */
class Response{

    public const FLAG_SUCCESS = 0;
    public const FLAG_NOT_FOUND = 1;
    public const FLAG_USER_ERROR = 2;
    public const FLAG_SERVER_ERROR = 3;
    public const FLAG_FORBIDDEN = 4;
    public const FLAG_UNAUTHORIZED = 5;
    public const FLAG_CONFLICT = 6;
    public const FLAG_RATE_LIMIT = 7;

    public const STATUS_SUCCESS = "success";
    public const STATUS_ERROR = "error";

    public string $status;
    public int $flag;
    public string $message;
    public array $data;

    public function __construct()
    {
        $this->status = self::STATUS_ERROR;
        $this->flag = self::FLAG_USER_ERROR;
        $this->message = "";
        $this->data = [];
    }
    public static function success(string $message, array $data = []): self
    {
        $response = new self();
        $response->status = self::STATUS_SUCCESS;
        $response->flag = self::FLAG_SUCCESS;
        $response->message = $message;
        $response->data = $data;
        return $response;
    }

    public static function error(string $message, int $flag = self::FLAG_USER_ERROR, array $data = []): self
    {
        $response = new self();
        $response->message = $message;
        $response->flag = $flag;
        $response->data = $data;
        return $response;
    }
}
