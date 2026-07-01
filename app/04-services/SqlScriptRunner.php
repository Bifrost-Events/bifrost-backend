<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Kjør SQL-filer via PDO (én statement om gangen).
 * Splitter på ; utenfor enkeltfnutter (COMMENT '...; ...' osv.).
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

        foreach ($this->splitStatements($sql) as $statement) {
            $result = $pdo->query($statement);
            if ($result instanceof \PDOStatement) {
                $result->fetchAll();
                $result->closeCursor();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                if ($char === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                    $current .= $sql[++$i];
                    continue;
                }
                if ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
