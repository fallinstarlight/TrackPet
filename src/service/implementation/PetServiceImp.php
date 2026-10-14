<?php

namespace TrackPet\service\implementation;

use Exception;
use TrackPet\service\IPetService;
use TrackPet\repository\PetRepository;
use TrackPet\model\response\Response;
use TrackPet\model\entity\Pet;
use TrackPet\utils\DbErrorHandler;
use TrackPet\utils\Uuid;
use PDOException;
use TrackPet\utils\Validator;

class PetServiceImp implements IPetService
{
    private PetRepository $petRepository;

    public function __construct(PetRepository $petRepository)
    {
        $this->petRepository = $petRepository;
    }

    public function createPet(array $pet): Response
    {
        $response = new Response;

        $shapeErrors = Validator::validateShape($pet, [
            'petName'        => 'string',
            'petSpecies'     => 'string',
            'petColour'      => 'string',
            'petWeight'      => 'float',
            'petHeight'      => 'float',
            'petLenght'      => 'float',
            'petDescription' => 'string',
            'ownerId'        => 'int',
        ]);

        if (!empty($shapeErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $shapeErrors;
            return $response;
        }

        $pet = new Pet($pet);

        $errors = $pet->validateParams();
        if (!empty($errors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $errors;
            return $response;
        }

        $pet->petId = Uuid::v7Bytes();

        try {
            $result = $this->petRepository->create($pet);

            if (isset($result['petId'])) {
                $result['petId'] = Uuid::bytesToString($result['petId']);
            }

            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Pet registered successfully";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function editPet(array $pet): Response
    {
        $response = new Response;

        $shapeErrors = Validator::validateShape($pet, [
            'petName'        => 'string',
            'petSpecies'     => 'string',
            'petColour'      => 'string',
            'petWeight'      => 'float',
            'petHeight'      => 'float',
            'petLenght'      => 'float',
            'petDescription' => 'string'
        ]);

        if (!empty($shapeErrors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $shapeErrors;
            return $response;
        }

        $petData  = $pet;
        $pet      = new Pet($petData);

        $errors = $pet->validateParams(true);
        if (!empty($errors)) {
            $response->message = "there are some errors with your request";
            $response->data    = $errors;
            return $response;
        }

        try {
            $pet->petId = Uuid::stringToBytes($pet->petId);
            $pet->ownerId = Uuid::stringToBytes($pet->ownerId);
            $result = $this->petRepository->edit($pet);

            if (isset($result['petId'])) {
                $result['petId'] = Uuid::bytesToString($result['petId']);
            }

            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Pet updated successfully";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        }
        return $response;
    }

    public function deletePet(string $petId): Response
    {
        $response = new Response;
        try {
            $petIdBytes = Uuid::stringToBytes($petId);
            $this->petRepository->delete($petIdBytes);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Pet deleted successfully";
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }
        return $response;
    }

    public function changeOwnership(string $petId, string $oldOwnerId, string $newOwnerId): Response
    {
        $response = new Response;
        try {
            $petIdBytes = Uuid::stringToBytes($petId);
            $oldOwnerId = Uuid::stringToBytes($oldOwnerId);
            $newOwnerId = Uuid::stringToBytes($newOwnerId);
            $this->petRepository->changeOwnership($petIdBytes, $oldOwnerId, $newOwnerId);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Ownership changed successfully";
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }
        return $response;
    }

    public function toggleLost(string $petId, string $ownerId): Response
    {
        $response = new Response;
        try {
            $petIdBytes = Uuid::stringToBytes($petId);
            $ownerId = Uuid::stringToBytes($ownerId);
            $result = $this->petRepository->toggleLost($petIdBytes, $ownerId);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Lost status toggled";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }
        return $response;
    }

    public function getLostStatus(string $petId): Response
    {
        $response = new Response;
        try {
            $petIdBytes = Uuid::stringToBytes($petId);
            $result = $this->petRepository->getLostStatus($petIdBytes);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Lost status retrieved";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }
        return $response;
    }

    public function getPetById(string $petId): Response
    {
        $response = new Response;
        try {
            $petIdBytes = Uuid::stringToBytes($petId);
            $result = $this->petRepository->getById($petIdBytes);
            if (isset($result['petId']))
                $result['petId'] = Uuid::bytesToString($result['petId']);
            if (isset($result['ownerId']))
                $result['ownerId'] = Uuid::bytesToString($result['ownerId']);
            $response->status  = Response::STATUS_SUCCESS;
            $response->flag    = Response::FLAG_SUCCESS;
            $response->message = "Pet found";
            $response->data    = $result;
        } catch (PDOException $e) {
            DbErrorHandler::apply($e, $response);
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }
        return $response;
    }
}
