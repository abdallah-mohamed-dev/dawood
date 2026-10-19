<?php

namespace App\Console\Commands;

use App\Support\DatabaseFile;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\MaterialTypeSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Wipes every record and starts the system from zero — what reset.bat runs on
 * the client's machine. Safety first, in this order:
 *
 *  1. Nothing happens unless the word RESET is typed (an accidental
 *     double-click on reset.bat must not cost a working shop its data).
 *  2. A full copy of the current database is written to
 *     storage/app/safety/before-reset/, named with the date and time.
 *  3. Only if that copy exists is anything deleted. A failed copy stops here.
 *
 * Then the tables are rebuilt and seeded with the admin (from .env) and the
 * two material types — never the demo data, whatever APP_ENV says.
 */
class ResetDataCommand extends Command
{
    protected $signature = 'app:reset-data';

    protected $description = 'Back up the database, then wipe all data and start from zero';

    public function handle(DatabaseFile $database): int
    {
        $this->newLine();
        $this->warn('  This DELETES every customer, room, payment, expense, material and partner.');
        $this->warn('  A full copy of the current data is saved first.');
        $this->newLine();

        if ($this->ask('Type RESET (capital letters) to continue, or anything else to cancel') !== 'RESET') {
            $this->info('Cancelled. Nothing was changed.');

            return self::FAILURE;
        }

        $target = storage_path('app/safety/before-reset').DIRECTORY_SEPARATOR.'dawood-'.now()->format('Y-m-d_H-i-s').'.sqlite';

        try {
            $database->copyTo($target);
        } catch (Throwable $exception) {
            $this->error('The backup could not be made, so NOTHING was deleted.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup saved: '.$target);

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => MaterialTypeSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Done. The system is empty and ready. Log in with the admin email and password from the .env file.');

        return self::SUCCESS;
    }
}
