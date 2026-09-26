<?php

class UserRepository{
    private PDO $CONN;

    public function __construct(PDO $CONN)
    {
       $this->CONN = $CONN;
    }

    public function getLoginInfo(string $value, bool $isEmail = false): array
    {
        $query = $this->CONN->prepare("CALL getHash(?, ?)");
        $query->execute([$value, $isEmail]);
        $result = $query->fetch();
        $query->closeCursor(); 
        return $result;
    }

    public function setLastLogin(int $id)
    {
        $query = $this->CONN->prepare("UPDATE Users SET lastLogin = ? WHERE id = ?");
        $query->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function createUser(User $user): array
    {
        $query = $this->CONN->prepare("CALL createUser(?, ?, ?, ?, ?, ?, ?)");
        $query->execute([
            $user->name, 
            $user->surname, 
            $user->username, 
            $user->email, 
            $user->passwordHash, 
            $user->phone, 
            $user->profilePhoto
        ]);
        $response = $query->fetch();
        $query->closeCursor();
        return $response; 
    }
}