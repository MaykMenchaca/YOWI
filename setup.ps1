# setup.ps1 — Configura FLUJO en un proyecto nuevo (Windows)
# Uso: .\setup.ps1 desde la raiz de tu proyecto

param(
    [string]$ProjectPath = "."
)

$ErrorActionPreference = "Stop"

Write-Host "==> Copiando skills a $ProjectPath\.claude\skills\" -ForegroundColor Cyan

New-Item -ItemType Directory -Force "$ProjectPath\.claude\skills" | Out-Null

$flujoSkills = Join-Path $PSScriptRoot ".claude\skills"
Copy-Item -Recurse -Force "$flujoSkills\*" "$ProjectPath\.claude\skills\"

Write-Host "==> Skills copiadas:" -ForegroundColor Green
Get-ChildItem "$ProjectPath\.claude\skills" | ForEach-Object { Write-Host "    - $($_.Name)" }

Write-Host ""
Write-Host "==> Instalando dependencias Python..." -ForegroundColor Cyan
pip install -r (Join-Path $PSScriptRoot "requirements.txt")

Write-Host ""
Write-Host "FLUJO listo. Abre el proyecto en Claude Code y corre /impeccable init" -ForegroundColor Green
