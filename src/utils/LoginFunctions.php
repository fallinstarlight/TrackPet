<?php

class LoginFunctions{
    public static function isEmail(string $value): bool
    {
       return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function verify(string $password, string $passwordHash): bool
    {
        return password_verify($password, $passwordHash);
    }

    public static function login(array $data)
    {
        Auth::start();
        Auth::regenerate();
        Auth::set('userId', $data['id']);
        Auth::set('name', $data['name']);
        Auth::set('surname', $data['surname']);
        Auth::set('username', $data['username']);
    }

    public static function logout()
    {
        Auth::destroy();
    }
}