@echo off
setlocal enabledelayedexpansion
title Reset de contrasena MySQL root (YOWI)

REM ============================================================
REM  Restablece la contrasena de 'root' de MySQL a: AdminDS2026
REM  Uso: clic derecho sobre este archivo -> "Ejecutar como administrador"
REM  No borra datos; solo cambia la contrasena de root.
REM ============================================================

set "NEWPASS=AdminDS2026"

REM --- Verificar permisos de administrador ---
net session >nul 2>&1
if %errorlevel% neq 0 (
  echo.
  echo   *** Tienes que ejecutarlo como ADMINISTRADOR ***
  echo   Clic derecho sobre reset-mysql-password.bat  --^>  Ejecutar como administrador
  echo.
  pause
  exit /b
)

echo Buscando la instalacion de MySQL Server...
set "MYSQLD="
set "MYINI="
for %%V in (9.0 8.4 8.3 8.2 8.1 8.0) do (
  if exist "C:\Program Files\MySQL\MySQL Server %%V\bin\mysqld.exe" (
    if not defined MYSQLD set "MYSQLD=C:\Program Files\MySQL\MySQL Server %%V\bin\mysqld.exe"
    if exist "C:\ProgramData\MySQL\MySQL Server %%V\my.ini" if not defined MYINI set "MYINI=C:\ProgramData\MySQL\MySQL Server %%V\my.ini"
  )
)
if not defined MYSQLD (
  echo.
  echo   No encontre mysqld.exe en las rutas tipicas.
  echo   Mandale a Claude la ruta de tu MySQL Server y ajusta el script.
  echo.
  pause
  exit /b
)
echo   mysqld:   !MYSQLD!
if defined MYINI echo   config:   !MYINI!

echo Buscando el servicio de Windows...
set "SVC="
for %%S in (MySQL80 MySQL84 MySQL83 MySQL82 MySQL81 MySQL90 MySQL) do (
  sc query %%S >nul 2>&1 && if not defined SVC set "SVC=%%S"
)
if not defined SVC (
  echo   No encontre el servicio de MySQL. Revisa el nombre en Servicios.
  pause
  exit /b
)
echo   servicio: !SVC!
echo.

echo Deteniendo MySQL...
net stop !SVC! >nul 2>&1

set "INIT=%TEMP%\ds-mysql-init.txt"
> "!INIT!" echo ALTER USER 'root'@'localhost' IDENTIFIED BY '!NEWPASS!';

echo Aplicando la nueva contrasena (espera ~20 seg)...
if defined MYINI (
  start "" /b "!MYSQLD!" --defaults-file="!MYINI!" --init-file="!INIT!"
) else (
  start "" /b "!MYSQLD!" --init-file="!INIT!"
)
timeout /t 20 /nobreak >nul
taskkill /f /im mysqld.exe >nul 2>&1
del "!INIT!" >nul 2>&1

echo Reiniciando el servicio de MySQL...
net start !SVC! >nul 2>&1

echo.
echo ============================================================
echo   LISTO (si no hubo errores rojos arriba).
echo     Usuario:     root
echo     Contrasena:  !NEWPASS!
echo   Pruebala en MySQL Workbench (conexion Local instance).
echo ============================================================
echo.
pause
