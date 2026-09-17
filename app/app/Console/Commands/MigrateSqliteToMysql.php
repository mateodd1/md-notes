<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class MigrateSqliteToMysql extends Command
{
    protected $signature = 'md-notes:migrate-sqlite {path : Absolute path to the SQLite database}';

    protected $description = 'Copy md-notes application data from SQLite to the configured MySQL database';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('The configured target must be MySQL.');

            return self::FAILURE;
        }

        $path = realpath((string) $this->argument('path'));
        if ($path === false || ! is_file($path)) {
            $this->error('The SQLite database file was not found.');

            return self::FAILURE;
        }

        $source = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $target = DB::connection();
        $counts = [];

        foreach (['users', 'password_reset_tokens', 'shared_notes', 'note_versions'] as $table) {
            if (! $this->hasTable($source, $table)) {
                continue;
            }

            $rows = $source->query('SELECT * FROM "'.$table.'"')->fetchAll();
            if ($rows === []) {
                $counts[$table] = 0;

                continue;
            }

            $uniqueBy = $table === 'password_reset_tokens' ? ['email'] : ['id'];
            $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));
            $target->table($table)->upsert($rows, $uniqueBy, $updateColumns);
            $counts[$table] = count($rows);
        }

        $this->info('SQLite data copied to MySQL: '.collect($counts)->map(fn (int $count, string $table): string => $table.'='.$count)->implode(', ').'.');

        return self::SUCCESS;
    }

    private function hasTable(PDO $connection, string $table): bool
    {
        $statement = $connection->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        $statement->execute(['table' => $table]);

        return (bool) $statement->fetchColumn();
    }
}
