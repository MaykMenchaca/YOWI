# ============================================================
#  provision-php.ps1
#  Descarga un PHP portable para Windows dentro de .\php del
#  proyecto (una sola vez) y genera un php.ini con las
#  extensiones que la tienda necesita. Lo llama iniciar-tienda.bat
#  cuando no encuentra ningun PHP en el sistema.
# ============================================================

$ErrorActionPreference = "Stop"
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# Carpeta del proyecto (este script vive en \scripts)
$root   = Split-Path -Parent $PSScriptRoot
$phpDir = Join-Path $root "php"

if (Test-Path (Join-Path $phpDir "php.exe")) {
    Write-Host "PHP portable ya presente en $phpDir"
    exit 0
}

Write-Host "Consultando la version estable de PHP para Windows..."
$rel = Invoke-RestMethod -Uri "https://windows.php.net/downloads/releases/releases.json" -UseBasicParsing

# Elegir una rama estable (preferimos 8.3; si no, lo que haya)
$branch = $null
foreach ($b in @("8.3","8.4","8.2","8.1")) {
    if ($rel.PSObject.Properties.Name -contains $b) { $branch = $b; break }
}
if (-not $branch) { $branch = ($rel.PSObject.Properties.Name | Select-Object -First 1) }
$node = $rel.$branch

# Buscar el zip NTS x64 (sirve para php -S)
$zipName = $node.PSObject.Properties.Name |
    Where-Object { $_ -match "nts" -and $_ -match "x64" -and $_ -match "\.zip$" } |
    Select-Object -First 1
if (-not $zipName) { throw "No encontre un ZIP NTS x64 para PHP $branch." }

$url = "https://windows.php.net/downloads/releases/$zipName"
$tmp = Join-Path $env:TEMP $zipName

Write-Host "Descargando $zipName ..."
Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing

Write-Host "Extrayendo en $phpDir ..."
if (Test-Path $phpDir) { Remove-Item $phpDir -Recurse -Force }
Expand-Archive -Path $tmp -DestinationPath $phpDir -Force
Remove-Item $tmp -Force

# php.ini con lo que usa la app
$ext = Join-Path $phpDir "ext"
$ini = @"
; Generado por provision-php.ps1 — PHP portable para la tienda local.
extension_dir="$ext"
extension=pdo_mysql
extension=mysqli
extension=mbstring
extension=fileinfo
extension=openssl
"@
Set-Content -Path (Join-Path $phpDir "php.ini") -Value $ini -Encoding ASCII

Write-Host "PHP portable listo en $phpDir"
exit 0
