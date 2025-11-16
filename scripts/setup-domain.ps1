# LILAC Domain Setup Script
# This script helps configure Apache virtual host for your domain

param(
    [Parameter(Mandatory=$true)]
    [string]$DomainName,
    
    [string]$ApacheConfDir = "",
    [string]$ProjectPath = "C:\xampp\htdocs\lilac",
    [switch]$IncludeWWW = $false,
    [switch]$Force = $false
)

Write-Host "========================================" -ForegroundColor Green
Write-Host "LILAC Domain Configuration Script" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# Check admin privileges
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
if (-NOT $isAdmin) {
    Write-Host "⚠️  WARNING: This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host "   Please run PowerShell as Administrator and try again." -ForegroundColor Yellow
    exit 1
}

# Validate domain name
if ($DomainName -notmatch '^([a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.)+[a-zA-Z]{2,}$') {
    Write-Host "❌ Invalid domain name format: $DomainName" -ForegroundColor Red
    Write-Host "   Example: lilac-awards.duckdns.org or yourdomain.com" -ForegroundColor Yellow
    exit 1
}

Write-Host "Domain: $DomainName" -ForegroundColor Cyan
Write-Host "Project Path: $ProjectPath" -ForegroundColor Cyan
Write-Host ""

# Find Apache configuration directory
if (-not $ApacheConfDir) {
    Write-Host "Searching for Apache configuration directory..." -ForegroundColor Yellow
    
    $possiblePaths = @(
        "C:\xampp\apache\conf",
        "C:\Program Files\Apache Software Foundation\Apache*\conf",
        "C:\wamp\bin\apache\apache*\conf",
        "C:\Apache24\conf"
    )
    
    foreach ($path in $possiblePaths) {
        $foundPaths = Get-ChildItem $path -ErrorAction SilentlyContinue
        foreach ($foundPath in $foundPaths) {
            if (Test-Path (Join-Path $foundPath.FullName "httpd.conf")) {
                $ApacheConfDir = $foundPath.FullName
                break
            }
        }
        if ($ApacheConfDir) { break }
    }
}

if (-not $ApacheConfDir -or -not (Test-Path $ApacheConfDir)) {
    Write-Host "❌ Could not locate Apache configuration directory." -ForegroundColor Red
    Write-Host "   Please specify with: -ApacheConfDir 'C:\xampp\apache\conf'" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Found Apache config: $ApacheConfDir" -ForegroundColor Green

# Verify project path exists
if (-not (Test-Path $ProjectPath)) {
    Write-Host "❌ Project path not found: $ProjectPath" -ForegroundColor Red
    exit 1
}

Write-Host "✅ Project path verified" -ForegroundColor Green
Write-Host ""

# Create virtual host configuration
$vhostFile = Join-Path $ApacheConfDir "extra\lilac-domain.conf"
$vhostContent = @"
# LILAC Awards System - Domain Configuration
# Generated on: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
# Domain: $DomainName

<VirtualHost *:80>
    ServerName $DomainName
$(if ($IncludeWWW) { "    ServerAlias www.$DomainName" })
    DocumentRoot "$ProjectPath"
    
    <Directory "$ProjectPath">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Error and access logs
    ErrorLog "$(Split-Path $ApacheConfDir -Parent)\logs\lilac-error.log"
    CustomLog "$(Split-Path $ApacheConfDir -Parent)\logs\lilac-access.log" common
    
    # PHP settings
    php_admin_value upload_max_filesize "10M"
    php_admin_value post_max_size "10M"
    php_admin_value max_execution_time "300"
    php_admin_value memory_limit "256M"
</VirtualHost>
"@

# Check if virtual host already exists
if ((Test-Path $vhostFile) -and -not $Force) {
    Write-Host "⚠️  Virtual host configuration already exists: $vhostFile" -ForegroundColor Yellow
    $overwrite = Read-Host "Overwrite existing configuration? (y/N)"
    if ($overwrite -ne "y" -and $overwrite -ne "Y") {
        Write-Host "Skipping virtual host creation." -ForegroundColor Yellow
    } else {
        Write-Host "Creating virtual host configuration..." -ForegroundColor Yellow
        Set-Content -Path $vhostFile -Value $vhostContent -Force
        Write-Host "✅ Virtual host configuration created!" -ForegroundColor Green
    }
} else {
    Write-Host "Creating virtual host configuration..." -ForegroundColor Yellow
    Set-Content -Path $vhostFile -Value $vhostContent -Force
    Write-Host "✅ Virtual host configuration created!" -ForegroundColor Green
}

# Update httpd.conf to include virtual host
$httpdConf = Join-Path $ApacheConfDir "httpd.conf"
if (Test-Path $httpdConf) {
    $httpdContent = Get-Content $httpdConf -Raw
    
    # Check if virtual hosts module is enabled
    if ($httpdContent -notmatch '#Include conf/extra/httpd-vhosts.conf' -and $httpdContent -notmatch 'Include conf/extra/httpd-vhosts.conf') {
        Write-Host "⚠️  Virtual hosts module not found. Adding basic configuration..." -ForegroundColor Yellow
    }
    
    # Check if our domain config is already included
    if ($httpdContent -notmatch 'Include.*lilac-domain\.conf') {
        Write-Host "Adding Include directive to httpd.conf..." -ForegroundColor Yellow
        
        $includeLine = "Include conf/extra/lilac-domain.conf"
        Add-Content $httpdConf "`n# LILAC Domain Configuration`n$includeLine`n"
        Write-Host "✅ Added Include directive!" -ForegroundColor Green
    } else {
        Write-Host "✅ Virtual host already included in httpd.conf" -ForegroundColor Green
    }
} else {
    Write-Host "⚠️  Could not find httpd.conf" -ForegroundColor Yellow
}

# Update .htaccess RewriteBase
$htaccessFile = Join-Path $ProjectPath ".htaccess"
if (Test-Path $htaccessFile) {
    Write-Host ""
    Write-Host "Updating .htaccess file..." -ForegroundColor Yellow
    
    $htaccessContent = Get-Content $htaccessFile -Raw
    
    # Update RewriteBase to root for domain
    if ($htaccessContent -match 'RewriteBase /lilac/') {
        $htaccessContent = $htaccessContent -replace 'RewriteBase /lilac/', 'RewriteBase /'
        Set-Content -Path $htaccessFile -Value $htaccessContent -Force
        Write-Host "✅ Updated RewriteBase in .htaccess" -ForegroundColor Green
    } else {
        Write-Host "⚠️  RewriteBase not found or already set. Check manually if needed." -ForegroundColor Yellow
    }
} else {
    Write-Host "⚠️  .htaccess file not found at: $htaccessFile" -ForegroundColor Yellow
}

# Get current IP address
Write-Host ""
Write-Host "Network Configuration:" -ForegroundColor Yellow
Write-Host "=====================" -ForegroundColor Yellow

try {
    $publicIP = (Invoke-RestMethod -Uri "https://api.ipify.org" -TimeoutSec 5)
    Write-Host "✅ Your public IP address: $publicIP" -ForegroundColor Green
} catch {
    $networkAdapters = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*"}
    if ($networkAdapters) {
        $localIP = $networkAdapters[0].IPAddress
        Write-Host "⚠️  Could not get public IP. Local IP: $localIP" -ForegroundColor Yellow
        Write-Host "   For domain setup, you'll need your public IP address." -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "DNS Configuration Instructions:" -ForegroundColor Yellow
Write-Host "==============================" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Log into your domain registrar (DuckDNS, Freenom, etc.)" -ForegroundColor White
Write-Host ""
Write-Host "2. Configure DNS A Record:" -ForegroundColor White
Write-Host "   Type: A" -ForegroundColor Cyan
Write-Host "   Name: @ (or leave blank for root domain)" -ForegroundColor Cyan
if ($publicIP) {
    Write-Host "   Value: $publicIP" -ForegroundColor Cyan
} else {
    Write-Host "   Value: [Your Public IP Address]" -ForegroundColor Cyan
}
Write-Host "   TTL: 3600" -ForegroundColor Cyan
Write-Host ""

if ($IncludeWWW) {
    Write-Host "3. Configure WWW A Record:" -ForegroundColor White
    Write-Host "   Type: A" -ForegroundColor Cyan
    Write-Host "   Name: www" -ForegroundColor Cyan
    if ($publicIP) {
        Write-Host "   Value: $publicIP" -ForegroundColor Cyan
    } else {
        Write-Host "   Value: [Your Public IP Address]" -ForegroundColor Cyan
    }
    Write-Host "   TTL: 3600" -ForegroundColor Cyan
    Write-Host ""
}

Write-Host "4. Wait for DNS propagation (1-48 hours, usually 1-2 hours)" -ForegroundColor White
Write-Host ""
Write-Host "5. Test DNS resolution:" -ForegroundColor White
Write-Host "   ping $DomainName" -ForegroundColor Cyan
Write-Host ""

# Restart Apache
Write-Host ""
Write-Host "Apache Configuration:" -ForegroundColor Yellow
Write-Host "====================" -ForegroundColor Yellow

$apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
if ($apacheService) {
    Write-Host "Apache service found: $($apacheService.Name)" -ForegroundColor Cyan
    Write-Host "Current status: $($apacheService.Status)" -ForegroundColor Cyan
    
    if ($apacheService.Status -eq "Running") {
        Write-Host ""
        $restart = Read-Host "Restart Apache to apply changes? (y/N)"
        if ($restart -eq "y" -or $restart -eq "Y") {
            Write-Host "Restarting Apache..." -ForegroundColor Yellow
            try {
                Restart-Service $apacheService.Name
                Start-Sleep -Seconds 3
                Write-Host "✅ Apache restarted successfully!" -ForegroundColor Green
            } catch {
                Write-Host "⚠️  Failed to restart Apache: $($_.Exception.Message)" -ForegroundColor Yellow
                Write-Host "   Please restart Apache manually from XAMPP Control Panel" -ForegroundColor Yellow
            }
        }
    } else {
        Write-Host ""
        Write-Host "⚠️  Apache is not running. Please start it from XAMPP Control Panel." -ForegroundColor Yellow
    }
} else {
    Write-Host "⚠️  Apache service not found. Please restart Apache manually." -ForegroundColor Yellow
}

# Final instructions
Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Domain Configuration Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "===========" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Configure DNS records (see instructions above)" -ForegroundColor White
Write-Host ""
Write-Host "2. Wait for DNS propagation (1-2 hours)" -ForegroundColor White
Write-Host ""
Write-Host "3. Test your domain:" -ForegroundColor White
Write-Host "   http://$DomainName" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. Test from different network (to verify external access)" -ForegroundColor White
Write-Host ""
Write-Host "5. Set up SSL/HTTPS (recommended for production):" -ForegroundColor White
Write-Host "   - Use Cloudflare (free SSL + protection)" -ForegroundColor Cyan
Write-Host "   - Or Let's Encrypt for direct SSL" -ForegroundColor Cyan
Write-Host ""
Write-Host "For detailed instructions, see: docs\DOMAIN_SETUP_GUIDE.md" -ForegroundColor Cyan
Write-Host ""
Write-Host "✨ Configuration complete! ✨" -ForegroundColor Green
Write-Host ""

