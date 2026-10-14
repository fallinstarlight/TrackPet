<?php
namespace TrackPet\repository;
use PDO;
use PDOException;
use TrackPet\model\entity\Pet;

/**
 * Class to handle database queries relating "Pet" inquiries
 * 
 * Needs a valid PDO object to initialize it
 */

class PetRepository
{
    private PDO $CONN;

    public function __construct(PDO $CONN)
    {
        $this->CONN = $CONN;
    }

    /**
     * Creates a pet using the data present on the Pet object
     * 
     * As the service layer and the Pet class itself already handle most of the data cleaning and validation
     * no further effort should be put in this call
     * 
     * Some parameters are optional, in the sense that they can be null inside the database, there's no worry to check for null values being sent into this function,
     * unless they're part of the mandatory parameters which are already described in the Pet class
     * 
     * As a final note, both petId and ownerId are binary values and should always be sent using the PDO::PARAM_LOB type
     * 
     * @param Pet $pet
     * The Pet object containing the info to be sent
     */
    public function create(Pet $pet): array
    {
        $query = $this->CONN->prepare("CALL createPet(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $query->bindValue(1, $pet->petId, PDO::PARAM_LOB);
        $query->bindValue(2, $pet->petName);
        $query->bindValue(3, $pet->petSpecies);
        $query->bindValue(4, $pet->petColour);
        $query->bindValue(5, $pet->petWeight);
        $query->bindValue(6, $pet->petHeight);
        $query->bindValue(7, $pet->petLenght);
        $query->bindValue(8, $pet->petDescription);
        $query->bindValue(9, $pet->ownerId, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    /**
     * Sends a request to update an existing pet using the data present on the Pet object
     * 
     * As the service layer and the Pet class itself already handle most of the data cleaning and validation
     * no further effort should be put in this call
     * 
     * Since every parameter is technically optional, in the sense that they can be null inside the procedure, denoting a non updated parameter, there's no worry to check for null values being sent into this function
     * 
     * As a final note, both petId and ownerId are binary values and should always be sent using the PDO::PARAM_LOB type
     * 
     * @param Pet $pet
     * The Pet object containing the info to be sent
     */
    public function edit(Pet $pet): array
    {
        $query = $this->CONN->prepare("CALL editPet(?, ?, ?, ?, ?, ?, ?)");
        $query->bindValue(1, $pet->petId, PDO::PARAM_LOB);
        $query->bindValue(2, $pet->petName);
        $query->bindValue(3, $pet->petColour);
        $query->bindValue(4, $pet->petWeight);
        $query->bindValue(5, $pet->petHeight);
        $query->bindValue(6, $pet->petLenght);
        $query->bindValue(7, $pet->petDescription);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    /**
     * Delete a Pet given its id
     * The id must be a valid V7 UUID in its binary form, 128 bits, so it's better used in
     * conjunction with the Uuid class
     * 
     * If this function was to be called alone, the value should be a 16 byte string
     * 
     * The deletion is a soft delete, it won't remove the database row, it will rather just set the pet status to 'inactive'
     */
    public function delete(string $petIdBytes): void
    {
        $query = $this->CONN->prepare("CALL deletePet(?)");
        $query->bindValue(1, $petIdBytes, PDO::PARAM_LOB);
        $query->execute();
        $query->closeCursor();
    }

    public function changeOwnership(string $petIdBytes, string $oldOwnerId, string $newOwnerId): void
    {
        $query = $this->CONN->prepare("CALL changePetOwnership(?, ?, ?)");
        $query->bindValue(1, $petIdBytes, PDO::PARAM_LOB);
        $query->bindValue(2, $oldOwnerId, PDO::PARAM_LOB);
        $query->bindValue(3, $newOwnerId, PDO::PARAM_LOB);
        $query->execute();
        $query->closeCursor();
    }

    public function toggleLost(string $petIdBytes, string $ownerId): array
    {
        $query = $this->CONN->prepare("CALL toggleLostStatus(?, ?)");
        $query->bindValue(1, $petIdBytes, PDO::PARAM_LOB);
        $query->bindValue(2, $ownerId, PDO::PARAM_LOB);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    public function getLostStatus(string $petIdBytes): array
    {
        $query = $this->CONN->prepare("CALL getLostStatus(?)");
        $query->bindValue(1, $petIdBytes, PDO::PARAM_LOB);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    public function getById(string $petIdBytes): array
    {
        $query = $this->CONN->prepare("CALL getPetById(?)");
        $query->bindValue(1, $petIdBytes, PDO::PARAM_LOB);
        $query->execute();
        $result = $query->fetch();
        $query->closeCursor();
        return $result ?: [];
    }

    public function getByOwner(int $ownerId): array
    {
        $query = $this->CONN->prepare("CALL getPetsByOwner(?)");
        $query->bindValue(1, $ownerId, PDO::PARAM_INT);
        $query->execute();
        $results = $query->fetchAll();
        $query->closeCursor();
        return $results ?: [];
    }
}