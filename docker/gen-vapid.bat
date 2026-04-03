@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - print Web Push VAPID keys from container mirai-php
REM Start stack first: docker\start.bat
REM Paste keys into .env, then docker\stop.bat and docker\start.bat
REM Russian docs: DEPLOY.md
REM =============================================================================

cd /d "%~dp0\.."
if not exist "scripts\generate-vapid.php" (
    echo ERROR: scripts\generate-vapid.php not found. Copy the full repo, including the scripts folder.
    echo.
    pause
    endlocal
    exit /b 1
)

echo VAPID keys from mirai-php:
echo.

docker exec mirai-php php /var/www/mirailounge/scripts/generate-vapid.php
if errorlevel 1 (
    echo.
    echo ERROR: docker exec failed. Check: Docker running, container mirai-php up (docker\start.bat).
    echo If message was "Could not open input file":
    echo   Use docker-compose.yml that mounts scripts into php, then recreate the container:
    echo   docker compose -f docker\docker-compose.yml up -d --force-recreate php
    echo.
    pause
    endlocal
    exit /b 1
)

echo.
echo Next: copy MIRAI_VAPID_PUBLIC and MIRAI_VAPID_PRIVATE into .env in project root.
echo Then: docker\stop.bat, then docker\start.bat
echo.
pause
endlocal
exit /b 0
