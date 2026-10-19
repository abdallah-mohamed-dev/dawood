@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"
title DAWOOD - Reset all data

rem ======================================================================
rem  Standalone: needs only what every DAWOOD copy already has —
rem  php\php.exe, artisan, and the AdminUserSeeder / MaterialTypeSeeder.
rem  Order is the whole point:
rem    1. nothing happens unless RESET is typed;
rem    2. a full copy of the database is written first (VACUUM INTO, which
rem       also captures writes still in the WAL file);
rem    3. only if that copy exists is anything deleted.
rem ======================================================================

echo ============================================================
echo   DAWOOD - RESET ALL DATA (start from zero)
echo ============================================================
echo.
echo   This DELETES every customer, room, payment, expense,
echo   material and partner, and starts the system from zero.
echo   A full copy of the current data is saved first.
echo.
echo   Close the DAWOOD window (run.bat) before you continue.
echo.

if not exist "php\php.exe" (
    echo   ERROR: php\php.exe was not found next to this file. Nothing was changed.
    goto :end
)
if not exist "artisan" (
    echo   ERROR: put reset.bat in the DAWOOD folder, next to run.bat. Nothing was changed.
    goto :end
)

set "DAWOOD_DB=database\database.sqlite"
if defined DB_DATABASE set "DAWOOD_DB=%DB_DATABASE%"
if not exist "%DAWOOD_DB%" (
    echo   ERROR: the database file was not found. Nothing was changed.
    goto :end
)

set "CONFIRM="
set /p "CONFIRM=  Type RESET (capital letters) and press Enter to continue: "
if not "%CONFIRM%"=="RESET" (
    echo.
    echo   Cancelled. Nothing was changed.
    goto :end
)

echo.
echo   [1/3] Saving a full copy of the current data...
php\php.exe -r "date_default_timezone_set('Africa/Cairo'); $dir = 'storage/app/safety/before-reset'; if (! is_dir($dir)) { mkdir($dir, 0777, true); } $target = $dir.'/dawood-'.date('Y-m-d_H-i-s').'.sqlite'; try { $db = new PDO('sqlite:'.getenv('DAWOOD_DB')); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db->exec('VACUUM INTO '.$db->quote($target)); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); } if (! is_file($target) || filesize($target) === 0) { exit(1); } echo '        Backup saved: '.$target.PHP_EOL;"
if errorlevel 1 (
    echo.
    echo   ERROR: the backup could not be made, so NOTHING was deleted.
    goto :end
)

echo   [2/3] Deleting all data...
php\php.exe artisan optimize:clear >nul 2>&1
php\php.exe artisan migrate:fresh --force
if errorlevel 1 (
    echo.
    echo   ERROR while rebuilding the database. Your data is safe in the backup above.
    goto :end
)

echo   [3/3] Creating the admin account and material types...
php\php.exe artisan db:seed --class=AdminUserSeeder --force
php\php.exe artisan db:seed --class=MaterialTypeSeeder --force
if errorlevel 1 (
    echo.
    echo   ERROR while creating the admin account. Your data is safe in the backup above.
    goto :end
)

echo.
echo   Done. The system is empty and ready.
echo   Log in with the admin email and password written in the .env file.

:end
echo.
pause
