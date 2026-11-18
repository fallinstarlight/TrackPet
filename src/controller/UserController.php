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
        if ($username === '' || $password === '') {
            $response = new Response();
            $response->message = "provide valid parameters";
            $response->data    = [];
            return json_encode($response);
        }
        return json_encode($this->userService->login($username, $password), JSON_THROW_ON_ERROR);
    }

    public function logout(): string
    {
        return json_encode($this->userService->logout(), JSON_THROW_ON_ERROR);
    }

    public function createUser(array $user): string
    {
        return json_encode($this->userService->createUser($user), JSON_THROW_ON_ERROR);
    }

    public function editUser(array $user): string
    {
        return json_encode($this->userService->editUser($user), JSON_THROW_ON_ERROR);
    }

    public function deleteUser(string $id): string
    {
        return json_encode($this->userService->deleteUser($id), JSON_THROW_ON_ERROR);
    }

    public function getUserByUsername(string $username): string
    {
        return json_encode($this->userService->getUserByUsername($username), JSON_THROW_ON_ERROR);
    }

    public function getUserById(string $id): string
    {
        return json_encode($this->userService->getUserById($id), JSON_THROW_ON_ERROR);
    }

    public function getProfile(string $userId): string
    {
        return json_encode($this->userService->getProfile($userId), JSON_THROW_ON_ERROR);
    }

    public function getPetsByOwner(string $ownerId): string
    {
        return json_encode($this->userService->getPetsByOwner($ownerId), JSON_THROW_ON_ERROR);
    }
}
