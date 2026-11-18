<?php
namespace TrackPet\service\implementation;
use PDOException;
use TrackPet\repository\DiscoveryRepository;
use TrackPet\model\response\Response;
use TrackPet\utils\{ApiException, DbErrorHandler, TrackingInput as Input};
class DiscoveryServiceImp
{
    public function __construct(private DiscoveryRepository $repository) {}
    public function listing(string $kind, string $search, bool $lostOnly, int $limit, int $offset): Response
    {
        try {
            if (!in_array($kind, ['users', 'pets'], true)) throw new ApiException('kind debe ser users o pets.');
            return Response::success('Directorio actualizado.', $this->repository->listing($kind, Input::text($search, 'La búsqueda', 100), $lostOnly, $limit, $offset));
        } catch (ApiException $e) { return Response::error($e->getMessage(), $e->flag, $e->data); }
        catch (PDOException $e) { $result = new Response(); DbErrorHandler::apply($e, $result); return $result; }
    }
    public function stats(): Response
    {
        try { return Response::success('Resumen de la comunidad.', $this->repository->stats()); }
        catch (PDOException $e) { $result = new Response(); DbErrorHandler::apply($e, $result); return $result; }
    }
}
