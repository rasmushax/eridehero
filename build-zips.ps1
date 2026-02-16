Add-Type -AssemblyName System.IO.Compression.FileSystem

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# --- erh-theme.zip ---
$themeZip = Join-Path $baseDir "erh-theme.zip"
if (Test-Path $themeZip) { Remove-Item $themeZip }
$zip = [System.IO.Compression.ZipFile]::Open($themeZip, 'Create')

Get-ChildItem -Path (Join-Path $baseDir "erh-theme") -Recurse -File | Where-Object {
    $_.FullName -notmatch '\\node_modules\\'
} | ForEach-Object {
    $entryName = $_.FullName.Substring($baseDir.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
}

$zip.Dispose()
$size = [math]::Round((Get-Item $themeZip).Length / 1MB, 1)
Write-Host "Created erh-theme.zip - ${size} MB"

# --- erh-core.zip ---
$coreZip = Join-Path $baseDir "erh-core.zip"
if (Test-Path $coreZip) { Remove-Item $coreZip }
$zip = [System.IO.Compression.ZipFile]::Open($coreZip, 'Create')

Get-ChildItem -Path (Join-Path $baseDir "erh-core") -Recurse -File | ForEach-Object {
    $entryName = $_.FullName.Substring($baseDir.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
}

$zip.Dispose()
$size = [math]::Round((Get-Item $coreZip).Length / 1MB, 1)
Write-Host "Created erh-core.zip - ${size} MB"

# --- housefresh-tools.zip ---
$hftSource = "C:\laragon\www\housefresh\wp-content\plugins\housefresh-tools"
$hftZip = Join-Path $baseDir "housefresh-tools.zip"
if (Test-Path $hftZip) { Remove-Item $hftZip }
$zip = [System.IO.Compression.ZipFile]::Open($hftZip, 'Create')

Get-ChildItem -Path $hftSource -Recurse -File | ForEach-Object {
    $entryName = ("housefresh-tools/" + $_.FullName.Substring($hftSource.Length + 1)).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
}

$zip.Dispose()
$size = [math]::Round((Get-Item $hftZip).Length / 1MB, 1)
Write-Host "Created housefresh-tools.zip - ${size} MB"
