<?php

namespace TrackPet\model\entity;

/**
 * Class to represent Users from the database as objects
 * 
 * Requires array for construction
 * 
 * If you were to call the constructor, you should validate the parameters first to avoid uncaught InvalidArgumentException
 */
class User
{
    public ?string $userId = null;
    public ?string $firstName = null;
    public ?string $surname = null;
    public ?string $username = null;
    public ?string $email = null;
    public ?string $passwordHash = null;
    public ?string $phone = null;
    public ?string $profilePhoto = null;
    public ?string $lastLogin = null;
    public ?string $creationDate = null;
    public ?string $modificationDate = null;

    private ?string $password = null;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && ($key !== 'password' || $key !== 'passwordHash')) {
                $this->$key = $value;
            }
        }
    }

    /**
    * Validation of parametes to send before a user creation or update
    * 
    * Checks basic constrains such as string lenght, also validates email address and sets up passwordhash
    *
    * Further validation is needed, currently done by Sanitizer and Validator classes
    */
    public function validateParams(bool $isEdit = false): array
    {
        $errors = [];
        $required = ['firstName', 'surname', 'username', 'email'];
        $maxLengths = [
            'firstName' => 50,
            'surname'   => 50,
            'username'  => 25,
            'phone'     => 15,
            'email'     => 50,
        ];

        if ($this->password !== null && $this->validatePassword()) {
            $this->hashPassword();
        }

        if (!isset($this->passwordHash) || $this->passwordHash === '') {
            $errors[] = "please provide a password at least 8 characters long, one uppercase, one lowercase, a number and a special character";
        }

        if (!$isEdit) {
            foreach ($required as $field) {
                if (!isset($this->$field) || $this->$field === '') {
                    $errors[] = "missing the following info: $field";
                }
            }

            if (!empty($errors)) return $errors;
        }

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

    /**
     * Seter for private password attribute
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Returns the hash of a given password
     */
    private function hashPassword(): void
    {
        $this->passwordHash = password_hash($this->password, PASSWORD_DEFAULT);
    }

    /**
     * Validates a password against a regex rule that checks for 8 minimum characters, one uppercast letter, one lowercast letter, a number and a special character
     */
    private function validatePassword(): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_])[A-Za-z\d@$!%*?&_]{8,}$/', $this->password) === 1;
    }
}
