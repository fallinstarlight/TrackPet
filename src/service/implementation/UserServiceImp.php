<?php
namespace TrackPet\service\implementation;

use InvalidArgumentException;
use PDOException;
use TrackPet\service\IUserService;
use TrackPet\repository\UserRepository;
use TrackPet\model\response\Response;
use TrackPet\model\entity\User;
use TrackPet\utils\{Auth, DbErrorHandler, LoginFunctions, Uuid, Validator};

class UserServiceImp implements IUserService
{
    public function __construct(private UserRepository $userRepository) {}

    public function login(string $username, string $password): Response
    {
        try {
            $info = $this->userRepository->getLoginInfo($username, LoginFunctions::isEmail($username));
            if (!$info || !LoginFunctions::verify($password, $info['passwordHash'])) {
                return Response::error('invalid username or password', Response::FLAG_UNAUTHORIZED);
            }
            $pets = array_map([Uuid::class, 'serializeRow'], $this->userRepository->getPetsByOwner($info['userId']));
            $this->userRepository->setLastLogin($info['userId']);
            $info = Uuid::serializeRow($info);
            LoginFunctions::login($info);
            return Response::success('login successful', [
                'userId' => $info['userId'], 'username' => $info['username'],
                'firstName' => $info['firstName'], 'surname' => $info['surname'], 'pets' => $pets,
            ]);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '45000') {
                return Response::error('invalid username or password', Response::FLAG_UNAUTHORIZED);
            }
            $response = new Response();
            DbErrorHandler::apply($e, $response);
            return $response;
        }
    }

    public function logout(): Response
    {
        LoginFunctions::logout();
        return Response::success('logout successful');
    }

    public function createUser(array $data): Response
    {
        return $this->save($data, false);
    }

    public function editUser(array $data): Response
    {
        return $this->save($data, true);
    }

    private function save(array $data, bool $edit): Response
    {
        $schema = [
            'firstName' => 'string', 'surname' => 'string', 'username' => 'string',
            'email' => 'string', 'phone' => 'string?', 'profilePhoto' => 'string?', 'password' => 'string',
        ];
        if ($edit) $schema['userId'] = 'string';
        $errors = Validator::validateShape($data, $schema);
        foreach (array_diff(array_keys($data), array_keys($schema)) as $field) {
            $errors[] = "unsupported field: $field";
        }
        if ($edit && !Uuid::isValid($data['userId'] ?? null)) $errors[] = 'provide a valid userId';
        if ($errors) return Response::error('there are some errors with your request', Response::FLAG_USER_ERROR, $errors);
        $data = Validator::clean($data);
        $user = new User($data);
        if (isset($data['password'])) $user->setPassword($data['password']);
        $errors = $user->validateParams($edit);
        if ($errors) return Response::error('there are some errors with your request', Response::FLAG_USER_ERROR, $errors);
        return $this->run(function () use ($user, $edit): array {
            $user->userId = $edit ? Uuid::stringToBytes($user->userId) : Uuid::v7Bytes();
            return Uuid::serializeRow($edit ? $this->userRepository->editUser($user) : $this->userRepository->createUser($user));
        }, $edit ? 'user updated successfully' : 'Thank you for joining us');
    }

    public function deleteUser(string $userId): Response
    {
        return $this->run(function () use ($userId): array {
            $this->userRepository->deleteUser(Uuid::stringToBytes($userId));
            Auth::destroy();
            return [];
        }, 'user deleted successfully');
    }

    public function getUserByUsername(string $username): Response
    {
        return $this->run(fn() => $this->publicProfile($this->userRepository->getByUsername($username)), 'user found');
    }

    public function getUserById(string $userId): Response
    {
        return $this->run(fn() => $this->publicProfile($this->userRepository->getById(Uuid::stringToBytes($userId))), 'user found');
    }

    public function getProfile(string $userId): Response
    {
        return $this->run(function () use ($userId): array {
            $bytes = Uuid::stringToBytes($userId);
            $user = Uuid::serializeRow($this->userRepository->getById($bytes));
            $user['pets'] = array_map([Uuid::class, 'serializeRow'], $this->userRepository->getPetsByOwner($bytes));
            return $user;
        }, 'profile retrieved');
    }

    private function publicProfile(array $user): array
    {
        return array_intersect_key(Uuid::serializeRow($user), array_flip([
            'userId', 'firstName', 'surname', 'username', 'profilePhoto', 'creationDate',
        ]));
    }

    public function getPetsByOwner(string $ownerId): Response
    {
        return $this->run(fn() => array_map([Uuid::class, 'serializeRow'],
            $this->userRepository->getPetsByOwner(Uuid::stringToBytes($ownerId))), 'Pets retrieved');
    }

    private function run(callable $action, string $message): Response
    {
        try {
            return Response::success($message, $action());
        } catch (PDOException $e) {
            $response = new Response();
            DbErrorHandler::apply($e, $response);
            return $response;
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }
    }
}
