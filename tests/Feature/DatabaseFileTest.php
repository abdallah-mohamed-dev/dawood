<?php

use App\Support\DatabaseFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Backups (DatabaseFile, VACUUM INTO) and the client's reset.bat.
 *
 * reset.bat is tested by running the real file through cmd, against a
 * throw-away database file — the same way the client double-clicks it.
 * It needs Windows and the bundled php\php.exe, so it is skipped elsewhere.
 */
function runResetBat(string $database, string $answer): Illuminate\Contracts\Process\ProcessResult
{
    return Process::path(base_path())
        ->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'APP_ENV' => 'production'])
        ->input($answer."\r\n\r\n")
        ->timeout(180)
        ->run(['cmd', '/c', base_path('reset.bat')]);
}

function freshTestDatabase(): string
{
    $dir = storage_path('framework/testing/reset-'.uniqid());
    mkdir($dir, 0777, true);
    $file = $dir.DIRECTORY_SEPARATOR.'database.sqlite';
    touch($file);

    Process::path(base_path())
        ->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'production'])
        ->run([PHP_BINARY, 'artisan', 'migrate', '--force'])
        ->throw();

    $pdo = new PDO('sqlite:'.$file);
    foreach (['أحمد', 'منى', 'كريم'] as $name) {
        $pdo->exec("INSERT INTO customers (name, created_at, updated_at) VALUES ('{$name}', '2026-01-01', '2026-01-01')");
    }

    return $file;
}

function removeTestDatabase(string $file): void
{
    $dir = dirname($file);
    array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*'));
    rmdir($dir);
}

test('reset.bat: anything but RESET changes nothing and takes no backup', function () {
    $file = freshTestDatabase();
    $backupsBefore = glob(storage_path('app/safety/before-reset/*.sqlite')) ?: [];

    $result = runResetBat($file, 'reset');

    expect($result->output())->toContain('Cancelled. Nothing was changed.');
    expect((int) (new PDO('sqlite:'.$file))->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(3);
    expect(glob(storage_path('app/safety/before-reset/*.sqlite')) ?: [])->toBe($backupsBefore);

    removeTestDatabase($file);
})->skip(fn () => PHP_OS_FAMILY !== 'Windows' || ! is_file(base_path('php/php.exe')), 'needs Windows and the bundled php\\php.exe');

test('reset.bat: if the backup cannot be made, nothing is deleted', function () {
    // Make the backup folder impossible to create: a FILE sits at its path.
    // Only when nothing is there yet — never touch real backups.
    $safety = storage_path('app/safety');
    if (file_exists($safety)) {
        $this->markTestSkipped('storage/app/safety already exists on this machine.');
    }
    file_put_contents($safety, 'not a folder');
    $file = freshTestDatabase();

    $result = runResetBat($file, 'RESET');

    expect($result->output())->toContain('the backup could not be made, so NOTHING was deleted');
    expect((int) (new PDO('sqlite:'.$file))->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(3);

    unlink($safety);
    removeTestDatabase($file);
})->skip(fn () => PHP_OS_FAMILY !== 'Windows' || ! is_file(base_path('php/php.exe')), 'needs Windows and the bundled php\\php.exe');

test('reset.bat: backs up first, then starts from zero', function () {
    $file = freshTestDatabase();

    $result = runResetBat($file, 'RESET');

    expect($result->output())->toContain('Done. The system is empty and ready.');
    preg_match('/Backup saved: (\S+\.sqlite)/', $result->output(), $match);
    $backup = base_path(str_replace('/', DIRECTORY_SEPARATOR, $match[1] ?? 'missing'));

    // The backup holds the data as it was…
    expect(is_file($backup))->toBeTrue();
    expect((int) (new PDO('sqlite:'.$backup))->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(3);

    // …and the live database starts from zero: admin + material types + one
    // open season, and no demo data even though the seeders exist.
    $live = new PDO('sqlite:'.$file);
    expect((int) $live->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(0);
    expect((int) $live->query('SELECT count(*) FROM rooms')->fetchColumn())->toBe(0);
    expect((int) $live->query('SELECT count(*) FROM users')->fetchColumn())->toBe(1);
    expect($live->query('SELECT name FROM material_types ORDER BY position')->fetchAll(PDO::FETCH_COLUMN))->toBe(['خامة', 'اكسسوار']);
    expect((int) $live->query("SELECT count(*) FROM seasons WHERE status = 'open'")->fetchColumn())->toBe(1);
    $live = null;

    unlink($backup);
    removeTestDatabase($file);
})->skip(fn () => PHP_OS_FAMILY !== 'Windows' || ! is_file(base_path('php/php.exe')), 'needs Windows and the bundled php\\php.exe');

test('the backup holds writes that are still in the WAL file, which a plain copy would miss', function () {
    $dir = storage_path('framework/testing/db-snapshot-'.uniqid());
    mkdir($dir, 0777, true);
    $live = $dir.DIRECTORY_SEPARATOR.'live.sqlite';
    touch($live);

    config(['database.connections.snapshot_test' => [
        'driver' => 'sqlite', 'database' => $live, 'prefix' => '',
        'foreign_key_constraints' => true, 'journal_mode' => 'WAL',
    ]]);
    $connection = DB::connection('snapshot_test');
    // Keep every write in the -wal file, as on a busy machine between checkpoints.
    $connection->statement('PRAGMA wal_autocheckpoint = 0');
    $connection->statement('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');
    foreach (range(1, 50) as $i) {
        $connection->insert('INSERT INTO notes (body) VALUES (?)', ["note {$i}"]);
    }

    // What a plain file copy gets: the main file without the WAL.
    copy($live, $dir.DIRECTORY_SEPARATOR.'plain.sqlite');
    $plain = new PDO('sqlite:'.$dir.DIRECTORY_SEPARATOR.'plain.sqlite');
    $plainHasTable = (bool) $plain->query("SELECT count(*) FROM sqlite_master WHERE name = 'notes'")->fetchColumn();

    $backup = (new DatabaseFile)->copyTo($dir.DIRECTORY_SEPARATOR.'backup.sqlite', $connection);
    $copy = new PDO('sqlite:'.$backup);

    expect($plainHasTable)->toBeFalse(); // the problem this guards against
    expect((int) $copy->query('SELECT count(*) FROM notes')->fetchColumn())->toBe(50);

    $plain = $copy = null;
    DB::purge('snapshot_test');
    array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*'));
    rmdir($dir);
});

test('a second backup never overwrites an existing one', function () {
    $target = storage_path('framework/testing/'.uniqid('exists-').'.sqlite');
    file_put_contents($target, 'old');

    expect(fn () => (new DatabaseFile)->copyTo($target))->toThrow(RuntimeException::class);
    expect(file_get_contents($target))->toBe('old');

    unlink($target);
});
