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
}