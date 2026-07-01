<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Kjør SQL-filer via PDO (én statement om gangen).
 */
final class SqlScriptRunner
{
    public function runFile(PDO $pdo, string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Mangler SQL-fil: ' . $file);
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException('Kunne ikke lese SQL-fil: ' . $file);
        }

        $this->runSql($pdo, $sql);
    }

    public function runSql(PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $stmt): bool => $stmt !== '',
        );

        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }

            $result = $pdo->query($statement);
            if ($result instanceof \PDOStatement) {
                $result->fetchAll();
                $result->closeCursor();
            }
        }
    }
}
