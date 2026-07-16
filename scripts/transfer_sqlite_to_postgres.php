<?php

declare(strict_types=1);

$sqlitePath = $argv[1] ?? null;
$postgresDsn = getenv('TRANSFER_PG_DSN') ?: '';
$postgresUser = getenv('TRANSFER_PG_USER') ?: '';
$postgresPassword = getenv('TRANSFER_PG_PASSWORD') ?: '';

if (! $sqlitePath || ! is_file($sqlitePath) || ! $postgresDsn || ! $postgresUser) {
    fwrite(STDERR, "Usage: TRANSFER_PG_DSN=... TRANSFER_PG_USER=... TRANSFER_PG_PASSWORD=... php scripts/transfer_sqlite_to_postgres.php database/database.sqlite\n");
    exit(1);
}

$sqlite = new PDO('sqlite:'.$sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$postgres = new PDO($postgresDsn, $postgresUser, $postgresPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sourceTables = $sqlite->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$targetTables = $postgres->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
$tables = array_values(array_intersect($sourceTables, $targetTables));

$postgres->beginTransaction();
try {
    $postgres->exec("SET LOCAL session_replication_role = 'replica'");
    foreach ($tables as $table) {
        $quotedTable = '"'.str_replace('"', '""', $table).'"';
        $postgres->exec("TRUNCATE TABLE {$quotedTable} RESTART IDENTITY CASCADE");
    }

    foreach ($tables as $table) {
        $quotedTable = '"'.str_replace('"', '""', $table).'"';
        $source = $sqlite->query("SELECT * FROM {$quotedTable}");
        $first = $source->fetch(PDO::FETCH_ASSOC);
        if ($first === false) {
            fwrite(STDOUT, "{$table}: 0\n");
            continue;
        }

        $columns = array_keys($first);
        $quotedColumns = array_map(fn (string $column) => '"'.str_replace('"', '""', $column).'"', $columns);
        $placeholders = array_fill(0, count($columns), '?');
        $insert = $postgres->prepare("INSERT INTO {$quotedTable} (".implode(', ', $quotedColumns).') VALUES ('.implode(', ', $placeholders).')');
        $booleanColumns = $postgres->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND data_type = 'boolean'");
        $booleanColumns->execute([$table]);
        $booleans = array_flip($booleanColumns->fetchAll(PDO::FETCH_COLUMN));
        $count = 0;

        do {
            $values = [];
            foreach ($columns as $column) {
                $value = $first[$column];
                if ($value !== null && isset($booleans[$column])) {
                    $value = ((int) $value) === 1 ? 'true' : 'false';
                }
                $values[] = $value;
            }
            $insert->execute($values);
            $count++;
        } while (($first = $source->fetch(PDO::FETCH_ASSOC)) !== false);

        fwrite(STDOUT, "{$table}: {$count}\n");
    }

    foreach ($tables as $table) {
        $columns = $postgres->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_default LIKE 'nextval(%'");
        $columns->execute([$table]);
        foreach ($columns->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $sequence = $postgres->query("SELECT pg_get_serial_sequence(".$postgres->quote($table).', '.$postgres->quote($column).')')->fetchColumn();
            if ($sequence) {
                $quotedTable = '"'.str_replace('"', '""', $table).'"';
                $quotedColumn = '"'.str_replace('"', '""', $column).'"';
                $maximum = $postgres->query("SELECT MAX({$quotedColumn}) FROM {$quotedTable}")->fetchColumn();
                $statement = $postgres->prepare('SELECT setval(?, ?, ?)');
                $statement->execute([$sequence, $maximum ?: 1, $maximum !== null ? 'true' : 'false']);
            }
        }
    }

    $postgres->commit();
} catch (Throwable $exception) {
    $postgres->rollBack();
    throw $exception;
}

fwrite(STDOUT, 'Transferred '.count($tables)." tables successfully.\n");
