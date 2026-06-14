Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$src  = 'C:\Users\ramir\OneDrive\Documentos\Autonomo\plugin fichaje\plugin-fichaje\plugin-fichaje'
$dest = 'C:\Users\ramir\OneDrive\Documentos\Autonomo\plugin fichaje\plugin-fichaje-v2.0.0.zip'

if (Test-Path $dest) { Remove-Item $dest }

$stream = [System.IO.File]::Open($dest, [System.IO.FileMode]::Create)
$zip    = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

Get-ChildItem -Path $src -Recurse -File | ForEach-Object {
    $rel    = $_.FullName.Substring($src.Length + 1).Replace('\', '/')
    $entry  = $zip.CreateEntry('plugin-fichaje/' + $rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = $entry.Open()
    $reader = [System.IO.File]::OpenRead($_.FullName)
    $reader.CopyTo($writer)
    $reader.Close()
    $writer.Close()
}

$zip.Dispose()
$stream.Dispose()
Write-Host "ZIP creado: $dest"
