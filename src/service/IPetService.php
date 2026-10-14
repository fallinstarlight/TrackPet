<?php
namespace TrackPet\service;
use TrackPet\model\response\Response;


interface IPetService
{
    public function createPet(array $pet): Response;
    public function editPet(array $pet): Response;
    public function deletePet(string $petId): Response;
    public function changeOwnership(string $petId, string $oldOwnerId, string $newOwnerId): Response;
    public function toggleLost(string $petId, string $ownerId): Response;
    public function getLostStatus(string $petId): Response;
    public function getPetById(string $petId): Response;
}