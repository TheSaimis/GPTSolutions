# GPTSolutions Backend - paleidimas per PHP (be Docker)
# Reikia: PHP 8.2+, Composer, MySQL su duomenų baze "GPT-solutions"

$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot

Set-Location $ProjectRoot

# --- 1. Rasti PHP ---
$phpPath = $null
$jooPath = "C:\Users\memeh\OneDrive\Desktop\joo"
if (Get-Command php -ErrorAction SilentlyContinue) { $phpPath = "php" }
elseif (Test-Path "C:\Program Files\PHP\php.exe") { $phpPath = "C:\Program Files\PHP\php.exe" }
elseif (Test-Path "C:\php\php.exe") { $phpPath = "C:\php\php.exe" }
elseif (Test-Path "C:\xampp\php\php.exe") { $phpPath = "C:\xampp\php\php.exe" }
elseif (Test-Path "$jooPath\php\php.exe") { $phpPath = "$jooPath\php\php.exe" }
elseif (Test-Path "$jooPath\php.exe") { $phpPath = "$jooPath\php.exe" }

if (-not $phpPath) {
    Write-Host "KLAIDA: PHP nerastas. Atsisiųskite PHP 8.2+ (pvz. https://windows.php.net/download/)" -ForegroundColor Red
    Write-Host "Arba XAMPP: https://www.apachefriends.org/" -ForegroundColor Yellow
    exit 1
}
Write-Host "PHP: $phpPath" -ForegroundColor Green

# --- 2. Rasti Composer ---
$composerPhar = $null
if (Get-Command composer -ErrorAction SilentlyContinue) { $composerPhar = $null }
elseif (Test-Path "$jooPath\composer.phar") { $composerPhar = "$jooPath\composer.phar" }
elseif (Test-Path "$ProjectRoot\composer.phar") { $composerPhar = "$ProjectRoot\composer.phar" }
if (-not $composerPhar -and -not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "KLAIDA: Composer nerastas. Įdėkite į PATH arba įdėkite composer.phar į $jooPath" -ForegroundColor Red
    exit 1
}
Write-Host "Composer: OK" -ForegroundColor Green

# --- 3. composer install ---
if (-not (Test-Path "$ProjectRoot\vendor\autoload.php")) {
    Write-Host "`nInstaliuojamos priklausomybės (composer install)..." -ForegroundColor Cyan
    if ($composerPhar) {
        & $phpPath $composerPhar install --no-interaction
    } else {
        composer install --no-interaction
    }
    if ($LASTEXITCODE -ne 0) { exit 1 }
} else {
    Write-Host "`nVendor jau egzistuoja." -ForegroundColor Gray
}

# --- 4. JWT raktai ---
$jwtDir = "$ProjectRoot\config\jwt"
$privatePem = "$jwtDir\private.pem"
if (-not (Test-Path $privatePem)) {
    Write-Host "`nGeneruojami JWT raktai..." -ForegroundColor Cyan
    if (-not (Test-Path $jwtDir)) { New-Item -ItemType Directory -Path $jwtDir -Force | Out-Null }
    & $phpPath bin/console lexik:jwt:generate-keypair --no-interaction 2>$null
    if (-not (Test-Path $privatePem)) {
        Write-Host "Perspėjimas: JWT raktų generavimas nepavyko (reikia OpenSSL). Sukurkite ranka:" -ForegroundColor Yellow
        Write-Host "  mkdir config\jwt" -ForegroundColor Gray
        Write-Host "  openssl genrsa -out config/jwt/private.pem -aes256 4096" -ForegroundColor Gray
        Write-Host "  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem" -ForegroundColor Gray
    }
}

# --- 5. Cache ---
Write-Host "`nValomas cache..." -ForegroundColor Cyan
& $phpPath bin/console cache:clear 2>$null

# --- 6. Serveris ---
$hostUrl = "http://127.0.0.1:8000"
Write-Host "`nPaleidžiamas serveris: $hostUrl" -ForegroundColor Green
Write-Host "Sustabdyti: Ctrl+C`n" -ForegroundColor Gray
& $phpPath -S 127.0.0.1:8000 -t public
