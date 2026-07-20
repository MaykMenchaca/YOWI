@echo off
REM ─────────────────────────────────────────────────────────────
REM  Levanta el sitio en local (requiere PHP en el PATH — Laragon lo trae).
REM  Doble clic para arrancar. Ctrl+C para detener.
REM ─────────────────────────────────────────────────────────────
cd /d "%~dp0"
echo.
echo   Distribuidor de Suplementos - servidor local
echo   Tienda:  http://localhost:8080
echo   Admin:   http://localhost:8080/admin/login.html
echo.
echo   (Ctrl+C para detener)
echo.
start "" http://localhost:8080
php -S localhost:8080 -t site
