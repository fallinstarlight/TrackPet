<?php

class User
{
    public ?int $id;
    public ?string $name;
    public ?string $surname;
    public ?string $username;
    public ?string $email;
    public ?string $passwordHash;
    public ?string $phone;
    public ?string $profilePhoto;
    public ?string $creationDate;
    public ?string $modificationDate;

    private string $password;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function validateParams(): array
    {
        $errors = [];
        $required = ['name', 'surname', 'username', 'email', 'passwordHash'];
        $maxLengths = [
            'name'     => 50,
            'surname'  => 50,
            'username' => 25,
            'phone'    => 15,
            'email'    => 50,
        ];

        if(isset($this->password) && !isset($this->passwordHash)) self::hashPassword();

        foreach ($required as $field) {
            if (!isset($this->$field) || $this->$field === '') {
                $errors[] = "missing the following info: $field";
            }
        }

        if(!empty($errors)) return $errors;

        if (!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "not a valid email address";
        }

        foreach ($maxLengths as $field => $max) {
            if (isset($this->$field) && mb_strlen($this->$field) > $max) {
                $errors[] = "$field exceeds $max char limit";
            }
        }

        return $errors;
    }

    private function hashPassword()
    {
        $this->passwordHash = password_hash($this->password, PASSWORD_DEFAULT);
    }
}
