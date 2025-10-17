<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSO Configuration Helper
 * Manages environment-specific SSO settings
 */
class SAS_SSO_Config {

    private static $instance = null;

    // Configuration settings
    private $settings = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_settings();
    }

    /**
     * Load configuration settings
     */
    private function load_settings() {
        // Detect environment
        $is_local = $this->is_local_environment();

        // Base configuration
        $this->settings = array(
            'environment' => $is_local ? 'local' : 'production',
            'provider_url' => $this->get_provider_url($is_local),
            'token_lifetime' => 300, // 5 minutes in seconds
            'rate_limit_login' => 10, // attempts per minute
            'rate_limit_validate' => 20, // attempts per minute
            'auto_create_users' => true, // Auto-create users if not exists
            'default_role' => 'subscriber', // Default role for new users
            'debug_mode' => $is_local, // Enable verbose logging in local
            'ssl_verify' => !$is_local, // Disable SSL verification in local
            'role_mapping' => array(
                // Map Laravel roles to WordPress roles
                'dev' => 'administrator',
                'server' => 'administrator',
                'editor' => 'editor',
                'author' => 'author',
                'contributor' => 'contributor',
                'subscriber' => 'subscriber',
            ),
            'username_mapping' => array(
                // Map Laravel roles to WordPress usernames (for existing users)
                'dev' => 'sas_dev',
                'server' => 'sas_server',
                'tech' => 'sas_tech',
                'seo' => 'sas_seo',
            ),
        );

        // Allow customization via WordPress options
        $custom_settings = get_option('sas_sso_config', array());
        $this->settings = array_merge($this->settings, $custom_settings);
    }

    /**
     * Check if running in local environment
     */
    private function is_local_environment() {
        $site_url = get_site_url();

        // Check for common local development indicators
        $local_indicators = array(
            'localhost',
            '.local',
            '127.0.0.1',
            '::1',
            '.test',
            '.dev',
            '192.168.',
            '10.0.',
        );

        foreach ($local_indicators as $indicator) {
            if (strpos($site_url, $indicator) !== false) {
                return true;
            }
        }

        // Check for WP_DEBUG constant
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        return false;
    }

    /**
     * Get provider URL based on environment
     */
    private function get_provider_url($is_local) {
        // Check for custom override in wp-config.php
        if (defined('SAS_SSO_PROVIDER_URL')) {
            return constant('SAS_SSO_PROVIDER_URL');
        }

        // Production URL
        $production_url = 'https://annotation.sitesatscale.com';

        // For local development, you can use localhost Laravel or mock
        if ($is_local) {
            // Option 1: Use local Laravel app (ACTIVE for testing)
            return 'http://localhost:8000';

            // Option 2: Use local mock server (uncomment to use)
            // return get_site_url() . '/test-sso-mock.php';

            // Option 3: Use production (uncomment for testing against live Laravel)
            // return $production_url;
        }

        return $production_url;
    }

    /**
     * Get a configuration value
     */
    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Set a configuration value
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;

        // Save to database
        $custom_settings = get_option('sas_sso_config', array());
        $custom_settings[$key] = $value;
        update_option('sas_sso_config', $custom_settings);
    }

    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Get validation endpoint URL
     */
    public function get_validation_endpoint() {
        $provider_url = $this->get('provider_url');

        // For local mock server, use query parameter instead of path
        if (strpos($provider_url, 'test-sso-mock.php') !== false) {
            return $provider_url . '?endpoint=validate-sso-token';
        }

        return $provider_url . '/api/wordpress/auth/validate-token';
    }

    /**
     * Get login log endpoint URL
     */
    public function get_login_log_endpoint() {
        $provider_url = $this->get('provider_url');

        // For local mock server, use query parameter instead of path
        if (strpos($provider_url, 'test-sso-mock.php') !== false) {
            return $provider_url . '?endpoint=log-sso-login';
        }

        return $provider_url . '/api/wordpress/auth/log-login';
    }

    /**
     * Get logout log endpoint URL
     */
    public function get_logout_log_endpoint() {
        $provider_url = $this->get('provider_url');

        // For local mock server, use query parameter instead of path
        if (strpos($provider_url, 'test-sso-mock.php') !== false) {
            return $provider_url . '?endpoint=log-sso-logout';
        }

        return $provider_url . '/api/wordpress/auth/log-logout';
    }

    /**
     * Get token validation endpoint (for admin user creation)
     */
    public function get_admin_token_endpoint() {
        $provider_url = $this->get('provider_url');

        // For local mock server, use query parameter instead of path
        if (strpos($provider_url, 'test-sso-mock.php') !== false) {
            return $provider_url . '?endpoint=validate-token';
        }

        return $provider_url . '/api/wordpress/auth/validate-token';
    }

    /**
     * Get log action endpoint (for admin user creation)
     */
    public function get_log_action_endpoint() {
        $provider_url = $this->get('provider_url');

        // For local mock server, use query parameter instead of path
        if (strpos($provider_url, 'test-sso-mock.php') !== false) {
            return $provider_url . '?endpoint=log-action';
        }

        return $provider_url . '/api/wordpress/auth/log-action';
    }

    /**
     * Map Laravel role to WordPress role
     */
    public function map_role($laravel_role) {
        $role_mapping = $this->get('role_mapping', array());
        $laravel_role_lower = strtolower($laravel_role);

        if (isset($role_mapping[$laravel_role_lower])) {
            return $role_mapping[$laravel_role_lower];
        }

        // Default fallback
        return $this->get('default_role', 'subscriber');
    }

    /**
     * Get WordPress username for Laravel role (for existing user matching)
     */
    public function get_username_for_role($laravel_role) {
        $username_mapping = $this->get('username_mapping', array());
        $laravel_role_lower = strtolower($laravel_role);

        if (isset($username_mapping[$laravel_role_lower])) {
            return $username_mapping[$laravel_role_lower];
        }

        return null;
    }

    /**
     * Check if in debug mode
     */
    public function is_debug_mode() {
        return (bool) $this->get('debug_mode', false);
    }

    /**
     * Check if SSL verification is enabled
     */
    public function is_ssl_verify() {
        return (bool) $this->get('ssl_verify', true);
    }

    /**
     * Log debug message
     */
    public function debug_log($message, $context = array()) {
        if (!$this->is_debug_mode()) {
            return;
        }

        $log_message = '[SAS SSO Debug] ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }

        error_log($log_message);
    }

    /**
     * Display admin notice about environment
     */
    public function show_environment_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $env = $this->get('environment');
        $provider_url = $this->get('provider_url');

        if ($env === 'local') {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>SAS SSO:</strong> Running in LOCAL mode.
                    Provider URL: <code><?php echo esc_html($provider_url); ?></code>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Reset to default settings
     */
    public function reset() {
        delete_option('sas_sso_config');
        $this->load_settings();
    }
}

// Initialize configuration
function sas_sso_config() {
    return SAS_SSO_Config::get_instance();
}

// Add admin notice hook
add_action('admin_notices', array(SAS_SSO_Config::get_instance(), 'show_environment_notice'));
