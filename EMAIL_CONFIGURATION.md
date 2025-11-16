# Email Configuration - Simple Guide

## 📋 Current Situation

Right now, emails are **logged to a file** instead of being sent. This is fine for testing!

**Where to find reset links**: Open `logs/emails.log` - all password reset links are saved there.

---

## 🚀 Quick Setup (Choose ONE method)

### Method 1: Test Without Email Setup (Easiest for now)

1. Request password reset from the login page
2. Open `logs/emails.log` file
3. Copy the reset link (looks like: `http://localhost/lilac/reset-password.php?token=...`)
4. Paste it in your browser to reset your password

**That's it!** You can test everything without configuring email.

---

### Method 2: Enable Real Email Sending (For Production)

#### Step 1: Open `api/config.php`

#### Step 2: Add these lines at the very end of the file:

```php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');        // Change this for your email provider
define('SMTP_PORT', 587);                      // Usually 587 or 465
define('SMTP_USERNAME', 'your-email@gmail.com');  // Your email address
define('SMTP_PASSWORD', 'your-password');         // Your password or app password
define('SMTP_FROM_EMAIL', 'noreply@cpu.edu.ph');  // Sender email
define('SMTP_FROM_NAME', 'LILAC System');         // Sender name
```

#### Step 3: Update the values above with your email settings

**Gmail Users:**
- SMTP_HOST: `smtp.gmail.com`
- SMTP_PORT: `587`
- SMTP_USERNAME: your Gmail address
- SMTP_PASSWORD: [Create an App Password](https://support.google.com/accounts/answer/185833) (not your regular password)

**Outlook/Office 365:**
- SMTP_HOST: `smtp-mail.outlook.com`
- SMTP_PORT: `587`
- Use your Outlook email and password

**Other Email Providers:**
- Contact your email provider for SMTP settings
- Common ports: 587 (TLS) or 465 (SSL)

#### Step 4: Save the file and test!

Try resetting your password again - you should receive an email.

---

## ❓ Troubleshooting

**Email still not received?**
1. Check `logs/emails.log` - the reset link is always there
2. Verify SMTP settings are correct
3. Check PHP error logs for connection errors
4. For Gmail: Make sure you're using an App Password (not regular password)

**"mail() function not available" warning?**
- This is normal on XAMPP/Windows - ignore it for now
- Email will still work if SMTP is configured correctly

---

## 📝 Summary

- ✅ **Testing now**: Use `logs/emails.log` to get reset links
- ✅ **Production**: Add SMTP settings to `api/config.php`
- ✅ **Always works**: All emails are logged to `logs/emails.log` even if sending fails

