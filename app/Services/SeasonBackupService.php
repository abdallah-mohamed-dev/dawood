<?php

namespace App\Services;

use App\Exceptions\SeasonCloseFailedException;
use App\Support\DatabaseFile;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Copies the database before a season closes (specs/012 ق-10). If the
 * copy cannot be made, the close does not start.
 *
 * The copy goes through DatabaseFile (VACUUM INTO), not a plain file copy:
 * in WAL mode the newest writes may not be in the main file yet.
 */
class SeasonBackupService
{
    public function __construct(private readonly DatabaseFile $database = new DatabaseFile) {}

    public function create(): string
    {
        $target = storage_path('app/season-backups').DIRECTORY_SEPARATOR.'season-close-'.now()->format('Y-m-d-His').'.sqlite';

        try {
            return $this->database->copyTo($target);
        } catch (RuntimeException|QueryException $exception) {
            throw new SeasonCloseFailedException('ماقدرتش أعمل نسخة احتياطية قبل الإقفال، فالإقفال اتوقف. ('.$exception->getMessage().')');
        }
    }
}
