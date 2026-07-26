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
$rel  = Invoke-RestMethod -Uri "https://windows.php.net/downloads/releases/releases.json" -UseBasicParsing
$base = "https://windows.php.net/downloads/releases"
$zip  = Join-Path $env:TEMP "php-portable.zip"
$ok   = $false

# Construimos el nombre del ZIP desde el campo 'version' (confiable) y
# probamos la carpeta actual y la de archivos historicos.
foreach ($branch in @("8.3","8.2","8.1","8.4","8.0")) {
    if (-not ($rel.PSObject.Properties.Name -contains $branch)) { continue }
    $ver = $rel.$branch.version
    if (-not $ver) { continue }
    if ($branch -eq "8.4") { $vs = "vs17" } else { $vs = "vs16" }
    $name = "php-$ver-nts-Win32-$vs-x64.zip"
    foreach ($u in @("$base/$name", "$base/archives/$name")) {
        try {
            Write-Host "Descargando $u ..."
            Invoke-WebRequest -Uri $u -OutFile $zip -UseBasicParsing
            $ok = $true
            break
        } catch {
            Write-Host "  (no disponible, probando otra)"
        }
    }
    if ($ok) { break }
}

if (-not $ok) { throw "No pude descargar PHP desde windows.php.net (revisa internet/antivirus)." }

Write-Host "Extrayendo en $phpDir ..."
if (Test-Path $phpDir) { Remove-Item $phpDir -Recurse -Force }
Expand-Archive -Path $zip -DestinationPath $phpDir -Force
Remove-Item $zip -Force

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
; Permitir subir imágenes de promoción/productos sin toparse con el límite por defecto (2M).
upload_max_filesize=16M
post_max_size=20M
"@
Set-Content -Path (Join-Path $phpDir "php.ini") -Value $ini -Encoding ASCII

Write-Host "PHP portable listo en $phpDir"
exit 0
