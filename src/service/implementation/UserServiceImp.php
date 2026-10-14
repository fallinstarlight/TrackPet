<?php

namespace TrackPet\service\implementation;

use TrackPet\service\IUserService;
use TrackPet\repository\UserRepository;
use TrackPet\model\response\Response;
use TrackPet\model\entity\User;
use TrackPet\utils\LoginFunctions;
use TrackPet\utils\DbErrorHandler;
use PDOException;
use TrackPet\repository\PetRepository;
use TrackPet\utils\Uuid;
use TrackPet\utils\Validator;

class UserServiceImp implements IUserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(string $username, string $password): Response
    {
        $response = new Response;

        $isEmail = LoginFunctions::isEmail($username);
        try {
            $loginInfo = $this->userRepository->getLoginInfo($username, $isEmail);

            if (empty($loginInfo)) {
                $response->message = "invalid username or password";
                return $response;
            }

            $passwordHash = $loginInfo['passwordHash'];
            if (LoginFunctions::verify($password, $passwordHash)) {
                $response->flag    = Response::FLAG_SUCCESS;
                $response->status  = Response::STATUS_SUCCESS;
                $response->message = "login successful";
                $pets = $this->userRepository->getPetsByOwner($loginInfo['userId']);
                $loginInfo['userId'] = Uuid::bytesToString($loginInfo['userId']);
                foreach ($pets as &$row) {
                    if (isset($row['petId'])) {
                        $row['petId'] = Uuid::bytesToString($row['petId']);
                    }
                }
                unset($row);
                $loginInfo['pets'] = $pets;
                LoginFunctions::login($loginInfo);
                $response->data = [
                    "username" => $loginInfo["username"],
                    "name"     => $loginInfo["firstName"],
                    "surname"  => $loginInfo["surname"],
                    "pets" => $loginInfo['pets']
                ];
                $this->userRepository->setLastLogin($loginInfo['userId']);
            } else {
                $response->message = "invalid username or password";
            }
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function logout(): Response
    {
        $response = new Response;
        LoginFunctions::logout();
        $response->flag    = Response::FLAG_SUCCESS;
        $response->status  = Response::STATUS_SUCCESS;
        $response->message = "logout successful";
        $response->data    = ["logout date" => date('Y-m-d H:i:s')];
        return $response;
    }

    public function createUser(array $user): Response
    {
        $response = new Response;

        $shapeErrors = Validator::validateShape($user, [
            'firstName'    => 'string',
            'surname'      => 'string',
            'username'     => 'string',
            'email'        => 'string',
            'phone'        => 'string',
            'profilePhoto' => 'string',
        ]);

        if (!empty($shapeErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $shapeErrors;
            return $response;
        }

        $userData = $user;
        $user = new User($userData);

        if (isset($userData['password']) && is_string($userData['password'])) {
            $user->setPassword($userData['password']);
        }

        $validationErrors = $user->validateParams();
        if (!empty($validationErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $validationErrors;
            return $response;
        }

        try {
            $user->userId = Uuid::v7Bytes();
            $result = $this->userRepository->createUser($user);
            if(isset($result['userId'])) $result['userId'] = Uuid::bytesToString($result['userId']);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Thank you for joining us";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function editUser(array $user): Response
    {
        $response = new Response;

        $shapeErrors = Validator::validateShape($user, [
            'firstName'    => 'string',
            'surname'      => 'string',
            'username'     => 'string',
            'email'        => 'string',
            'phone'        => 'string',
            'profilePhoto' => 'string',
        ]);

        if (!empty($shapeErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $shapeErrors;
            return $response;
        }

        $userData = $user;
        $user = new User($userData);

        if (isset($userData['password']) && is_string($userData['password'])) {
            $user->setPassword($userData['password']);
        }
        if (!isset($userData['userId'])) {
            $response->message = "you must provide an id";
            $response->data    = [];
            return $response;
        }

        $validationErrors = $user->validateParams(true);
        if (!empty($validationErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $validationErrors;
            return $response;
        }

        try {
            $user->userId = Uuid::stringToBytes($user->userId);
            $result = $this->userRepository->editUser($user);
            if(isset($result['userId'])) $result['userId'] = Uuid::bytesToString($result['userId']);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "user updated successfully";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function deleteUser(string $userId): Response
    {
        $response = new Response;
        try {
            $userId = Uuid::stringToBytes($userId);
            $this->userRepository->deleteUser($userId);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "user deleted successfully";
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function getUserByUsername(string $username): Response
    {
        $response = new Response;
        try {
            $result = $this->userRepository->getByUsername($username);
            if(isset($result['userId'])) $result['userId'] = Uuid::bytesToString($result['userId']);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "user found";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function getUserById(string $userId): Response
    {
        $response = new Response;
        try {
            $userId = Uuid::stringToBytes($userId);
            $result = $this->userRepository->getById($userId);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "user found";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function getPetsByOwner(string $ownerId): Response
    {
        $response = new Response;
        try {
            $results = $this->userRepository->getPetsByOwner($ownerId);
            foreach ($results as &$row) {
                if (isset($row['petId'])) {
                    $row['petId'] = Uuid::bytesToString($row['petId']);
                }
                if (isset($row['ownerId'])) {
                    $row['ownerId'] = Uuid::bytesToString($row['ownerId']);
                }
            }
            unset($row);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Pets retrieved";
            $response->data    = $results;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }
}
