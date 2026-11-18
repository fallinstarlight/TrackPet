<?php
namespace TrackPet\controller;
use TrackPet\service\implementation\DiscoveryServiceImp;
class DiscoveryController
{
    public function __construct(private DiscoveryServiceImp $service) {}
    public function listing(string $kind, string $search, bool $lostOnly, int $limit, int $offset): string { return json_encode($this->service->listing($kind, $search, $lostOnly, $limit, $offset), JSON_THROW_ON_ERROR); }
    public function stats(): string { return json_encode($this->service->stats(), JSON_THROW_ON_ERROR); }
}
