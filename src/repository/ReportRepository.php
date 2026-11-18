<?php
namespace TrackPet\repository;

use PDO;
use DateTimeImmutable;
use DateTimeZone;
use TrackPet\model\response\Response;
use TrackPet\utils\{ApiException, TrackingInput as Input, Transaction, Uuid};

class ReportRepository
{
    public function __construct(private PDO $db) {}

    private function query(string $sql, array $values = []): \PDOStatement
    {
        $query = $this->db->prepare($sql);
        foreach (array_values($values) as $index => $value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $query->bindValue($index + 1, $value, $type);
        }
        $query->execute();
        return $query;
    }
    private function row(string $sql, array $values = []): array { return $this->query($sql, $values)->fetch() ?: []; }

    private function actor(string $id): void
    {
        $user = $this->row('SELECT status FROM Users WHERE userId = ? FOR UPDATE', [$id]);
        if (($user['status'] ?? null) !== 'active') throw new ApiException('La sesión ya no está disponible.', Response::FLAG_UNAUTHORIZED);
    }
    private function pet(string $id): array
    {
        $pet = $this->row('SELECT petId, ownerId, isActive, isLost FROM Pets WHERE petId = ? FOR UPDATE', [$id]);
        if (!$pet) throw new ApiException('La mascota no existe.', Response::FLAG_NOT_FOUND);
        return $pet;
    }
    private function owned(array $pet, string $actor): void
    {
        if ($pet['ownerId'] !== $actor) throw new ApiException('Solo el dueño actual puede realizar esta acción.', Response::FLAG_FORBIDDEN);
        if (!(bool)$pet['isActive']) throw new ApiException('La mascota está dada de baja.', Response::FLAG_CONFLICT);
    }
    private function reportLock(string $id): array
    {
        $report = $this->row('SELECT * FROM LostReports WHERE reportId = ? FOR UPDATE', [$id]);
        if (!$report) throw new ApiException('El reporte no existe.', Response::FLAG_NOT_FOUND);
        return $report;
    }
    private function findPetId(string $reportId): string
    {
        $row = $this->row('SELECT petId FROM LostReports WHERE reportId = ?', [$reportId]);
        if (!$row) throw new ApiException('El reporte no existe.', Response::FLAG_NOT_FOUND);
        return $row['petId'];
    }

    public function create(array $data, string $ownerId): array
    {
        $id = Uuid::v7Bytes();
        return Transaction::run($this->db, function () use ($id, $data, $ownerId): array {
            $this->actor($ownerId);
            $existing = $this->row('SELECT reportId, payloadHash FROM LostReports WHERE ownerId = ? AND clientRequestId = ?', [$ownerId, $data['clientRequestId']]);
            if ($existing) {
                if (!is_string($existing['payloadHash']) || !hash_equals($existing['payloadHash'], $data['payloadHash'])) throw new ApiException('Este identificador de apertura ya se utilizó con otros datos.', Response::FLAG_CONFLICT);
                return $this->detail($existing['reportId'], $ownerId) + ['replayed' => true];
            }
            $this->owned($this->pet($data['petId']), $ownerId);
            $active = $this->row('SELECT reportId FROM LostReports WHERE activePetId = ?', [$data['petId']]);
            if ($active) throw new ApiException('Esta mascota ya tiene un reporte abierto.', Response::FLAG_CONFLICT, ['reportId' => Uuid::bytesToString($active['reportId'])]);
            $this->query('INSERT INTO LostReports (reportId, petId, ownerId, activePetId, lostAt, lastSeenArea, details, lastSeenLatitude, lastSeenLongitude, clientRequestId, payloadHash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $data['petId'], $ownerId, $data['petId'], $data['lostAt'], $data['lastSeenArea'], $data['details'], $data['lastSeenLatitude'], $data['lastSeenLongitude'], $data['clientRequestId'], $data['payloadHash'],
            ]);
            $this->query('UPDATE Pets SET isLost = 1 WHERE petId = ?', [$data['petId']]);
            return $this->detail($id, $ownerId) + ['replayed' => false];
        });
    }

    public function edit(string $reportId, array $changes, string $actor): array
    {
        return Transaction::run($this->db, function () use ($reportId, $changes, $actor): array {
            $this->actor($actor);
            $this->owned($this->pet($this->findPetId($reportId)), $actor);
            $report = $this->reportLock($reportId);
            if ($report['ownerId'] !== $actor) throw new ApiException('No tienes permiso para editar este reporte.', Response::FLAG_FORBIDDEN);
            if ($report['status'] !== 'open') throw new ApiException('El reporte está cerrado y no puede modificarse.', Response::FLAG_CONFLICT);
            if (isset($changes['lostAt'])) {
                $earliest = $this->query('SELECT MIN(observedAt) FROM Sightings WHERE reportId = ? AND isRetracted = 0', [$reportId])->fetchColumn();
                if ($earliest && new DateTimeImmutable($changes['lostAt'], new DateTimeZone('UTC')) > (new DateTimeImmutable($earliest, new DateTimeZone('UTC')))->modify('+5 minutes')) {
                    throw new ApiException('La fecha de desaparición no puede dejar avistamientos existentes fuera de la búsqueda.');
                }
            }
            $fields = []; $values = [];
            foreach (['lostAt', 'lastSeenArea', 'details', 'lastSeenLatitude', 'lastSeenLongitude'] as $field) {
                if (array_key_exists($field, $changes)) { $fields[] = "$field = ?"; $values[] = $changes[$field]; }
            }
            if (!$fields) throw new ApiException('No hay cambios válidos.');
            $values[] = $reportId;
            $this->query('UPDATE LostReports SET ' . implode(', ', $fields) . ' WHERE reportId = ?', $values);
            return $this->detail($reportId, $actor);
        });
    }

    public function close(string $reportId, string $ownerId, string $status, string $reason): array
    {
        return Transaction::run($this->db, function () use ($reportId, $ownerId, $status, $reason): array {
            $this->actor($ownerId);
            $this->owned($this->pet($this->findPetId($reportId)), $ownerId);
            $report = $this->reportLock($reportId);
            if ($report['ownerId'] !== $ownerId) throw new ApiException('No tienes permiso para cerrar este reporte.', Response::FLAG_FORBIDDEN);
            // Repeating the same close is safe; a different terminal state is a conflict.
            if ($report['status'] !== 'open') {
                if ($report['status'] !== $status) throw new ApiException('El reporte ya está cerrado con otro estado.', Response::FLAG_CONFLICT);
                return $this->detail($reportId, $ownerId);
            }
            $this->query('UPDATE LostReports SET status = ?, activePetId = NULL, closedAt = UTC_TIMESTAMP(6), closureReason = ? WHERE reportId = ?', [$status, $reason, $reportId]);
            $this->query('UPDATE Pets SET isLost = 0 WHERE petId = ?', [$report['petId']]);
            return $this->detail($reportId, $ownerId);
        });
    }

    private function rawDetail(string $id): array
    {
        return $this->row("SELECT r.*, p.petName, p.petSpecies, p.petColour, p.petPhoto,
            p.ownerId AS currentOwnerId, p.isActive AS petActive, u.username AS ownerUsername,
            u.status AS originalOwnerStatus, currentUser.status AS currentOwnerStatus,
            (SELECT COUNT(*) FROM Sightings s WHERE s.reportId = r.reportId AND s.isRetracted = 0) AS sightingsCount
            FROM LostReports r JOIN Pets p ON p.petId = r.petId
            JOIN Users u ON u.userId = r.ownerId JOIN Users currentUser ON currentUser.userId = p.ownerId
            WHERE r.reportId = ?", [$id]);
    }
    private function canTrack(array $row, ?string $actor): bool
    {
        return $actor !== null && $row['ownerId'] === $actor && $row['currentOwnerId'] === $actor && $row['currentOwnerStatus'] === 'active';
    }
    private function reportOutput(array $row, bool $private): array
    {
        $fields = ['reportId', 'petId', 'ownerId', 'status', 'lostAt', 'lastSeenArea', 'details', 'createdAt', 'updatedAt', 'closedAt',
            'petName', 'petSpecies', 'petColour', 'petPhoto', 'ownerUsername', 'sightingsCount'];
        if ($private) $fields = array_merge($fields, ['lastSeenLatitude', 'lastSeenLongitude', 'closureReason', 'petActive']);
        $result = Uuid::serializeRow(array_intersect_key($row, array_flip($fields)));
        foreach (['lostAt', 'createdAt', 'updatedAt', 'closedAt'] as $field) $result[$field] = Input::outputDate($result[$field]);
        foreach (['lastSeenLatitude', 'lastSeenLongitude'] as $field) if (isset($result[$field])) $result[$field] = (float)$result[$field];
        $result['sightingsCount'] = (int)$result['sightingsCount'];
        $result['canTrack'] = $private;
        return $result;
    }
    public function detail(string $id, ?string $actor = null): array
    {
        $row = $this->rawDetail($id);
        if (!$row) throw new ApiException('El reporte no existe.', Response::FLAG_NOT_FOUND);
        $private = $this->canTrack($row, $actor);
        if (!$private && (!(bool)$row['petActive'] || $row['originalOwnerStatus'] !== 'active' || $row['currentOwnerStatus'] !== 'active')) {
            throw new ApiException('El reporte ya no está disponible.', Response::FLAG_NOT_FOUND);
        }
        return $this->reportOutput($row, $private);
    }

    public function activeForPet(string $petId, ?string $actor): array
    {
        $row = $this->row("SELECT r.reportId FROM LostReports r JOIN Pets p ON p.petId = r.activePetId
            WHERE p.petId = ? AND p.isActive = 1 AND p.isLost = 1 AND r.status = 'open'", [$petId]);
        if (!$row) throw new ApiException('Esta mascota no tiene una búsqueda abierta.', Response::FLAG_NOT_FOUND);
        return $this->detail($row['reportId'], $actor);
    }

    public function listing(?string $actor, bool $mine, string $search, int $limit, int $offset): array
    {
        $where = $mine ? 'r.ownerId = ? AND p.ownerId = ?' : "r.status = 'open' AND p.isActive = 1 AND u.status = 'active' AND currentUser.status = 'active'";
        $params = $mine ? [$actor, $actor] : [];
        if ($search !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $where .= " AND (p.petName LIKE ? ESCAPE '!' OR p.petSpecies LIKE ? ESCAPE '!' OR r.lastSeenArea LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like);
        }
        $join = ' FROM LostReports r JOIN Pets p ON p.petId = r.petId JOIN Users u ON u.userId = r.ownerId JOIN Users currentUser ON currentUser.userId = p.ownerId WHERE ' . $where;
        $total = (int)$this->query('SELECT COUNT(*)' . $join, $params)->fetchColumn();
        $rows = $this->query("SELECT r.*, p.petName, p.petSpecies, p.petColour, p.petPhoto, p.isActive AS petActive, u.username AS ownerUsername,
            (SELECT COUNT(*) FROM Sightings s WHERE s.reportId = r.reportId AND s.isRetracted = 0) AS sightingsCount" . $join . ' ORDER BY r.createdAt DESC, r.reportId DESC LIMIT ? OFFSET ?', [...$params, $limit, $offset])->fetchAll();
        return ['items' => array_map(fn($row) => $this->reportOutput($row, $mine), $rows), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    private function sightingOutput(array $row): array
    {
        $result = Uuid::serializeRow($row);
        unset($result['payloadHash']);
        foreach (['latitude', 'longitude', 'accuracyMeters'] as $key) if (isset($result[$key])) $result[$key] = (float)$result[$key];
        foreach (['observedAt', 'createdAt', 'retractedAt'] as $key) if (array_key_exists($key, $result)) $result[$key] = Input::outputDate($result[$key]);
        if (isset($result['isRetracted'])) $result['isRetracted'] = (bool)$result['isRetracted'];
        return $result;
    }
    public function sighting(array $data, ?string $actor): array
    {
        $id = Uuid::v7Bytes();
        // Reports always belong to the same pet. Resolve it before starting the read snapshot.
        $petId = $this->findPetId($data['reportId']);
        return Transaction::run($this->db, function () use ($data, $actor, $id, $petId): array {
            if ($actor !== null) $this->actor($actor);
            $pet = $this->pet($petId);
            // The account lock serializes signed-in submissions, the pet lock serializes guests for this report.
            $scope = $actor === null ? 'reporterId IS NULL AND reportId = ?' : 'reporterId = ?';
            $scopeId = $actor ?? $data['reportId'];
            $existing = $this->row("SELECT * FROM Sightings WHERE $scope AND clientRequestId = ?", [$scopeId, $data['clientRequestId']]);
            if ($existing) {
                if (!hash_equals($existing['payloadHash'], $data['payloadHash'])) throw new ApiException('Este identificador de envío ya se utilizó con otros datos.', Response::FLAG_CONFLICT);
                return $this->sightingOutput($existing) + ['replayed' => true];
            }
            $report = $this->reportLock($data['reportId']);
            $owner = $this->row('SELECT status FROM Users WHERE userId = ?', [$pet['ownerId']]);
            if ($report['status'] !== 'open' || !(bool)$pet['isLost'] || !(bool)$pet['isActive'] || ($owner['status'] ?? null) !== 'active' || $pet['ownerId'] !== $report['ownerId']) {
                throw new ApiException('La búsqueda ya no está activa; no se aceptan más avistamientos.', Response::FLAG_CONFLICT);
            }
            $minimum = (new DateTimeImmutable($report['lostAt'], new DateTimeZone('UTC')))->modify('-5 minutes');
            if (new DateTimeImmutable($data['observedAt'], new DateTimeZone('UTC')) < $minimum) throw new ApiException('El avistamiento es anterior a la desaparición. Revisa su fecha.');
            $recent = (int)$this->query("SELECT COUNT(*) FROM Sightings WHERE $scope AND createdAt >= UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE", [$scopeId])->fetchColumn();
            if ($recent >= 20) throw new ApiException('Has enviado muchos avistamientos. Intenta de nuevo en un minuto.', Response::FLAG_RATE_LIMIT);
            $this->query('INSERT INTO Sightings (sightingId, reportId, reporterId, clientRequestId, payloadHash, latitude, longitude, accuracyMeters, observedAt, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $id, $data['reportId'], $actor, $data['clientRequestId'], $data['payloadHash'], $data['latitude'], $data['longitude'], $data['accuracyMeters'], $data['observedAt'], $data['notes'], $data['source'],
            ]);
            return $this->sightingOutput($this->row('SELECT * FROM Sightings WHERE sightingId = ?', [$id])) + ['replayed' => false];
        });
    }

    public function tracking(string $reportId, string $actor, int $limit, int $offset): array
    {
        return Transaction::run($this->db, function () use ($reportId, $actor, $limit, $offset): array {
            $report = $this->rawDetail($reportId);
            if (!$report) throw new ApiException('El reporte no existe.', Response::FLAG_NOT_FOUND);
            if (!$this->canTrack($report, $actor)) throw new ApiException('Solo el dueño autorizado puede consultar estas ubicaciones.', Response::FLAG_FORBIDDEN);
            $sql = "SELECT s.*, CASE WHEN s.reporterId IS NULL THEN NULL WHEN u.status = 'active' THEN u.username ELSE 'Cuenta inactiva' END AS reporterUsername
                FROM Sightings s LEFT JOIN Users u ON u.userId = s.reporterId WHERE s.reportId = ? AND s.isRetracted = 0 ORDER BY s.observedAt DESC, s.createdAt DESC, s.sightingId DESC";
            $latest = $this->row($sql . ' LIMIT 1', [$reportId]);
            $items = $this->query($sql . ' LIMIT ? OFFSET ?', [$reportId, $limit, $offset])->fetchAll();
            return [
                'report' => $this->reportOutput($report, true),
                'latestSighting' => $latest ? $this->sightingOutput($latest) : null,
                'sightings' => ['items' => array_map([$this, 'sightingOutput'], $items), 'total' => (int)$report['sightingsCount'], 'limit' => $limit, 'offset' => $offset],
                'fetchedAt' => Input::outputDate(Input::databaseDate(Input::now())),
            ];
        });
    }
    public function mySightings(string $actor, int $limit, int $offset): array
    {
        $total = (int)$this->query('SELECT COUNT(*) FROM Sightings WHERE reporterId = ?', [$actor])->fetchColumn();
        $rows = $this->query('SELECT s.*, p.petName, r.status AS reportStatus FROM Sightings s JOIN LostReports r ON r.reportId = s.reportId JOIN Pets p ON p.petId = r.petId WHERE s.reporterId = ? ORDER BY s.createdAt DESC, s.sightingId DESC LIMIT ? OFFSET ?', [$actor, $limit, $offset])->fetchAll();
        return ['items' => array_map([$this, 'sightingOutput'], $rows), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }
    public function retract(string $sightingId, string $actor): array
    {
        return Transaction::run($this->db, function () use ($sightingId, $actor): array {
            $this->actor($actor);
            $peek = $this->row('SELECT reportId FROM Sightings WHERE sightingId = ?', [$sightingId]);
            if (!$peek) throw new ApiException('El avistamiento no existe.', Response::FLAG_NOT_FOUND);
            $pet = $this->pet($this->findPetId($peek['reportId']));
            $report = $this->reportLock($peek['reportId']);
            $sighting = $this->row('SELECT * FROM Sightings WHERE sightingId = ? FOR UPDATE', [$sightingId]);
            $owner = $report['ownerId'] === $actor && $pet['ownerId'] === $actor;
            if ($sighting['reporterId'] !== $actor && !$owner) throw new ApiException('No puedes retirar este avistamiento.', Response::FLAG_FORBIDDEN);
            if (!(bool)$sighting['isRetracted']) $this->query('UPDATE Sightings SET isRetracted = 1, retractedAt = UTC_TIMESTAMP(6) WHERE sightingId = ?', [$sightingId]);
            return ['sightingId' => Uuid::bytesToString($sightingId), 'isRetracted' => true];
        });
    }
}
