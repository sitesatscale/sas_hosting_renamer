# Role Mapping Guide

## Overview

The plugin now supports **smart user matching** with three methods and automatic role mapping from your Laravel app to WordPress.

## How User Matching Works

The plugin tries to find WordPress users in this order:

### Priority 1: Role-Based Username Mapping (NEW!)
Maps Laravel roles to specific WordPress usernames.

**Example:**
- Laravel user has role: `dev`
- Plugin looks for WordPress user: `sas_dev`
- If found, logs in that user

### Priority 2: Username Match
Uses the username provided in the token.

**Example:**
- Token contains: `username: 'john_doe'`
- Plugin looks for WordPress user: `john_doe`

### Priority 3: Email Match
Uses the email address (most common).

**Example:**
- Token contains: `email: 'john@example.com'`
- Plugin looks for WordPress user with that email

## Role Mapping Configuration

### Laravel Role → WordPress Username

Configured in `includes/sso-config.php` lines 56-62:

```php
'username_mapping' => array(
    'dev' => 'sas_dev',      // Laravel 'dev' → WordPress 'sas_dev'
    'admin' => 'sas_admin',  // Laravel 'admin' → WordPress 'sas_admin'
    'tech' => 'sas_tech',    // Laravel 'tech' → WordPress 'sas_tech'
    'seo' => 'sas_seo',      // Laravel 'seo' → WordPress 'sas_seo'
),
```

### Laravel Role → WordPress Role

Configured in `includes/sso-config.php` lines 47-55:

```php
'role_mapping' => array(
    'dev' => 'administrator',        // Laravel 'dev' → WP 'administrator'
    'admin' => 'administrator',      // Laravel 'admin' → WP 'administrator'
    'editor' => 'editor',           // Laravel 'editor' → WP 'editor'
    'author' => 'author',           // Laravel 'author' → WP 'author'
    'contributor' => 'contributor', // Laravel 'contributor' → WP 'contributor'
    'subscriber' => 'subscriber',   // Laravel 'subscriber' → WP 'subscriber'
),
```

## Your Use Case: Dev Role → sas_dev User

### Scenario
You have:
- Laravel app with user that has role: `dev`
- WordPress site with existing user: `sas_dev`

### Setup

**Step 1: Ensure WordPress User Exists**
Make sure each WordPress site has the `sas_dev` user created:
```sql
SELECT * FROM wp_users WHERE user_login = 'sas_dev';
```

**Step 2: Laravel Token Generation**
```php
public function generateSsoToken(Request $request)
{
    $user = Auth::user(); // User with 'dev' role

    $tokenData = [
        'email' => 'dev@sitesatscale.com',     // Can be any email
        'user_id' => $user->id,
        'username' => 'ignored',               // Will be ignored
        'role' => 'dev',                       // THIS is important!
        'expires_at' => now()->addMinutes(5),
    ];

    // Store token in database...

    return redirect("https://example1.com/?sas_sso_token={$token}");
}
```

**Step 3: Plugin Behavior**
1. Receives token with `role: 'dev'`
2. Checks role mapping: `'dev' => 'sas_dev'`
3. Looks for WordPress user: `sas_dev`
4. If found → logs in as `sas_dev` ✓
5. If not found → tries username, then email

## Testing Locally

### Test 1: Role-Based Mapping (Dev → sas_dev)

**Prerequisites:**
1. Create WordPress user `sas_dev` manually:
   - Go to Users > Add New
   - Username: `sas_dev`
   - Email: `dev@sitesatscale.com`
   - Role: Administrator

2. Test with token:
```
http://localhost:10004/?sas_sso_token=test_dev_token
```

**Expected Result:**
- Logs in as existing `sas_dev` user
- Does NOT create new user
- Debug log shows: "Found user by role mapping"

### Test 2: Verify in Debug Log

After testing, check `wp-content/debug.log`:
```
[SAS SSO Debug] Validating SSO token
[SAS SSO Debug] Found user by role mapping | Context: {"laravel_role":"dev","mapped_username":"sas_dev","wp_user_id":1}
```

### Test 3: Check SSO Logs Table

```sql
SELECT
    status,
    message,
    user_id,
    created_at
FROM wp_sas_sso_logs
WHERE status = 'success'
ORDER BY created_at DESC
LIMIT 5;
```

## Matching Flow Diagram

```
Token Received
     ↓
Has 'role' field?
     ↓ Yes
Check role mapping
'dev' → 'sas_dev'
     ↓
User 'sas_dev' exists?
     ↓ Yes → LOGIN ✓
     ↓ No
     ↓
Has 'username' field?
     ↓ Yes
User with that username exists?
     ↓ Yes → LOGIN ✓
     ↓ No
     ↓
User with that email exists?
     ↓ Yes → LOGIN ✓
     ↓ No
     ↓
Auto-create user
```

## Examples

### Example 1: Dev User Login (Your Case)

**Laravel Token:**
```json
{
  "email": "dev@sitesatscale.com",
  "user_id": 123,
  "username": "doesnt_matter",
  "role": "dev"
}
```

**Plugin Behavior:**
1. Checks role mapping: `dev` → `sas_dev`
2. Finds WordPress user `sas_dev`
3. Logs in as `sas_dev` ✓

### Example 2: Admin User Login

**Laravel Token:**
```json
{
  "email": "admin@example.com",
  "user_id": 456,
  "username": "john_admin",
  "role": "admin"
}
```

**Plugin Behavior:**
1. Checks role mapping: `admin` → `sas_admin`
2. Looks for WordPress user `sas_admin`
3. If not found, looks for `john_admin`
4. If not found, looks for email `admin@example.com`
5. Logs in or creates user

### Example 3: New User (No Existing Match)

**Laravel Token:**
```json
{
  "email": "newuser@example.com",
  "user_id": 789,
  "username": "newuser",
  "role": "editor"
}
```

**Plugin Behavior:**
1. Checks role mapping: no match (no 'editor' in username_mapping)
2. Looks for username `newuser`: not found
3. Looks for email `newuser@example.com`: not found
4. Creates new user `newuser` with role `editor` ✓

## Customizing Mappings

### Add More Role Mappings

Edit `includes/sso-config.php`:

```php
'username_mapping' => array(
    'dev' => 'sas_dev',
    'admin' => 'sas_admin',
    'tech' => 'sas_tech',
    'seo' => 'sas_seo',
    'marketing' => 'sas_marketing',  // Add new mapping
    'support' => 'sas_support',      // Add new mapping
),
```

### Change Role Mapping

```php
'role_mapping' => array(
    'dev' => 'administrator',
    'manager' => 'editor',           // Add new role
    'writer' => 'author',            // Add new role
),
```

## Advantages of This Approach

### ✓ Best of Both Worlds
- Uses existing WordPress users when available
- Creates new users when needed
- Flexible username per site

### ✓ Security
- Email verification still works
- User association tracked
- Audit logs maintained

### ✓ Simplicity
- Configure once in `sso-config.php`
- Works across all WordPress sites
- Easy to test and debug

## Important Notes

1. **Role-based mapping is optional**
   - If no mapping found, falls back to username/email

2. **Email should still be unique**
   - Even with username matching, provide valid email

3. **WordPress user must exist**
   - For role mapping to work, create WP users first
   - Or let plugin auto-create them

4. **Case insensitive**
   - Roles are matched case-insensitively
   - `'Dev'`, `'dev'`, `'DEV'` all work

## Recommended Setup for Your Use Case

### 1. In Each WordPress Site

Create these users manually:
```
Username: sas_dev
Email: dev@sitesatscale.com
Role: Administrator

Username: sas_tech
Email: tech@sitesatscale.com
Role: Administrator

Username: sas_seo
Email: seo@sitesatscale.com
Role: Editor
```

### 2. In Laravel App

When generating tokens:
```php
$tokenData = [
    'email' => 'dev@sitesatscale.com',
    'user_id' => $user->id,
    'username' => 'fallback_name',  // Optional fallback
    'role' => $user->role,          // 'dev', 'tech', 'seo', etc.
    'expires_at' => now()->addMinutes(5),
];
```

### 3. Plugin Will:
- Match `role: 'dev'` → WordPress user `sas_dev`
- Match `role: 'tech'` → WordPress user `sas_tech`
- Match `role: 'seo'` → WordPress user `sas_seo`
- Always logs in existing user if found
- Auto-creates only if no match found

## Summary

**Your original question:** "Can I use sas_dev user when Laravel role is dev?"

**Answer:** ✓ **Yes!**

The plugin now:
1. Checks if Laravel role is `dev`
2. Maps to WordPress username `sas_dev`
3. Logs in as that user if exists
4. Falls back to email/username if not found

This is **better than just email** because:
- Works with existing WordPress users
- Predictable username per role
- No user creation needed if user exists
- Maintains email verification as fallback

**Test it now:**
```
http://localhost:10004/?sas_sso_token=test_dev_token
```

(Make sure `sas_dev` user exists in WordPress first!)
