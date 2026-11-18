<?php
namespace TrackPet\repository;

use PDO;
use TrackPet\utils\Uuid;

class DiscoveryRepository
{
    public function __construct(private PDO $db) {}
    public function listing(string $kind, string $search, bool $lostOnly, int $limit, int $offset): array
    {
        $params = [];
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
        if ($kind === 'users') {
            $from = " FROM Users u WHERE u.status = 'active'";
            if ($search !== '') { $from .= " AND (u.username LIKE ? ESCAPE '!' OR u.firstName LIKE ? ESCAPE '!' OR u.surname LIKE ? ESCAPE '!')"; $params = [$like, $like, $like]; }
            $select = "SELECT u.userId, u.firstName, u.surname, u.username, u.profilePhoto,
                (SELECT COUNT(*) FROM Pets p WHERE p.ownerId = u.userId AND p.isActive = 1) AS petCount,
                (SELECT COUNT(*) FROM Pets p WHERE p.ownerId = u.userId AND p.isActive = 1 AND p.isLost = 1) AS lostCount";
            $order = 'u.username, u.userId';
        } else {
            $from = " FROM Pets p JOIN Users u ON u.userId = p.ownerId LEFT JOIN LostReports r ON r.activePetId = p.petId WHERE p.isActive = 1 AND u.status = 'active'";
            if ($lostOnly) $from .= " AND r.status = 'open'";
            if ($search !== '') { $from .= " AND (p.petName LIKE ? ESCAPE '!' OR p.petSpecies LIKE ? ESCAPE '!' OR p.petColour LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')"; $params = [$like, $like, $like, $like]; }
            $select = 'SELECT p.petId, p.ownerId, p.petName, p.petSpecies, p.petColour, p.petDescription, p.petPhoto, p.isLost, u.username AS ownerUsername, r.reportId';
            $order = 'p.petName, p.petId';
        }
        $count = $this->db->prepare('SELECT COUNT(*)' . $from); $count->execute($params); $total = (int)$count->fetchColumn();
        $query = $this->db->prepare($select . $from . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?');
        foreach ([...$params, $limit, $offset] as $index => $value) $query->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->execute();
        $items = array_map(static function (array $row): array {
            $row = Uuid::serializeRow($row);
            foreach (['petCount', 'lostCount'] as $key) if (isset($row[$key])) $row[$key] = (int)$row[$key];
            if (isset($row['isLost'])) $row['isLost'] = (bool)$row['isLost'];
            return $row;
        }, $query->fetchAll());
        return ['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }
    public function stats(): array
    {
        $row = $this->db->query("SELECT
            (SELECT COUNT(*) FROM Users WHERE status = 'active') AS activeUsers,
            (SELECT COUNT(*) FROM Pets p JOIN Users u ON u.userId = p.ownerId WHERE p.isActive = 1 AND u.status = 'active') AS activePets,
            (SELECT COUNT(*) FROM LostReports r JOIN Pets p ON p.petId = r.petId JOIN Users u ON u.userId = p.ownerId WHERE r.status = 'open' AND p.isActive = 1 AND u.status = 'active') AS activeReports,
            (SELECT COUNT(*) FROM LostReports r JOIN Pets p ON p.petId = r.petId JOIN Users u ON u.userId = r.ownerId WHERE r.status = 'recovered' AND p.isActive = 1 AND u.status = 'active') AS recoveredReports")->fetch();
        return array_map('intval', $row);
    }
}
