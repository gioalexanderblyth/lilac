
# LILAC Awards System - Domain Setup Guide

This guide will help you get a free domain name and configure your LILAC system to use it.

---

## 📋 Table of Contents

- [Getting a Free Domain](#getting-a-free-domain)
- [Option 1: Free Subdomain Services](#option-1-free-subdomain-services)
- [Option 2: Free Domain Extensions](#option-2-free-domain-extensions)
- [Option 3: Free Domain with Hosting](#option-3-free-domain-with-hosting)
- [Domain Configuration](#domain-configuration)
- [Apache Virtual Host Setup](#apache-virtual-host-setup)
- [DNS Configuration](#dns-configuration)
- [SSL/HTTPS Setup](#sslhttps-setup)
- [Application Configuration Updates](#application-configuration-updates)
- [Testing Your Domain](#testing-your-domain)
- [Troubleshooting](#troubleshooting)

---

## 🌐 Getting a Free Domain

There are several options for getting a free domain for your LILAC system. Choose the option that best fits your needs.

---

## 🆓 Option 1: Free Subdomain Services

### Best Option: No-IP or DuckDNS (Recommended for Testing)

These services provide free subdomains that point to your dynamic IP address.

#### **DuckDNS** (Recommended)

**Pros:**
- ✅ Completely free
- ✅ Easy setup
- ✅ Automatic IP updates
- ✅ Works with dynamic IPs
- ✅ Good for testing and small deployments

**Cons:**
- ❌ Subdomain only (yourname.duckdns.org)
- ❌ Less professional than .com domain

**Steps:**

1. **Sign up at DuckDNS:**
   - Visit: https://www.duckdns.org
   - Sign up for free account
   - Create a subdomain (e.g., `lilac-awards`)
   - Your domain will be: `lilac-awards.duckdns.org`

2. **Get your token:**
   - Copy your DuckDNS token from the dashboard
   - You'll need this for auto-update script

3. **Set up auto-update (optional):**
   - Download the DuckDNS update client
   - Or use their update URL manually

4. **Configure DNS (not needed for DuckDNS):**
   - DuckDNS handles DNS automatically
   - Just make sure your IP is updated

#### **No-IP**

1. Visit: https://www.noip.com
2. Sign up for free account
3. Create a free hostname (e.g., `lilac-awards.ddns.net`)
4. Install No-IP Dynamic Update Client on your server
5. Client automatically updates your IP

---

## 🆓 Option 2: Free Domain Extensions

### **Freenom** (Limited Availability)

**Note:** Freenom has reduced availability. Some extensions may no longer be available.

**Available extensions:**
- `.tk` - Tokelau
- `.ml` - Mali
- `.ga` - Gabon
- `.cf` - Central African Republic
- `.gq` - Equatorial Guinea

**Steps:**

1. Visit: https://www.freenom.com
2. Search for your desired domain name
3. Select a free extension (.tk, .ml, .ga, etc.)
4. Add to cart and complete registration
5. Manage DNS settings in Freenom dashboard

**Pros:**
- ✅ Free custom domain name
- ✅ Professional appearance
- ✅ Full control over DNS

**Cons:**
- ❌ Limited availability
- ❌ Less trustworthy extensions
- ❌ May have reliability issues
- ❌ Some registrars block these domains

---

## 🆓 Option 3: Free Domain with Hosting

These options provide a free domain when you purchase hosting, but the domain is free for the first year.

### **GoogieHost Free Hosting**

1. Visit: https://googiehost.com/freedomains
2. Choose a free domain extension:
   - `.cu.ma`
   - `.thats.im`
   - `.c1.is`
   - `.onweb.im`
3. Sign up and get free hosting + domain

### **InfinityFree**

1. Visit: https://www.infinityfree.net
2. Sign up for free hosting
3. You can use a free subdomain or connect your own domain

---

## ⚙️ Domain Configuration

Once you have your domain, you need to configure it to point to your LILAC server.

### Prerequisites

Before configuring your domain, ensure:

1. ✅ Your LILAC application is installed and running
2. ✅ Apache is configured and accessible locally
3. ✅ Your server has a static IP address (or use dynamic DNS)
4. ✅ Port 80 (HTTP) is open in your firewall

---

## 🔧 Apache Virtual Host Setup

You need to configure Apache to serve your LILAC application on your domain.

### Step 1: Create Virtual Host Configuration

1. **Open Apache configuration:**
   - Navigate to: `C:\xampp\apache\conf\extra\`
   - Create new file: `lilac-domain.conf`

2. **Add virtual host configuration:**

   ```apache
   # LILAC Awards System - Domain Configuration
   <VirtualHost *:80>
       ServerName yourdomain.com
       ServerAlias www.yourdomain.com
       DocumentRoot "C:/xampp/htdocs/lilac"
       
       <Directory "C:/xampp/htdocs/lilac">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       # Error and access logs
       ErrorLog "C:/xampp/apache/logs/lilac-error.log"
       CustomLog "C:/xampp/apache/logs/lilac-access.log" common
       
       # PHP settings
       php_admin_value upload_max_filesize "10M"
       php_admin_value post_max_size "10M"
       php_admin_value max_execution_time "300"
       php_admin_value memory_limit "256M"
   </VirtualHost>
   ```

3. **Update the configuration:**
   - Replace `yourdomain.com` with your actual domain
   - Replace `www.yourdomain.com` with your www subdomain (if applicable)
   - Verify the DocumentRoot path matches your installation

### Step 2: Enable Virtual Host in Apache

1. **Edit main Apache config:**
   - Open: `C:\xampp\apache\conf\httpd.conf`
   - Find the line: `#Include conf/extra/httpd-vhosts.conf`
   - Uncomment it: `Include conf/extra/httpd-vhosts.conf`

2. **Add your domain configuration:**
   - At the end of `httpd.conf`, add:
     ```apache
     # Include LILAC domain configuration
     Include conf/extra/lilac-domain.conf
     ```

3. **Restart Apache:**
   - Open XAMPP Control Panel
   - Stop Apache
   - Start Apache

### Step 3: Update .htaccess

Update your `.htaccess` file for domain use:

1. **Edit `.htaccess` file:**
   - Location: `C:\xampp\htdocs\lilac\.htaccess`

2. **Update RewriteBase:**
   - If using root domain (e.g., `lilac-awards.duckdns.org`):
     ```apache
     RewriteBase /
     ```
   
   - If using subdirectory (e.g., `yourdomain.com/lilac`):
     ```apache
     RewriteBase /lilac/
     ```

---

## 🔗 DNS Configuration

You need to point your domain to your server's IP address.

### Step 1: Find Your Public IP Address

1. **Check your current IP:**
   - Visit: https://whatismyipaddress.com
   - Note your IPv4 address (e.g., `123.45.67.89`)

2. **For dynamic IP addresses:**
   - Use DuckDNS or No-IP (they handle this automatically)
   - Or set up dynamic DNS client

### Step 2: Configure DNS Records

#### For DuckDNS:
- DNS is handled automatically
- Just ensure your IP is updated in their system

#### For Freenom or other registrars:

1. **Log into your domain registrar dashboard**

2. **Find DNS Management / Nameservers section**

3. **Add/Edit A Record:**
   - Type: `A`
   - Name/Host: `@` (or leave blank for root domain)
   - Value/Points to: `Your.IP.Address` (e.g., `123.45.67.89`)
   - TTL: `3600` (or default)

4. **Add WWW record (optional):**
   - Type: `A`
   - Name/Host: `www`
   - Value/Points to: `Your.IP.Address`
   - TTL: `3600`

5. **Save changes**

6. **Wait for DNS propagation:**
   - Can take 24-48 hours
   - Usually works within 1-2 hours

### Step 3: Verify DNS Configuration

1. **Check DNS propagation:**
   - Visit: https://www.whatsmydns.net
   - Enter your domain
   - Check if A record points to your IP

2. **Test from command prompt:**
   ```cmd
   ping yourdomain.com
   ```
   - Should show your IP address

---

## 🔒 SSL/HTTPS Setup (Recommended)

For production use, you should enable HTTPS with a free SSL certificate.

### Option 1: Let's Encrypt (Free SSL)

#### Using Certbot on Windows:

**Note:** Let's Encrypt works best on Linux. For Windows, consider alternatives below.

#### Alternative: Cloudflare (Recommended for Windows)

1. **Sign up for free Cloudflare account:**
   - Visit: https://www.cloudflare.com
   - Sign up (free plan is sufficient)

2. **Add your domain to Cloudflare:**
   - Add your domain
   - Cloudflare will scan your DNS records
   - Update nameservers in your domain registrar to Cloudflare's nameservers

3. **Enable SSL/TLS:**
   - Go to SSL/TLS settings
   - Select "Flexible" mode (encrypts connection between user and Cloudflare)
   - Or "Full" mode if you set up SSL on your server

4. **Benefits:**
   - ✅ Free SSL certificate
   - ✅ DDoS protection
   - ✅ CDN (faster loading)
   - ✅ Free DNS management

### Option 2: Self-Signed Certificate (Testing Only)

**Warning:** Self-signed certificates show security warnings to users. Use only for testing.

1. **Generate self-signed certificate:**
   ```cmd
   # Install OpenSSL (download from openssl.org)
   openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout lilac.key -out lilac.crt
   ```

2. **Configure Apache for HTTPS:**
   - Enable SSL module in Apache
   - Configure virtual host for port 443

---

## ⚙️ Application Configuration Updates

### Step 1: Update API Configuration (if needed)

Your application uses relative paths, which is good! However, check these files:

1. **Check `api/config.php`:**
   - Database host should still be `localhost` (not the domain)
   - No changes needed here

### Step 2: Update Any Hardcoded URLs

1. **Search for localhost references:**
   - Most should be relative paths (already good)
   - Some JavaScript files may have localhost checks - these are fine

2. **Email configuration (if using):**
   - Update email sender domain
   - Configure SMTP settings if sending emails

### Step 3: Update Session Configuration

PHP sessions should work automatically, but ensure:

1. **Session domain (if needed):**
   - Edit `php.ini` or add to `.htaccess`:
     ```apache
     php_value session.cookie_domain ".yourdomain.com"
     ```
   - This allows sessions to work across subdomains

---

## 🧪 Testing Your Domain

### Step 1: Test Basic Access

1. **Test domain resolution:**
   ```cmd
   ping yourdomain.com
   ```
   - Should return your server's IP

2. **Test HTTP access:**
   - Open browser: `http://yourdomain.com`
   - Should load LILAC login page

3. **Test HTTPS (if configured):**
   - Open browser: `https://yourdomain.com`
   - Should load with SSL certificate

### Step 2: Test Application Functions

1. **Login:**
   - [ ] Can login with admin credentials
   - [ ] Dashboard loads correctly

2. **File uploads:**
   - [ ] Can upload files
   - [ ] Uploads directory accessible

3. **API endpoints:**
   - [ ] All API calls work
   - [ ] No CORS errors
   - [ ] Data loads correctly

4. **OCR processing:**
   - [ ] Document upload works
   - [ ] OCR analysis completes

### Step 3: Test from Different Locations

- Test from different computers
- Test from mobile devices
- Test from different networks

---

## 🛠️ Troubleshooting

### Problem: Domain doesn't resolve

**Symptoms:**
- Browser shows "This site can't be reached"
- Ping fails

**Solutions:**
1. Check DNS records are correct
2. Wait for DNS propagation (up to 48 hours)
3. Verify IP address is correct
4. Check domain registrar DNS settings

### Problem: Domain resolves but shows default page

**Symptoms:**
- Domain works but shows XAMPP default page or 404

**Solutions:**
1. Check virtual host configuration is correct
2. Verify DocumentRoot path is correct
3. Ensure virtual host is enabled in `httpd.conf`
4. Restart Apache after changes

### Problem: SSL certificate errors

**Symptoms:**
- Browser shows security warning
- Certificate not trusted

**Solutions:**
1. If using self-signed: Normal for testing
2. Use Cloudflare for free trusted SSL
3. Check certificate expiration date
4. Verify certificate matches domain name

### Problem: Internal API calls fail

**Symptoms:**
- Dashboard loads but data doesn't appear
- API endpoints return errors

**Solutions:**
1. Check API paths are relative (not absolute)
2. Verify `.htaccess` RewriteBase is correct
3. Check Apache mod_rewrite is enabled
4. Review browser console for errors

### Problem: File uploads fail

**Symptoms:**
- Cannot upload files
- Upload errors

**Solutions:**
1. Check `uploads/` folder permissions
2. Verify PHP upload settings in `php.ini`
3. Check file size limits
4. Review Apache error logs

---

## 📝 Quick Setup Checklist

Use this checklist when setting up your domain:

- [ ] Domain registered/obtained (DuckDNS, Freenom, etc.)
- [ ] Server IP address identified
- [ ] DNS records configured (A record pointing to IP)
- [ ] DNS propagation verified (ping works)
- [ ] Apache virtual host created
- [ ] Virtual host enabled in `httpd.conf`
- [ ] `.htaccess` RewriteBase updated
- [ ] Apache restarted
- [ ] Domain accessible in browser
- [ ] Application loads correctly
- [ ] Login works
- [ ] File uploads work
- [ ] SSL/HTTPS configured (if needed)
- [ ] Tested from multiple locations
- [ ] Firewall rules configured
- [ ] Backup procedures documented

---

## 🌐 Recommended Setup for LILAC

### For Testing/Development:
- **DuckDNS** (free subdomain)
- No SSL required (HTTP only)
- Simple and quick setup

### For Production:
- **Domain with hosting** (first year free with hosting plans)
- **Cloudflare** (free SSL + protection)
- **HTTPS enabled**
- Regular backups

### For Institutional Use:
- **Purchase .com/.org domain** (~$10-15/year)
- **Cloudflare** for SSL and performance
- **Professional email** (optional)
- **Regular security updates**

---

## 📞 Next Steps After Domain Setup

1. **Update documentation:**
   - Update user guides with new domain
   - Update email signatures
   - Update any printed materials

2. **Configure backups:**
   - Set up automated backups
   - Test restore procedures

3. **Monitor performance:**
   - Set up uptime monitoring
   - Monitor error logs
   - Track usage statistics

4. **Security hardening:**
   - Change default passwords
   - Enable security headers
   - Regular security audits

---

## 🔗 Useful Links

- **DuckDNS:** https://www.duckdns.org
- **No-IP:** https://www.noip.com
- **Freenom:** https://www.freenom.com
- **Cloudflare:** https://www.cloudflare.com
- **Let's Encrypt:** https://letsencrypt.org
- **DNS Propagation Checker:** https://www.whatsmydns.net
- **IP Address Checker:** https://whatismyipaddress.com

---

**Last Updated: January 2025**

*This guide is part of the LILAC Awards System documentation.*

