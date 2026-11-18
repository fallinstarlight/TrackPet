<?php
namespace TrackPet\model\entity;

use TrackPet\utils\TrackingInput as Input;
use TrackPet\utils\ApiException;

class Sighting
{
    public static function fromBeacon(array $body, string $reportId, string $requestId): array
    {
        $coordinates = Input::shape($body, ['latitude' => 'float', 'longitude' => 'float'], ['latitude', 'longitude']);
        $result = self::fromRequest($coordinates + [
            'reportId' => $reportId, 'clientRequestId' => $requestId, 'source' => 'ble',
            'observedAt' => Input::now()->format('Y-m-d\TH:i:s.u\Z'),
        ]);
        // Receipt time changes on retry. Identify the original client command independently of it.
        $result['payloadHash'] = hash('sha256', json_encode([
            'beaconSighting', bin2hex($result['reportId']), $result['latitude'], $result['longitude'],
        ], JSON_THROW_ON_ERROR));
        return $result;
    }

    public static function fromRequest(array $data): array
    {
        $data = Input::shape($data, [
            'reportId' => 'string', 'latitude' => 'float', 'longitude' => 'float', 'accuracyMeters' => 'float?',
            'observedAt' => 'string', 'notes' => 'string', 'source' => 'string', 'clientRequestId' => 'string',
        ], ['reportId', 'latitude', 'longitude', 'observedAt', 'clientRequestId']);
        $source = $data['source'] ?? 'manual';
        if (!in_array($source, ['manual', 'gps', 'ble'], true)) throw new ApiException('La fuente debe ser manual, gps o ble.');
        $result = [
            'reportId' => Input::uuid($data['reportId'], 'reportId'),
            'latitude' => Input::number($data['latitude'], 'La latitud', -90, 90),
            'longitude' => Input::number($data['longitude'], 'La longitud', -180, 180),
            'accuracyMeters' => isset($data['accuracyMeters']) ? Input::number($data['accuracyMeters'], 'La precisión', 0, 100000, 2) : null,
            'observedAt' => Input::databaseDate(Input::date($data['observedAt'], 'observedAt')),
            'notes' => Input::text($data['notes'] ?? '', 'Las notas', 1000),
            'source' => $source,
            'clientRequestId' => Input::uuid($data['clientRequestId'], 'clientRequestId'),
        ];
        $payload = $result;
        $payload['reportId'] = bin2hex($payload['reportId']);
        $payload['clientRequestId'] = bin2hex($payload['clientRequestId']);
        $result['payloadHash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        return $result;
    }
}
