<?php
namespace TrackPet\repository;
use PDO;
use TrackPet\model\entity\User;

class UserRepository
{
    private PDO $CONN;

    public function __construct(PDO $CONN)
    {
        $this->CONN = $CONN;
    }

    public function getLoginInfo(string $username, bool $isEmail = false): array
    {
        $query = $this->CONN->prepare("CALL getHash(?, ?)");
        $query->bindValue(1, $username);
        $query->bindValue(2, $isEmail, PDO::PARAM_BOOL);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    public function setLastLogin(string $userId): void
    {
        $query = $this->CONN->prepare("UPDATE Users SET lastLogin = ? WHERE userId = ?");
        $query->bindValue(1, date('Y-m-d H:i:s'));
        $query->bindValue(2, $userId, PDO::PARAM_LOB);
        $query->execute();
    }

    public function createUser(User $user): array
    {
        $query = $this->CONN->prepare("CALL createUser(?, ?, ?, ?, ?, ?, ?, ?)");
        $query->bindValue(1, $user->userId, PDO::PARAM_LOB);
        $query->bindValue(2, $user->firstName);
        $query->bindValue(3, $user->surname);
        $query->bindValue(4, $user->username);
        $query->bindValue(5, $user->email);
        $query->bindValue(6, $user->passwordHash);
        $query->bindValue(7, $user->phone);
        $query->bindValue(8, $user->profilePhoto);
        $query->execute();
        $response = $query->fetch();
        $query->closeCursor();
        return $response ?: [];
    }

    public function editUser(User $user): array
    {
        $query = $this->CONN->prepare("CALL editUser(?, ?, ?, ?, ?, ?, ?, ?)");
        $query->bindValue(1, $user->userId, PDO::PARAM_LOB);
        $query->bindValue(2, $user->firstName);
        $query->bindValue(3, $user->surname);
        $query->bindValue(4, $user->username);
        $query->bindValue(5, $user->email);
        $query->bindValue(6, $user->passwordHash);
        $query->bindValue(7, $user->phone);
        $query->bindValue(8, $user->profilePhoto);
        $query->execute();
        $response = $query->fetch();
        $query->closeCursor();
        return $response ?: [];
    }

    public function deleteUser(string $userId): void
    {
        $query = $this->CONN->prepare("CALL deleteUser(?)");
        $query->bindValue(1, $userId, PDO::PARAM_LOB);
        $query->execute();
        $query->closeCursor();
    }

    public function getByUsername(string $username): array
    {
        $query = $this->CONN->prepare("CALL getUserByUsername(?)");
        $query->execute([$username]);
        $response = $query->fetch();
        $query->closeCursor();
        return $response ?: [];
    }

    public function getById(string $userId): array
    {
        $query = $this->CONN->prepare("CALL getUserById(?)");
        $query->bindValue(1, $userId, PDO::PARAM_LOB);
        $query->execute();
        $response = $query->fetch();
        $query->closeCursor();
        return $response ?: [];
    }

    public function getPetsByOwner(string $ownerId): array
    {
        $query = $this->CONN->prepare("CALL getPetsByOwner(?)");
        $query->bindValue(1, $ownerId, PDO::PARAM_LOB);
        $query->execute();
        $results = $query->fetchAll();
        $query->closeCursor();
        return $results ?: [];
    }
    public function isActive(string $userId): bool
    {
        $query = $this->CONN->prepare("SELECT 1 FROM Users WHERE userId = ? AND status = 'active'");
        $query->bindValue(1, $userId, PDO::PARAM_LOB);
        $query->execute();
        return (bool)$query->fetchColumn();
    }
    public function getIdentity(string $userId): array
    {
        $query = $this->CONN->prepare("SELECT username FROM Users WHERE userId = ? AND status = 'active'");
        $query->bindValue(1, $userId, PDO::PARAM_LOB);
        $query->execute();
        return $query->fetch() ?: [];
    }
}