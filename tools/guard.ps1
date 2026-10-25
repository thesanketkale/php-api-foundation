# tools/guard.ps1: Clean-room and secret hygiene scanner
[CmdletBinding()]
param()

$forbidden = @(
    "vuexr",
    "trium",
    "fudge3d",
    "BEGIN OPENSSH PRIVATE KEY",
    "BEGIN RSA PRIVATE KEY",
    "BEGIN EC PRIVATE KEY",
    "BEGIN PRIVATE KEY"
)

$repoRoot = (git rev-parse --show-toplevel 2>$null)
if (-not $repoRoot) { $repoRoot = Get-Location }

$failed = $false

# Check tracked and staged files
$files = git ls-files | Where-Object { $_ -notlike ".git*" -and $_ -notlike "*.png" -and $_ -notlike "*.jpg" }

foreach ($f in $files) {
    $fullPath = Join-Path $repoRoot $f
    if (Test-Path $fullPath -PathType Leaf) {
        $content = Get-Content $fullPath -Raw -ErrorAction SilentlyContinue
        if ($content) {
            foreach ($term in $forbidden) {
                if ($content -match "(?i)$term") {
                    # Allow mention of guard rules checking for forbidden words in docs or guard script
                    if ($f -eq "tools/guard.ps1" -or $f -eq "tools/guard.sh" -or $f -eq "AGENTS.md") {
                        continue
                    }
                    Write-Error "Clean-room violation in $f : matched '$term'"
                    $failed = $true
                }
            }
        }
    }
}

if ($failed) {
    Write-Host "Guard scan FAILED. Clean-room or secret check violated." -ForegroundColor Red
    exit 1
} else {
    Write-Host "Guard scan PASSED. No clean-room or secret violations detected." -ForegroundColor Green
    exit 0
}
