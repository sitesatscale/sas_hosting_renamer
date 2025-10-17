# Local Testing Guide for localhost:10004

## ✅ Setup Complete!

Your WordPress SSO plugin is now configured for local testing at `http://localhost:10004/`

## 🎯 What's Configured

1. **Mock SSO Server** - Created at `/test-sso-mock.php`
2. **Auto-Detection** - Plugin automatically uses mock server for localhost
3. **Test Tokens** - Pre-configured tokens ready to use
4. **Debug Mode** - Enabled for local environment

## 🚀 Quick Test (Right Now!)

### Test 1: Admin Login
Open your browser and visit:
```
http://localhost:10004/?sas_sso_token=test_admin_token
```

**Expected Result:**
- See success page "Welcome back, test_admin!"
- Auto-redirect to WordPress admin
- User logged in as administrator

### Test 2: Editor Login
```
http://localhost:10004/?sas_sso_token=test_editor_token
```

### Test 3: With Custom Redirect
```
http://localhost:10004/?sas_sso_token=test_admin_token&redirect_to=/wp-admin/post-new.php
```
Logs in and redirects to "New Post" page

### Test 4: Invalid Token (Error Test)
```
http://localhost:10004/?sas_sso_token=invalid_token
```
Shows error page

### Test 5: Expired Token
```
http://localhost:10004/?sas_sso_token=expired_token
```
Shows expired token error

## 📋 Available Test Tokens

| Token | User Type | Email | Role |
|-------|-----------|-------|------|
| `test_admin_token` | New Admin | admin@test.com | Administrator |
| `test_editor_token` | New Editor | editor@test.com | Editor |
| `test_subscriber_token` | New Subscriber | subscriber@test.com | Subscriber |
| `existing_user_token` | Existing User | admin@example.com | Administrator |
| `expired_token` | Error Test | - | - |
| `invalid_token` | Error Test | - | - |

## 🔍 How to Test

### Method 1: Browser (Easiest)

Just click or visit these URLs:

1. **Test Admin Login:**
   ```
   http://localhost:10004/?sas_sso_token=test_admin_token
   ```

2. **Test with Redirect:**
   ```
   http://localhost:10004/?sas_sso_token=test_admin_token&redirect_to=/wp-admin/
   ```

3. **Test Error Handling:**
   ```
   http://localhost:10004/?sas_sso_token=invalid_token
   ```

### Method 2: REST API (Direct)

Test the API endpoint directly:

```bash
curl -X POST http://localhost:10004/wp-json/sas-hosting/v1/auth/sso-login/ \
  -H "Content-Type: application/json" \
  -d '{"token":"test_admin_token"}'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Authentication successful.",
  "user_id": 123,
  "username": "test_admin",
  "email": "admin@test.com",
  "redirect_url": "http://localhost:10004/wp-admin/"
}
```

### Method 3: Check Mock Server

Visit the mock server directly to see available tokens:
```
http://localhost:10004/test-sso-mock.php
```

## 📊 Verify It's Working

### 1. Check WordPress Admin Notice

When you log into WordPress admin, you should see:
```
SAS SSO: Running in LOCAL mode.
Provider URL: http://localhost:10004/test-sso-mock.php
```

### 2. Check Debug Logs

Enable debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

### 3. Check SSO Logs Table

In your database:
```sql
SELECT * FROM wp_sas_sso_logs
ORDER BY created_at DESC
LIMIT 10;
```

### 4. Check Mock Server Logs

View request logs:
```
wp-content/plugins/sas_hosting_renamer/sso-mock-log.txt
```

Or in WordPress root:
```
sso-mock-log.txt
```

## 🎨 What You'll See

### Success Flow:
1. Click SSO URL with token
2. Plugin intercepts request
3. Validates token with mock server
4. Creates/logs in user
5. Shows beautiful success page with animation
6. Auto-redirects after 2 seconds

### Error Flow:
1. Click SSO URL with invalid token
2. Plugin intercepts request
3. Mock server returns error
4. Shows professional error page
5. Options to login manually or go home

## 🧪 Testing Scenarios

### Scenario 1: New User Creation
```
URL: http://localhost:10004/?sas_sso_token=test_admin_token
Expected: Creates new user "test_admin" and logs in
```

### Scenario 2: Existing User Login
First, manually create a WordPress user with email: `admin@example.com`

Then visit:
```
URL: http://localhost:10004/?sas_sso_token=existing_user_token
Expected: Logs in existing user (no new user created)
```

### Scenario 3: Custom Redirect
```
URL: http://localhost:10004/?sas_sso_token=test_editor_token&redirect_to=/wp-admin/edit.php
Expected: Logs in and redirects to Posts page
```

### Scenario 4: Force Re-login
```
URL: http://localhost:10004/?sas_sso_token=test_admin_token&force_login=1
Expected: Re-logs in even if already authenticated
```

### Scenario 5: Rate Limiting
Visit the SSO URL 11 times quickly:
```
Expected: 11th request shows "Too many login attempts"
```

## 🔧 Troubleshooting

### Issue: "No route was found"

**Fix:** Flush permalinks
1. Go to `http://localhost:10004/wp-admin/options-permalink.php`
2. Click "Save Changes"
3. Try again

### Issue: "Could not connect to authentication provider"

**Fix:** Check mock file exists
```bash
ls C:\Users\kiere\Local Sites\wp\app\public\test-sso-mock.php
```

Should exist and be accessible at:
```
http://localhost:10004/test-sso-mock.php
```

### Issue: No admin notice showing

**Fix:** Make sure you're logged in as admin and on admin dashboard

### Issue: Users being created but login fails

**Fix:** Check user table:
```sql
SELECT * FROM wp_users WHERE user_email LIKE '%test%';
```

Delete test users if needed:
```sql
DELETE FROM wp_users WHERE user_login LIKE 'test_%';
```

## 📱 Create Your Own Test Token

Edit `test-sso-mock.php` and add new token:

```php
'my_custom_token' => [
    'valid' => true,
    'email' => 'myemail@example.com',
    'user_id' => 9999,
    'username' => 'my_username',
    'role' => 'administrator',
    'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
],
```

Then test:
```
http://localhost:10004/?sas_sso_token=my_custom_token
```

## 🎯 Test Checklist

- [ ] Visit mock server URL - shows available tokens
- [ ] Test admin login - creates user and logs in
- [ ] Test editor login - creates editor user
- [ ] Test subscriber login - creates subscriber
- [ ] Test invalid token - shows error page
- [ ] Test expired token - shows error message
- [ ] Test with redirect parameter - redirects correctly
- [ ] Check SSO logs in database - records created
- [ ] Check debug log - SSO messages logged
- [ ] Check admin notice - shows local mode
- [ ] Test rate limiting - blocks after 10 attempts
- [ ] Logout and login again - works multiple times

## 📈 Next Steps

After local testing works:

1. **Switch to Production:**
   Edit `includes/sso-config.php` line 100-107
   ```php
   // Change from:
   return get_site_url() . '/test-sso-mock.php';

   // To:
   return 'https://annotation.sitesatscale.com';
   ```

2. **Implement Laravel Endpoints:**
   See `SSO_IMPLEMENTATION.md` for examples

3. **Test with Real Laravel:**
   Generate real tokens from Laravel app

4. **Deploy:**
   Plugin auto-detects production environment

## 🔐 Security Notes

- Mock server is **ONLY for local testing**
- All tokens in mock are fake
- Delete `test-sso-mock.php` before production
- Production uses real Laravel API at annotation.sitesatscale.com

## 📞 Quick Reference

**Your Local WordPress:**
```
http://localhost:10004/
```

**Test Admin Login:**
```
http://localhost:10004/?sas_sso_token=test_admin_token
```

**Mock Server Info:**
```
http://localhost:10004/test-sso-mock.php
```

**REST API Endpoint:**
```
http://localhost:10004/wp-json/sas-hosting/v1/auth/sso-login/
```

**Debug Log:**
```
C:\Users\kiere\Local Sites\wp\app\public\wp-content\debug.log
```

**SSO Logs Table:**
```sql
SELECT * FROM wp_sas_sso_logs ORDER BY created_at DESC LIMIT 10;
```

---

**Ready to test!** 🎉

Just open your browser and visit:
```
http://localhost:10004/?sas_sso_token=test_admin_token
```

You should see the SSO login working immediately!
