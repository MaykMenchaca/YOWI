@echo off
setlocal
title Distribuidor de Suplementos - Tienda local
cd /d "%~dp0"

REM ============================================================
REM  Lanzador "todo en uno": encuentra PHP, configura la BD y
REM  levanta la tienda. Doble clic y listo.
REM  (Si MySQL esta detenido, ejecutalo como administrador.)
REM ============================================================

REM --- Contrasena de root de MySQL (la del reset). Cambiala aqui si es otra. ---
set "DB_PASS=AdminDS2026"

echo.
echo   Buscando PHP...

REM 1) PHP en el PATH
set "PHP="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"

REM 2) PHP de Laragon (cualquier version)
if not defined PHP for /d %%D in ("C:\laragon\bin\php\*") do if exist "%%D\php.exe" set "PHP=%%D\php.exe"

REM 3) Rutas comunes
if not defined PHP if exist "C:\php\php.exe" set "PHP=C:\php\php.exe"
if not defined PHP if exist "C:\tools\php\php.exe" set "PHP=C:\tools\php\php.exe"
if not defined PHP if exist "%ProgramFiles%\php\php.exe" set "PHP=%ProgramFiles%\php\php.exe"
if not defined PHP if exist "%ProgramFiles(x86)%\php\php.exe" set "PHP=%ProgramFiles(x86)%\php\php.exe"

if not defined PHP (
  echo.
  echo   No encontre php.exe automaticamente.
  echo   Mandale a Claude la ruta donde tienes PHP y lo ajusta.
  echo.
  pause
  exit /b
)
echo   PHP: %PHP%

REM --- Intentar arrancar MySQL (si ya corre o no hay permisos, se ignora) ---
for %%S in (MySQL80 MySQL84 MySQL83 MySQL82 MySQL81 MySQL90 MySQL) do net start %%S >nul 2>&1

REM --- Escribir la configuracion de conexion (env.php) ---
echo   Escribiendo configuracion (env.php)...
(
echo ^<?php
echo return [
echo     'DB_HOST'    =^> '127.0.0.1',
echo     'DB_NAME'    =^> 'ds_sports_supplements',
echo     'DB_USER'    =^> 'root',
echo     'DB_PASS'    =^> '%DB_PASS%',
echo     'DB_CHARSET' =^> 'utf8mb4',
echo ];
)> "site\api\config\env.php"

REM --- Preparar la base de datos (crea BD, 24 productos y admin) ---
echo.
echo   Preparando la base de datos...
echo.
"%PHP%" scripts\setup-local.php
if errorlevel 1 (
  echo.
  echo   ============================================================
  echo   Hubo un problema con MySQL. Revisa el mensaje de arriba.
  echo   Suele ser que MySQL no esta encendido o la contrasena de
  echo   'env.php' no coincide con la de tu root.
  echo   ============================================================
  echo.
  pause
  exit /b
)

REM --- Abrir la tienda y dejar el servidor corriendo ---
echo.
echo   ============================================================
echo     Abriendo la tienda...  NO cierres esta ventana.
echo     Tienda: http://localhost:8080
echo     Admin:  http://localhost:8080/admin/login.html
echo             (admin@ds.com / AdminDS2026)
echo   ============================================================
echo.
start "" http://localhost:8080
"%PHP%" -S localhost:8080 -t site
