# LILAC Awards System - Client Deployment Guide

This guide provides step-by-step instructions for deploying the LILAC Awards System to a client's computer.

---

## 📋 Table of Contents

- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [Option 1: Manual Deployment](#option-1-manual-deployment)
- [Option 2: Automated Deployment Package](#option-2-automated-deployment-package)
- [Post-Deployment Configuration](#post-deployment-configuration)
- [Testing & Verification](#testing--verification)
- [Client Training](#client-training)
- [Troubleshooting](#troubleshooting)

---

## ✅ Pre-Deployment Checklist

### Before You Start

- [ ] Application is fully tested and working
- [ ] Database backup is created (if existing data)
- [ ] All sensitive information is removed or replaced
- [ ] Documentation is updated and complete
- [ ] Client requirements are documented (network access, firewall, etc.)

### Required Files to Package

- [ ] All PHP files (`/api`, root directory)
- [ ] All JavaScript files (`/js`)
- [ ] All CSS files (if separate)
- [ ] Database SQL file (`/database/lilac.sql`)
- [ ] Assets folder (`/assets`)
- [ ] Uploads folder structure (empty)
- [ ] Configuration files (`.htaccess`, etc.)
- [ ] Setup scripts (`/scripts`)
- [ ] Documentation (`/docs`)

---

## 📦 Option 1: Manual Deployment

### Step 1: Prepare Deployment Package

1. **Create a deployment folder structure:**
   ```
   LILAC-Deployment/
   ├── Application/
   │   └── lilac/          (all application files)
   ├── Database/
   │   └── lilac.sql       (database schema)
   ├── Setup/
   │   ├── install-tesseract.ps1
   │   ├── setup-apache.ps1
   │   └── XAMPP-Installer.exe (optional, if providing XAMPP)
   ├── Documentation/
   │   ├── README.md
   │   └── DEPLOYMENT_GUIDE.md
   └── Deployment-Instructions.txt
   ```

2. **Package the application:**
   - Copy entire `lilac` folder to deployment package
   - Exclude development files (.git, node_modules, etc.)
   - Include empty `uploads/` folder structure

### Step 2: Client Machine Requirements

**Minimum Requirements:**
- Windows 10/11 (64-bit) or Windows Server 2016+
- 4GB RAM (8GB recommended)
- 2GB free disk space
- Administrator access for installation

**Software Requirements:**
- XAMPP 8.0+ (Apache, MySQL, PHP)
- Tesseract OCR (for document processing)
- Modern web browser (Chrome, Firefox, Edge)

### Step 3: Install XAMPP on Client Machine

1. **Download XAMPP:**
   - Visit: https://www.apachefriends.org
   - Download XAMPP for Windows
   - Version 8.0 or higher recommended

2. **Install XAMPP:**
   - Run the installer
   - Install to default location: `C:\xampp\`
   - **Important:** Select **Apache** and **MySQL** components
   - Complete the installation

3. **Start XAMPP Services:**
   - Open **XAMPP Control Panel**
   - Click **Start** for **Apache**
   - Click **Start** for **MySQL**
   - Verify both show green "Running" status

### Step 4: Deploy Application Files

1. **Copy application files:**
   - Navigate to `C:\xampp\htdocs\`
   - Copy the `lilac` folder from your deployment package
   - Ensure path is: `C:\xampp\htdocs\lilac\`

2. **Verify folder structure:**
   ```
   C:\xampp\htdocs\lilac\
   ├── api/
   ├── assets/
   ├── database/
   ├── js/
   ├── uploads/
   ├── index.php
   ├── dashboard.php
   └── ... (other files)
   ```

### Step 5: Set Up Database

1. **Create Database:**
   - Open browser: http://localhost/phpmyadmin
   - Click **"New"** in left sidebar
   - Database name: `lilac`
   - Collation: `utf8mb4_general_ci`
   - Click **"Create"**

2. **Import Database Schema:**
   - Select `lilac` database
   - Click **"Import"** tab
   - Click **"Choose File"**
   - Select `lilac.sql` from deployment package
   - Click **"Go"**
   - Wait for import to complete

3. **Verify Database:**
   - Check that tables were created
   - Verify `users` table exists
   - Check default admin account exists

### Step 6: Configure Application

1. **Update Database Configuration (if needed):**
   - Edit `api/config.php`
   - Verify database credentials match client's XAMPP setup
   - Default: `root` user, no password

2. **Set File Permissions:**
   - Ensure `uploads/` folder is writable
   - Right-click `uploads` folder → Properties → Security
   - Give "Users" group "Modify" permissions

3. **Configure PHP Settings (if needed):**
   - Edit `C:\xampp\php\php.ini`
   - Ensure these settings:
     ```ini
     upload_max_filesize = 10M
     post_max_size = 10M
     max_execution_time = 300
     memory_limit = 256M
     ```
   - Restart Apache after changes

### Step 7: Install Tesseract OCR

1. **Option A: Manual Installation**
   - Download Tesseract from: https://github.com/UB-Mannheim/tesseract/wiki
   - Run installer
   - **Important:** Check "Add to PATH" during installation
   - Install to: `C:\Program Files\Tesseract-OCR\`

2. **Option B: Using Setup Script**
   - Open PowerShell as Administrator
   - Navigate to `C:\xampp\htdocs\lilac\scripts\`
   - Run: `.\install-tesseract.ps1`
   - Follow on-screen instructions

3. **Verify Installation:**
   - Open Command Prompt
   - Run: `tesseract --version`
   - Should display Tesseract version

### Step 8: Test the Application

1. **Access the Application:**
   - Open browser: http://localhost/lilac
   - Should see login page

2. **Test Login:**
   - Username: `admin`
   - Password: `admin123`
   - Login should be successful

3. **Test File Upload:**
   - Navigate to Awards section
   - Try uploading a test document
   - Verify OCR processing works

---

## 🤖 Option 2: Automated Deployment Package

### Creating an Automated Installer

You can create a PowerShell deployment script that automates the process:

1. **Create deployment script** (`deploy-lilac.ps1`):
   ```powershell
   # LILAC Automated Deployment Script
   # Run as Administrator
   
   param(
       [string]$XamppPath = "C:\xampp",
       [string]$AppSource = ".\lilac"
   )
   
   # Check admin privileges
   if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
       Write-Host "This script requires Administrator privileges!" -ForegroundColor Red
       exit 1
   }
   
   # Check XAMPP installation
   if (-not (Test-Path $XamppPath)) {
       Write-Host "XAMPP not found at $XamppPath" -ForegroundColor Red
       Write-Host "Please install XAMPP first!" -ForegroundColor Yellow
       exit 1
   }
   
   # Deploy application files
   $targetPath = Join-Path $XamppPath "htdocs\lilac"
   Write-Host "Deploying application to: $targetPath" -ForegroundColor Yellow
   
   if (Test-Path $targetPath) {
       $backup = "$targetPath.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
       Write-Host "Backing up existing installation to: $backup" -ForegroundColor Yellow
       Move-Item $targetPath $backup
   }
   
   Copy-Item -Path $AppSource -Destination $targetPath -Recurse -Force
   Write-Host "Application files deployed!" -ForegroundColor Green
   
   # Set permissions
   $uploadsPath = Join-Path $targetPath "uploads"
   if (Test-Path $uploadsPath) {
       icacls $uploadsPath /grant "Users:(OI)(CI)M" /T
       Write-Host "Uploads folder permissions set!" -ForegroundColor Green
   }
   
   # Start XAMPP services
   Write-Host "Starting XAMPP services..." -ForegroundColor Yellow
   $apacheService = Get-Service -Name "*apache*" -ErrorAction SilentlyContinue
   $mysqlService = Get-Service -Name "*mysql*" -ErrorAction SilentlyContinue
   
   if ($apacheService) { Start-Service $apacheService.Name }
   if ($mysqlService) { Start-Service $mysqlService.Name }
   
   Write-Host "Deployment completed!" -ForegroundColor Green
   Write-Host "Access the application at: http://localhost/lilac" -ForegroundColor Cyan
   ```

2. **Package with deployment script** for client to run

---

## ⚙️ Post-Deployment Configuration

### 1. Create Initial Admin Account (if needed)

If database was fresh, verify admin account exists:

```sql
-- Login to phpMyAdmin and run:
INSERT INTO users (username, email, password, role, created_at) 
VALUES ('admin', 'admin@cpu.edu.ph', '$2y$10$...', 'admin', NOW());
```

**Or use the provided update script:**
- Navigate to: `http://localhost/lilac/update-users-to-admin.php`
- Run once to create/update admin account
- **Delete this file after use for security**

### 2. Configure Network Access (if needed)

If client needs network access from other computers:

1. **Configure Apache to listen on all interfaces:**
   - Edit `C:\xampp\apache\conf\httpd.conf`
   - Find: `Listen 80`
   - Change to: `Listen 0.0.0.0:80`
   - Save and restart Apache

2. **Configure Windows Firewall:**
   - Open Windows Firewall
   - Add exception for Apache/HTTP (Port 80)
   - Or temporarily disable firewall for testing

3. **Find Client's IP Address:**
   - Open Command Prompt
   - Run: `ipconfig`
   - Note the IPv4 address (e.g., 192.168.1.100)

4. **Access from other computers:**
   - Use: `http://192.168.1.100/lilac`
   - Or use the setup-apache.ps1 script

### 3. Set Up Scheduled Backups

1. **Database Backup Script:**
   ```batch
   @echo off
   set BACKUP_DIR=C:\xampp\htdocs\lilac\backups
   set DATE=%date:~-4,4%%date:~-10,2%%date:~-7,2%
   
   if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
   
   C:\xampp\mysql\bin\mysqldump.exe -u root lilac > "%BACKUP_DIR%\lilac_backup_%DATE%.sql"
   ```

2. **Schedule using Windows Task Scheduler:**
   - Run daily at 2:00 AM
   - Keep last 30 days of backups

### 4. Security Hardening

1. **Change Default Passwords:**
   - Admin account password
   - MySQL root password (if exposing to network)

2. **Remove Development Files:**
   - Delete test files
   - Remove `update-users-to-admin.php` if used
   - Remove any temporary scripts

3. **Update php.ini for Production:**
   ```ini
   display_errors = Off
   log_errors = On
   error_log = C:\xampp\apache\logs\php_error.log
   ```

---

## 🧪 Testing & Verification

### Basic Functionality Tests

1. **Login Test:**
   - [ ] Admin login works
   - [ ] User login works (if users exist)
   - [ ] Logout works

2. **Dashboard Test:**
   - [ ] Dashboard loads correctly
   - [ ] Statistics display properly
   - [ ] Navigation works

3. **Awards Test:**
   - [ ] Can view awards
   - [ ] Can upload award documents
   - [ ] OCR processing works
   - [ ] Analysis results display

4. **File Upload Test:**
   - [ ] Profile picture upload
   - [ ] Document upload (MOU/MOA)
   - [ ] Award document upload
   - [ ] File size limits enforced

5. **Database Test:**
   - [ ] Data saves correctly
   - [ ] Queries execute properly
   - [ ] No connection errors

### Performance Tests

1. **Load Time:**
   - [ ] Pages load in < 3 seconds
   - [ ] No timeout errors

2. **Concurrent Users:**
   - [ ] Multiple users can login simultaneously
   - [ ] No session conflicts

### Browser Compatibility

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Edge (latest)
- [ ] Safari (if Mac client)

---

## 👥 Client Training

### Documentation to Provide

1. **User Manual** - Basic usage instructions
2. **Admin Guide** - Administrative functions
3. **Troubleshooting Guide** - Common issues and solutions
4. **Support Contact** - How to get help

### Training Topics

1. **Basic Navigation**
   - Login/logout
   - Dashboard overview
   - Menu navigation

2. **Core Functions**
   - Submitting award applications
   - Uploading documents
   - Viewing results

3. **Administrative Tasks** (for admins)
   - User management
   - Document approval
   - System configuration

4. **Maintenance Tasks**
   - Backup procedures
   - Updating content
   - Checking logs

---

## 🔧 Troubleshooting

### Common Issues

#### 1. "Cannot connect to database"

**Symptoms:**
- Error message when accessing application
- Database connection failed

**Solutions:**
- Check MySQL is running in XAMPP Control Panel
- Verify database name in `api/config.php`
- Check database credentials
- Verify database `lilac` exists in phpMyAdmin

#### 2. "404 Page Not Found"

**Symptoms:**
- Blank page or 404 error
- Application not accessible

**Solutions:**
- Verify Apache is running
- Check application is in `C:\xampp\htdocs\lilac\`
- Access via: `http://localhost/lilac` (not `http://localhost`)
- Check `.htaccess` file exists

#### 3. "File Upload Failed"

**Symptoms:**
- Cannot upload files
- Upload error messages

**Solutions:**
- Check `uploads/` folder permissions
- Verify `php.ini` settings:
  ```ini
  upload_max_filesize = 10M
  post_max_size = 10M
  ```
- Restart Apache after php.ini changes

#### 4. "OCR Not Working"

**Symptoms:**
- Document analysis fails
- Tesseract errors

**Solutions:**
- Verify Tesseract is installed
- Check Tesseract path in `api/config.php`
- Test Tesseract in Command Prompt: `tesseract --version`
- Restart Apache after Tesseract installation

#### 5. "Session Not Working"

**Symptoms:**
- Logged out unexpectedly
- Login doesn't persist

**Solutions:**
- Clear browser cookies
- Check PHP session directory permissions
- Verify `session_start()` in PHP files

#### 6. "Slow Performance"

**Symptoms:**
- Pages load slowly
- Timeout errors

**Solutions:**
- Check available RAM
- Increase PHP memory limit in `php.ini`:
  ```ini
  memory_limit = 512M
  ```
- Check database indexes
- Clear browser cache

### Getting Support

If issues persist:

1. Check error logs:
   - PHP errors: `C:\xampp\apache\logs\error.log`
   - Application logs: `C:\xampp\htdocs\lilac\logs\activity.log`

2. Document the issue:
   - Error message
   - Steps to reproduce
   - Browser and version
   - Screenshots if possible

3. Contact support with:
   - System information
   - Error logs
   - Issue description

---

## 📝 Deployment Checklist

Use this checklist during deployment:

### Pre-Deployment
- [ ] Application tested and working
- [ ] Database backup created
- [ ] Deployment package prepared
- [ ] Documentation included

### Installation
- [ ] XAMPP installed
- [ ] Apache and MySQL running
- [ ] Application files copied
- [ ] Database created and imported
- [ ] Configuration updated

### Setup
- [ ] File permissions set
- [ ] Tesseract OCR installed
- [ ] PHP settings configured
- [ ] Initial admin account created

### Testing
- [ ] Login works
- [ ] Dashboard loads
- [ ] File upload works
- [ ] OCR processing works
- [ ] All features tested

### Documentation
- [ ] User manual provided
- [ ] Admin guide provided
- [ ] Support contact information
- [ ] Training completed

### Security
- [ ] Default passwords changed
- [ ] Development files removed
- [ ] Security settings configured
- [ ] Backup procedures established

---

## 🎯 Quick Reference

### Important URLs
- Application: `http://localhost/lilac`
- phpMyAdmin: `http://localhost/phpmyadmin`
- XAMPP Dashboard: `http://localhost/dashboard`

### Important Paths
- Application: `C:\xampp\htdocs\lilac\`
- XAMPP: `C:\xampp\`
- Apache Config: `C:\xampp\apache\conf\httpd.conf`
- PHP Config: `C:\xampp\php\php.ini`

### Default Credentials
- Admin Username: `admin`
- Admin Password: `admin123` (**Change after first login!**)
- MySQL User: `root`
- MySQL Password: *(empty by default)*

### Support Commands
```powershell
# Start XAMPP services
net start Apache2.4
net start mysql

# Stop XAMPP services
net stop Apache2.4
net stop mysql

# Check Tesseract
tesseract --version

# Backup database
C:\xampp\mysql\bin\mysqldump.exe -u root lilac > backup.sql
```

---

## 📞 Post-Deployment Support

After deployment, ensure client has:

1. **Access Information:**
   - Local URL: `http://localhost/lilac`
   - Network URL (if applicable): `http://[IP]/lilac`
   - Admin credentials

2. **Documentation:**
   - User manual
   - Admin guide
   - Troubleshooting guide

3. **Support Contact:**
   - Email address
   - Phone number
   - Preferred support hours

4. **Backup Procedures:**
   - How to backup database
   - Backup schedule
   - Restore procedures

---

**Last Updated: January 2025**

*This deployment guide is part of the LILAC Awards System documentation.*

