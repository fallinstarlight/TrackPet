<?php
class UserResponse{

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