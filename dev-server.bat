@echo off
setlocal EnableExtensions
REM =============================================================================
REM Локальный сайт: PHP из КОРНЯ проекта (router отдает public/ и /assets).
REM Не открывайте просто index.php из проводника — запускайте этот файл.
REM =============================================================================
cd /d "%~dp0"
where php >nul 2>&1
if errorlevel 1 (
    echo ERROR: php not in PATH. Install PHP or use Docker.
    pause
    exit /b 1
)
echo Open http://127.0.0.1:8080
echo Document root: public   (router.php in repo root)
echo Ctrl+C to stop.
php -S 127.0.0.1:8080 -t public router.php
