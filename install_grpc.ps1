# PowerShell script to download and install gRPC extension for PHP 8.2
# Run this script as Administrator if needed

$downloadUrl = "https://windows.php.net/downloads/pecl/releases/grpc/1.66.0/php_grpc-1.66.0-8.2-ts-vs16-x64.zip"
$tempZip = "$env:TEMP\php_grpc.zip"
$tempExtract = "$env:TEMP\php_grpc_extracted"
$phpExtDir = "C:\xampp\php\ext"

Write-Host "Downloading gRPC extension..." -ForegroundColor Cyan
Invoke-WebRequest -Uri $downloadUrl -OutFile $tempZip

Write-Host "Extracting..." -ForegroundColor Cyan
Expand-Archive -Path $tempZip -DestinationPath $tempExtract -Force

Write-Host "Installing to PHP extensions directory..." -ForegroundColor Cyan
Copy-Item -Path "$tempExtract\php_grpc.dll" -Destination "$phpExtDir\php_grpc.dll" -Force

Write-Host "Cleaning up..." -ForegroundColor Cyan
Remove-Item $tempZip -Force
Remove-Item $tempExtract -Recurse -Force

Write-Host "`n✅ gRPC extension installed successfully!" -ForegroundColor Green
Write-Host "📝 Next steps:" -ForegroundColor Yellow
Write-Host "   1. Restart Apache in XAMPP Control Panel"
Write-Host "   2. Run: php -m | findstr grpc"
Write-Host "   3. You should see 'grpc' in the output"
