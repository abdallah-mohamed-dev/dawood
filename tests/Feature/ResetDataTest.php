<?php

use App\Models\Customer;
use App\Models\Season;
use App\Support\DatabaseFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * reset.bat → `php artisan app:reset-data`. The order is the whole point:
 * nothing is deleted unless RESET is typed AND the backup was written first.
 */
beforeEach(function () {
    Customer::factory()->count(3)->create();
});

test('typing anything but RESET changes nothing and takes no backup', function () {
    $this->mock(DatabaseFile::class)->shouldNotReceive('copyTo');

    $this->artisan('app:reset-data')
        ->expectsQuestion('Type RESET (capital letters) to continue, or anything else to cancel', 'reset')
        ->expectsOutput('Cancelled. Nothing was changed.')
        ->assertFailed();

    expect(Customer::query()->count())->toBe(3);
});

test('if the backup fails, nothing is deleted', function () {
    $this->mock(DatabaseFile::class)->shouldReceive('copyTo')->once()->andThrow(new RuntimeException('disk full'));

    $this->artisan('app:reset-data')
        ->expectsQuestion('Type RESET (capital letters) to continue, or anything else to cancel', 'RESET')
        ->expectsOutput('The backup could not be made, so NOTHING was deleted.')
        ->assertFailed();

    expect(Customer::query()->count())->toBe(3);
});

test('end to end: backs up, then wipes everything and starts from zero', function () {
    // Run exactly as reset.bat does — a real artisan process on a real
    // database file. In-process it cannot work: migrate:fresh VACUUMs, which
    // SQLite refuses inside the test transaction.
    $dir = storage_path('framework/testing/reset-'.uniqid());
    mkdir($dir, 0777, true);
    $file = $dir.DIRECTORY_SEPARATOR.'database.sqlite';
    touch($file);
    $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'production'];
    $artisan = fn (array $args, string $input = '') => Process::path(base_path())->env($env)->input($input)->timeout(120)
        ->run([PHP_BINARY, 'artisan', ...$args]);

    expect($artisan(['migrate', '--force'])->successful())->toBeTrue();
    $pdo = new PDO('sqlite:'.$file);
    foreach (['أحمد', 'منى', 'كريم'] as $name) {
        $pdo->exec("INSERT INTO customers (name, created_at, updated_at) VALUES ('{$name}', '2026-01-01', '2026-01-01')");
    }
    $pdo = null;

    $result = $artisan(['app:reset-data'], "RESET\n");

    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
    preg_match('/Backup saved: (.+\.sqlite)/', $result->output(), $match);
    $backup = trim($match[1] ?? '');

    // The backup holds the data as it was…
    expect(is_file($backup))->toBeTrue();
    expect((int) (new PDO('sqlite:'.$backup))->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(3);

    // …and the live database starts from zero: admin + material types + one
    // open season, and no demo data.
    $live = new PDO('sqlite:'.$file);
    expect((int) $live->query('SELECT count(*) FROM customers')->fetchColumn())->toBe(0);
    expect((int) $live->query('SELECT count(*) FROM rooms')->fetchColumn())->toBe(0);
    expect((int) $live->query('SELECT count(*) FROM users')->fetchColumn())->toBe(1);
    expect($live->query('SELECT name FROM material_types ORDER BY position')->fetchAll(PDO::FETCH_COLUMN))->toBe(['خامة', 'اكسسوار']);
    expect((int) $live->query("SELECT count(*) FROM seasons WHERE status = 'open'")->fetchColumn())->toBe(1);
    $live = null;

    // Rebuilding from scratch leaves no stray empty copies beside the database.
    expect(glob($dir.DIRECTORY_SEPARATOR.'dawood-before-*'))->toBe([]);

    unlink($backup);
    array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*'));
    rmdir($dir);
});

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
