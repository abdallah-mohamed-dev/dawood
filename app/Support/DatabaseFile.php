<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Copies the live SQLite database to one file — the single way every backup
 * in the app is taken (season close, the download button, the reset script).
 *
 * Not a plain file copy: the connection runs in WAL mode (config/database.php),
 * so recent writes can still sit in "database.sqlite-wal" beside the main
 * file, and copying the main file alone could miss them. SQLite's own
 * `VACUUM INTO` writes a complete, consistent snapshot — WAL included — into
 * a fresh single file. It cannot run inside a transaction, so callers take
 * the backup before they open one.
 */
class DatabaseFile
{
    public function copyTo(string $target, ?Connection $connection = null): string
    {
        $connection ??= DB::connection();

        if (file_exists($target)) {
            throw new RuntimeException("فيه نسخة بنفس الاسم بالفعل: {$target}");
        }

        File::ensureDirectoryExists(dirname($target));

        $connection->statement('VACUUM INTO ?', [$target]);

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException("النسخة الاحتياطية ماتعملتش: {$target}");
        }

        return $target;
    }
}
