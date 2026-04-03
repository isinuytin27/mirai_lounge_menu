@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - docker compose up
REM Folder prep: docker\prepare-deploy.bat
REM HTTPS certs: docker\ssl\setup-certs.bat
REM Russian docs: DEPLOY.md
REM =============================================================================

cd /d "%~dp0\.."
echo Starting Docker stack...
docker compose -f docker\docker-compose.yml up -d
if errorlevel 1 (
    echo.
    echo ERROR. Check:
    echo   1. Docker Desktop is running
    echo   2. wsl --list --verbose shows WSL 2
    echo   3. Virtualization enabled in BIOS
    echo.
    pause
    endlocal
    exit /b 1
)

echo.
echo OK. Open https://mirailounge.ru or http://localhost
echo Tip: add mirailounge.ru to C:\Windows\System32\drivers\etc\hosts for local test.
echo.
pause
endlocal
exit /b 0
