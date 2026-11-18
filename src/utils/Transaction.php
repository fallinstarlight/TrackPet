<?php
namespace TrackPet\utils;

use PDO;
use PDOException;
use Throwable;

class Transaction
{
    /** Actions must contain only database work: a deadlock may replay the action. */
    public static function run(PDO $db, callable $action): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            $db->beginTransaction();
            try {
                $result = $action();
                $db->commit();
                return $result;
            } catch (Throwable $error) {
                if ($db->inTransaction()) $db->rollBack();
                if ($error instanceof PDOException && in_array($error->errorInfo[1] ?? null, [1205, 1213], true) && $attempt < 2) {
                    usleep(random_int(10000, 50000));
                    continue;
                }
                throw $error;
            }
        }
    }
}
