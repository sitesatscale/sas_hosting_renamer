# Laravel SSO Implementation Guide

## Quick Start

This WordPress plugin requires 3 API endpoints in your Laravel app for SSO authentication.

### Environment

- **Local**: `http://localhost:5173`
- **Production**: `https://annotation.sitesatscale.com`
- **Test WordPress**: `http://localhost:10004`

## 1. Add Routes

File: `routes/api.php`

```php
use App\Http\Controllers\WordPressSSOController;

Route::prefix('wordpress/auth')->group(function () {
    Route::post('validate-sso-token', [WordPressSSOController::class, 'validateSsoToken']);
    Route::post('log-sso-login', [WordPressSSOController::class, 'logSsoLogin']);
    Route::post('log-sso-logout', [WordPressSSOController::class, 'logSsoLogout']);
});
```

## 2. Create Controller

File: `app/Http/Controllers/WordPressSSOController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WordPressSSOController extends Controller
{
    /**
     * Validate SSO token - WordPress calls this to verify user
     *
     * POST /api/wordpress/auth/validate-sso-token
     * Body: {"token": "abc123", "site": "http://localhost:10004"}
     */
    public function validateSsoToken(Request $request)
    {
        Log::info('SSO Validation Request', [
            'token' => $request->input('token'),
            'site' => $request->input('site'),
        ]);

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'site' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid request',
            ], 400);
        }

        $token = $request->input('token');

        // TODO: Implement real token validation
        // For now, using mock data for testing

        // Mock test tokens for development
        $mockUsers = [
            'test_dev_token' => [
                'email' => 'dev@test.com',
                'name' => 'Dev User',
                'username' => 'dev',
                'role' => 'dev', // CRITICAL: WordPress uses this to match user
            ],
            'test_server_token' => [
                'email' => 'server@test.com',
                'name' => 'Server Admin',
                'username' => 'server',
                'role' => 'server', // Maps to WordPress user 'sas_server'
            ],
        ];

        $userData = $mockUsers[$token] ?? null;

        if (!$userData) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid or expired token',
            ], 401);
        }

        return response()->json([
            'valid' => true,
            'email' => $userData['email'],
            'name' => $userData['name'],
            'username' => $userData['username'],
            'role' => $userData['role'], // IMPORTANT: WordPress needs this
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]);
    }

    /**
     * Log successful SSO login - WordPress notifies us after login
     *
     * POST /api/wordpress/auth/log-sso-login
     */
    public function logSsoLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site' => 'required|url',
            'email' => 'required|email',
            'wp_user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false], 400);
        }

        Log::info('WordPress SSO Login', $request->all());

        // TODO: Store in database if needed for analytics

        return response()->json([
            'success' => true,
            'message' => 'Login logged',
        ]);
    }

    /**
     * Log SSO logout - WordPress notifies us when user logs out
     *
     * POST /api/wordpress/auth/log-sso-logout
     */
    public function logSsoLogout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site' => 'required|url',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false], 400);
        }

        Log::info('WordPress SSO Logout', $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Logout logged',
        ]);
    }
}
```

## 3. Test Endpoints

Start Laravel dev server:
```bash
php artisan serve --host=0.0.0.0 --port=5173
```

Test validation endpoint:
```bash
curl -X POST http://localhost:5173/api/wordpress/auth/validate-sso-token \
  -H "Content-Type: application/json" \
  -d '{"token":"test_dev_token","site":"http://localhost:10004"}'
```

Expected response:
```json
{
  "valid": true,
  "email": "dev@test.com",
  "name": "Dev User",
  "username": "dev",
  "role": "dev",
  "expires_at": "2025-10-16T12:35:00Z"
}
```

## 4. Test WordPress SSO Login

Visit: `http://localhost:10004/?sas_sso_token=test_dev_token`

Should log you in as WordPress user `sas_dev`.

## Role Mapping Reference

WordPress plugin maps Laravel roles to WordPress usernames:

| Laravel Role | WordPress Username | Matching Priority |
|--------------|-------------------|-------------------|
| dev          | sas_dev           | 1. Role mapping   |
| server       | sas_server        | 2. Username       |
| tech         | sas_tech          | 3. Email          |
| seo          | sas_seo           |                   |

**Critical**: The `role` field in your API response determines which WordPress user gets logged in.

## Database Implementation (Optional but Recommended)

### Migration

```bash
php artisan make:migration create_sso_tokens_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 64)->unique();
            $table->string('site_url');
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamps();

            $table->index(['token', 'used']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sso_tokens');
    }
};
```

Run: `php artisan migrate`

### Model

```bash
php artisan make:model SsoToken
```

File: `app/Models/SsoToken.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SsoToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'site_url',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate new SSO token
     */
    public static function generate($userId, $siteUrl)
    {
        return self::create([
            'user_id' => $userId,
            'token' => Str::random(64),
            'site_url' => $siteUrl,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * Check if valid
     */
    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }
}
```

### Update Controller to Use Database

Replace the mock data section in `validateSsoToken()`:

```php
// Find token in database
$ssoToken = \App\Models\SsoToken::where('token', $token)
    ->where('site_url', $request->input('site'))
    ->where('expires_at', '>', now())
    ->where('used', false)
    ->first();

if (!$ssoToken) {
    return response()->json([
        'valid' => false,
        'error' => 'Invalid or expired token',
    ], 401);
}

$user = $ssoToken->user;

// Mark as used (one-time use)
$ssoToken->update(['used' => true]);

// Determine user role (adjust based on your user model)
$role = $user->role ?? 'subscriber';

return response()->json([
    'valid' => true,
    'email' => $user->email,
    'name' => $user->name,
    'username' => $user->username ?? explode('@', $user->email)[0],
    'role' => $role,
    'expires_at' => $ssoToken->expires_at->toIso8601String(),
]);
```

## 5. Generate SSO Tokens (In Your Laravel App)

When user clicks "Login to WordPress", generate token:

```php
use App\Models\SsoToken;

// In your controller
public function loginToWordPress(Request $request)
{
    $user = auth()->user();
    $siteUrl = $request->input('site_url'); // e.g., "http://localhost:10004"

    // Generate token
    $ssoToken = SsoToken::generate($user->id, $siteUrl);

    // Redirect to WordPress with token
    return redirect($siteUrl . '/?sas_sso_token=' . $ssoToken->token);
}
```

## CORS Configuration (If Needed)

File: `config/cors.php`

```php
'paths' => ['api/*', 'wordpress/*'],

'allowed_origins' => [
    'http://localhost:10004',
    'https://example1.com',
    'https://example2.com',
],

'allowed_methods' => ['POST'],
```

## Troubleshooting

### WordPress can't reach Laravel

Check:
- Laravel running: `php artisan serve --host=0.0.0.0 --port=5173`
- Port 5173 accessible
- Check logs: `tail -f storage/logs/laravel.log`

### Token validation fails

Check:
- Response includes all required fields: `valid`, `email`, `name`, `username`, `role`
- The `role` field is correct
- Laravel logs show request

### User not found in WordPress

Check:
- WordPress user `sas_dev` exists (for role "dev")
- WordPress user `sas_server` exists (for role "server")
- Role field matches expected value

## Security Checklist

Before production:

- [ ] Remove mock test tokens
- [ ] Implement database token validation
- [ ] Add rate limiting to endpoints
- [ ] Validate `site` parameter against whitelist
- [ ] Use HTTPS in production
- [ ] Add CSRF protection if needed
- [ ] Log all SSO attempts

## Complete Flow

1. User in Laravel clicks "Login to WordPress"
2. Laravel generates token, redirects to: `{wordpress_url}/?sas_sso_token={token}`
3. WordPress extracts token from URL
4. WordPress calls Laravel: `POST /api/wordpress/auth/validate-sso-token`
5. Laravel validates token, returns user data including `role`
6. WordPress finds matching user (by role → username → email)
7. WordPress logs user in
8. WordPress calls Laravel: `POST /api/wordpress/auth/log-sso-login`

## API Reference

### Endpoint 1: Validate Token

**Request:**
```http
POST /api/wordpress/auth/validate-sso-token
Content-Type: application/json

{
  "token": "abc123",
  "site": "http://localhost:10004"
}
```

**Success Response (200):**
```json
{
  "valid": true,
  "email": "user@example.com",
  "name": "User Name",
  "username": "username",
  "role": "dev",
  "expires_at": "2025-10-16T12:35:00Z"
}
```

**Error Response (401):**
```json
{
  "valid": false,
  "error": "Invalid or expired token"
}
```

### Endpoint 2: Log Login

**Request:**
```http
POST /api/wordpress/auth/log-sso-login
Content-Type: application/json

{
  "site": "http://localhost:10004",
  "email": "user@example.com",
  "username": "username",
  "wp_user_id": 123
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login logged"
}
```

### Endpoint 3: Log Logout

**Request:**
```http
POST /api/wordpress/auth/log-sso-logout
Content-Type: application/json

{
  "site": "http://localhost:10004",
  "email": "user@example.com",
  "username": "username"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logout logged"
}
```

---

## Summary

**Minimum Required:**
1. Create controller with 3 methods
2. Add 3 routes
3. Return user data with `role` field

**For Production:**
1. Add database migration for tokens
2. Create SsoToken model
3. Implement token generation
4. Add security measures

**Test with:**
- URL: `http://localhost:10004/?sas_sso_token=test_dev_token`
- Should log in as WordPress user `sas_dev`
