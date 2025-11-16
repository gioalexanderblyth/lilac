# Quick Start: Getting a Free Domain for LILAC

This is a simplified guide to quickly get your LILAC system running on a free domain.

---

## 🚀 Fastest Option: DuckDNS (Recommended)

### Why DuckDNS?
- ✅ **Completely free** - No credit card needed
- ✅ **Super easy setup** - 5 minutes
- ✅ **Works with dynamic IP** - Updates automatically
- ✅ **Perfect for testing** - Great for development/demo

### Step-by-Step Setup:

#### 1. Get Your Domain (2 minutes)
1. Visit: **https://www.duckdns.org**
2. Click **"Sign in with Google"** or **"Sign in with Twitter"** (no email/password needed!)
3. Sign in with your Google/Twitter account
4. In the dashboard, type your desired subdomain (e.g., `lilac-awards`)
5. Click **"Add domain"**
6. ✅ You now have: `lilac-awards.duckdns.org` (or whatever you named it)

#### 2. Get Your IP Address (1 minute)
1. Visit: **https://whatismyipaddress.com**
2. Copy your **IPv4 address** (looks like: `123.45.67.89`)

#### 3. Update DNS in DuckDNS (1 minute)
1. Go back to DuckDNS dashboard
2. Under your domain, you'll see your token (looks like: `abc12345-6789-...`)
3. The IP address should auto-detect, but you can manually enter it
4. Click **"Save"** or it saves automatically
5. ✅ DNS is configured! (DuckDNS handles everything)

#### 4. Configure Apache (5 minutes)

**Option A: Use our setup script (Easiest)**
```powershell
# Open PowerShell as Administrator
cd C:\xampp\htdocs\lilac\scripts
.\setup-domain.ps1 -DomainName "lilac-awards.duckdns.org"
```

**Option B: Manual setup**
1. Edit: `C:\xampp\apache\conf\extra\lilac-domain.conf` (create if doesn't exist)
2. Add this content:
```apache
<VirtualHost *:80>
    ServerName lilac-awards.duckdns.org
    DocumentRoot "C:/xampp/htdocs/lilac"
    
    <Directory "C:/xampp/htdocs/lilac">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Edit: `C:\xampp\apache\conf\httpd.conf`
4. Add at the end:
```apache
Include conf/extra/lilac-domain.conf
```

5. Restart Apache in XAMPP Control Panel

#### 5. Update .htaccess (1 minute)
1. Edit: `C:\xampp\htdocs\lilac\.htaccess`
2. Find: `RewriteBase /lilac/`
3. Change to: `RewriteBase /`
4. Save

#### 6. Test Your Domain!
1. Wait 5-10 minutes for DNS to update
2. Open browser: `http://lilac-awards.duckdns.org`
3. ✅ Your LILAC system should load!

---

## 🎯 Alternative Options

### Option 2: No-IP (Similar to DuckDNS)

1. Visit: **https://www.noip.com**
2. Sign up for free account
3. Create hostname (e.g., `lilac-awards.ddns.net`)
4. Download **Dynamic Update Client** (updates IP automatically)
5. Configure Apache the same way

### Option 3: Free Subdomain from GoogieHost

1. Visit: **https://googiehost.com/freedomains**
2. Choose free extension: `.cu.ma`, `.thats.im`, etc.
3. Sign up and get your subdomain
4. Configure DNS A record pointing to your IP
5. Configure Apache

---

## 🔧 Troubleshooting

### "Site can't be reached"
- **Wait longer** - DNS can take 5-30 minutes to propagate
- **Check DNS** - Visit https://www.whatsmydns.net and search your domain
- **Verify IP** - Make sure DuckDNS has correct IP address

### "XAMPP default page shows instead"
- **Check virtual host** - Make sure it's configured correctly
- **Restart Apache** - After making changes
- **Check httpd.conf** - Ensure Include line is added

### "404 Not Found"
- **Check .htaccess** - RewriteBase should be `/`
- **Check DocumentRoot** - Should point to your lilac folder
- **Check file permissions** - Ensure files are readable

---

## 📝 Checklist

Quick checklist to ensure everything works:

- [ ] Domain registered at DuckDNS
- [ ] IP address added to DuckDNS
- [ ] Apache virtual host created
- [ ] Virtual host included in httpd.conf
- [ ] .htaccess RewriteBase updated
- [ ] Apache restarted
- [ ] Firewall allows port 80
- [ ] Tested: http://yourdomain.duckdns.org

---

## 🔒 Want HTTPS? (Optional)

For production, you should use HTTPS. Easiest way:

1. **Sign up for Cloudflare (free):**
   - Visit: https://www.cloudflare.com
   - Add your domain
   - Follow their setup guide
   - Enable SSL/TLS (automatic)
   - ✅ Your site now has free SSL!

---

## 💡 Pro Tips

1. **Bookmark your domain** - Easier to access later
2. **Share with colleagues** - They can access from anywhere
3. **Use Cloudflare** - Free SSL + faster loading
4. **Set up dynamic DNS** - If your IP changes, DuckDNS updates automatically

---

## 🆘 Still Need Help?

See the full guide: `docs/DOMAIN_SETUP_GUIDE.md`

Or check:
- DuckDNS Help: https://www.duckdns.org/faq.jsp
- Apache Documentation: https://httpd.apache.org/docs/

---

**That's it! You now have your LILAC system on a free domain! 🎉**

