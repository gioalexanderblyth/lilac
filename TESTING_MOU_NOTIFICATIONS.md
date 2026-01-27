# Testing Guide: MOU/MOA Expiration Notifications

## Overview
The system automatically checks for expired and expiring MOU/MOA entries and creates notifications. This guide explains how to test this functionality.

## How It Works

1. **Notification Check**: The system checks for:
   - **Expired MOU/MOA**: `end_date < today`
   - **Expiring MOU/MOA**: `end_date` is within the notification period (default: 6 months/180 days)

2. **Notification Period**: Default is 6 months (180 days) before expiration. Users can customize this in their profile settings.

3. **Duplicate Prevention**: 
   - Expired notifications: Won't create duplicate within 30 days
   - Expiring notifications: Won't create duplicate within 7 days

## Testing Methods

### Method 1: Manual API Call (Recommended for Testing)

1. **Open your browser** and navigate to:
   ```
   http://localhost/lilac/api/notifications.php?action=check
   ```
   (Replace `localhost/lilac` with your actual domain)

2. **You should see a JSON response** like:
   ```json
   {
     "success": true,
     "mou_notifications_created": 5,
     "event_notifications_created": 0,
     "total_created": 5
   }
   ```

3. **Check the notification count** - This tells you how many new notifications were created.

### Method 2: Check Database Directly

1. **Open phpMyAdmin** or your database tool
2. **Run this SQL query** to see all MOU/MOA notifications:
   ```sql
   SELECT * FROM notifications 
   WHERE related_type = 'mou_moa' 
   ORDER BY created_at DESC;
   ```

3. **Check for expired MOU/MOA entries**:
   ```sql
   SELECT id, title, institution, partner, end_date, status 
   FROM mou_moa 
   WHERE end_date IS NOT NULL 
   AND deleted_at IS NULL
   AND end_date < CURDATE()
   ORDER BY end_date DESC;
   ```

4. **Check for expiring MOU/MOA entries** (within 180 days):
   ```sql
   SELECT id, title, institution, partner, end_date, status,
          DATEDIFF(end_date, CURDATE()) as days_until_expiry
   FROM mou_moa 
   WHERE end_date IS NOT NULL 
   AND deleted_at IS NULL
   AND end_date >= CURDATE()
   AND end_date <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)
   ORDER BY end_date ASC;
   ```

### Method 3: Test with Test Data

1. **Create a test MOU/MOA entry** with:
   - `end_date` = Yesterday's date (to test expired notifications)
   - `end_date` = Today's date (to test expired notifications)
   - `end_date` = 30 days from now (to test expiring notifications)
   - `end_date` = 179 days from now (to test expiring notifications - within 180 day period)

2. **Run the notification check** via API:
   ```
   http://localhost/lilac/api/notifications.php?action=check
   ```

3. **Verify notifications appear** in the UI:
   - Click the notification bell icon
   - Check if notifications appear in the dropdown
   - Verify the "Renew" and "Renewed" buttons work

### Method 4: Check Notification Period Settings

1. **Check user notification period**:
   ```sql
   SELECT u.id, u.username, up.preference_value as notification_period_days
   FROM users u
   LEFT JOIN user_preferences up ON u.id = up.user_id 
     AND up.preference_key = 'mou_notification_period_days'
   ORDER BY u.id;
   ```

2. **Check minimum notification period** (system uses the shortest period set by any user):
   ```sql
   SELECT MIN(CAST(preference_value AS UNSIGNED)) as min_period_days
   FROM user_preferences 
   WHERE preference_key = 'mou_notification_period_days';
   ```

### Method 5: Automated Check (System Behavior)

The system automatically checks for notifications when:
- User opens the notification dropdown
- Page loads (if configured)
- Scheduled via cron job (if set up)

**Check the browser console** (F12) for:
- `Notifications checked: {success: true, ...}`
- Any errors in the notification system

## Verification Steps

### Step 1: Verify Notifications Are Created
1. Run the API check: `api/notifications.php?action=check`
2. Check the response for `mou_notifications_created` count
3. Verify in database: `SELECT COUNT(*) FROM notifications WHERE related_type = 'mou_moa'`

### Step 2: Verify Notifications Appear in UI
1. Click the notification bell icon (top right)
2. Check if expired/expiring MOU/MOA notifications appear
3. Verify notification text shows correct institution/partner name
4. Verify notification shows correct expiration date

### Step 3: Verify Notification Types
- **Expired**: Should show "MOU/MOA Expired: [Name]" with red icon
- **Expiring**: Should show "MOU/MOA Expiring Soon: [Name]" with yellow icon

### Step 4: Test Renew/Renewed Buttons
1. Click "Renew" button - should open MOU/MOA details modal
2. Click "Renewed" button - should mark as renewed and remove notification
3. Verify notification disappears from list after clicking "Renewed"

### Step 5: Test Notification Period Customization
1. Go to Profile page
2. Set notification period to 1 month (30 days)
3. Create MOU/MOA with `end_date` = 31 days from now
4. Run notification check - should NOT create notification (outside period)
5. Change MOU/MOA `end_date` to 29 days from now
6. Run notification check - SHOULD create notification (within period)

## Common Issues & Solutions

### Issue: No notifications created
**Solutions:**
- Check if MOU/MOA entries have `end_date` set
- Check if `deleted_at` is NULL (not soft-deleted)
- Check if notification period covers the expiration date
- Check if duplicate notification already exists (within 30/7 day window)

### Issue: Notifications not appearing in UI
**Solutions:**
- Check browser console for errors
- Verify notifications exist in database with `user_id = NULL`
- Check if notifications are marked as read
- Verify notification API returns data: `api/notifications.php`

### Issue: Wrong notification period
**Solutions:**
- Check user preferences in database
- Verify minimum notification period calculation
- Check if custom period is set in profile

## SQL Queries for Testing

### Find all expired MOU/MOA
```sql
SELECT id, title, institution, partner, end_date, 
       DATEDIFF(CURDATE(), end_date) as days_expired
FROM mou_moa 
WHERE end_date < CURDATE() 
AND deleted_at IS NULL
ORDER BY end_date DESC;
```

### Find all expiring MOU/MOA (within notification period)
```sql
SELECT id, title, institution, partner, end_date,
       DATEDIFF(end_date, CURDATE()) as days_until_expiry
FROM mou_moa 
WHERE end_date >= CURDATE()
AND end_date <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)
AND deleted_at IS NULL
ORDER BY end_date ASC;
```

### Check recent notifications
```sql
SELECT n.*, m.title, m.institution, m.end_date
FROM notifications n
LEFT JOIN mou_moa m ON n.related_id = m.id
WHERE n.related_type = 'mou_moa'
ORDER BY n.created_at DESC
LIMIT 20;
```

### Count notifications by type
```sql
SELECT type, COUNT(*) as count
FROM notifications
WHERE related_type = 'mou_moa'
GROUP BY type;
```

## Quick Test Checklist

- [ ] Run API check: `api/notifications.php?action=check`
- [ ] Verify response shows notifications created
- [ ] Check database for new notification records
- [ ] Open notification dropdown in UI
- [ ] Verify notifications appear with correct text
- [ ] Test "Renew" button opens modal
- [ ] Test "Renewed" button removes notification
- [ ] Verify notification badge count updates
- [ ] Test with different expiration dates
- [ ] Test notification period customization

