<?php

interface IUserService{
    public function login(string $username, string $password): UserResponse;
    public function logout(): UserResponse;
    public function createUser(array $user): UserResponse;
}