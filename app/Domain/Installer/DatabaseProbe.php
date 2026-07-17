<?php

namespace App\Domain\Installer;

use PDO;
use PDOException;

/**
 * Opens one throwaway connection to the database the buyer just typed in.
 *
 * This exists as its own class for one reason: it is the only thing standing
 * between a test and the rest of `storeDatabase`. While the connect was inlined
 * in the controller, the only way to test the step was to aim it at a dead port
 * and assert the failure — so the .env write beyond it never ran under test, and
 * shipped reading a request key that had no validation rule and therefore never
 * arrived. Swap this in the container and the happy path is reachable.
 */
class DatabaseProbe
{
    /**
     * @throws PDOException when the database is unreachable, or refuses these credentials.
     */
    public function handle(string $host, int $port, string $database, string $username, string $password): void
    {
        new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
