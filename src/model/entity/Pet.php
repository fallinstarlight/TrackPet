<?php

namespace TrackPet\model\entity;

/**
 * Class to represent Pets from the database as pet objects
 * 
 * Requires array for construction
 * 
 * If you were to call the constructor, you should validate the parameters first to avoid uncaught InvalidArgumentException
 * 
 */
class Pet
{
    public ?string $petId = null;      // binary 16 bytes (or UUID string when coming from API)
    public ?string $petName = null;
    public ?string $petSpecies = null;
    public ?string $petColour = null;
    public ?float  $petWeight = null;
    public ?float  $petHeight = null;
    public ?float  $petLenght = null;
    public ?bool   $isLost = null;
    public ?string $petDescription = null;
    public ?string $ownerId = null;
    public ?bool   $isActive = null;
    public ?string $creationDate = null;
    public ?string $modificationDate = null;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Validates parameters before pet creation or update, represents basic validations such as string lenght and the presence of required parameters
     * 
     * Further validation and string cleanning is done inside the service either by itself or using the Sanitizer and Validator classes
     */
    public function validateParams(bool $isEdit = false): array
    {
        $errors = [];
        $required = ['petName', 'petSpecies', 'petColour', 'ownerId'];
        $maxLengths = [
            'petName'        => 100,
            'petSpecies'     => 100,
            'petColour'      => 100,
            'petDescription' => 500,
        ];

        if (!$isEdit) {
            foreach ($required as $field) {
                if (!isset($this->$field) || $this->$field === '') {
                    $errors[] = "missing the following info: $field";
                }
            }

            if (!empty($errors)) return $errors;
        }

        foreach ($maxLengths as $field => $max) {
            if (isset($this->$field) && mb_strlen($this->$field) > $max) {
                $errors[] = "$field exceeds $max char limit";
            }
        }

        foreach (['petWeight', 'petHeight', 'petLenght'] as $field) {
            if (isset($this->$field) && $this->$field !== null && !is_numeric($this->$field)) {
                $errors[] = "$field must be numeric";
            }
        }

        return $errors;
    }
}
