<?php
namespace TrackPet\controller;
use TrackPet\service\IPetService;
use TrackPet\service\implementation\PetServiceImp;

class PetController
{
    private PetServiceImp $petService;

    public function __construct(PetServiceImp $petService)
    {
        $this->petService = $petService;
    }

    public function createPet(array $pet): string
    {
        return json_encode($this->petService->createPet($pet));
    }

    public function editPet(array $pet): string
    {
        return json_encode($this->petService->editPet($pet));
    }

    public function deletePet(string $petId): string
    {
        return json_encode($this->petService->deletePet($petId));
    }

    public function changeOwnership(string $petId, string $oldOwnerId, string $newOwnerId): string
    {
        return json_encode($this->petService->changeOwnership($petId, $oldOwnerId, $newOwnerId));
    }

    public function toggleLost(string $petId, string $ownerId): string
    {
        return json_encode($this->petService->toggleLost($petId, $ownerId));
    }

    public function getLostStatus(string $petId): string
    {
        return json_encode($this->petService->getLostStatus($petId));
    }

    public function getPetById(string $petId): string
    {
        return json_encode($this->petService->getPetById($petId));
    }
}