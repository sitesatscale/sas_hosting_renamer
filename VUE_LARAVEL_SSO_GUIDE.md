# Vue + Laravel SSO Implementation Guide

## Architecture Overview

**Frontend**: Vue.js app (likely running on `http://localhost:5173` with Vite)
**Backend**: Laravel API
**WordPress Sites**: Multiple WordPress installations that users will log into

## Implementation Parts

1. **Laravel API** - 3 endpoints for WordPress to validate tokens
2. **Laravel API** - 1 endpoint for Vue to generate SSO tokens
3. **Vue Frontend** - Button/link to trigger WordPress login

---

## Part 1: Laravel API Endpoints for WordPress

### Add Routes

File: `routes/api.php`

```php
use App\Http\Controllers\WordPressSSOController;

// WordPress SSO endpoints (called by WordPress)
Route::prefix('wordpress/auth')->group(function () {
    Route::post('validate-sso-token', [WordPressSSOController::class, 'validateSsoToken']);
    Route::post('log-sso-login', [WordPressSSOController::class, 'logSsoLogin']);
    Route::post('log-sso-logout', [WordPressSSOController::class, 'logSsoLogout']);
});

// Generate SSO token (called by Vue frontend)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('sso/generate-token', [WordPressSSOController::class, 'generateToken']);
});
```

### Create Controller

File: `app/Http/Controllers/WordPressSSOController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WordPressSSOController extends Controller
{
    /**
     * Generate SSO token for Vue frontend
     * Vue calls this, gets token, then redirects user to WordPress
     *
     * POST /api/sso/generate-token
     * Body: {"site_url": "http://localhost:10004"}
     */
    public function generateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid site URL',
            ], 400);
        }

        $user = $request->user(); // Authenticated via Sanctum
        $siteUrl = $request->input('site_url');

        // TODO: Implement database storage (see below)
        // For now, using a simple token with user info encoded

        // Generate token
        $token = Str::random(64);

        // Store in cache (5 minutes)
        cache()->put("sso_token:{$token}", [
            'user_id' => $user->id,
            'site_url' => $siteUrl,
            'created_at' => now(),
        ], now()->addMinutes(5));

        Log::info('SSO Token Generated', [
            'user_id' => $user->id,
            'site_url' => $siteUrl,
            'token' => substr($token, 0, 10) . '...',
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'wordpress_url' => $siteUrl . '/?sas_sso_token=' . $token,
            'expires_in' => 300, // 5 minutes in seconds
        ]);
    }

    /**
     * Validate SSO token - WordPress calls this
     *
     * POST /api/wordpress/auth/validate-sso-token
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
        $siteUrl = $request->input('site');

        // Check cache for token
        $tokenData = cache()->get("sso_token:{$token}");

        if (!$tokenData) {
            // For development: check if it's a test token
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

            if (isset($mockUsers[$token])) {
                return response()->json([
                    'valid' => true,
                    ...$mockUsers[$token],
                    'expires_at' => now()->addMinutes(5)->toIso8601String(),
                ]);
            }

            return response()->json([
                'valid' => false,
                'error' => 'Invalid or expired token',
            ], 401);
        }

        // Verify site URL matches
        if ($tokenData['site_url'] !== $siteUrl) {
            return response()->json([
                'valid' => false,
                'error' => 'Token not valid for this site',
            ], 401);
        }

        // Get user
        $user = \App\Models\User::find($tokenData['user_id']);

        if (!$user) {
            return response()->json([
                'valid' => false,
                'error' => 'User not found',
            ], 401);
        }

        // Delete token from cache (one-time use)
        cache()->forget("sso_token:{$token}");

        // Get user role - adjust based on your user model
        $role = $this->getUserRole($user);

        return response()->json([
            'valid' => true,
            'email' => $user->email,
            'name' => $user->name,
            'username' => $user->username ?? explode('@', $user->email)[0],
            'role' => $role, // CRITICAL: WordPress needs this
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]);
    }

    /**
     * Log SSO login
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

        return response()->json([
            'success' => true,
            'message' => 'Login logged',
        ]);
    }

    /**
     * Log SSO logout
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

    /**
     * Get user role - adjust based on your user model
     */
    private function getUserRole($user)
    {
        // Option 1: Direct role field
        if (isset($user->role)) {
            return $user->role;
        }

        // Option 2: Relationship
        if (method_exists($user, 'roles') && $user->roles()->exists()) {
            return $user->roles()->first()->name ?? 'subscriber';
        }

        // Option 3: Laravel Permission package (Spatie)
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            return $roles->first() ?? 'subscriber';
        }

        // Default
        return 'subscriber';
    }
}
```

---

## Part 2: Vue Frontend Implementation

### API Service

File: `src/services/ssoService.js` (or wherever you keep API calls)

```javascript
import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

export const ssoService = {
  /**
   * Generate SSO token and get WordPress login URL
   * @param {string} siteUrl - WordPress site URL (e.g., "http://localhost:10004")
   * @returns {Promise<{token: string, wordpress_url: string}>}
   */
  async generateWordPressToken(siteUrl) {
    try {
      const response = await axios.post(
        `${API_URL}/sso/generate-token`,
        { site_url: siteUrl },
        {
          headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`, // or however you store auth token
          },
        }
      );

      return response.data;
    } catch (error) {
      console.error('Failed to generate SSO token:', error);
      throw error;
    }
  },

  /**
   * Redirect to WordPress with SSO token
   * @param {string} siteUrl - WordPress site URL
   */
  async loginToWordPress(siteUrl) {
    try {
      const data = await this.generateWordPressToken(siteUrl);

      // Redirect to WordPress with token
      window.location.href = data.wordpress_url;
    } catch (error) {
      console.error('SSO login failed:', error);
      throw error;
    }
  },
};
```

### Vue Component Example 1: Simple Button

File: `src/components/WordPressLoginButton.vue`

```vue
<template>
  <button
    @click="loginToWordPress"
    :disabled="loading"
    class="btn btn-primary"
  >
    <span v-if="loading">Logging in...</span>
    <span v-else>Login to WordPress</span>
  </button>
</template>

<script setup>
import { ref } from 'vue';
import { ssoService } from '@/services/ssoService';

const props = defineProps({
  siteUrl: {
    type: String,
    required: true,
    // Example: "http://localhost:10004"
  },
});

const loading = ref(false);
const error = ref(null);

const loginToWordPress = async () => {
  loading.value = true;
  error.value = null;

  try {
    await ssoService.loginToWordPress(props.siteUrl);
    // User will be redirected, so this won't execute
  } catch (err) {
    error.value = err.message || 'Failed to login';
    loading.value = false;
  }
};
</script>
```

### Vue Component Example 2: Site List with Login Buttons

File: `src/components/WordPressSitesList.vue`

```vue
<template>
  <div class="wordpress-sites">
    <h2>Your WordPress Sites</h2>

    <div v-if="loading" class="loading">
      Loading sites...
    </div>

    <div v-else class="sites-grid">
      <div
        v-for="site in sites"
        :key="site.id"
        class="site-card"
      >
        <h3>{{ site.name }}</h3>
        <p>{{ site.url }}</p>

        <button
          @click="loginToSite(site.url)"
          :disabled="loggingIn === site.id"
          class="btn btn-primary"
        >
          <span v-if="loggingIn === site.id">
            Logging in...
          </span>
          <span v-else>
            One-Click Login
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ssoService } from '@/services/ssoService';
import axios from 'axios';

const sites = ref([
  // Example data - replace with API call
  { id: 1, name: 'Example Site 1', url: 'http://localhost:10004' },
  { id: 2, name: 'Example Site 2', url: 'https://example1.com' },
  { id: 3, name: 'Example Site 3', url: 'https://example2.com' },
]);

const loading = ref(false);
const loggingIn = ref(null);

const loginToSite = async (siteUrl) => {
  const site = sites.value.find(s => s.url === siteUrl);
  loggingIn.value = site.id;

  try {
    await ssoService.loginToWordPress(siteUrl);
    // User will be redirected
  } catch (error) {
    console.error('Login failed:', error);
    alert('Failed to login to WordPress: ' + error.message);
    loggingIn.value = null;
  }
};

// Optional: Fetch sites from API
onMounted(async () => {
  // loading.value = true;
  // try {
  //   const response = await axios.get('/api/wordpress-sites');
  //   sites.value = response.data;
  // } catch (error) {
  //   console.error('Failed to load sites:', error);
  // }
  // loading.value = false;
});
</script>

<style scoped>
.sites-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.site-card {
  border: 1px solid #ddd;
  padding: 1rem;
  border-radius: 8px;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
```

### Vue Component Example 3: With Composable

File: `src/composables/useWordPressSSO.js`

```javascript
import { ref } from 'vue';
import { ssoService } from '@/services/ssoService';

export function useWordPressSSO() {
  const loading = ref(false);
  const error = ref(null);

  const loginToWordPress = async (siteUrl) => {
    loading.value = true;
    error.value = null;

    try {
      await ssoService.loginToWordPress(siteUrl);
      // User will be redirected
    } catch (err) {
      error.value = err.message || 'Failed to login to WordPress';
      loading.value = false;
      throw err;
    }
  };

  const generateToken = async (siteUrl) => {
    try {
      const data = await ssoService.generateWordPressToken(siteUrl);
      return data;
    } catch (err) {
      error.value = err.message || 'Failed to generate token';
      throw err;
    }
  };

  return {
    loading,
    error,
    loginToWordPress,
    generateToken,
  };
}
```

Usage in component:
```vue
<script setup>
import { useWordPressSSO } from '@/composables/useWordPressSSO';

const { loading, error, loginToWordPress } = useWordPressSSO();

const handleLogin = () => {
  loginToWordPress('http://localhost:10004');
};
</script>
```

---

## Part 3: Configuration

### Laravel .env

```env
# Your existing config...

# For CORS - allow Vue app to call Laravel API
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
SESSION_DOMAIN=localhost
```

### Vue .env

File: `.env.local` or `.env`

```env
VITE_API_URL=http://localhost:8000/api
```

### CORS Configuration

File: `config/cors.php`

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        // Add production URL when ready
        'https://annotation.sitesatscale.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

---

## Testing Flow

### 1. Start Both Servers

```bash
# Terminal 1 - Laravel API
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 - Vue Frontend
npm run dev
# Should start on http://localhost:5173
```

### 2. Test Laravel Endpoint Directly

```bash
# Test with mock token
curl -X POST http://localhost:8000/api/wordpress/auth/validate-sso-token \
  -H "Content-Type: application/json" \
  -d '{"token":"test_dev_token","site":"http://localhost:10004"}'

# Should return: {"valid":true,"email":"dev@test.com",...}
```

### 3. Test Vue Frontend

1. Login to your Vue app
2. Click "Login to WordPress" button
3. Should redirect to: `http://localhost:10004/?sas_sso_token=abc123...`
4. WordPress validates token with Laravel
5. User logged into WordPress

---

## Database Implementation (Recommended for Production)

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

### Model

```bash
php artisan make:model SsoToken
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SsoToken extends Model
{
    protected $fillable = ['user_id', 'token', 'site_url', 'expires_at', 'used'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generate($userId, $siteUrl)
    {
        return self::create([
            'user_id' => $userId,
            'token' => Str::random(64),
            'site_url' => $siteUrl,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }
}
```

### Update Controller to Use Database

In `generateToken()` method:

```php
// Replace cache with database
$ssoToken = \App\Models\SsoToken::generate($user->id, $siteUrl);

return response()->json([
    'success' => true,
    'token' => $ssoToken->token,
    'wordpress_url' => $siteUrl . '/?sas_sso_token=' . $ssoToken->token,
    'expires_in' => 300,
]);
```

In `validateSsoToken()` method:

```php
// Replace cache lookup with database
$ssoToken = \App\Models\SsoToken::where('token', $token)
    ->where('site_url', $siteUrl)
    ->where('used', false)
    ->where('expires_at', '>', now())
    ->first();

if (!$ssoToken) {
    return response()->json(['valid' => false, 'error' => 'Invalid token'], 401);
}

$user = $ssoToken->user;

// Mark as used
$ssoToken->update(['used' => true]);
```

---

## Role Mapping Reference

| Laravel User Role | WordPress Username | WordPress Role  |
|------------------|-------------------|-----------------|
| dev              | sas_dev           | administrator   |
| server           | sas_server        | administrator   |
| tech             | sas_tech          | (existing)      |
| seo              | sas_seo           | (existing)      |

**Critical**: Make sure your Laravel API returns the correct `role` field based on your user model.

---

## Complete User Flow

1. **User on Vue app** sees "Login to WordPress" button for a site
2. **User clicks button**
3. **Vue calls** `POST /api/sso/generate-token` with `site_url`
4. **Laravel generates** token, stores in cache/database
5. **Laravel returns** `wordpress_url` with token
6. **Vue redirects** user to: `http://localhost:10004/?sas_sso_token=abc123`
7. **WordPress extracts** token from URL
8. **WordPress calls** `POST /api/wordpress/auth/validate-sso-token`
9. **Laravel validates** token, returns user data with role
10. **WordPress matches** user by role → username → email
11. **WordPress logs user in**, redirects to dashboard
12. **WordPress notifies** Laravel via `POST /api/wordpress/auth/log-sso-login`

---

## Summary: What to Implement

### Laravel (4 endpoints):
1. ✅ `POST /api/sso/generate-token` - For Vue to generate tokens
2. ✅ `POST /api/wordpress/auth/validate-sso-token` - For WordPress to validate
3. ✅ `POST /api/wordpress/auth/log-sso-login` - For WordPress to log success
4. ✅ `POST /api/wordpress/auth/log-sso-logout` - For WordPress to log logout

### Vue (1 service + components):
1. ✅ SSO service with API calls
2. ✅ Button/component to trigger WordPress login
3. ✅ Handle loading and error states

### WordPress (Already Done):
✅ Plugin configured and ready at `http://localhost:10004`

**Test URL**: After implementing, visit `http://localhost:10004/?sas_sso_token=test_dev_token`
