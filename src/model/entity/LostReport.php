<?php
namespace TrackPet\model\entity;

use TrackPet\utils\TrackingInput as Input;
use TrackPet\utils\ApiException;

class LostReport
{
    public static function fromRequest(array $data): array
    {
        $data = Input::shape($data, [
            'petId' => 'string', 'lostAt' => 'string', 'lastSeenArea' => 'string', 'details' => 'string', 'clientRequestId' => 'string',
            'lastSeenLatitude' => 'float?', 'lastSeenLongitude' => 'float?',
        ], ['petId', 'lastSeenArea']);
        $latitude = $data['lastSeenLatitude'] ?? null;
        $longitude = $data['lastSeenLongitude'] ?? null;
        if (($latitude === null) !== ($longitude === null)) throw new ApiException('Proporciona ambas coordenadas o deja ambas vacías.');
        $result = [
            'petId' => Input::uuid($data['petId'], 'petId'),
            'clientRequestId' => isset($data['clientRequestId']) ? Input::uuid($data['clientRequestId'], 'clientRequestId') : \TrackPet\utils\Uuid::v7Bytes(),
            'lostAt' => Input::databaseDate(isset($data['lostAt']) ? Input::date($data['lostAt'], 'lostAt') : Input::now()),
            'lastSeenArea' => Input::text($data['lastSeenArea'], 'La zona', 160, true),
            'details' => Input::text($data['details'] ?? '', 'La descripción', 2000),
            'lastSeenLatitude' => $latitude === null ? null : Input::number($latitude, 'La latitud', -90, 90),
            'lastSeenLongitude' => $longitude === null ? null : Input::number($longitude, 'La longitud', -180, 180),
        ];
        $payload = $result;
        unset($payload['clientRequestId']);
        $payload['petId'] = bin2hex($payload['petId']);
        if (!isset($data['lostAt'])) $payload['lostAt'] = null;
        $result['payloadHash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        return $result;
    }
    public static function changes(array $data): array
    {
        $data = Input::shape($data, [
            'lostAt' => 'string', 'lastSeenArea' => 'string', 'details' => 'string',
            'lastSeenLatitude' => 'float?', 'lastSeenLongitude' => 'float?',
        ]);
        if (!$data) throw new ApiException('Envía al menos un campo para actualizar.');
        if (array_key_exists('lastSeenLatitude', $data) !== array_key_exists('lastSeenLongitude', $data)) throw new ApiException('Actualiza ambas coordenadas a la vez.');
        if (array_key_exists('lastSeenLatitude', $data)) {
            if (($data['lastSeenLatitude'] === null) !== ($data['lastSeenLongitude'] === null)) throw new ApiException('Proporciona ambas coordenadas o deja ambas vacías.');
            if ($data['lastSeenLatitude'] !== null) {
                $data['lastSeenLatitude'] = Input::number($data['lastSeenLatitude'], 'La latitud', -90, 90);
                $data['lastSeenLongitude'] = Input::number($data['lastSeenLongitude'], 'La longitud', -180, 180);
            }
        }
        if (isset($data['lostAt'])) $data['lostAt'] = Input::databaseDate(Input::date($data['lostAt'], 'lostAt'));
        if (isset($data['lastSeenArea'])) $data['lastSeenArea'] = Input::text($data['lastSeenArea'], 'La zona', 160, true);
        if (isset($data['details'])) $data['details'] = Input::text($data['details'], 'La descripción', 2000);
        return $data;
    }
}
