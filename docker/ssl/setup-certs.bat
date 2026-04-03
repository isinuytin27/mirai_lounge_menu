@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - build nginx PEM bundle from Reg.ru files
REM Put in this folder: certificate.crt, certificate_ca.crt, certificate.key
REM Output: mirailounge.ru\fullchain.pem and privkey.pem
REM Russian docs: DEPLOY.md
REM =============================================================================

cd /d "%~dp0"
mkdir mirailounge.ru 2>nul

set "CERT=%~dp0certificate.crt"
set "CA=%~dp0certificate_ca.crt"
set "KEY=%~dp0certificate.key"

if not exist "%CERT%" (
    echo ERROR: certificate.crt not found.
    echo Copy Reg.ru certificate.crt, certificate_ca.crt, certificate.key into docker\ssl\
    echo.
    pause
    endlocal
    exit /b 1
)

echo Building PEM files in docker\ssl\mirailounge.ru\
type "%CERT%" > mirailounge.ru\fullchain.pem
type "%CA%" >> mirailounge.ru\fullchain.pem
copy /y "%KEY%" mirailounge.ru\privkey.pem >nul
if errorlevel 1 (
    echo ERROR: copy privkey failed. Check certificate.key exists.
    echo.
    pause
    endlocal
    exit /b 1
)

echo OK. Use docker\start.bat or docker\run.bat next.
echo.
pause
endlocal
exit /b 0
