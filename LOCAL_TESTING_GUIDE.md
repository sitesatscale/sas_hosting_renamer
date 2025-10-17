# Local Testing Guide for SSO

## Your Configuration

- **Web App URL:** https://annotation.sitesatscale.com
- **Local WordPress URL:** http://wp.local (or your Local by Flywheel URL)

## Quick Start Testing

### Step 1: Get Your Local WordPress URL

Your current WordPress site is accessible at:
```
C:\Users\kiere\Local Sites\wp\app\public
```

Check your Local by Flywheel for the exact URL (likely `http://wp.local`)

### Step 2: Test the REST API Endpoints

Open your browser or use curl to test:

#### Test 1: Check if SSO endpoint is available
```
http://wp.local/wp-json/sas-hosting/v1/auth/sso-login/
```

You should see: `{"code":"rest_no_route","message":"No route was found matching the URL and request method"...}`

This is normal for GET request. The endpoint needs POST.

#### Test 2: Check if validate endpoint is available
```
http://wp.local/wp-json/sas-hosting/v1/auth/validate-token/
```

### Step 3: Create a Test Token Manually

For local testing without Laravel implementation, create a simple test:

#### Option A: Use Postman or Insomnia

**POST Request to validate-token:**
```
URL: http://wp.local/wp-json/sas-hosting/v1/auth/validate-token/
Method: POST
Headers: Content-Type: application/json
Body:
{
  "token": "test_token_123"
}
```

**Expected Response:**
```json
{
  "code": "provider_connection_failed",
  "message": "Could not connect to authentication provider.",
  "data": {
    "status": 503
  }
}
```

This is expected because your Laravel app isn't set up yet.

#### Option B: Create a Local Mock Server

Create a file: `C:\Users\kiere\Local Sites\wp\app\public\test-sso-mock.php`

```php
<?php
/**
 * Mock SSO Token Validator for Local Testing
 * This simulates your Laravel web app responses
 */

header('Content-Type: application/json');

// Get the request
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Check what endpoint is being called
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Mock: Validate SSO Token
if (strpos($requestUri, 'validate-sso-token') !== false) {
    $token = $data['token'] ?? '';

    // Test tokens (use these for testing)
    $validTokens = [
        'test_admin_token' => [
            'valid' => true,
            'email' => 'admin@test.com',
            'user_id' => 1,
            'username' => 'test_admin',
            'role' => 'administrator',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
        ],
        'test_editor_token' => [
            'valid' => true,
            'email' => 'editor@test.com',
            'user_id' => 2,
            'username' => 'test_editor',
            'role' => 'editor',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
        ],
        'expired_token' => [
            'valid' => false,
            'message' => 'Token is invalid or expired'
        ]
    ];

    if (isset($validTokens[$token])) {
        http_response_code(200);
        echo json_encode($validTokens[$token]);
    } else {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'message' => 'Token is invalid or expired'
        ]);
    }
    exit;
}

// Mock: Log SSO Login
if (strpos($requestUri, 'log-sso-login') !== false) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Login logged']);
    exit;
}

// Mock: Log SSO Logout
if (strpos($requestUri, 'log-sso-logout') !== false) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logout logged']);
    exit;
}

// Mock: Validate Token (for admin user creation)
if (strpos($requestUri, 'validate-token') !== false) {
    $token = $data['token'] ?? '';

    if ($token === 'create_user_token') {
        http_response_code(200);
        echo json_encode(['valid' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['valid' => false]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
```

### Step 4: Temporarily Point to Local Mock

For local testing only, temporarily modify the API URL:

**File:** `includes/api/endpoints.php`

Change line 297 from:
```php
$validation_url = 'https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token';
```

To:
```php
// FOR LOCAL TESTING ONLY - REVERT BEFORE PRODUCTION
$validation_url = 'http://wp.local/test-sso-mock.php?endpoint=validate-sso-token';
```

**IMPORTANT:** Remember to change this back when deploying!

### Step 5: Test SSO Login with Mock Token

#### Test in Browser:

Visit this URL in your browser:
```
http://wp.local/?sas_sso_token=test_admin_token
```

You should see:
- Loading screen
- Success message "Welcome back, test_admin!"
- Auto-redirect to WordPress admin

#### Test with curl:

```bash
curl -X POST http://wp.local/wp-json/sas-hosting/v1/auth/sso-login/ \
  -H "Content-Type: application/json" \
  -d '{"token":"test_admin_token"}'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Authentication successful.",
  "user_id": 1,
  "username": "test_admin",
  "email": "admin@test.com",
  "redirect_url": "http://wp.local/wp-admin/"
}
```

### Step 6: Test Different Scenarios

#### Test 1: Valid Admin Token
```
URL: http://wp.local/?sas_sso_token=test_admin_token
Expected: Login as administrator
```

#### Test 2: Valid Editor Token
```
URL: http://wp.local/?sas_sso_token=test_editor_token
Expected: Login as editor
```

#### Test 3: Invalid Token
```
URL: http://wp.local/?sas_sso_token=invalid_token
Expected: Error page showing "Token is invalid or expired"
```

#### Test 4: Expired Token
```
URL: http://wp.local/?sas_sso_token=expired_token
Expected: Error page showing token expired message
```

#### Test 5: With Redirect
```
URL: http://wp.local/?sas_sso_token=test_admin_token&redirect_to=/wp-admin/post-new.php
Expected: Login and redirect to new post page
```

## Testing with Real Laravel App

Once your Laravel app is ready at https://annotation.sitesatscale.com:

### Step 1: Ensure Endpoints are Ready

Your Laravel app needs these routes:

```php
// routes/api.php or routes/web.php

Route::post('/api/wordpress/auth/validate-sso-token', [SsoController::class, 'validateToken']);
Route::post('/api/wordpress/auth/log-sso-login', [SsoController::class, 'logLogin']);
Route::post('/api/wordpress/auth/log-sso-logout', [SsoController::class, 'logLogout']);
```

### Step 2: Test Laravel Endpoints Directly

Test if your Laravel API is accessible:

```bash
# Test from your local machine
curl -X POST https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token \
  -H "Content-Type: application/json" \
  -d '{
    "token": "test_token",
    "domain": "wp.local",
    "timestamp": 1697385600,
    "ip": "127.0.0.1",
    "user_agent": "Test"
  }'
```

### Step 3: Generate Token in Laravel

In your Laravel web app, create a test page:

```php
// Example route for testing
Route::get('/test-sso', function() {
    // Generate a test token
    $token = \Str::random(64);

    // Store in database
    SsoToken::create([
        'user_id' => auth()->id(),
        'token' => $token,
        'wordpress_site' => 'wp.local', // Your local WordPress
        'data' => json_encode([
            'email' => auth()->user()->email,
            'username' => auth()->user()->username,
            'user_id' => auth()->id(),
            'role' => 'administrator'
        ]),
        'expires_at' => now()->addMinutes(5),
        'used' => false
    ]);

    // Generate SSO URL
    $ssoUrl = "http://wp.local/?sas_sso_token={$token}";

    // Show link or auto-redirect
    return view('test-sso', ['ssoUrl' => $ssoUrl]);
});
```

### Step 4: Update wp-config.php for HTTPS Compatibility

If testing with HTTPS Laravel app from HTTP local WordPress, add to `wp-config.php`:

```php
// Allow external HTTP requests for SSO testing
define('WP_HTTP_BLOCK_EXTERNAL', false);

// For development only - disable SSL verification
add_filter('https_ssl_verify', '__return_false');
add_filter('https_local_ssl_verify', '__return_false');
```

**IMPORTANT:** Remove these in production!

## Debugging

### Enable WordPress Debug Mode

Edit `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

### Check SSO Logs in Database

Connect to your local MySQL database:

```sql
SELECT * FROM wp_sas_sso_logs
ORDER BY created_at DESC
LIMIT 10;
```

### Common Issues and Solutions

#### Issue 1: "No route was found"
**Solution:** Make sure plugin is activated and REST API is working
```bash
# Test REST API
curl http://wp.local/wp-json/
```

#### Issue 2: "Could not connect to authentication provider"
**Solution:**
- Check if Laravel app is running
- Verify URL is correct
- Check firewall settings
- Try using mock server for local testing

#### Issue 3: "User not found"
**Solution:**
- User will be auto-created if username is provided in token
- Or manually create user with matching email first

#### Issue 4: CORS errors
**Solution:** Add to Laravel `cors.php`:
```php
'paths' => ['api/*'],
'allowed_origins' => ['*'], // or specific: ['http://wp.local']
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

## Testing Checklist

- [ ] WordPress plugin activated
- [ ] REST API endpoints accessible
- [ ] Mock server working (for local testing)
- [ ] Can generate test tokens
- [ ] SSO login works with test token
- [ ] Error pages display correctly
- [ ] Success page redirects properly
- [ ] User auto-creation working
- [ ] SSO logs being created
- [ ] Rate limiting working (try 11 requests quickly)
- [ ] Token expiration working
- [ ] Redirect parameter working

## Environment-Specific Configuration

### For Local Development
```php
// Use local mock or local Laravel
$is_local = (strpos(get_site_url(), 'localhost') !== false || strpos(get_site_url(), '.local') !== false);

if ($is_local) {
    $validation_url = 'http://localhost:8000/api/wordpress/auth/validate-sso-token';
} else {
    $validation_url = 'https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token';
}
```

### For Production
Always use HTTPS:
```php
$validation_url = 'https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token';
```

## Next Steps

1. **Create mock server** (`test-sso-mock.php`) for immediate local testing
2. **Test all scenarios** listed above
3. **Implement Laravel endpoints** based on documentation
4. **Switch from mock to real Laravel app**
5. **Test with real tokens** from Laravel
6. **Remove mock server** and debug code
7. **Deploy to staging/production**

## Quick Test URLs

Replace `wp.local` with your actual local URL:

- Test valid login: `http://wp.local/?sas_sso_token=test_admin_token`
- Test with redirect: `http://wp.local/?sas_sso_token=test_admin_token&redirect_to=/wp-admin/`
- Test invalid token: `http://wp.local/?sas_sso_token=invalid_token`
- REST API: `http://wp.local/wp-json/sas-hosting/v1/auth/sso-login/`

## Support Files Location

```
plugins/sas_hosting_renamer/
├── includes/
│   ├── api/
│   │   └── endpoints.php          # API endpoints
│   └── sso-handler.php            # URL-based SSO handler
├── SSO_IMPLEMENTATION.md          # Full documentation
├── LOCAL_TESTING_GUIDE.md         # This file
└── sas-hosting-renamer.php        # Main plugin file
```

---

**Your Configuration:**
- Web App: https://annotation.sitesatscale.com
- Local WP: Check Local by Flywheel (likely http://wp.local)
- Plugin Version: 6.0.3
