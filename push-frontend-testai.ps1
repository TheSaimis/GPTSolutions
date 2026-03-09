# Commit + push i frontend-testai su autoriumi ricardas20
# Paleiskite kataloguje su Git: .\push-frontend-testai.ps1

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

git config user.name "ricardas20"
git config user.email "ricardas20@users.noreply.github.com"

if (-not (Test-Path ".git")) {
    Write-Host "Inicializuojama Git repozitorija..."
    git init
    git remote add origin https://github.com/TheSaimis/GPTSolutions.git
    git fetch origin 2>$null
    git checkout -b frontend-testai origin/frontend-testai 2>$null
    if ($LASTEXITCODE -ne 0) { git checkout -b frontend-testai }
} else {
    git fetch origin 2>$null
    git checkout frontend-testai 2>$null
    if ($LASTEXITCODE -ne 0) {
        git checkout -b frontend-testai origin/frontend-testai 2>$null
        if ($LASTEXITCODE -ne 0) { git checkout -b frontend-testai }
    }
}

git add -A
$status = git status --short
if (-not $status) {
    Write-Host "Nera pakeitimu commitinti."
    exit 0
}
git commit -m "Frontend testai ir konfiguracija"
git push -u origin frontend-testai
Write-Host "Baigta."
