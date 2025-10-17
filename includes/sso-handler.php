<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAS SSO Handler
 * Handles SSO landing page and automatic login
 */
class SAS_SSO_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into WordPress init to check for SSO token
        add_action('init', array($this, 'handle_sso_request'), 1);

        // Add SSO logout handler
        add_action('wp_logout', array($this, 'handle_sso_logout'));

        // Add shortcode for SSO button
        add_shortcode('sas_sso_button', array($this, 'sso_button_shortcode'));
    }

    /**
     * Handle SSO request from URL parameters
     */
    public function handle_sso_request() {
        // Check if this is an SSO request
        if (!isset($_GET['sas_sso_token']) && !isset($_GET['sas-sso-token'])) {
            return;
        }

        // Don't process if user is already logged in (unless force_login is set)
        if (is_user_logged_in() && !isset($_GET['force_login'])) {
            $redirect_to = $this->get_redirect_url();
            wp_safe_redirect($redirect_to);
            exit;
        }

        // Get token from URL parameter (support both underscore and hyphen)
        $token = sanitize_text_field($_GET['sas_sso_token'] ?? $_GET['sas-sso-token'] ?? '');

        if (empty($token)) {
            $this->show_error_page('Invalid authentication token.');
            exit;
        }

        // Get optional redirect URL
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';

        // Process SSO login via REST API endpoint
        $login_result = $this->process_sso_login($token, $redirect_to);

        if (is_wp_error($login_result)) {
            $this->show_error_page($login_result->get_error_message());
            exit;
        }

        // Successful login - redirect
        $final_redirect = $login_result['redirect_url'] ?? admin_url();

        // Show success page with auto-redirect
        $this->show_success_page($final_redirect, $login_result);
        exit;
    }

    /**
     * Process SSO login directly (replicate the logic from endpoints.php)
     */
    private function process_sso_login($token, $redirect_to = '') {
        if (empty($token)) {
            return new WP_Error('missing_token', 'Authentication token is required.');
        }

        // Validate token with Laravel API
        $config = sas_sso_config();
        $validation_url = $config->get_validation_endpoint();

        $response = wp_remote_post($validation_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => json_encode(array(
                'token' => $token,
                'site' => get_site_url(),
            )),
            'timeout' => 15,
            'sslverify' => $config->is_ssl_verify()
        ));

        if (is_wp_error($response)) {
            return new WP_Error('sso_failed', 'Could not connect to authentication provider.');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $error_message = $data['message'] ?? 'Token validation failed';
            return new WP_Error('invalid_token', $error_message);
        }

        if (!isset($data['valid']) || $data['valid'] !== true) {
            return new WP_Error('token_invalid', 'Authentication token is invalid or expired.');
        }

        // Extract user data
        $user_data = $data['user'] ?? array();
        $user_email = sanitize_email($user_data['email'] ?? '');
        $username = sanitize_text_field($user_data['username'] ?? '');
        $laravel_role = sanitize_text_field($user_data['role'] ?? '');

        if (empty($user_email)) {
            return new WP_Error('invalid_user_data', 'Invalid user data received from authentication provider.');
        }

        // Find WordPress user
        $wp_user = false;

        // Try role-based username mapping first
        if (!empty($laravel_role)) {
            $mapped_username = $config->get_username_for_role($laravel_role);
            if ($mapped_username) {
                $wp_user = get_user_by('login', $mapped_username);
            }
        }

        // Try by username
        if (!$wp_user && !empty($username)) {
            $wp_user = get_user_by('login', $username);
        }

        // Try by email
        if (!$wp_user) {
            $wp_user = get_user_by('email', $user_email);
        }

        // Create user if not found
        if (!$wp_user && !empty($username)) {
            $wp_user_id = wp_create_user(
                $username,
                wp_generate_password(32, true, true),
                $user_email
            );

            if (is_wp_error($wp_user_id)) {
                return new WP_Error('user_creation_failed', 'Failed to create user account.');
            }

            $wp_user = new WP_User($wp_user_id);
            $wp_role = $config->map_role($laravel_role);
            $wp_user->set_role($wp_role);

            update_user_meta($wp_user_id, 'sas_sso_user', true);
        }

        if (!$wp_user) {
            return new WP_Error('user_not_found', 'User account not found on this WordPress site.');
        }

        // Log the user in
        wp_set_current_user($wp_user->ID);
        wp_set_auth_cookie($wp_user->ID, true, is_ssl());
        do_action('wp_login', $wp_user->user_login, $wp_user);

        // Update metadata
        update_user_meta($wp_user->ID, 'sas_sso_last_login', current_time('mysql'));

        // Notify Laravel about successful login
        $log_url = $config->get_login_log_endpoint();
        wp_remote_post($log_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'site' => get_site_url(),
                'email' => $wp_user->user_email,
                'wp_user_id' => $wp_user->ID,
            )),
            'timeout' => 5,
            'blocking' => false
        ));

        // Prepare redirect URL
        if (empty($redirect_to)) {
            $redirect_to = admin_url();
        }

        return array(
            'success' => true,
            'message' => 'Authentication successful.',
            'user_id' => $wp_user->ID,
            'username' => $wp_user->user_login,
            'email' => $wp_user->user_email,
            'redirect_url' => $redirect_to,
        );
    }

    /**
     * Get redirect URL from various sources
     */
    private function get_redirect_url() {
        // Priority order for redirect URL
        if (!empty($_GET['redirect_to'])) {
            return esc_url_raw($_GET['redirect_to']);
        }

        if (!empty($_REQUEST['redirect'])) {
            return esc_url_raw($_REQUEST['redirect']);
        }

        // Default to admin dashboard
        return admin_url();
    }

    /**
     * Show error page for SSO failures
     */
    private function show_error_page($message) {
        $site_name = get_bloginfo('name');
        $home_url = home_url();
        $login_url = wp_login_url();

        // Sanitize message
        $message = esc_html($message);

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Authentication Failed - <?php echo esc_html($site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 480px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                }
                .icon {
                    width: 64px;
                    height: 64px;
                    background: #fee;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 32px;
                }
                h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 12px;
                }
                .message {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .error-details {
                    background: #fee;
                    border-left: 4px solid #c33;
                    padding: 15px;
                    margin-bottom: 30px;
                    text-align: left;
                    border-radius: 4px;
                }
                .error-details strong {
                    color: #c33;
                    display: block;
                    margin-bottom: 8px;
                }
                .buttons {
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                    justify-content: center;
                }
                .button {
                    display: inline-block;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 500;
                    transition: all 0.3s;
                }
                .button-primary {
                    background: #667eea;
                    color: white;
                }
                .button-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                .button-secondary {
                    background: #f1f1f1;
                    color: #333;
                }
                .button-secondary:hover {
                    background: #e1e1e1;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">❌</div>
                <h1>Authentication Failed</h1>
                <p class="message">We couldn't log you in automatically.</p>
                <div class="error-details">
                    <strong>Error Details:</strong>
                    <?php echo $message; ?>
                </div>
                <div class="buttons">
                    <a href="<?php echo esc_url($login_url); ?>" class="button button-primary">Login Manually</a>
                    <a href="<?php echo esc_url($home_url); ?>" class="button button-secondary">Go to Homepage</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Show success page with auto-redirect
     */
    private function show_success_page($redirect_url, $user_data) {
        $site_name = get_bloginfo('name');
        $username = esc_html($user_data['username'] ?? 'User');

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta http-equiv="refresh" content="2;url=<?php echo esc_url($redirect_url); ?>">
            <title>Login Successful - <?php echo esc_html($site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 480px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                }
                .icon {
                    width: 64px;
                    height: 64px;
                    background: #d4edda;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    font-size: 32px;
                    animation: scaleIn 0.5s ease-out;
                }
                @keyframes scaleIn {
                    from {
                        transform: scale(0);
                    }
                    to {
                        transform: scale(1);
                    }
                }
                h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 12px;
                }
                .message {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .loader {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .redirect-message {
                    color: #999;
                    font-size: 14px;
                }
                .redirect-message a {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 500;
                }
                .redirect-message a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">✓</div>
                <h1>Welcome back, <?php echo $username; ?>!</h1>
                <p class="message">You have been successfully authenticated.</p>
                <div class="loader"></div>
                <p class="redirect-message">
                    Redirecting you now...<br>
                    <small>If you're not redirected, <a href="<?php echo esc_url($redirect_url); ?>">click here</a>.</small>
                </p>
            </div>
            <script>
                // Fallback redirect after 2 seconds
                setTimeout(function() {
                    window.location.href = <?php echo json_encode($redirect_url); ?>;
                }, 2000);
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Handle SSO logout
     */
    public function handle_sso_logout() {
        // Notify provider about logout
        $user = wp_get_current_user();

        if ($user->ID > 0) {
            $is_sso_user = get_user_meta($user->ID, 'sas_sso_user', true);

            if ($is_sso_user) {
                // Notify provider
                $config = sas_sso_config();
                $log_url = $config->get_logout_log_endpoint();

                wp_remote_post($log_url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode(array(
                        'site' => get_site_url(),
                        'email' => $user->user_email,
                    )),
                    'timeout' => 5,
                    'blocking' => false
                ));
            }
        }
    }

    /**
     * Shortcode to display SSO login button
     * Usage: [sas_sso_button text="Login with Web App" redirect="/dashboard/"]
     */
    public function sso_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'Login with SSO',
            'redirect' => admin_url(),
            'class' => 'sas-sso-button'
        ), $atts);

        // Generate SSO URL (this would typically come from your web app)
        $config = sas_sso_config();
        $provider_url = $config->get('provider_url');
        $sso_info_url = $provider_url . '/generate-sso-token?site=' . urlencode(get_site_url());

        $button_html = sprintf(
            '<a href="%s" class="%s" rel="nofollow">%s</a>',
            esc_url($sso_info_url),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );

        return $button_html;
    }
}

// Initialize SSO Handler
SAS_SSO_Handler::get_instance();
