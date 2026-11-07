#requires -Version 5.1
<#
.SYNOPSIS
    Builds a shared-hosting upload package for KKKT CMS.

.DESCRIPTION
    Copies the project to a temporary folder, removes secrets and development files, keeps only production
    Composer packages, and writes a zip with forward-slash paths. The local project, .env and database are
    never modified. Run from the project root after `npm run build`:

        powershell -ExecutionPolicy Bypass -File docs\build-deploy-package.ps1
#>
param(
    [string] $Output = (Join-Path (Split-Path -Parent (Get-Location)) 'kkkt-cms-shared-hosting.zip')
)

$ErrorActionPreference = 'Stop'
$root = (Get-Location).Path

if (-not (Test-Path (Join-Path $root 'public\build\manifest.json'))) {
    throw 'public\build\manifest.json is missing. Run "npm run build" first.'
}

$stage = Join-Path $env:TEMP 'kkkt-stage'
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }

robocopy $root $stage /E /NFL /NDL /NJH /NJS `
    /XD .git .claude .github .idea .vscode node_modules vendor tests storage docs `
    /XF .env '.env.*' '*.sqlite' '*.sqlite-journal' .mcp.json boost.json AGENTS.md CLAUDE.md phpunit.xml .npmrc package.json package-lock.json `
        vite.config.js postcss.config.js tailwind.config.js README.md PROJECT_PROGRESS.md | Out-Null

Copy-Item (Join-Path $root '.env.production.example') (Join-Path $stage '.env.production.example')

foreach ($directory in 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs') {
    $path = Join-Path $stage "storage\$directory"
    New-Item -ItemType Directory -Force $path | Out-Null
    New-Item -ItemType File -Force (Join-Path $path '.gitignore') | Out-Null
}

# Reuse the locally installed vendor tree, then drop development packages and rebuild the autoloader.
robocopy (Join-Path $root 'vendor') (Join-Path $stage 'vendor') /E /NFL /NDL /NJH /NJS | Out-Null
Push-Location $stage
try {
    $installedFile = 'vendor\composer\installed.json'
    $installed = Get-Content $installedFile -Raw | ConvertFrom-Json
    $development = @($installed.'dev-package-names')

    composer dump-autoload --no-dev --optimize --no-interaction --no-scripts | Out-Null
    foreach ($package in $development) {
        $folder = Join-Path 'vendor' $package
        if (Test-Path $folder) { Remove-Item $folder -Recurse -Force }
    }

    $installed.packages = @($installed.packages | Where-Object { $development -notcontains $_.name })
    $installed.dev = $false
    $installed.'dev-package-names' = @()
    [IO.File]::WriteAllText((Join-Path $stage $installedFile), ($installed | ConvertTo-Json -Depth 100), (New-Object Text.UTF8Encoding($false)))

    Remove-Item 'bootstrap\cache\*.php' -Force -ErrorAction SilentlyContinue
}
finally {
    Pop-Location
}

Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
if (Test-Path $Output) { Remove-Item $Output -Force }
$stream = [IO.File]::Open($Output, 'Create')
$archive = New-Object IO.Compression.ZipArchive($stream, 'Create')
$prefix = (Resolve-Path $stage).Path.TrimEnd('\') + '\'

Get-ChildItem $stage -Recurse -File -Force | ForEach-Object {
    $entry = $_.FullName.Substring($prefix.Length).Replace('\', '/')
    [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $entry, 'Optimal') | Out-Null
}

$archive.Dispose()
$stream.Dispose()
Remove-Item $stage -Recurse -Force

Write-Host ("Created {0} ({1} MB)" -f $Output, [math]::Round((Get-Item $Output).Length / 1MB, 1))
