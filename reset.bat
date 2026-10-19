@echo off
setlocal
cd /d %~dp0
title DAWOOD - Reset all data

echo ============================================================
echo   DAWOOD - RESET ALL DATA (start from zero)
echo ============================================================
echo.
echo   Close the DAWOOD window (run.bat) before you continue.
echo.

if not exist "php\php.exe" (
    echo   ERROR: php\php.exe was not found. Nothing was changed.
    echo.
    pause
    exit /b 1
)

rem Stale cached config could point the reset at the wrong database.
php\php.exe artisan optimize:clear >nul 2>&1

rem Asks for the word RESET, backs up to storage\app\safety\before-reset\,
rem and deletes nothing if the backup fails.
php\php.exe artisan app:reset-data

echo.
pause
