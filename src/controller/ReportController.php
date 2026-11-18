<?php
namespace TrackPet\controller;

use TrackPet\service\implementation\ReportServiceImp;

class ReportController
{
    public function __construct(private ReportServiceImp $service) {}
    public function create(array $body, string $actor): string { return json_encode($this->service->create($body, $actor), JSON_THROW_ON_ERROR); }
    public function edit(string $id, array $body, string $actor): string { return json_encode($this->service->edit($id, $body, $actor), JSON_THROW_ON_ERROR); }
    public function close(string $id, array $body, string $actor): string { return json_encode($this->service->close($id, $body, $actor), JSON_THROW_ON_ERROR); }
    public function detail(string $id, ?string $actor): string { return json_encode($this->service->detail($id, $actor), JSON_THROW_ON_ERROR); }
    public function activeForPet(string $id, ?string $actor): string { return json_encode($this->service->activeForPet($id, $actor), JSON_THROW_ON_ERROR); }
    public function listing(?string $actor, bool $mine, string $query, int $limit, int $offset): string { return json_encode($this->service->listing($actor, $mine, $query, $limit, $offset), JSON_THROW_ON_ERROR); }
    public function sighting(array $body, ?string $actor): string { return json_encode($this->service->sighting($body, $actor), JSON_THROW_ON_ERROR); }
    public function beaconSighting(array $body, string $reportId, string $requestId, string $actor): string
    {
        return json_encode($this->service->beaconSighting($body, $reportId, $requestId, $actor), JSON_THROW_ON_ERROR);
    }
    public function tracking(string $id, string $actor, int $limit, int $offset): string { return json_encode($this->service->tracking($id, $actor, $limit, $offset), JSON_THROW_ON_ERROR); }
    public function mySightings(string $actor, int $limit, int $offset): string { return json_encode($this->service->mySightings($actor, $limit, $offset), JSON_THROW_ON_ERROR); }
    public function retract(string $id, string $actor): string { return json_encode($this->service->retract($id, $actor), JSON_THROW_ON_ERROR); }
}
