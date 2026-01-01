# PowerShell script to download Xdebug DLL
# Run this script manually if automated download fails

$url = "https://xdebug.org/files/php_xdebug-3.5.0-8.4-ts-vs17-x86_64.dll"
$output = "C:\php8.4\ext\php_xdebug.dll"

Write-Host "Downloading Xdebug DLL..."
Write-Host "URL: $url"
Write-Host "Output: $output"

# Try multiple methods
$success = $false

# Method 1: Invoke-WebRequest with -SkipCertificateCheck (PowerShell 6+)
try {
    if ($PSVersionTable.PSVersion.Major -ge 6) {
        Invoke-WebRequest -Uri $url -OutFile $output -SkipCertificateCheck -ErrorAction Stop
        $success = $true
        Write-Host "Downloaded using Invoke-WebRequest (PowerShell 6+)"
    }
} catch {
    Write-Host "Method 1 failed: $_"
}

# Method 2: System.Net.WebClient with certificate callback
if (-not $success) {
    try {
        Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllCertsPolicy : ICertificatePolicy {
    public bool CheckValidationResult(ServicePoint srvPoint, X509Certificate certificate, WebRequest request, int certificateProblem) {
        return true;
    }
}
"@
        [System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCertsPolicy
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
        
        $client = New-Object System.Net.WebClient
        $client.DownloadFile($url, $output)
        $success = $true
        Write-Host "Downloaded using WebClient with certificate bypass"
    } catch {
        Write-Host "Method 2 failed: $_"
    }
}

# Method 3: Manual download instructions
if (-not $success) {
    Write-Host ""
    Write-Host "Automatic download failed. Please download manually:"
    Write-Host "1. Open browser and go to: $url"
    Write-Host "2. Save the file to: $output"
    Write-Host "3. Run this script again to verify"
    exit 1
}

# Verify download
if (Test-Path $output) {
    $file = Get-Item $output
    Write-Host ""
    Write-Host "SUCCESS: File downloaded"
    Write-Host "File: $($file.Name)"
    Write-Host "Size: $($file.Length) bytes"
    Write-Host "Location: $($file.FullName)"
    
    # Verify PHP can load it
    Write-Host ""
    Write-Host "Verifying Xdebug loads..."
    php -m | findstr /i xdebug
    php -r "echo phpversion('xdebug');"
} else {
    Write-Host "ERROR: File not found after download"
    exit 1
}

