<?php

class UserController
{
    private IUserService $userService;

    public function __construct(IUserService $userService)
    {
        $this->userService = $userService;
    }

    public function login(string $username, string $password): string
    {
        if (empty($username) || empty($password)) {
            $response = new UserResponse();
            $response->message = "provide valid parameters";
            $response->data    = [];
            return json_encode($response);
        }
        return json_encode($this->userService->login($username, $password));
    }

    public function logout(): string
    {
        return json_encode($this->userService->logout());
    }

    public function createUser(array $user): string
    {
        return json_encode($this->userService->createUser($user));
    }
}
