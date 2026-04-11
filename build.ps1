# Copy this script to the root of your testing Directory
# and use it to build core and update the relevant core files after that.

# $SOURCE_PATH needs to be set to the root of the development directory.

$ErrorActionPreference = "Stop"

# --- REQUIRED CONFIG ---
$SOURCE_PATH = ""  # e.g. "C:\calmpress"

if (-not $SOURCE_PATH) {
    Write-Host "Please set `$SOURCE_PATH at the top of this script."
    exit 1
}

$SOURCE_PATH = Resolve-Path $SOURCE_PATH
$BUILD_OUTPUT = Join-Path $SOURCE_PATH "build"
$TARGET = Get-Location

Write-Host "Source: $SOURCE_PATH"
Write-Host "Target: $TARGET"

# --- RUN BUILD ---
Write-Host "Running Grunt build..."
Push-Location $SOURCE_PATH
try {
    grunt build
} finally {
    Pop-Location
}

function Mirror-Directory {
    param (
        [string]$SourceDir,
        [string]$TargetDir,
        [bool]$DeleteExtra = $false
    )

    # create target if missing
    if (-not (Test-Path $TargetDir)) {
        New-Item -ItemType Directory -Path $TargetDir | Out-Null
    }

    # copy all files and folders from source to target
    Copy-Item "$SourceDir\*" $TargetDir -Recurse -Force

    if ($DeleteExtra) {
        # remove anything in target not present in source
        Get-ChildItem $TargetDir | ForEach-Object {
            $targetItem = $_
            $relativePath = $targetItem.Name
            $sourceItem = Join-Path $SourceDir $relativePath

            if (-not (Test-Path $sourceItem)) {
                Remove-Item $targetItem.FullName -Recurse -Force
            } elseif ($targetItem.PSIsContainer) {
                # recursive call
                Mirror-Directory $sourceItem $targetItem.FullName $true
            }
        }
    }
}

# --- MIRROR CORE DIRECTORIES ---
Mirror-Directory "$BUILD_OUTPUT\wp-admin" "$TARGET\wp-admin" $true
Mirror-Directory "$BUILD_OUTPUT\wp-includes" "$TARGET\wp-includes" $true

# --- COPY ROOT CODE FILES ---
Copy-Item "$BUILD_OUTPUT\*.php" $TARGET -Force

# --- MIRROR shipped themes/plugins ---
$shippedThemes = Join-Path $BUILD_OUTPUT "wp-content\themes"
$targetThemes  = Join-Path $TARGET "wp-content\themes"
Mirror-Directory $shippedThemes $targetThemes $true

$shippedPlugins = Join-Path $BUILD_OUTPUT "wp-content\plugins"
$targetPlugins  = Join-Path $TARGET "wp-content\plugins"
Mirror-Directory $shippedPlugins $targetPlugins $true

# Remove files related to object cache.
$objectCachePath = Join-Path $TARGET "wp-content\.private\object-cache"

if (Test-Path $objectCachePath) {
    Write-Host "Removing object cache directory..."
    Remove-Item $objectCachePath -Recurse -Force
}

Write-Host "Done build and copy."
[console]::beep(800, 300)