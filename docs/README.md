# LILAC Awards System

**LILAC** (Leadership in International Learning and Collaborative Achievement) is a comprehensive web-based awards management system designed for educational institutions to track international achievements, manage documents, and streamline award applications.

---

## 📋 Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation & Setup](#installation--setup)
- [Database Setup](#database-setup)
- [Project Structure](#project-structure)
- [User Roles & Access](#user-roles--access)
- [Application Flow](#application-flow)
- [Default Credentials](#default-credentials)
- [Troubleshooting](#troubleshooting)
- [Technologies Used](#technologies-used)

---

## ✨ Features

### For All Users
- **Dashboard** - Overview of activities, awards, and statistics
- **Awards Progress Tracker** - Submit and track award applications with OCR-powered document analysis
- **Events & Activities** - Manage and view institutional events
- **Scheduler** - Calendar-based event scheduling
- **Profile Management** - Update personal information and change password
- **Dark Mode** - Full dark mode support across all pages

### Admin-Only Features
- **MOU & MOA Management** - Upload and manage Memorandums of Understanding/Agreement
- **Documents Hub** - Unified document management (MOU, MOA, Other Documents)
- **User Management** - Manage user accounts and permissions
- **Award Criteria Management** - Configure award requirements and categories
- **Analytics Dashboard** - Advanced analytics with OCR accuracy metrics, performance radar charts, and AI-powered recommendations

---

## 💻 System Requirements

- **XAMPP** (Version 8.0 or higher recommended)
  - Apache Web Server
  - MySQL Database (version 5.7+)
  - PHP 7.4 or higher
- **Web Browser** (Chrome, Firefox, Edge, or Safari)
- **Minimum 2GB RAM**
- **500MB free disk space**

---

## 🚀 Installation & Setup

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org)
2. Run the installer and install XAMPP to `C:\xampp\` (Windows) or `/Applications/XAMPP` (Mac)
3. During installation, ensure **Apache** and **MySQL** are selected

### Step 2: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Both should show green "Running" status

### Step 3: Extract Project Files

1. Navigate to `C:\xampp\htdocs\`
2. Ensure the `lilac` folder is present with all project files
3. Your project path should be: `C:\xampp\htdocs\lilac\`

### Step 4: Verify File Structure

Ensure you have the following structure:
```
C:\xampp\htdocs\lilac\
├── api/
│   ├── config.php
│   ├── profile.php
│   ├── mou-moa.php
│   ├── other-documents.php
│   └── ...
├── uploads/
│   ├── mou_moa/
│   └── other_documents/
├── index.php
├── dashboard.php
├── profile.php
├── documents.php
└── README.md
```

---

## 🗄️ Database Setup

### Option 1: Using phpMyAdmin (Recommended)

1. Open your web browser
2. Navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Click **"New"** in the left sidebar
4. Database name: `lilac`
5. Collation: `utf8mb4_general_ci`
6. Click **"Create"**



### Import Database Tables

1. In phpMyAdmin, select `lilac` database
2. Click **"Import"** tab
3. Click **"Choose File"**
4. Select `lilac.sql` from the project folder (if provided in the database folder)
5. Click **"Go"**


## 📂 Project Structure

```
lilac/
│
├── index.php                 # Login page
├── dashboard.php             # Main dashboard (all users)
├── profile.php               # User profile management
├── documents.php             # Documents hub (admin only)
├── mou-moa.php              # MOU/MOA management
├── user-awards.php           # Awards tracker (users)
├── admin-awards.php          # Awards management (admin)
├── events-activities.php     # Events management
├── scheduler.php             # Event scheduler
├── logout.php                # Logout handler
│
├── api/                      # Backend API endpoints
│   ├── config.php           # Database configuration
│   ├── profile.php          # Profile API
│   ├── mou-moa.php          # MOU/MOA API
│   ├── other-documents.php  # Documents API
│   └── ...
│
└── uploads/                  # File uploads directory
    ├── mou_moa/             # MOU/MOA files
    └── other_documents/      # Other document files
```

---

## 👥 User Roles & Access

### 🔴 Admin User
**Full system access including:**
- ✅ All user features
- ✅ User management
- ✅ Award criteria configuration
- ✅ Document approval/rejection
- ✅ System analytics dashboard
- ✅ MOU/MOA management
- ✅ Documents hub (unified view)

**Pages:** All pages

### 🔵 Regular User
**Standard access including:**
- ✅ Personal dashboard
- ✅ Submit award applications
- ✅ Upload supporting documents
- ✅ View own awards progress
- ✅ Manage events
- ✅ Schedule activities
- ✅ Update profile

**Restricted from:**
- ❌ Documents hub
- ❌ User management
- ❌ Award criteria configuration
- ❌ Full analytics dashboard

---

## 🔄 Application Flow

### 1. User Registration & Login Flow

```
┌─────────────┐
│  index.php  │ ← Login Page
└──────┬──────┘
       │
       ├─ Valid credentials? → Yes → Set session → Dashboard
       │
       └─ No → Show error message
```

### 2. Admin Workflow

```
Admin Login
    ↓
Dashboard (Overview)
    ↓
┌───────────────────────────────────────────┐
│                                           │
├─→ Documents Hub (documents.php)          │
│   ├─ View all MOU/MOA/Other Docs         │
│   ├─ Add new documents                    │
│   ├─ Edit/Delete documents                │
│   └─ Analytics & Reports                  │
│                                           │
├─→ Award Management (admin-awards.php)     │
│   ├─ Configure award criteria            │
│   ├─ Review submissions                   │
│   └─ Approve/Reject applications          │
│                                           │
├─→ User Management                         │
│   ├─ View all users                       │
│   └─ Manage permissions                   │
│                                           │
└─→ Analytics Dashboard                     │
    ├─ OCR accuracy metrics                 │
    ├─ Performance radar charts             │
    ├─ Processing timeline                  │
    └─ AI-powered recommendations           │
```

### 3. User Workflow

```
User Login
    ↓
Dashboard (Personal Overview)
    ↓
┌───────────────────────────────────────────┐
│                                           │
├─→ Awards Progress (user-awards.php)       │
│   ├─ Process Award Tab                    │
│   │   ├─ Upload award document            │
│   │   ├─ OCR processing (6 stages)        │
│   │   ├─ Document scanning animation      │
│   │   ├─ AI analysis & matching           │
│   │   └─ View results                     │
│   │                                       │
│   └─ Analytics Dashboard Tab              │
│       ├─ View performance metrics         │
│       ├─ Category radar chart             │
│       ├─ AI recommendations               │
│       └─ Processing history               │
│                                           │
├─→ Events & Activities                     │
│   ├─ Create new events                    │
│   ├─ View event calendar                  │
│   └─ RSVP to events                       │
│                                           │
├─→ Scheduler                                │
│   ├─ Calendar view                        │
│   └─ Schedule management                  │
│                                           │
├─→ MOU & MOA                                │
│   ├─ View partnerships                    │
│   └─ Upload agreements                    │
│                                           │
└─→ Profile                                  │
    ├─ Edit personal info                   │
    │   ├─ Username                          │
    │   ├─ Email                             │
    │   ├─ Department                        │
    │   └─ Phone                             │
    │                                       │
    └─ Change password                      │
```

### 4. Document Processing Flow (OCR)

```
Upload Document
    ↓
┌─────────────────────────────────────────┐
│ Stage 1: Upload & Validation (0-15%)    │
│  ├─ Uploading document...               │
│  ├─ Validating file format...           │
│  └─ Securing upload connection...       │
├─────────────────────────────────────────┤
│ Stage 2: Pre-processing (15-25%)        │
│  ├─ Pre-processing image...             │
│  ├─ Adjusting image contrast...         │
│  └─ Detecting text regions...           │
├─────────────────────────────────────────┤
│ Stage 3: OCR Init (25-45%)              │
│  ├─ Initializing OCR engine...          │
│  ├─ Analyzing document layout...        │
│  └─ Identifying text blocks...          │
├─────────────────────────────────────────┤
│ Stage 4: Extraction (45-65%)            │
│  ├─ Extracting characters...            │
│  ├─ Recognizing words and phrases...    │
│  └─ Processing page 1 of 1...           │
├─────────────────────────────────────────┤
│ Stage 5: Enhancement (65-80%)           │
│  ├─ Applying language models...         │
│  ├─ Validating extracted text...        │
│  └─ Enhancing OCR accuracy...           │
├─────────────────────────────────────────┤
│ Stage 6: Analysis (80-90%)              │
│  ├─ Analyzing against award criteria... │
│  ├─ Matching requirements...            │
│  └─ Computing eligibility scores...     │
└─────────────────────────────────────────┘
    ↓
Display Results
    ├─ Eligible awards
    ├─ Match percentage
    ├─ Requirements analysis
    └─ Recommendations
```

---

## 🔑 Default Credentials

### Admin Account
- **Username:** `lesley`
- **Password:** `Central@2025`

### Test User Account (if created)
- **Username:** `user`
- **Email:** `user@cpu.edu.ph`
- **Password:** `password`

⚠️ **Important:** Change these passwords after first login!

---

## 🌐 Accessing the Application

1. Ensure Apache and MySQL are running in XAMPP
2. Open your web browser
3. Navigate to: [http://localhost/lilac](http://localhost/lilac)
4. Login with admin credentials
5. Start using the system!

---

## 🔧 Troubleshooting

### Problem: "Cannot connect to database"

**Solution:**
1. Check MySQL is running in XAMPP Control Panel
2. Verify database name in `api/config.php`
3. Check database credentials (default: username `root`, no password)

### Problem: "Page not found" / 404 Error

**Solution:**
1. Verify Apache is running
2. Check project is in `C:\xampp\htdocs\lilac\`
3. Access via `http://localhost/lilac` (not `http://localhost`)

### Problem: "Upload directory not found"

**Solution:**
```bash
# Create upload directories
mkdir c:\xampp\htdocs\lilac\uploads\mou_moa
mkdir c:\xampp\htdocs\lilac\uploads\other_documents
```

### Problem: "Session not working" / "Not logged in"

**Solution:**
1. Check `session_start()` is called in each PHP file
2. Verify PHP session directory has write permissions
3. Clear browser cookies and try again

### Problem: File upload fails

**Solution:**
1. Check `php.ini` settings:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```
2. Verify upload directory has write permissions
3. Restart Apache after changing `php.ini`

### Problem: Dark mode not working

**Solution:**
1. Clear browser localStorage
2. Hard refresh browser (Ctrl + Shift + R)
3. Check browser JavaScript is enabled

---

## 🛠️ Technologies Used

### Frontend
- **HTML5** - Page structure
- **Tailwind CSS** - Styling framework
- **JavaScript (ES6+)** - Interactivity
- **Chart.js** - Data visualization
- **Material Symbols** - Icons

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 5.7+** - Database
- **PDO** - Database abstraction

### Features
- **OCR Simulation** - Multi-stage document processing
- **Session Management** - User authentication
- **File Upload** - Document management
- **RESTful API** - Backend endpoints
- **Responsive Design** - Mobile-friendly
- **Dark Mode** - Theme switching

---

## 📝 Additional Notes

### File Permissions (Linux/Mac)
```bash
chmod 755 /path/to/lilac
chmod 777 /path/to/lilac/uploads
chmod 777 /path/to/lilac/uploads/mou_moa
chmod 777 /path/to/lilac/uploads/other_documents
```

### Database Backup
Regular backups recommended:
```bash
mysqldump -u root lilac > backup_$(date +%Y%m%d).sql
```

### Security Recommendations
1. ✅ Change default admin password
2. ✅ Use strong passwords (min 8 characters)
3. ✅ Keep XAMPP updated
4. ✅ Restrict database access
5. ✅ Enable HTTPS in production

---

## 📧 Support

For issues or questions:
- Check the [Troubleshooting](#troubleshooting) section
- Review error logs in `C:\xampp\apache\logs\`
- Check MySQL logs in `C:\xampp\mysql\data\`

---

## 📄 License

This project is developed for Central Philippine University's International Affairs Office.

---

## 🎯 Quick Start Checklist

- [ ] XAMPP installed and running
- [ ] Database `lilac` created
- [ ] Tables imported/created
- [ ] Admin account created
- [ ] Upload directories created
- [ ] Accessed http://localhost/lilac
- [ ] Logged in successfully
- [ ] Changed default password

**You're ready to use LILAC Awards System!** 🎉

---

*Last Updated: January 2025*
