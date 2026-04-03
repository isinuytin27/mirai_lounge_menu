@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - docker compose down
REM Russian docs: DEPLOY.md
REM =============================================================================

cd /d "%~dp0\.."
echo Stopping Docker stack...
docker compose -f docker\docker-compose.yml down
if errorlevel 1 (
    echo.
    echo ERROR: docker compose down failed.
    echo.
    pause
    endlocal
    exit /b 1
)
echo OK. Stack stopped.
echo.
pause
endlocal
exit /b 0
