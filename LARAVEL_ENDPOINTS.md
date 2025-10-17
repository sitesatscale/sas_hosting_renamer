# Laravel SSO Endpoint Implementation

This guide shows you how to implement the SSO endpoints in your Laravel app at `http://localhost:5173`.

## Required Routes

Add these routes to your Laravel `routes/api.php`:

```php
<?php

use App\Http\Controllers\WordPressSSOController;

Route::prefix('wordpress/auth')->group(function () {
    Route::post('validate-sso-token', [WordPressSSOController::class, 'validateSsoToken']);
    Route::post('log-sso-login', [WordPressSSOController::class, 'logSsoLogin']);
    Route::post('log-sso-logout', [WordPressSSOController::class, 'logSsoLogout']);
});
```

## Controller Implementation

Create `app/Http/Controllers/WordPressSSOController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WordPressSSOController extends Controller
{
    /**
     * Validate SSO token and return user data
     *
     * POST /api/wordpress/auth/validate-sso-token
     */
    public function validateSsoToken(Request $request)
    {
        // Log the request for debugging
        Log::info('SSO Token Validation Request', [
            'token' => $request->input('token'),
            'site' => $request->input('site'),
            'ip' => $request->ip(),
        ]);

        // Validate request
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'site' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid request parameters',
                'details' => $validator->errors()
            ], 400);
        }

        $token = $request->input('token');
        $site = $request->input('site');

        // TODO: Replace with your actual token validation logic
        // This should:
        // 1. Check if token exists in your database (e.g., sso_tokens table)
        // 2. Verify token hasn't expired (check created_at + 5 minutes)
        // 3. Verify token is for the correct site
        // 4. Get the associated user

        // Example using a database query:
        /*
        $ssoToken = \App\Models\SsoToken::where('token', $token)
            ->where('site_url', $site)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->first();

        if (!$ssoToken) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid or expired token'
            ], 401);
        }

        $user = $ssoToken->user;

        // Mark token as used (optional - for one-time use tokens)
        $ssoToken->update(['used' => true]);
        */

        // TEMPORARY: For testing, accept any token and return mock user data
        // Remove this and uncomment the database code above when ready
        if (empty($token)) {
            return response()->json([
                'valid' => false,
                'error' => 'Token is required'
            ], 401);
        }

        // Get current authenticated user or use mock data
        $user = auth()->user();

        if (!$user) {
            // Return mock user for testing
            // In production, this should return an error if no user found
            $mockUsers = [
                'test_dev_token' => [
                    'email' => 'dev@test.com',
                    'name' => 'Dev User',
                    'username' => 'dev',
                    'role' => 'dev',
                ],
                'test_server_token' => [
                    'email' => 'server@test.com',
                    'name' => 'Server Admin',
                    'username' => 'server',
                    'role' => 'server',
                ],
            ];

            $userData = $mockUsers[$token] ?? [
                'email' => 'user@test.com',
                'name' => 'Test User',
                'username' => 'testuser',
                'role' => 'subscriber',
            ];
        } else {
            // Use actual user data
            $userData = [
                'email' => $user->email,
                'name' => $user->name,
                'username' => $user->username ?? explode('@', $user->email)[0],
                'role' => $this->getUserRole($user),
            ];
        }

        return response()->json([
            'valid' => true,
            'email' => $userData['email'],
            'name' => $userData['name'],
            'username' => $userData['username'],
            'role' => $userData['role'],
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]);
    }

    /**
     * Log successful SSO login
     *
     * POST /api/wordpress/auth/log-sso-login
     */
    public function logSsoLogin(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'site' => 'required|url',
            'email' => 'required|email',
            'username' => 'nullable|string',
            'wp_user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request parameters',
                'details' => $validator->errors()
            ], 400);
        }

        // Log the successful login
        Log::info('WordPress SSO Login', [
            'site' => $request->input('site'),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'wp_user_id' => $request->input('wp_user_id'),
            'ip' => $request->ip(),
        ]);

        // TODO: Store in your database if you want to track logins
        /*
        \App\Models\SsoLoginLog::create([
            'site_url' => $request->input('site'),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'wp_user_id' => $request->input('wp_user_id'),
            'ip_address' => $request->ip(),
            'logged_at' => now(),
        ]);
        */

        return response()->json([
            'success' => true,
            'message' => 'Login logged successfully'
        ]);
    }

    /**
     * Log SSO logout
     *
     * POST /api/wordpress/auth/log-sso-logout
     */
    public function logSsoLogout(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'site' => 'required|url',
            'email' => 'required|email',
            'username' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request parameters',
                'details' => $validator->errors()
            ], 400);
        }

        // Log the logout
        Log::info('WordPress SSO Logout', [
            'site' => $request->input('site'),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'ip' => $request->ip(),
        ]);

        // TODO: Store in your database if you want to track logouts
        /*
        \App\Models\SsoLogoutLog::create([
            'site_url' => $request->input('site'),
            'email' => $request->input('email'),
            'username' => $request->input('username'),
            'ip_address' => $request->ip(),
            'logged_at' => now(),
        ]);
        */

        return response()->json([
            'success' => true,
            'message' => 'Logout logged successfully'
        ]);
    }

    /**
     * Helper: Get user's role
     * Adjust this based on your actual user role system
     */
    private function getUserRole($user)
    {
        // Example 1: If you have a 'role' field directly on user
        if (isset($user->role)) {
            return $user->role;
        }

        // Example 2: If using Laravel's default roles/permissions
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            return $roles->first() ?? 'subscriber';
        }

        // Example 3: If using a custom relationship
        if ($user->roles()->exists()) {
            return $user->roles()->first()->name ?? 'subscriber';
        }

        // Default fallback
        return 'subscriber';
    }
}
```

## Database Migration (Optional)

If you want to store SSO tokens in your database, create a migration:

```bash
php artisan make:migration create_sso_tokens_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Support;

class CreateSsoTokensTable extends Migration
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
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sso_tokens');
    }
}
```

## Model (Optional)

Create `app/Models/SsoToken.php`:

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
     * Generate a new SSO token for a user
     */
    public static function generate($userId, $siteUrl, $expiresInMinutes = 5)
    {
        return self::create([
            'user_id' => $userId,
            'token' => Str::random(64),
            'site_url' => $siteUrl,
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'used' => false,
        ]);
    }

    /**
     * Check if token is valid
     */
    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }
}
```

## Generating SSO Tokens

When a user clicks "Login to WordPress" in your Laravel app, generate a token:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SsoToken;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function generateWordPressLogin(Request $request)
    {
        $user = auth()->user();
        $siteUrl = $request->input('site_url'); // e.g., "http://localhost:10004"

        // Generate SSO token
        $ssoToken = SsoToken::generate($user->id, $siteUrl);

        // Build WordPress login URL
        $loginUrl = $siteUrl . '/?sas_sso_token=' . $ssoToken->token;

        // Redirect to WordPress with token
        return redirect($loginUrl);
    }
}
```

## Testing the Integration

1. **Start your Laravel app**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=5173
   ```

2. **Test validation endpoint**:
   ```bash
   curl -X POST http://localhost:5173/api/wordpress/auth/validate-sso-token \
     -H "Content-Type: application/json" \
     -d '{"token":"test_dev_token","site":"http://localhost:10004"}'
   ```

3. **Test SSO login**:
   - Visit: `http://localhost:10004/?sas_sso_token=test_dev_token`
   - Should log you in as the user matching the role "dev" (e.g., sas_dev)

## Production Checklist

Before deploying to production:

- [ ] Remove mock user data from `validateSsoToken()`
- [ ] Implement actual database token validation
- [ ] Add proper token expiration checks
- [ ] Implement token generation in your Laravel app
- [ ] Add rate limiting to endpoints
- [ ] Enable CORS if needed
- [ ] Test with production URL: https://annotation.sitesatscale.com
- [ ] Update WordPress plugin to use production URL

## CORS Configuration (If Needed)

If you get CORS errors, add to `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'wordpress/*'],

'allowed_origins' => [
    'http://localhost:10004',
    'https://example1.com',
    'https://example2.com',
    'https://example3.com',
],
```

## Troubleshooting

### WordPress can't reach Laravel app
- Check Laravel is running: `php artisan serve --host=0.0.0.0 --port=5173`
- Check firewall allows localhost:5173
- Check wp-config.php has HTTP localhost configuration (already added)

### Token validation fails
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Check WordPress debug log
- Use the sso-debug.php page to test endpoints

### User not found
- Check username_mapping in includes/sso-config.php:58
- Verify WordPress user exists with username "sas_dev" for role "dev"
- Check Laravel is returning correct role name
