<?php

namespace App\Services;

use App\Exceptions\SeasonCloseFailedException;
use Illuminate\Support\Facades\File;

/**
 * Copies the database file before a season closes (specs/012 ق-10). If the
 * copy cannot be made, the close does not start.
 */
class SeasonBackupService
{
    public function create(): string
    {
        $source = database_path('database.sqlite');

        if (! is_file($source)) {
            throw new SeasonCloseFailedException('ما لقيتش ملف قاعدة البيانات عشان أعمل نسخة احتياطية قبل الإقفال، فالإقفال اتوقف.');
        }

        $directory = storage_path('app/season-backups');
        File::ensureDirectoryExists($directory);

        $target = $directory.DIRECTORY_SEPARATOR.'season-close-'.now()->format('Y-m-d-His').'.sqlite';

        if (! copy($source, $target)) {
            throw new SeasonCloseFailedException('النسخة الاحتياطية اتعملت فشل، فالإقفال اتوقف.');
        }

        return $target;
    }
}
