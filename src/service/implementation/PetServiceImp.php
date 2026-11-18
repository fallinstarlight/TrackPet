<?php
namespace TrackPet\service\implementation;

use InvalidArgumentException;
use PDOException;
use TrackPet\service\IPetService;
use TrackPet\repository\PetRepository;
use TrackPet\model\response\Response;
use TrackPet\model\entity\Pet;
use TrackPet\utils\{DbErrorHandler, Uuid, Validator};

class PetServiceImp implements IPetService
{
    public function __construct(private PetRepository $petRepository) {}

    public function createPet(array $data): Response
    {
        return $this->save($data, false);
    }

    public function editPet(array $data, string $ownerId): Response
    {
        $data['ownerId'] = $ownerId;
        return $this->save($data, true);
    }

    private function save(array $data, bool $edit): Response
    {
        $schema = [
            'petName' => 'string', 'petSpecies' => 'string', 'petColour' => 'string',
            'petWeight' => 'float?', 'petHeight' => 'float?', 'petLenght' => 'float?',
            'petDescription' => 'string?', 'petPhoto' => 'string?', 'ownerId' => 'string',
        ];
        if ($edit) $schema['petId'] = 'string';
        $errors = Validator::validateShape($data, $schema);
        foreach (array_diff(array_keys($data), array_keys($schema)) as $field) $errors[] = "unsupported field: $field";
        if (!Uuid::isValid($data['ownerId'] ?? null)) $errors[] = 'provide a valid ownerId';
        if ($edit && !Uuid::isValid($data['petId'] ?? null)) $errors[] = 'provide a valid petId';
        if ($errors) return Response::error('there are some errors with your request', Response::FLAG_USER_ERROR, $errors);
        $pet = new Pet(Validator::clean($data));
        $errors = $pet->validateParams($edit);
        if ($errors) return Response::error('there are some errors with your request', Response::FLAG_USER_ERROR, $errors);
        return $this->run(function () use ($pet, $edit): array {
            $pet->petId = $edit ? Uuid::stringToBytes($pet->petId) : Uuid::v7Bytes();
            $pet->ownerId = Uuid::stringToBytes($pet->ownerId);
            return $edit ? $this->petRepository->edit($pet) : $this->petRepository->create($pet);
        }, $edit ? 'Pet updated successfully' : 'Pet registered successfully');
    }

    public function deletePet(string $petId, string $ownerId): Response
    {
        return $this->run(function () use ($petId, $ownerId): array {
            $this->petRepository->delete(Uuid::stringToBytes($petId), Uuid::stringToBytes($ownerId));
            return [];
        }, 'Pet deleted successfully');
    }

    public function changeOwnership(string $petId, string $oldOwnerId, string $newOwnerId): Response
    {
        return $this->run(function () use ($petId, $oldOwnerId, $newOwnerId): array {
            $this->petRepository->changeOwnership(Uuid::stringToBytes($petId), Uuid::stringToBytes($oldOwnerId), Uuid::stringToBytes($newOwnerId));
            return [];
        }, 'Ownership changed successfully');
    }

    public function toggleLost(string $petId, string $ownerId): Response
    {
        return $this->run(fn() => $this->petRepository->toggleLost(Uuid::stringToBytes($petId), Uuid::stringToBytes($ownerId)), 'Lost status toggled');
    }

    public function getLostStatus(string $petId): Response
    {
        return $this->run(fn() => $this->petRepository->getLostStatus(Uuid::stringToBytes($petId)), 'Lost status retrieved');
    }

    public function getPetById(string $petId): Response
    {
        return $this->run(fn() => $this->petRepository->getById(Uuid::stringToBytes($petId)), 'Pet found');
    }

    private function run(callable $action, string $message): Response
    {
        try {
            return Response::success($message, Uuid::serializeRow($action()));
        } catch (PDOException $e) {
            $response = new Response();
            DbErrorHandler::apply($e, $response);
            return $response;
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }
    }
}
