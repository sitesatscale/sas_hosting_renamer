# SSO Token-Based Authentication Implementation Guide

## Overview

This plugin implements a secure token-based Single Sign-On (SSO) system that allows your Laravel web app (OAuth Provider) to authenticate users on WordPress sites (OAuth Clients) without handling WordPress passwords.

## Architecture

```
┌─────────────────────────────────┐
│   Annotation Web App            │
│   (OAuth Provider)              │
│   https://annotation.           │
│   sitesatscale.com              │
│   - Laravel                     │
│   - Token Generation            │
│   - User Management             │
└───────────┬─────────────────────┘
            │
            │ Token Authentication
            │
    ┌───────┴────────┬───────────────┬────────────┐
    │                │               │            │
    ▼                ▼               ▼            ▼
┌─────────┐    ┌─────────┐    ┌─────────┐  ┌─────────┐
│ WP Site │    │ WP Site │    │ WP Site │  │   ...   │
│ (Client)│    │ (Client)│    │ (Client)│  │         │
└─────────┘    └─────────┘    └─────────┘  └─────────┘
```

## How It Works

### 1. User Flow

1. User visits your web app (annotation.sitesatscale.com)
2. User clicks "One-Click Login" for a specific WordPress site
3. Web app generates a secure, time-limited token
4. User is redirected to WordPress site with token in URL
5. WordPress plugin validates token with web app
6. User is automatically logged into WordPress
7. User is redirected to their destination

### 2. Token Validation Flow

```
Web App (Provider)           WordPress Plugin (Client)
      │                              │
      │  1. Generate Token           │
      ├─────────────────────────────>│
      │  sas_sso_token=xyz...        │
      │                              │
      │  2. Validate Token Request   │
      │<─────────────────────────────┤
      │                              │
      │  3. Token Data Response      │
      ├─────────────────────────────>│
      │  {valid: true, email: ...}   │
      │                              │
      │  4. Notify Login Success     │
      │<─────────────────────────────┤
      │                              │
```

## API Endpoints

### WordPress Plugin Endpoints

#### 1. SSO Login Endpoint
**URL:** `/wp-json/sas-hosting/v1/auth/sso-login/`
**Method:** POST
**Purpose:** Authenticate user with SSO token

**Request Body:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "redirect_url": "https://example1.com/wp-admin/" // Optional
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Authentication successful.",
  "user_id": 123,
  "username": "john_doe",
  "email": "john@example.com",
  "redirect_url": "https://example1.com/wp-admin/",
  "auth_cookie": {
    "logged_in": true,
    "user_login": "john_doe"
  }
}
```

**Error Response (401):**
```json
{
  "code": "invalid_token",
  "message": "Authentication token is invalid or expired.",
  "data": {
    "status": 401
  }
}
```

#### 2. Token Validation Endpoint
**URL:** `/wp-json/sas-hosting/v1/auth/validate-token/`
**Method:** POST
**Purpose:** Check if token is valid (used by web app)

**Request Body:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "message": "Token is valid.",
  "user_email": "john@example.com",
  "expires_at": "2025-10-15 14:30:00"
}
```

### Web App Provider Endpoints (To Be Implemented)

#### 1. Validate SSO Token
**URL:** `https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token`
**Method:** POST
**Purpose:** Validate token and return user data

**Request Headers:**
```
Content-Type: application/json
X-WordPress-Site: https://example1.com
```

**Request Body:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "domain": "example1.com",
  "timestamp": 1697385600,
  "ip": "192.168.1.1",
  "user_agent": "Mozilla/5.0..."
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "email": "john@example.com",
  "user_id": 456,
  "username": "john_doe",
  "role": "administrator",
  "expires_at": "2025-10-15 14:30:00",
  "created_at": "2025-10-15 14:00:00"
}
```

**Error Response (401):**
```json
{
  "valid": false,
  "message": "Token is invalid or expired"
}
```

#### 2. Log SSO Login (Optional)
**URL:** `https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-login`
**Method:** POST
**Purpose:** Track successful logins

**Request Body:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "domain": "example1.com",
  "user_id": 123,
  "username": "john_doe",
  "email": "john@example.com",
  "timestamp": "2025-10-15 14:00:00"
}
```

#### 3. Log SSO Logout (Optional)
**URL:** `https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-logout`
**Method:** POST
**Purpose:** Track logout events

## Implementation Steps

### For WordPress Plugin (Already Implemented ✓)

The plugin includes:
- ✓ REST API endpoints for SSO authentication
- ✓ Token validation with web app
- ✓ Automatic user login
- ✓ Rate limiting (10 login attempts/min, 20 validation/min)
- ✓ Security logging (stores in `wp_sas_sso_logs` table)
- ✓ URL-based SSO handler
- ✓ Beautiful success/error pages
- ✓ Auto-redirect after login

### For Laravel Web App (To Be Implemented)

#### 1. Generate SSO Tokens

```php
// Laravel Controller Example
public function generateSsoToken(Request $request)
{
    $user = Auth::user();
    $wordpressSite = $request->input('wordpress_site'); // e.g., "example1.com"

    // Create token with user data
    $tokenData = [
        'user_id' => $user->id,
        'email' => $user->email,
        'username' => $user->username,
        'role' => 'administrator', // or map from your roles
        'wordpress_site' => $wordpressSite,
        'expires_at' => now()->addMinutes(5), // Token valid for 5 minutes
        'created_at' => now(),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ];

    // Store token in database
    $token = SsoToken::create([
        'user_id' => $user->id,
        'token' => Str::random(64), // Or use JWT
        'wordpress_site' => $wordpressSite,
        'data' => json_encode($tokenData),
        'expires_at' => now()->addMinutes(5),
        'used' => false
    ]);

    // Build WordPress SSO URL
    $ssoUrl = "https://{$wordpressSite}/?sas_sso_token={$token->token}";

    // Optional: Add redirect parameter
    if ($request->has('redirect_to')) {
        $ssoUrl .= '&redirect_to=' . urlencode($request->input('redirect_to'));
    }

    // Redirect user to WordPress site
    return redirect($ssoUrl);
}
```

#### 2. Validate SSO Tokens

```php
// Laravel API Controller
public function validateSsoToken(Request $request)
{
    $tokenString = $request->input('token');
    $domain = $request->input('domain');

    // Find token in database
    $token = SsoToken::where('token', $tokenString)
        ->where('wordpress_site', $domain)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();

    if (!$token) {
        return response()->json([
            'valid' => false,
            'message' => 'Token is invalid or expired'
        ], 401);
    }

    // Mark token as used (one-time use)
    $token->update(['used' => true, 'used_at' => now()]);

    // Return user data
    $tokenData = json_decode($token->data, true);

    return response()->json([
        'valid' => true,
        'email' => $tokenData['email'],
        'user_id' => $tokenData['user_id'],
        'username' => $tokenData['username'],
        'role' => $tokenData['role'],
        'expires_at' => $token->expires_at->toDateTimeString()
    ]);
}
```

#### 3. Database Migration for Tokens

```php
// Laravel Migration
Schema::create('sso_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('token', 64)->unique();
    $table->string('wordpress_site');
    $table->json('data');
    $table->timestamp('expires_at');
    $table->boolean('used')->default(false);
    $table->timestamp('used_at')->nullable();
    $table->string('ip_address')->nullable();
    $table->timestamps();

    $table->index(['token', 'wordpress_site']);
    $table->index(['user_id', 'used']);
    $table->index('expires_at');
});
```

## Usage Examples

### Example 1: One-Click Login Button in Web App

```html
<!-- In your Laravel Blade template -->
<div class="wordpress-sites">
    @foreach($wordpressSites as $site)
        <div class="site-card">
            <h3>{{ $site->name }}</h3>
            <p>{{ $site->domain }}</p>
            <a href="{{ route('sso.generate', ['site' => $site->domain]) }}"
               class="btn btn-primary">
                One-Click Login to WordPress
            </a>
        </div>
    @endforeach
</div>
```

### Example 2: Direct URL Construction

```php
// Generate SSO URL with custom redirect
$token = 'generated_token_here';
$wpSite = 'example1.com';
$redirectTo = '/wp-admin/post.php?post=123&action=edit';

$ssoUrl = "https://{$wpSite}/?sas_sso_token={$token}&redirect_to=" . urlencode($redirectTo);

return redirect($ssoUrl);
```

### Example 3: Using Shortcode in WordPress

```php
// In WordPress page/post content
[sas_sso_button text="Login via Web App" redirect="/dashboard/"]
```

## URL Parameters

### WordPress Site Accepts:

- `sas_sso_token` or `sas-sso-token` - The SSO token (required)
- `redirect_to` - Where to redirect after login (optional)
- `force_login` - Force login even if already authenticated (optional)

### Example URLs:

```
https://example1.com/?sas_sso_token=abc123xyz
https://example2.com/?sas-sso-token=abc123xyz&redirect_to=/wp-admin/
https://example3.com/?sas_sso_token=abc123xyz&redirect_to=/dashboard/&force_login=1
```

## Security Features

### 1. Rate Limiting
- **SSO Login:** 10 attempts per minute per IP
- **Token Validation:** 20 attempts per minute per IP
- Returns 429 status code when exceeded

### 2. Token Security
- Tokens should expire after 5 minutes
- One-time use only (mark as used after validation)
- Tokens are hashed when logged (SHA-256)
- Store tokens securely in database

### 3. Logging
- All SSO attempts logged in `wp_sas_sso_logs` table
- Tracks: token hash, status, user ID, IP, user agent, timestamp
- Useful for security audits and debugging

### 4. HTTPS Required
- All API calls use `sslverify => true`
- Tokens should only be transmitted over HTTPS

### 5. IP Validation (Optional Enhancement)
- Can validate IP matches between token generation and usage
- Helps prevent token theft

## Testing

### Test SSO Login

```bash
# 1. Generate a test token in your web app
# 2. Use curl to test WordPress endpoint

curl -X POST https://example1.com/wp-json/sas-hosting/v1/auth/sso-login/ \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_generated_token_here",
    "redirect_url": "https://example1.com/wp-admin/"
  }'
```

### Test Token Validation

```bash
curl -X POST https://example1.com/wp-json/sas-hosting/v1/auth/validate-token/ \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_generated_token_here"
  }'
```

### Test URL-Based Login

Simply visit in browser:
```
https://example1.com/?sas_sso_token=your_generated_token_here
```

## User Creation Options

### Option 1: Auto-Create Users (Current Implementation)
When a token is valid but user doesn't exist in WordPress:
- Plugin creates new WordPress user
- Username from token
- Random secure password (32 chars)
- Email from token
- Role from token (default: subscriber)
- Marks user as SSO user

### Option 2: Manual Pre-Creation (Recommended)
- Create WordPress users manually before SSO
- Match by email address
- More control over user roles and permissions

## Troubleshooting

### Issue: "Token validation failed"
- Check web app API endpoint is accessible
- Verify token is not expired (default 5 min)
- Check token hasn't been used already
- Verify WordPress site domain matches token

### Issue: "User not found"
- User doesn't exist in WordPress
- Enable auto-creation or create user manually
- Ensure email in token matches WordPress user email

### Issue: "Rate limit exceeded"
- Too many requests from same IP
- Wait 1 minute and try again
- Check for loops or automation issues

### Issue: "Authentication service unavailable"
- Web app API is down or unreachable
- Check web app server status
- Verify API URL is correct
- Check firewall/network settings

## Database Tables

### wp_sas_sso_logs
```sql
CREATE TABLE wp_sas_sso_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    token_hash varchar(64) NOT NULL,
    status varchar(20) NOT NULL,
    message text,
    user_id bigint(20) DEFAULT 0,
    ip_address varchar(45) NOT NULL,
    user_agent varchar(255),
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY token_hash (token_hash),
    KEY status (status),
    KEY user_id (user_id),
    KEY created_at (created_at)
);
```

## User Meta Fields

SSO users have these meta fields:
- `sas_sso_user` - Boolean, marks user as SSO user
- `sas_sso_provider_id` - User ID from web app
- `sas_sso_created` - When user was created via SSO
- `sas_sso_last_login` - Last successful SSO login
- `sas_sso_last_ip` - Last IP address used for SSO

## Configuration

### Update API Endpoints

If your web app API URL is different, update in:

**Current Configuration:**

All endpoints are configured to use: `https://annotation.sitesatscale.com`

**Files configured:**
- `includes/api/endpoints.php` - Lines 297, 437, 464, 540
- `includes/sso-handler.php` - Lines 396, 426

**To change web app URL (if needed):**

```php
// File: includes/api/endpoints.php
// Line 297
$validation_url = 'https://annotation.sitesatscale.com/api/wordpress/auth/validate-sso-token';

// Line 437
wp_remote_post('https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-login', ...);
```

```php
// File: includes/sso-handler.php
// Line 396
wp_remote_post('https://annotation.sitesatscale.com/api/wordpress/auth/log-sso-logout', ...);

// Line 426
$sso_info_url = 'https://annotation.sitesatscale.com/generate-sso-token?site=' . urlencode(get_site_url());
```

## Best Practices

1. **Token Expiration:** Keep tokens short-lived (5 minutes max)
2. **One-Time Use:** Mark tokens as used after validation
3. **HTTPS Only:** Never transmit tokens over HTTP
4. **Rate Limiting:** Prevent brute force attacks
5. **Logging:** Monitor SSO attempts for security
6. **Clean Up:** Periodically delete old expired tokens
7. **User Matching:** Match users by email (unique identifier)
8. **Role Mapping:** Map web app roles to WordPress roles carefully

## Future Enhancements

Potential improvements:
- [ ] JWT token support
- [ ] Multi-factor authentication
- [ ] Session management across sites
- [ ] Global logout (sign out from all sites)
- [ ] Admin dashboard for SSO logs
- [ ] IP whitelist/blacklist
- [ ] Geographic restrictions
- [ ] Custom token lifetime per site
- [ ] OAuth 2.0 full implementation

## Support

For issues or questions:
1. Check WordPress error logs
2. Check SSO logs table in database
3. Verify web app API responses
4. Test with direct curl commands
5. Enable WordPress debug mode for detailed errors

---

**Plugin Version:** 6.0.3
**Author:** SAS Server Engineer
**Last Updated:** October 2025
