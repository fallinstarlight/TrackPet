<?php
/** Single API entry point. Public UUIDs use canonical strings; database UUIDs use 16 bytes. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $error): void {
    if ($error instanceof InvalidArgumentException) {
        responseFromString($error->getMessage(), 400);
    }
    error_log('[API ERROR] ' . $error);
    responseFromString('There was an unexpected error, please try again', 500);
});
require __DIR__ . '/../../vendor/autoload.php';

use TrackPet\config\Connection;
use TrackPet\controller\{PetController, UserController, ReportController, DiscoveryController};
use TrackPet\repository\{PetRepository, UserRepository, ReportRepository, DiscoveryRepository};
use TrackPet\service\implementation\{PetServiceImp, UserServiceImp, ReportServiceImp, DiscoveryServiceImp};
use TrackPet\utils\{Auth, Upload, Uuid};

$connection = Connection::getConnection();
$userRepository = new UserRepository($connection);
$petRepository = new PetRepository($connection);
$uController = new UserController(new UserServiceImp($userRepository));
$pController = new PetController(new PetServiceImp($petRepository));
$rController = new ReportController(new ReportServiceImp(new ReportRepository($connection)));
$dController = new DiscoveryController(new DiscoveryServiceImp(new DiscoveryRepository($connection)));
$requestMethod = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
if (!is_string($resource) || $resource === '') responseFromString('provide a resource', 400);
$body = in_array($requestMethod, ['POST', 'PATCH', 'DELETE'], true) ? validateJSON() : [];

switch ($requestMethod) {
    case 'GET':
        switch ($resource) {
            case 'reports':
                $mine = boolParam('mine');
                responseFromController($rController->listing($mine ? requireSession() : null, $mine, textParam('q'), intParam('limit', 12, 1, 50), intParam('offset', 0, 0, 10000)));
            case 'report':
                if (isset($_GET['petId'])) {
                    if (isset($_GET['reportId'])) responseFromString('provide petId or reportId, not both', 400);
                    responseFromController($rController->activeForPet(uuidParam($_GET['petId']), optionalSession()));
                }
                responseFromController($rController->detail(uuidParam($_GET['reportId'] ?? null), optionalSession()));
            case 'tracking':
                responseFromController($rController->tracking(uuidParam($_GET['reportId'] ?? null), requireSession(), intParam('limit', 50, 1, 100), intParam('offset', 0, 0, 10000)));
            case 'sightings':
                responseFromController($rController->mySightings(requireSession(), intParam('limit', 20, 1, 50), intParam('offset', 0, 0, 10000)));
            case 'directory':
                responseFromController($dController->listing(textParam('kind', 'pets'), textParam('q'), boolParam('lost'), intParam('limit', 12, 1, 50), intParam('offset', 0, 0, 10000)));
            case 'stats':
                responseFromController($dController->stats());
            case 'viewer':
                $viewerId = optionalSession();
                $identity = $viewerId === null ? [] : $userRepository->getIdentity(Uuid::stringToBytes($viewerId));
                $viewer = !$identity ? ['authenticated' => false] : ['authenticated' => true, 'userId' => $viewerId, 'username' => $identity['username']];
                responseFromController(json_encode(\TrackPet\model\response\Response::success('Session identity', $viewer), JSON_THROW_ON_ERROR));
            case 'session':
                responseFromController($uController->getProfile(requireSession()));
            case 'user':
                if (isset($_GET['userId']) && isset($_GET['username'])) responseFromString('provide only one user identifier', 400);
                if (isset($_GET['userId'])) responseFromController($uController->getUserById(uuidParam($_GET['userId'])));
                if (isset($_GET['username']) && is_string($_GET['username']) && trim($_GET['username']) !== '') {
                    responseFromController($uController->getUserByUsername($_GET['username']));
                }
                responseFromString('provide userId or username', 400);
            case 'pet':
                if (isset($_GET['petId'])) responseFromController($pController->getPetById(uuidParam($_GET['petId'])));
                // userId remains an alias for older clients.
                responseFromController($uController->getPetsByOwner(uuidParam($_GET['ownerId'] ?? $_GET['userId'] ?? null)));
            case 'getLostStatus':
            case 'isPetLost':
                responseFromController($pController->getLostStatus(uuidParam($_GET['petId'] ?? null)));
        }
        break;
    case 'POST':
        switch ($resource) {
            case 'report':
                responseFromController($rController->create($body, requireSession()));
            case 'sighting':
                responseFromController($rController->sighting($body, optionalSession()));
            case 'beaconSighting':
                $actor = requireSession();
                responseFromController($rController->beaconSighting($body, uuidParam($_GET['reportId'] ?? null), uuidParam($_GET['clientRequestId'] ?? null), $actor));
            case 'login':
                if (Auth::isLoggedIn()) {
                    $id = Auth::get('userId');
                    if (Uuid::isValid($id) && $userRepository->isActive(Uuid::stringToBytes($id))) {
                        responseFromController($uController->getProfile($id));
                    }
                    Auth::destroy();
                }
                if (!is_string($body['username'] ?? null) || !is_string($body['password'] ?? null)
                    || trim($body['username']) === '' || $body['password'] === '') {
                    responseFromString('provide a valid username and password', 400);
                }
                responseFromController($uController->login(trim($body['username']), $body['password']));
            case 'logout':
                requireSession();
                responseFromController($uController->logout());
            case 'user':
                responseFromController($uController->createUser($body));
            case 'pet':
                $ownerId = requireSession();
                if (array_key_exists('ownerId', $body) && uuidParam($body['ownerId']) !== $ownerId) {
                    responseFromString('you cannot add a pet for another user', 403);
                }
                $body['ownerId'] = $ownerId;
                responseFromController($pController->createPet($body));
            case 'upload':
                $ownerId = requireSession();
                if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data;')) {
                    responseFromString('use multipart/form-data for uploads', 415);
                }
                $target = $_GET['target'] ?? null;
                if (!in_array($target, ['user', 'pet'], true)) responseFromString('target must be user or pet', 400);
                $petId = null;
                $oldPhoto = null;
                if ($target === 'user') {
                    $oldPhoto = $userRepository->getById(Uuid::stringToBytes($ownerId))['profilePhoto'] ?? null;
                }
                if ($target === 'pet') {
                    $petId = uuidParam($_GET['petId'] ?? null);
                    $pet = $petRepository->getById(Uuid::stringToBytes($petId));
                    $oldPhoto = $pet['petPhoto'] ?? null;
                    if (!isset($pet['ownerId']) || Uuid::bytesToString($pet['ownerId']) !== $ownerId) {
                        responseFromString('you do not own this pet', 403);
                    }
                }
                try {
                    $filename = (new Upload())->publish();
                } catch (RuntimeException $error) {
                    responseFromString($error->getMessage(), 400);
                }
                $path = 'assets/img/uploads/' . $filename;
                try {
                    $response = $target === 'user'
                        ? $uController->editUser(['userId' => $ownerId, 'profilePhoto' => $path])
                        : $pController->editPet(['petId' => $petId, 'petPhoto' => $path], $ownerId);
                    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                    if (($decoded['status'] ?? null) !== 'success') {
                        unlink(__DIR__ . '/../' . $path);
                    } else {
                        $decoded['data'][$target === 'user' ? 'profilePhoto' : 'petPhoto'] = $path;
                        $response = json_encode($decoded, JSON_THROW_ON_ERROR);
                    }
                } catch (Throwable $error) {
                    unlink(__DIR__ . '/../' . $path);
                    throw $error;
                }
                if (($decoded['status'] ?? null) === 'success') {
                    try { cleanupPhoto($oldPhoto); } catch (Throwable $error) { error_log('[PHOTO CLEANUP] ' . $error); }
                }
                responseFromController($response);
        }
        break;
    case 'PATCH':
        $ownerId = requireSession();
        if (!$body && !in_array($resource, ['toggleLostStatus'], true)) responseFromString('send at least one field to edit', 400);
        switch ($resource) {
            case 'report':
                $reportId = requestId($body, 'reportId');
                unset($body['reportId']);
                responseFromController(array_key_exists('status', $body) ? $rController->close($reportId, $body, $ownerId) : $rController->edit($reportId, $body, $ownerId));
            case 'user':
                if (isset($body['userId']) || isset($_GET['userId'])) {
                    if (requestId($body, 'userId') !== $ownerId) responseFromString('you cannot edit another account', 403);
                }
                $body['userId'] = $ownerId;
                responseFromController($uController->editUser($body));
            case 'pet':
                $body['petId'] = requestId($body, 'petId');
                if (array_key_exists('ownerId', $body) && uuidParam($body['ownerId']) !== $ownerId) {
                    responseFromString('use changeOwnership to transfer a pet', 403);
                }
                responseFromController($pController->editPet($body, $ownerId));
            case 'toggleLostStatus':
                responseFromController($pController->toggleLost(requestId($body, 'petId'), $ownerId));
            case 'changeOwnership':
                if (isset($body['oldOwnerId']) && uuidParam($body['oldOwnerId']) !== $ownerId) {
                    responseFromString('invalid current owner', 403);
                }
                responseFromController($pController->changeOwnership(requestId($body, 'petId'), $ownerId, uuidParam($body['newOwnerId'] ?? null)));
        }
        break;
    case 'DELETE':
        $ownerId = requireSession();
        switch ($resource) {
            case 'sighting':
                responseFromController($rController->retract(requestId($body, 'sightingId'), $ownerId));
            case 'user':
                responseFromController($uController->deleteUser($ownerId));
            case 'pet':
                responseFromController($pController->deletePet(requestId($body, 'petId'), $ownerId));
        }
        break;
    default:
        header('Allow: GET, POST, PATCH, DELETE');
        responseFromString('method not allowed', 405);
}
responseFromString('resource not found', 404);

function requireSession(): string
{
    global $userRepository;
    Auth::requireLogIn();
    $id = Auth::get('userId');
    if (!Uuid::isValid($id) || !$userRepository->isActive(Uuid::stringToBytes($id))) {
        Auth::destroy();
        responseFromString('your session is no longer valid', 401);
    }
    return Uuid::normalize($id);
}

function uuidParam(mixed $value): string
{
    if (!Uuid::isValid($value)) responseFromString('provide a valid UUID', 400);
    return Uuid::normalize($value);
}

function requestId(array $body, string $field): string
{
    $id = uuidParam($body[$field] ?? $_GET[$field] ?? null);
    if (array_key_exists($field, $body) && isset($_GET[$field]) && $id !== uuidParam($_GET[$field])) {
        responseFromString('conflicting identifiers', 400);
    }
    return $id;
}

function validateJSON(): array
{
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType === 'multipart/form-data') {
        if (($_GET['resource'] ?? null) !== 'upload' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            responseFromString('multipart bodies are only supported for uploads', 415);
        }
        return [];
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw !== false && strlen($raw) > 65536) responseFromString('JSON body exceeds the 64 KiB limit', 413);
    if ($raw === '' || $raw === false) return [];
    if ($contentType !== 'application/json') responseFromString('use application/json', 415);
    try {
        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof stdClass) responseFromString('JSON body must be an object', 400);
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        responseFromString('Invalid JSON payload', 400);
    }
}

function responseFromString(string $message, int $code): never
{
    http_response_code($code);
    echo json_encode(['status' => $code >= 400 ? 'error' : 'success', 'message' => $message, 'data' => []], JSON_THROW_ON_ERROR);
    exit;
}

function responseFromController(string $response): never
{
    $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    if (($data['flag'] ?? null) === 7) header('Retry-After: 60');
    http_response_code(match ($data['flag'] ?? 3) {
        0 => 200, 1 => 404, 2 => 400, 4 => 403, 5 => 401, 6 => 409, 7 => 429, default => 500,
    });
    echo $response;
    exit;
}

/** Remove replaced files only once no user or pet still references them. */
function cleanupPhoto(?string $path): void
{
    global $connection;
    if ($path === null || !preg_match('/\Aassets\/img\/uploads\/[a-f0-9]{32}\.(?:jpg|jpeg|png|gif|webp)\z/', $path)) return;
    $query = $connection->prepare('SELECT (SELECT COUNT(*) FROM Users WHERE profilePhoto = ?) + (SELECT COUNT(*) FROM Pets WHERE petPhoto = ?)');
    $query->execute([$path, $path]);
    if ((int)$query->fetchColumn() === 0 && is_file(__DIR__ . '/../' . $path)) unlink(__DIR__ . '/../' . $path);
}

function optionalSession(): ?string
{
    global $userRepository;
    if (!Auth::isLoggedIn()) return null;
    $id = Auth::get('userId');
    if (!Uuid::isValid($id) || !$userRepository->isActive(Uuid::stringToBytes($id))) { Auth::destroy(); return null; }
    return Uuid::normalize($id);
}
function textParam(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) responseFromString("$key must be a string", 400);
    return trim($value);
}
function intParam(string $key, int $default, int $min, int $max): int
{
    $raw = $_GET[$key] ?? (string)$default;
    if (!is_string($raw) || !ctype_digit($raw) || strlen($raw) > 9 || (int)$raw < $min || (int)$raw > $max) {
        responseFromString("$key must be an integer between $min and $max", 400);
    }
    return (int)$raw;
}
function boolParam(string $key): bool
{
    $value = $_GET[$key] ?? 'false';
    if (!in_array($value, ['true', 'false', '1', '0'], true)) responseFromString("$key must be true or false", 400);
    return in_array($value, ['true', '1'], true);
}
