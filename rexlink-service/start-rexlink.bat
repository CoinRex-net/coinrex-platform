@echo off
setlocal
cd /d "%~dp0.."
echo Starting RexLink API...
echo.
node rexlink-service\server.js
echo.
echo RexLink API stopped. Press any key to close.
pause >nul
