<?php

/**
 * Main API entry point
 * Every API request sent by a client should be sent here
 * The base path should be <host>:<port>/api/endpoint.php
 * Followed by a resourse
 * So for example api/endpoint.php?resource=login
 */

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../../vendor/autoload.php';

/**
 * Namescpaces we're using
 */

use TrackPet\config\Connection;
use TrackPet\controller\PetController;
use TrackPet\controller\UserController;
use TrackPet\repository\PetRepository;
use TrackPet\repository\UserRepository;
use Exception;
use TrackPet\service\implementation\UserServiceImp;
use TrackPet\service\implementation\PetServiceImp;
use TrackPet\utils\Auth;
use TrackPet\utils\Sanitizer;

/**
 * Controller object initialization 
 * Use them to call the respective controller to each endpoint
 * If new controllers should be added, this is the way it should be done
 */
$uController = new UserController(new UserServiceImp(new UserRepository(Connection::getConnection())));
$pController = new PetController(new PetServiceImp(new PetRepository(Connection::getConnection())));

$requestMethod = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$body     = validateJSON();

/**
 * Switching the request method sent by the client
 * Use Patch instead of Put for updating purposes
 */
switch ($requestMethod) {
    case 'GET':
        switch ($resource) {
            case 'user':
                // Get user by id
                if (isset($_GET['userId'])) {
                    if (!ctype_digit((string)$_GET['userId'])) {
                        responseFromString("provide a valid integer id", 400);
                    }
                    responseFromController($uController->getUserById((int)$_GET['userId']));
                }
                // Get user by username
                if (isset($_GET['username'])) {
                    if (!is_string($_GET['username']) || $_GET['username'] === '') {
                        responseFromString("provide a valid username", 400);
                    }
                    responseFromController($uController->getUserByUsername($_GET['username']));
                }
                unknownRequest();
                break;

            case 'pet':
                // Get pets by owner ID (must be V7 UUID)
                if (isset($_GET['userId']) && !isset($_GET['petId'])) {
                    if (!validateUuid($_GET['userId'])) {
                        responseFromString("provide a valid uuid", 400);
                    }
                    responseFromController($uController->getPetsByOwner($_GET['userId']));
                }
                // Get pet by its id
                if (isset($_GET['petId'])) {
                    if (!validateUuid($_GET['petId']) || $_GET['petId'] === '') {
                        responseFromString("provide a valid uuid", 400);
                    }
                    responseFromController($pController->getPetById($_GET['petId']));
                }
                unknownRequest();
                break;
            // get a pet lost status by its id
            case 'getLostStatus':
                if (!isset($_GET['petId']) || !validateUuid($_GET['petId'])) {
                        responseFromString("provide a valid uuid", 400);
                    }
                    responseFromController($pController->getLostStatus($_GET['petId']));
                break;
            default:
                unknownRequest();
        }
        break;

    case 'POST':
        switch ($resource) {
            // Login 
            case 'login':
                if (Auth::isLoggedIn()) responseFromString("you are already logged in", 200);
                if (!isset($body['username']) || !isset($body['password'])) {
                    responseFromString("provide a valid username and password", 400);
                }
                responseFromController($uController->login($body['username'], $body['password']));
                break;

            //l log out
            case 'logout':
                Auth::requireLogIn();
                responseFromController($uController->logout());
                break;
            
            //ceate user
            case 'user':
                foreach ($body as $param => $value) {
                    if ($param !== 'password' && is_string($value)) {
                        $body[$param] = (new Sanitizer($value))->cleanString();
                    }
                }
                responseFromController($uController->createUser($body));
                break;
            
            // create pet
            case 'pet':
                Auth::requireLogIn();
                if (isset($body['ownerId']) && $body['ownerId'] !== Auth::get('userId')) {
                    responseFromString("you can not add a pet for another user", 403);
                }
                if (isset($_BODY['ownerId']) && !validateUuid($_BODY['ownerId']))
                    responseFromString("provide a valid owner uuid", 400);
                foreach ($body as $param => $value) {
                    if (!is_string($value)) continue;
                    if ($param === 'ownerId') continue;
                    if ($param === 'description') {
                        $body[$param] = (new Sanitizer($value, false, false))->cleanString();
                    } else {
                        $body[$param] = (new Sanitizer($value))->cleanString();
                    }
                }
                responseFromController($pController->createPet($body));
                break;

            default:
                unknownRequest();
        }
        break;

    case 'PATCH':
        switch ($resource) {     
            // update an user
            case 'user':
                Auth::requireLogIn();
                if (empty($body)) responseFromString("send at least one field to edit", 400);
                $body['userId'] = $body['userId'] ?? $_GET['userId'] ?? null;
                if (isset($body['userId']) && Auth::get('userId') !== $body['userId']) {
                    responseFromString("you can not edit someone else account", 403);
                }
                $body['userId'] = Auth::get('userId');
                responseFromController($uController->editUser($body));
                break;

            // update a pet
            case 'pet':
                Auth::requireLogIn();
                if (empty($body)) responseFromString("send at least one field to edit", 400);
                $pets = Auth::get('pets') ?? [];
                $body['petId'] = $body['petId'] ?? $_GET['petId'] ?? null;
                if (isset($body['petId']) && !in_array($body['petId'], array_column($pets, 'petId'))) {
                    responseFromString("the uuid does not match any uuid for a pet that you own", 403);
                }
                responseFromController($pController->editPet($body));
                break;

            // set a pet as lost
            case 'toggleLostStatus':
                Auth::requireLogIn();
                if (!isset($_GET['petId']) || !validateUuid($_GET['petId']) || $_GET['petId'] === '') {
                    responseFromString("provide a valid uuid", 400);
                }
                $pets = Auth::get('pets') ?? [];
                if (!in_array($_GET['petId'], array_column($pets, 'petId'))) {
                    responseFromString("the uuid does not match any uuid for a pet that you own", 403);
                }
                responseFromController($pController->toggleLost($_GET['petId'], Auth::get('userId')));
                break;

            // change pet ownership with another user
            case 'changeOwnership':
                Auth::requireLogIn();
                if (!isset($body['petId']) || !validateUuid($body['petId']) || $body['petId'] === '') {
                    responseFromString("provide a valid uuid", 400);
                }
                if (!isset($body['newOwnerId']) || !validateUuid($body['newOwnerId'])) {
                    responseFromString("provide a valid new owner id", 400);
                }
                $pets = Auth::get('pets') ?? [];
                if (!in_array($body['petId'], array_column($pets, 'petId'))) {
                    responseFromString("the uuid does not match any uuid for a pet that you own", 403);
                }
                responseFromController($pController->changeOwnership(
                    $body['petId'],
                    Auth::get('userId'),
                    $body['newOwnerId']
                ));
                break;

            default:
                unknownRequest();
        }
        break;

    case 'DELETE':
        switch ($resource) {
            // user soft delete
            case 'user':
                Auth::requireLogIn();
                responseFromController($uController->deleteUser(Auth::get('userId')));
                break;

            // pet soft delete
            case 'pet':
                Auth::requireLogIn();
                if (!isset($_GET['petId']) || !validateUuid($_GET['petId']) || $_GET['petId'] === '') {
                    responseFromString("provide a valid uuid", 400);
                }
                $pets = Auth::get('pets') ?? [];
                if (!in_array($_GET['petId'], array_column($pets, 'petId'))) {
                    responseFromString("the uuid does not match any uuid for a pet that you own", 403);
                }
                responseFromController($pController->deletePet($_GET['petId']));
                break;

            default:
                unknownRequest();
        }
        break;

    default:
        unknownRequest();
}

/**
 * Function to validate errors in the json payload from the request
 */
function validateJSON(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $body = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {   // <-- was json_last_Error()
        http_response_code(400);
        echo json_encode(["status" => "Error", "message" => "Invalid JSON payload"]);
        exit();
    }
    return is_array($body) ? $body : [];
}

/**
 * Send a direct custom response to the user if no suitable response exists
 * 
 * @param string $response the message to be sent
 * @param int $code the http status code
 * 
 * This function ends the script with die(), so don't call it in between the code
 */
function responseFromString(string $response, int $code): void
{
    http_response_code($code);
    $status = $code >= 400 ? "error" : "success";
    die(json_encode(["status" => $status, "message" => $response]));
}

/**
 * This function automatically sets the response and status code directly from a controller call
 *  
 * Only call this function if you have a suitable controller, don't send custom responses here or they may break
*/
function responseFromController(string $response, ?int $code = null): void
{
    if (empty($code)) {
        $data = json_decode($response, true);
        $httpCode = getHttpCode($data['flag'] ?? 3);
    } else {
        $httpCode = $code;
    }
    http_response_code($httpCode);
    echo $response;
    die;
}

function unknownRequest(): void
{
    responseFromString("unknown request", 400);
}

function getHttpCode(int $flag): int
{
    return match ($flag) {
        0 => 200,
        1 => 404,
        2 => 400,
        3 => 500
    };
}

function validateUuid(string $uuid)
{
    return ctype_xdigit($uuid) && strlen($uuid) === 32;
}
