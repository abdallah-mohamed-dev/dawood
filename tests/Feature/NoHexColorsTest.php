<?php

/**
 * CLAUDE.md §4-7: colors come from app.css tokens only — no hex value in a
 * Blade view. This test is the enforcement, so it stays true after this task
 * closes, not just at the moment it was checked by hand (specs/020.2).
 */
test('no view contains a literal hex color', function () {
    $files = glob(resource_path('views/**/*.blade.php'), GLOB_BRACE)
        ?: [];

    $offenders = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (preg_match('/#[0-9a-fA-F]{6}\b/', $contents)) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
