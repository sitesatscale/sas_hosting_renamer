# Instructions for Claude AI: Laravel SSO Implementation

This document contains instructions for implementing WordPress SSO (Single Sign-On) endpoints in a Laravel application.

## Context

We have a WordPress plugin that needs to authenticate users via SSO tokens. The Laravel app will act as the OAuth Provider, and WordPress sites will act as OAuth Clients.

**Key Information:**
- Laravel app URL (local): `http://localhost:5173`
- Laravel app URL (production): `https://annotation.sitesatscale.com`
- WordPress test site: `http://localhost:10004`

## User Role Mapping

The Laravel application has users with specific roles that need to map to WordPress users:

| Laravel Role | WordPress Username | WordPress Role   |
|--------------|-------------------|------------------|
| dev          | sas_dev           | administrator    |
| server       | sas_server        | administrator    |
| tech         | sas_tech          | (existing role)  |
| seo          | sas_seo           | (existing role)  |

## Required Implementation

### Step 1: Create Routes

Add these routes to `routes/api.php`:

```php
use App\Http\Controllers\WordPressSSOController;

Route::prefix('wordpress/auth')->group(function () {
    Route::post('validate-sso-token', [WordPressSSOController::class, 'validateSsoToken']);
    Route::post('log-sso-login', [WordPressSSOController::class, 'logSsoLogin']);
    Route::post('log-sso-logout', [WordPressSSOController::class, 'logSsoLogout']);
});
```

**Expected endpoints:**
- `POST /api/wordpress/auth/validate-sso-token`
- `POST /api/wordpress/auth/log-sso-login`
- `POST /api/wordpress/auth/log-sso-logout`

### Step 2: Create Controller

Create `app/Http/Controllers/WordPressSSOController.php` with three methods:

#### Method 1: validateSsoToken()

**Purpose**: Validate an SSO token and return user information

**Request Format:**
```json
{
  "token": "abc123xyz",
  "site": "http://localhost:10004"
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "email": "user@example.com",
  "name": "John Doe",
  "username": "johndoe",
  "role": "dev",
  "expires_at": "2025-10-16T12:30:00Z"
}
```

**Error Response (401):**
```json
{
  "valid": false,
  "error": "Invalid or expired token"
}
```

**Important Notes:**
- Token should expire after 5 minutes
- Token should be single-use (mark as "used" after validation)
- Must return the user's role (dev, server, tech, seo, etc.)
- The role field is CRITICAL for WordPress to match the correct user

#### Method 2: logSsoLogin()

**Purpose**: Log successful WordPress logins for analytics/security

**Request Format:**
```json
{
  "site": "http://localhost:10004",
  "email": "user@example.com",
  "username": "johndoe",
  "wp_user_id": 123
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login logged successfully"
}
```

**Notes:**
- This is informational only - WordPress has already logged the user in
- Store in database for audit trail (optional)
- Log to Laravel logs for debugging

#### Method 3: logSsoLogout()

**Purpose**: Log WordPress logouts

**Request Format:**
```json
{
  "site": "http://localhost:10004",
  "email": "user@example.com",
  "username": "johndoe"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout logged successfully"
}
```

### Step 3: Database Schema (Recommended)

Create a migration for storing SSO tokens:

```bash
php artisan make:migration create_sso_tokens_table
```

**Required fields:**
- `id` - Primary key
- `user_id` - Foreign key to users table
- `token` - Unique 64-character string
- `site_url` - Target WordPress site URL
- `expires_at` - Timestamp (5 minutes from creation)
- `used` - Boolean (false by default, true after validation)
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- Index on `token` and `used` (for fast lookups)
- Index on `user_id` and `created_at` (for user history)

### Step 4: Token Generation

When a user clicks "Login to WordPress" in your Laravel app, you need to:

1. Generate a unique token (64 characters, random string)
2. Store token in database with user_id, site_url, expires_at (now + 5 minutes)
3. Redirect user to: `{site_url}/?sas_sso_token={token}`

**Example:**
```
http://localhost:10004/?sas_sso_token=abc123xyz456...
```

## Testing Instructions

### Test 1: Endpoint Connectivity

Test that Laravel endpoints are accessible:

```bash
# Test validation endpoint
curl -X POST http://localhost:5173/api/wordpress/auth/validate-sso-token \
  -H "Content-Type: application/json" \
  -d '{"token":"test_dev_token","site":"http://localhost:10004"}'

# Expected response:
# {"valid":true,"email":"dev@test.com","name":"Dev User","username":"dev","role":"dev",...}
```

### Test 2: Mock User Testing

For initial testing, you can hardcode these test tokens in your controller:

| Token             | Email              | Username | Role   |
|-------------------|-------------------|----------|--------|
| test_dev_token    | dev@test.com      | dev      | dev    |
| test_server_token | server@test.com   | server   | server |

### Test 3: WordPress SSO Flow

1. Start Laravel: `php artisan serve --host=0.0.0.0 --port=5173`
2. Visit: `http://localhost:10004/?sas_sso_token=test_dev_token`
3. Expected: Should log in as WordPress user "sas_dev"

## Important Configuration Details

### Current WordPress Configuration

The WordPress plugin is configured to:
- **Local environment**: Use `http://localhost:5173`
- **Production environment**: Use `https://annotation.sitesatscale.com`
- **Token lifetime**: 5 minutes
- **SSL verification**: Disabled for local, enabled for production
- **Auto-create users**: Enabled (if user doesn't exist in WordPress)
- **Debug mode**: Enabled for local environment

### WordPress HTTP Configuration

WordPress has been configured to allow localhost HTTP requests (required for local testing):

```php
// In wp-config.php
define('WP_HTTP_BLOCK_EXTERNAL', false);
define('WP_ACCESSIBLE_HOSTS', 'localhost,localhost:10004,127.0.0.1');
```

## User Matching Logic

When WordPress receives an SSO token, it follows this priority:

1. **Priority 1**: Role-based username mapping
   - Laravel user has role "dev" → Find WordPress user "sas_dev"
   - Laravel user has role "server" → Find WordPress user "sas_server"

2. **Priority 2**: Username match
   - Match by the `username` field returned from Laravel

3. **Priority 3**: Email match
   - Match by the `email` field returned from Laravel

**CRITICAL**: The `role` field in your API response determines which WordPress user to match. Make sure this field is accurate!

## Error Handling

Handle these error cases:

1. **Token not found**: Return `{"valid": false, "error": "Invalid token"}`
2. **Token expired**: Return `{"valid": false, "error": "Token expired"}`
3. **Token already used**: Return `{"valid": false, "error": "Token already used"}`
4. **Invalid site URL**: Return `{"valid": false, "error": "Invalid site"}`
5. **User not found**: Return `{"valid": false, "error": "User not found"}`

## Security Considerations

1. **Rate Limiting**: Add rate limiting to prevent token brute-forcing
2. **HTTPS Only**: In production, only allow HTTPS requests
3. **Site Validation**: Verify the `site` parameter is an allowed WordPress site
4. **Token Entropy**: Use cryptographically secure random token generation
5. **Audit Logging**: Log all SSO attempts (success and failure)

## CORS Configuration

If you get CORS errors, add to `config/cors.php`:

```php
'paths' => ['api/*', 'wordpress/*'],

'allowed_origins' => [
    'http://localhost:10004',
    'https://example1.com',
    'https://example2.com',
    'https://example3.com',
],

'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

## Troubleshooting

### Issue: Laravel not receiving requests from WordPress

**Check:**
- Laravel is running: `php artisan serve --host=0.0.0.0 --port=5173`
- Firewall allows port 5173
- Check Laravel logs: `tail -f storage/logs/laravel.log`

### Issue: Token validation fails

**Check:**
- Token exists in database
- Token hasn't expired (check `expires_at`)
- Token hasn't been used already (check `used` field)
- Response includes all required fields: `valid`, `email`, `name`, `username`, `role`

### Issue: WordPress can't find user

**Check:**
- Response includes correct `role` field (dev, server, tech, seo)
- WordPress user exists with expected username (sas_dev, sas_server, etc.)
- Check WordPress debug log for user matching details

## Complete Controller Reference

See `LARAVEL_ENDPOINTS.md` in the WordPress plugin directory for complete controller code with:
- Full validation logic
- Mock user data for testing
- Database integration examples
- Model and migration examples
- Production-ready implementation

## Questions to Ask

If anything is unclear, ask:

1. What is your current user authentication system? (Sanctum, Passport, custom?)
2. Do you already have a `roles` system for users?
3. Where should SSO tokens be stored? (Database table, cache, Redis?)
4. What is your user model structure? (fields available)
5. Should tokens be single-use or allow multiple logins?
6. Do you need audit logging for SSO events?

## Success Criteria

Implementation is complete when:

- [ ] All three endpoints return proper JSON responses
- [ ] Test token `test_dev_token` returns valid user data
- [ ] WordPress can successfully log in user via SSO URL
- [ ] Laravel logs show incoming validation requests
- [ ] User matching works based on role field
- [ ] Tokens expire after 5 minutes
- [ ] Error responses are properly formatted

## Next Steps After Implementation

1. Replace mock test tokens with real database-backed tokens
2. Implement token generation in your Laravel app's dashboard
3. Add "Login to WordPress" buttons that redirect to WordPress with token
4. Test with production URLs (annotation.sitesatscale.com)
5. Add rate limiting and security measures
6. Deploy to production

---

**Need Help?** Check these files in the WordPress plugin:
- `LARAVEL_ENDPOINTS.md` - Complete controller code with examples
- `SSO_IMPLEMENTATION.md` - Overall SSO architecture
- `ROLE_MAPPING_GUIDE.md` - Detailed role mapping documentation
