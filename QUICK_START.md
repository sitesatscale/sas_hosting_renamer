# SSO Quick Start Guide

## ✅ Setup Complete!

Your WordPress plugin is now configured with SSO token-based authentication.

## Configuration

- **Web App:** https://annotation.sitesatscale.com
- **Local WordPress:** http://wp.local (or your Local by Flywheel URL)
- **Environment Detection:** Automatic (local/production)

## What's Been Configured

### 1. API Endpoints ✓
- `/wp-json/sas-hosting/v1/auth/sso-login/` - Token login
- `/wp-json/sas-hosting/v1/auth/validate-token/` - Token validation

### 2. Features ✓
- Token-based authentication
- Automatic environment detection
- Rate limiting (10 login, 20 validation per minute)
- Security logging
- Debug mode for local development
- Beautiful success/error pages

### 3. Files Created ✓
```
includes/
├── api/endpoints.php       # API endpoints (updated)
├── sso-handler.php         # URL handler
└── sso-config.php          # Configuration (NEW)
```

## Local Testing - 3 Options

### Option 1: Use Production (Easiest)
Plugin already configured to use https://annotation.sitesatscale.com
- Just implement Laravel endpoints
- Test directly

### Option 2: Use Local Mock Server
```bash
# 1. Create test-sso-mock.php in your WordPress root
# 2. Copy content from LOCAL_TESTING_GUIDE.md
# 3. Edit includes/sso-config.php line 67:
return get_site_url() . '/test-sso-mock.php';

# 4. Test with:
http://wp.local/?sas_sso_token=test_admin_token
```

### Option 3: Use Local Laravel
```bash
# 1. Run Laravel locally: php artisan serve
# 2. Edit includes/sso-config.php line 64:
return 'http://localhost:8000';

# 3. Implement Laravel endpoints
```

## Quick Test

### Test 1: Check Endpoints
```bash
# Visit in browser:
http://wp.local/wp-json/sas-hosting/v1/
```

### Test 2: Create Mock (Optional)
See `LOCAL_TESTING_GUIDE.md` for mock server setup

### Test 3: Try SSO URL
```
http://wp.local/?sas_sso_token=YOUR_TOKEN_HERE
```

## Configuration Options

### Enable Debug Logging
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs: `wp-content/debug.log`

### Override Provider URL
Add to `wp-config.php`:
```php
define('SAS_SSO_PROVIDER_URL', 'https://your-custom-url.com');
```

### Check Configuration
In WordPress admin, you'll see a notice showing:
- Environment (local/production)
- Provider URL being used

## Laravel Implementation Required

Your Laravel app needs these endpoints:

### 1. Validate Token (Required)
```
POST https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token
```
Returns user data if token valid

### 2. Log Login (Optional)
```
POST https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-login
```
Tracks successful logins

### 3. Log Logout (Optional)
```
POST https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-logout
```
Tracks logout events

See `SSO_IMPLEMENTATION.md` for full Laravel examples.

## Test Flow

1. **Generate Token** (in Laravel)
   ```php
   $token = Str::random(64);
   // Store in database with user data
   ```

2. **Create SSO URL**
   ```php
   $url = "http://wp.local/?sas_sso_token={$token}";
   ```

3. **Redirect User**
   ```php
   return redirect($url);
   ```

4. **WordPress validates and logs in**
   - Calls your Laravel validation endpoint
   - Logs user in automatically
   - Redirects to WordPress admin

## Environment Behavior

### Local Environment (Auto-detected)
- Debug logging enabled
- SSL verification disabled
- Admin notice shown
- Detected by: localhost, .local, 127.0.0.1, etc.

### Production Environment
- Debug logging disabled
- SSL verification enabled
- No admin notices

## Check What's Working

### 1. Plugin Activated?
```bash
# In WordPress admin: Plugins > SAS Hosting
# Should be activated and NOT visible in list (hidden from non-admin users)
```

### 2. Endpoints Available?
```bash
curl http://wp.local/wp-json/sas-hosting/v1/
```

### 3. Configuration Loaded?
```bash
# Should see admin notice in WordPress dashboard if local environment
```

### 4. Logs Being Created?
```sql
SELECT * FROM wp_sas_sso_logs ORDER BY created_at DESC LIMIT 5;
```

## Customization

### Change Provider URL
Edit `includes/sso-config.php` line 59-73

### Change Rate Limits
Edit `includes/sso-config.php` line 46-47

### Change Token Lifetime
Edit `includes/sso-config.php` line 45

### Disable Auto-Create Users
Edit `includes/sso-config.php` line 48

## Troubleshooting

### Issue: Endpoints not found
**Fix:** Flush permalinks
- Go to Settings > Permalinks
- Click "Save Changes"

### Issue: Can't connect to provider
**Fix:** Check Laravel app is accessible
```bash
curl -X POST https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token
```

### Issue: Local environment not detected
**Fix:** Add to wp-config.php:
```php
define('WP_DEBUG', true);
```

### Issue: SSL errors in local
**Fix:** Already handled! SSL verification disabled in local mode

## Documentation Files

- `SSO_IMPLEMENTATION.md` - Complete documentation
- `LOCAL_TESTING_GUIDE.md` - Local testing guide
- `QUICK_START.md` - This file

## Next Steps

1. ✅ Plugin configured
2. ⏭️ Implement Laravel validation endpoint
3. ⏭️ Generate test token in Laravel
4. ⏭️ Test SSO flow
5. ⏭️ Deploy to production

## Support

Check logs in:
- `wp-content/debug.log` (WordPress)
- Database: `wp_sas_sso_logs` table
- Laravel logs

## Security Notes

- Tokens expire after 5 minutes
- Rate limited (prevents brute force)
- All attempts logged
- SSL required in production
- Tokens should be one-time use

---

**Ready to test!** 🚀

Your WordPress plugin is fully configured. Just implement the Laravel endpoints and start testing.
