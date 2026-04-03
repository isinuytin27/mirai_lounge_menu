@echo off
setlocal EnableExtensions
REM =============================================================================
REM Mirai Lounge - start Docker stack (same as run.bat).
REM First HTTPS time: docker\ssl\setup-certs.bat
REM Russian docs: DEPLOY.md
REM =============================================================================
call "%~dp0run.bat"
endlocal
exit /b %ERRORLEVEL%
