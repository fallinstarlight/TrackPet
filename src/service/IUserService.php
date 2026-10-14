<?php
namespace TrackPet\service;
use TrackPet\model\response\Response;

interface IUserService
{
    public function login(string $username, string $password): Response;
    public function logout(): Response;
    public function createUser(array $user): Response;
    public function editUser(array $user): Response;
    public function deleteUser(string $id): Response;
    public function getUserByUsername(string $username): Response;
    public function getUserById(string $id): Response;
    public function getPetsByOwner(string $id): Response;
}
