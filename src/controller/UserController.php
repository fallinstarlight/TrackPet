<?php
namespace TrackPet\controller;
use TrackPet\service\implementation\UserServiceImp;
use TrackPet\model\response\Response;

class UserController
{
    private UserServiceImp $userService;

    public function __construct(UserServiceImp $userService)
    {
        $this->userService = $userService;
    }

    public function login(string $username, string $password): string
    {
        if (empty($username) || empty($password)) {
            $response = new Response();
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

    public function editUser(array $user): string
    {
        return json_encode($this->userService->editUser($user));
    }

    public function deleteUser(string $id): string
    {
        return json_encode($this->userService->deleteUser($id));
    }

    public function getUserByUsername(string $username): string
    {
        return json_encode($this->userService->getUserByUsername($username));
    }

    public function getUserById(string $id): string
    {
        return json_encode($this->userService->getUserById($id));
    }

    public function getPetsByOwner(string $ownerId): string
    {
        return json_encode($this->userService->getPetsByOwner($ownerId));
    }
}
