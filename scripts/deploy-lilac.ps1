# LILAC Automated Deployment Script
# Run this script as Administrator to deploy LILAC to client machine

param(
    [string]$XamppPath = "C:\xampp",
    [string]$AppSource = ".",
    [switch]$SkipTesseract = $false,
    [switch]$SkipDatabase = $false
)

Write-Host "========================================" -ForegroundColor Green
Write-Host "LILAC Awards System - Deployment Script" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# Check admin privileges
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
if (-NOT $isAdmin) {
    Write-Host "⚠️  WARNING: This script requires Administrator privileges!" -ForegroundColor Red
    Write-Host "   Please run PowerShell as Administrator and try again." -ForegroundColor Yellow
    Write-Host ""
    $continue = Read-Host "Continue anyway? (y/N)"
    if ($continue -ne "y" -and $continue -ne "Y") {
        exit 1
    }
}

# Check XAMPP installation
Write-Host "Checking XAMPP installation..." -ForegroundColor Yellow
if (-not (Test-Path $XamppPath)) {
    Write-Host "❌ XAMPP not found at: $XamppPath" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install XAMPP first:" -ForegroundColor Yellow
    Write-Host "1. Download from: https://www.apachefriends.org" -ForegroundColor Cyan
    Write-Host "2. Install to default location (C:\xampp\)" -ForegroundColor Cyan
    Write-Host "3. Run this script again" -ForegroundColor Cyan
    exit 1
}

Write-Host "✅ XAMPP found at: $XamppPath" -ForegroundColor Green

# Check if Apache and MySQL are installed
$apachePath = Join-Path $XamppPath "apache\bin\httpd.exe"
$mysqlPath = Join-Path $XamppPath "mysql\bin\mysql.exe"

if (-not (Test-Path $apachePath)) {
    Write-Host "⚠️  Warning: Apache not found. Make sure XAMPP includes Apache." -ForegroundColor Yellow
}

if (-not (Test-Path $mysqlPath)) {
    Write-Host "⚠️  Warning: MySQL not found. Make sure XAMPP includes MySQL." -ForegroundColor Yellow
}

Write-Host ""

# Deploy application files
$targetPath = Join-Path $XamppPath "htdocs\lilac"

Write-Host "Deploying application files..." -ForegroundColor Yellow
Write-Host "  Source: $AppSource" -ForegroundColor Cyan
Write-Host "  Target: $targetPath" -ForegroundColor Cyan

if (Test-Path $targetPath) {
    $backup = "$targetPath.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Write-Host "  ⚠️  Existing installation found!" -ForegroundColor Yellow
    Write-Host "  📦 Creating backup: $backup" -ForegroundColor Yellow
    
    try {
        Copy-Item -Path $targetPath -Destination $backup -Recurse -Force
        Write-Host "  ✅ Backup created successfully" -ForegroundColor Green
    } catch {
        Write-Host "  ❌ Failed to create backup: $($_.Exception.Message)" -ForegroundColor Red
        $continue = Read-Host "  Continue without backup? (y/N)"
        if ($continue -ne "y" -and $continue -ne "Y") {
            exit 1
        }
    }
    
    Write-Host "  🗑️  Removing old installation..." -ForegroundColor Yellow
    Remove-Item -Path $targetPath -Recurse -Force
}

# Copy application files
Write-Host "  📁 Copying application files..." -ForegroundColor Yellow
try {
    if ($AppSource -eq ".") {
        # If running from scripts folder, go up one level
        $currentPath = Get-Location
        $parentPath = Split-Path $currentPath -Parent
        
        # Check if we're in the lilac root directory
        if (Test-Path (Join-Path $parentPath "index.php")) {
            $AppSource = $parentPath
        } elseif (Test-Path (Join-Path $currentPath "index.php")) {
            $AppSource = $currentPath
        } else {
            Write-Host "  ❌ Cannot find application files!" -ForegroundColor Red
            Write-Host "     Please run this script from the lilac application directory" -ForegroundColor Yellow
            exit 1
        }
    }
    
    Copy-Item -Path "$AppSource\*" -Destination $targetPath -Recurse -Force -Exclude ".git",".gitignore","node_modules","*.log"
    Write-Host "  ✅ Application files deployed!" -ForegroundColor Green
} catch {
    Write-Host "  ❌ Failed to copy files: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Set file permissions for uploads folder
Write-Host ""
Write-Host "Setting file permissions..." -ForegroundColor Yellow
$uploadsPath = Join-Path $targetPath "uploads"
$logsPath = Join-Path $targetPath "logs"

if (Test-Path $uploadsPath) {
    try {
        # Give Users group modify permissions
        icacls $uploadsPath /grant "Users:(OI)(CI)M" /T /Q 2>$null
        Write-Host "  ✅ Uploads folder permissions set" -ForegroundColor Green
    } catch {
        Write-Host "  ⚠️  Could not set uploads permissions (may need manual setup)" -ForegroundColor Yellow
    }
}

if (Test-Path $logsPath) {
    try {
        icacls $logsPath /grant "Users:(OI)(CI)M" /T /Q 2>$null
        Write-Host "  ✅ Logs folder permissions set" -ForegroundColor Green
    } catch {
        Write-Host "  ⚠️  Could not set logs permissions (may need manual setup)" -ForegroundColor Yellow
    }
}

# Start XAMPP services
Write-Host ""
Write-Host "Starting XAMPP services..." -ForegroundColor Yellow

$apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
$mysqlService = Get-Service -Name "*mysql*" -ErrorAction SilentlyContinue

if ($apacheService) {
    if ($apacheService.Status -ne "Running") {
        Write-Host "  ▶️  Starting Apache..." -ForegroundColor Yellow
        try {
            Start-Service $apacheService.Name
            Write-Host "  ✅ Apache started" -ForegroundColor Green
        } catch {
            Write-Host "  ⚠️  Could not start Apache: $($_.Exception.Message)" -ForegroundColor Yellow
            Write-Host "     Please start Apache manually from XAMPP Control Panel" -ForegroundColor Yellow
        }
    } else {
        Write-Host "  ✅ Apache is already running" -ForegroundColor Green
    }
} else {
    Write-Host "  ⚠️  Apache service not found. Please start Apache from XAMPP Control Panel" -ForegroundColor Yellow
}

if ($mysqlService) {
    if ($mysqlService.Status -ne "Running") {
        Write-Host "  ▶️  Starting MySQL..." -ForegroundColor Yellow
        try {
            Start-Service $mysqlService.Name
            Write-Host "  ✅ MySQL started" -ForegroundColor Green
        } catch {
            Write-Host "  ⚠️  Could not start MySQL: $($_.Exception.Message)" -ForegroundColor Yellow
            Write-Host "     Please start MySQL manually from XAMPP Control Panel" -ForegroundColor Yellow
        }
    } else {
        Write-Host "  ✅ MySQL is already running" -ForegroundColor Green
    }
} else {
    Write-Host "  ⚠️  MySQL service not found. Please start MySQL from XAMPP Control Panel" -ForegroundColor Yellow
}

# Database setup
if (-not $SkipDatabase) {
    Write-Host ""
    Write-Host "Database Setup" -ForegroundColor Yellow
    Write-Host "==============" -ForegroundColor Yellow
    
    $dbPath = Join-Path $targetPath "database\lilac.sql"
    
    if (Test-Path $dbPath) {
        Write-Host "  📊 Database SQL file found: $dbPath" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "  To import the database:" -ForegroundColor Yellow
        Write-Host "  1. Open: http://localhost/phpmyadmin" -ForegroundColor Cyan
        Write-Host "  2. Create new database: 'lilac'" -ForegroundColor Cyan
        Write-Host "  3. Select 'lilac' database" -ForegroundColor Cyan
        Write-Host "  4. Click 'Import' tab" -ForegroundColor Cyan
        Write-Host "  5. Choose file: $dbPath" -ForegroundColor Cyan
        Write-Host "  6. Click 'Go'" -ForegroundColor Cyan
        Write-Host ""
    } else {
        Write-Host "  ⚠️  Database SQL file not found at: $dbPath" -ForegroundColor Yellow
        Write-Host "     You may need to set up the database manually" -ForegroundColor Yellow
    }
}

# Tesseract OCR setup
if (-not $SkipTesseract) {
    Write-Host ""
    Write-Host "Tesseract OCR Setup" -ForegroundColor Yellow
    Write-Host "===================" -ForegroundColor Yellow
    
    $tesseractPaths = @(
        "C:\Program Files\Tesseract-OCR\tesseract.exe",
        "C:\Program Files (x86)\Tesseract-OCR\tesseract.exe"
    )
    
    $tesseractInstalled = $false
    foreach ($path in $tesseractPaths) {
        if (Test-Path $path) {
            Write-Host "  ✅ Tesseract OCR found at: $path" -ForegroundColor Green
            $tesseractInstalled = $true
            break
        }
    }
    
    if (-not $tesseractInstalled) {
        Write-Host "  ⚠️  Tesseract OCR not found" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  To install Tesseract OCR:" -ForegroundColor Yellow
        Write-Host "  1. Download from: https://github.com/UB-Mannheim/tesseract/wiki" -ForegroundColor Cyan
        Write-Host "  2. Run the installer" -ForegroundColor Cyan
        Write-Host "  3. IMPORTANT: Check 'Add to PATH' during installation" -ForegroundColor Red
        Write-Host "  4. Or run: .\scripts\install-tesseract.ps1" -ForegroundColor Cyan
        Write-Host ""
    }
}

# Final instructions
Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Deployment Completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "===========" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Set up the database (if not done already)" -ForegroundColor White
Write-Host "   - Open: http://localhost/phpmyadmin" -ForegroundColor Cyan
Write-Host "   - Import: $targetPath\database\lilac.sql" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Access the application:" -ForegroundColor White
Write-Host "   - URL: http://localhost/lilac" -ForegroundColor Cyan
Write-Host "   - Default login: admin / admin123" -ForegroundColor Cyan
Write-Host "   - ⚠️  Change password after first login!" -ForegroundColor Red
Write-Host ""
Write-Host "3. Test the application:" -ForegroundColor White
Write-Host "   - Login with admin credentials" -ForegroundColor Cyan
Write-Host "   - Try uploading a test document" -ForegroundColor Cyan
Write-Host "   - Verify all features work" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. Install Tesseract OCR (if not already installed):" -ForegroundColor White
Write-Host "   - For document processing features" -ForegroundColor Cyan
Write-Host "   - Run: .\scripts\install-tesseract.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "5. Configure network access (if needed):" -ForegroundColor White
Write-Host "   - Run: .\scripts\setup-apache.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "For detailed instructions, see: docs\DEPLOYMENT_GUIDE.md" -ForegroundColor Cyan
Write-Host ""
Write-Host "✨ Deployment complete! Enjoy using LILAC! ✨" -ForegroundColor Green
Write-Host ""

