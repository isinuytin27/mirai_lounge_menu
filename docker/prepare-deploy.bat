@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - deploy folder checklist before copy to server
REM All echo text is ASCII: cmd.exe mishandles UTF-8, parens, and wildcards in echo.
REM Russian docs: DEPLOY.md
REM =============================================================================

cd /d "%~dp0\.."

echo === Mirai Lounge: deploy preparation ===
echo Full Russian guide: DEPLOY.md in project root
echo.

if not exist .env (
    if exist .env.example (
        copy /y .env.example .env >nul
        echo [OK] Created .env from .env.example. Edit for tokens if needed.
    ) else (
        echo [i] .env.example not found, skipped.
    )
) else (
    echo [i] .env already exists.
)

if not exist "data" mkdir data 2>nul
if not exist "public\assets\img\menu\uploads" mkdir "public\assets\img\menu\uploads" 2>nul
if not exist "public\assets\img\gallery\uploads" mkdir "public\assets\img\gallery\uploads" 2>nul

echo.
echo Before production, set in config\config.php:
echo    orders_security.signing_key must not stay default
echo    admin users and passwords
echo.
echo On server, typical order:
echo    1. Copy whole project folder.
echo    2. Put Reg.ru files into docker\ssl\ as certificate.crt, certificate_ca.crt, certificate.key
echo    3. Run docker\ssl\setup-certs.bat
echo    4. Run docker\start.bat or docker\run.bat
echo.
echo After composer.json or composer.lock change, rebuild PHP image:
echo    docker compose -f docker\docker-compose.yml build php
echo    docker compose -f docker\docker-compose.yml up -d
echo.
pause
endlocal
exit /b 0
