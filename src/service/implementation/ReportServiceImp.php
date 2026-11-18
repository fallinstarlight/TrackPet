<?php
namespace TrackPet\service\implementation;

use PDOException;
use TrackPet\repository\ReportRepository;
use TrackPet\model\entity\{LostReport, Sighting};
use TrackPet\model\response\Response;
use TrackPet\utils\{ApiException, DbErrorHandler, TrackingInput as Input};

class ReportServiceImp
{
    public function __construct(private ReportRepository $repository) {}
    public function create(array $body, string $actor): Response
    {
        return $this->run(fn() => $this->repository->create(LostReport::fromRequest($body), Input::uuid($actor, 'userId')), 'La búsqueda está abierta. Puedes compartir el reporte.');
    }
    public function edit(string $id, array $body, string $actor): Response
    {
        return $this->run(fn() => $this->repository->edit(Input::uuid($id, 'reportId'), LostReport::changes($body), Input::uuid($actor, 'userId')), 'La información del reporte se actualizó.');
    }
    public function close(string $id, array $body, string $actor): Response
    {
        return $this->run(function () use ($id, $body, $actor): array {
            $body = Input::shape($body, ['status' => 'string', 'closureReason' => 'string'], ['status']);
            if (!in_array($body['status'], ['recovered', 'cancelled'], true)) throw new ApiException('El estado de cierre debe ser recovered o cancelled.');
            $reason = Input::text($body['closureReason'] ?? '', 'El motivo', 1000);
            return $this->repository->close(Input::uuid($id, 'reportId'), Input::uuid($actor, 'userId'), $body['status'], $reason);
        }, 'El reporte se cerró.');
    }
    public function detail(string $id, ?string $actor): Response
    {
        return $this->run(fn() => $this->repository->detail(Input::uuid($id, 'reportId'), $actor === null ? null : Input::uuid($actor, 'userId')), 'Reporte encontrado.');
    }
    public function activeForPet(string $id, ?string $actor): Response
    {
        return $this->run(fn() => $this->repository->activeForPet(Input::uuid($id, 'petId'), $actor === null ? null : Input::uuid($actor, 'userId')), 'Búsqueda activa encontrada.');
    }
    public function listing(?string $actor, bool $mine, string $query, int $limit, int $offset): Response
    {
        return $this->run(fn() => $this->repository->listing($actor === null ? null : Input::uuid($actor, 'userId'), $mine, Input::text($query, 'La búsqueda', 100), $limit, $offset), 'Reportes encontrados.');
    }
    public function sighting(array $body, ?string $actor): Response
    {
        return $this->run(function () use ($body, $actor): array {
            $data = Sighting::fromRequest($body);
            if ($actor === null && $data['source'] === 'ble') {
                throw new ApiException('Inicia sesión para enviar una detección BLE.', Response::FLAG_UNAUTHORIZED);
            }
            return $this->repository->sighting($data, $actor === null ? null : Input::uuid($actor, 'userId'));
        }, 'Avistamiento recibido. Gracias por ayudar.');
    }
    public function beaconSighting(array $body, string $reportId, string $requestId, string $actor): Response
    {
        return $this->run(fn() => $this->repository->sighting(Sighting::fromBeacon($body, $reportId, $requestId), Input::uuid($actor, 'userId')), 'Detección BLE recibida.');
    }
    public function tracking(string $id, string $actor, int $limit, int $offset): Response
    {
        return $this->run(fn() => $this->repository->tracking(Input::uuid($id, 'reportId'), Input::uuid($actor, 'userId'), $limit, $offset), 'Ubicaciones actualizadas.');
    }
    public function mySightings(string $actor, int $limit, int $offset): Response
    {
        return $this->run(fn() => $this->repository->mySightings(Input::uuid($actor, 'userId'), $limit, $offset), 'Tus avistamientos.');
    }
    public function retract(string $id, string $actor): Response
    {
        return $this->run(fn() => $this->repository->retract(Input::uuid($id, 'sightingId'), Input::uuid($actor, 'userId')), 'El avistamiento se retiró del mapa.');
    }
    private function run(callable $action, string $message): Response
    {
        try { return Response::success($message, $action()); }
        catch (ApiException $e) { return Response::error($e->getMessage(), $e->flag, $e->data); }
        catch (PDOException $e) { $response = new Response(); DbErrorHandler::apply($e, $response); return $response; }
    }
}
