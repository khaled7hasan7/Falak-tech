@echo off
rem ---------------------------------------------------------------------------
rem  Falak Console — vendor side. Runs on this machine only.
rem
rem  The bind address is 127.0.0.1 deliberately: this server holds the private
rem  key that signs every customer licence, and it must never be reachable from
rem  the network. Do not change it to 0.0.0.0.
rem ---------------------------------------------------------------------------

setlocal
cd /d "%~dp0"

set PORT=8787
set PHP=php

where php >nul 2>nul || set PHP=C:\php\php.exe

if not exist "%PHP%" if "%PHP%"=="C:\php\php.exe" (
    echo لم يُعثر على PHP. ثبّته أو صحّح المسار في هذا الملف.
    pause
    exit /b 1
)

echo.
echo   لوحة فلك  ^>  http://127.0.0.1:%PORT%
echo   اتركها مفتوحة. اضغط Ctrl+C لإيقافها.
echo.

start "" http://127.0.0.1:%PORT%
"%PHP%" -S 127.0.0.1:%PORT% -t public public\router.php
