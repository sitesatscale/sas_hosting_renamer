<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent class redeclaration conflicts
if (class_exists('SAS_API_Endpoints')) {
    // If class exists but not instantiated, instantiate it
    if (!isset($GLOBALS['sas_api_endpoints_instance'])) {
        $GLOBALS['sas_api_endpoints_instance'] = new SAS_API_Endpoints();
    }
    return;
}

class SAS_API_Endpoints {
    
    private $namespace = 'sas-hosting/v1';
    private $allowed_users = array('amazonteam@sitesatscale.com','sitesatscale','sas_aws', 'sas_dev', 'sas_tech', 'sas_seo', 'Sites at Scale');
    
    public function __construct() {
        // Only register routes when REST API is being accessed
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // User creation endpoint (hidden)
        register_rest_route($this->namespace, '/tRpgexfNDptNgQEs/', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_admin_user'),
            'permission_callback' => array($this, 'check_secret_key')
        ));
        
        // Plugin list endpoint
        register_rest_route($this->namespace, '/plugin/list/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_plugin_list'),
            'permission_callback' => '__return_true'
        ));
        
        // Theme list endpoint
        register_rest_route($this->namespace, '/theme/list/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_theme_list'),
            'permission_callback' => '__return_true'
        ));
        
        // Core list endpoint
        register_rest_route($this->namespace, '/core/list/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_core_list'),
            'permission_callback' => '__return_true'
        ));
        
        // Site health status endpoint
        register_rest_route($this->namespace, '/site-health/status/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_health_status'),
            'permission_callback' => '__return_true'
        ));
        
        // Pages list endpoint
        register_rest_route($this->namespace, '/pages/list/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages_list'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Optional page ID to get specific page',
                    'required' => false,
                    'sanitize_callback' => 'absint'
                ),
                'page' => array(
                    'type' => 'integer',
                    'description' => 'Page number for pagination',
                    'required' => false,
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'description' => 'Number of items per page (max 100)',
                    'required' => false,
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Posts list endpoint
        register_rest_route($this->namespace, '/posts/list/', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_posts_list'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Optional post ID to get specific post',
                    'required' => false,
                    'sanitize_callback' => 'absint'
                ),
                'page' => array(
                    'type' => 'integer',
                    'description' => 'Page number for pagination',
                    'required' => false,
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'description' => 'Number of items per page (max 100)',
                    'required' => false,
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Unused JS scanner endpoint
        register_rest_route($this->namespace, '/performance/unused-js', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_unused_js'),
            'permission_callback' => '__return_true',
            'args' => array(
                'scan_type' => array(
                    'type' => 'string',
                    'enum' => array('quick', 'current', 'key_pages', 'full'),
                    'default' => 'quick',
                    'description' => 'Type of scan to perform',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'max_pages' => array(
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 200,
                    'description' => 'Maximum pages to scan for full scan',
                    'sanitize_callback' => 'absint'
                ),
                'current_url' => array(
                    'type' => 'string',
                    'description' => 'Current page URL for current scan type',
                    'sanitize_callback' => 'esc_url_raw'
                )
            )
        ));
        
        // Divi Supreme modules checker endpoint
        register_rest_route($this->namespace, '/divi/supreme-modules', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_divi_supreme_modules'),
            'permission_callback' => '__return_true'
        ));
        
        // Divi disabled sections/rows scanner endpoint
        register_rest_route($this->namespace, '/divi/scan-disabled-elements', array(
            'methods' => 'GET',
            'callback' => array($this, 'scan_divi_disabled_elements'),
            'permission_callback' => '__return_true',
            'args' => array(
                'scan_type' => array(
                    'type' => 'string',
                    'description' => 'Type of scan: quick (10 pages), full (all pages), single (one page)',
                    'default' => 'quick',
                    'enum' => array('quick', 'full', 'single'),
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'page_id' => array(
                    'type' => 'integer',
                    'description' => 'Page ID for single page scan',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
    }
    
    public function check_secret_key($request) {
        // Get auth token from request
        $params = $request->get_json_params();
        $auth_token = sanitize_text_field($params['auth_token'] ?? '');
        
        if (empty($auth_token)) {
            return false;
        }
        
        // Validate with external API
        // TODO: Change this back to https://api.sitesatscale.com for production
        $validation_url = 'http://localhost:8000/api/wordpress/auth/validate-token';
        
        $response = wp_remote_post($validation_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode(array(
                'token' => $auth_token,
                'domain' => parse_url(get_site_url(), PHP_URL_HOST),
                'action' => 'create_admin_user',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            )),
            'timeout' => 10,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            // Log error for debugging
            error_log('SAS Hosting API validation error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('SAS Hosting API validation failed with status: ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if validation passed
        if (!isset($data['valid']) || $data['valid'] !== true) {
            // Invalid token attempt
            return false;
        }
        
        return true;
    }
    
    public function create_admin_user($request) {
        $params = $request->get_json_params();

        // Extract user details
        $username = sanitize_text_field($params['username'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $password = $params['password'] ?? '';
        $auth_token = sanitize_text_field($params['auth_token'] ?? '');

        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($auth_token)) {
            return new WP_Error('missing_fields', 'Missing required fields.', array('status' => 400));
        }

        // Check if user already exists
        if (username_exists($username) || email_exists($email)) {
            return new WP_Error('user_exists', 'User already exists.', array('status' => 400));
        }

        // Create the user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return new WP_Error('user_creation_failed', 'Failed to create user.', array('status' => 500));
        }

        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        
        // Log the creation for security audit
        // Admin user created successfully
        
        // Notify the external API about successful creation
        // TODO: Change this back to https://api.sitesatscale.com for production
        wp_remote_post('http://localhost:8000/api/wordpress/auth/log-action', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'token' => $auth_token,
                'action' => 'admin_user_created',
                'domain' => parse_url(get_site_url(), PHP_URL_HOST),
                'username' => $username,
                'timestamp' => current_time('mysql')
            )),
            'timeout' => 5,
            'blocking' => false // Don't wait for response
        ));

        return rest_ensure_response(array(
            'success' => true,
            'user_id' => $user_id,
            'message' => 'Administrator user created successfully.'
        ));
    }
    
    public function get_plugin_list($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('plugin_list')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        // Check cache first
        $cache_key = 'sas_api_plugin_list';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugin_name = $plugin_data['Name'];
            $is_active = in_array($plugin_path, $active_plugins);
            $version = $plugin_data['Version'];

            $plugin_list[$plugin_name] = array(
                'status' => $is_active ? 'active' : 'inactive',
                'version' => $version
            );
        }
        
        // Cache for 5 minutes
        set_transient($cache_key, $plugin_list, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($plugin_list);
    }
    
    public function get_theme_list($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('theme_list')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        // Check cache first
        $cache_key = 'sas_api_theme_list';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        $all_themes = wp_get_themes();
        $current_theme = get_stylesheet();
        $theme_list = array();

        foreach ($all_themes as $theme_slug => $theme) {
            $theme_name = $theme->get('Name');
            $is_active = ($theme_slug === $current_theme);
            $version = $theme->get('Version');
            $parent_theme = $theme->get('Template');
            
            // Determine if it's a child theme (has a parent) or parent theme
            $theme_type = (!empty($parent_theme) && $parent_theme !== $theme_slug) ? 'child' : 'parent';

            $theme_data = array(
                'status' => $is_active ? 'active' : 'inactive',
                'version' => $version,
                'type' => $theme_type
            );
            
            // If it's a child theme, add the parent theme name
            if ($theme_type === 'child' && !empty($parent_theme)) {
                // Get parent theme object to get its name
                $parent_theme_obj = wp_get_theme($parent_theme);
                if ($parent_theme_obj->exists()) {
                    $theme_data['parent'] = $parent_theme_obj->get('Name');
                }
            }

            $theme_list[$theme_name] = $theme_data;
        }
        
        // Cache for 5 minutes
        set_transient($cache_key, $theme_list, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($theme_list);
    }
    
    public function get_core_list($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('core_list')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        global $wp_version;
        
        $core_info = array(
            'wordpress_version' => $wp_version,
            'php_version' => phpversion(),
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'max_upload_size' => size_format(wp_max_upload_size()),
            'max_post_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_directory' => wp_upload_dir()['basedir'],
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'timezone' => wp_timezone_string(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'enabled' : 'disabled'
        );
        
        return rest_ensure_response($core_info);
    }
    
    public function get_site_health_status($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('site_health')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        // Check cache first
        $cache_key = 'sas_api_site_health_status';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        // Load required files
        if (!class_exists('WP_Site_Health')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-site-health.php');
        }
        if (!function_exists('get_core_updates')) {
            require_once(ABSPATH . 'wp-admin/includes/update.php');
        }
        if (!function_exists('get_plugin_updates')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $site_health = new WP_Site_Health();
        
        // Initialize arrays for different issue types
        $critical_issues = array();
        $recommended_issues = array();
        $good_items = array();
        
        // Check WordPress version
        $core_updates = get_core_updates();
        if (!empty($core_updates) && !in_array($core_updates[0]->response, array('latest', 'development'))) {
            $recommended_issues[] = array(
                'label' => 'WordPress update available (' . $core_updates[0]->version . ')',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Performance',
                    'color' => 'blue'
                ),
                'description' => 'An update to WordPress is available.',
                'test' => 'wordpress_version'
            );
        } else {
            $good_items[] = array(
                'label' => 'Your WordPress is up to date',
                'status' => 'good',
                'test' => 'wordpress_version'
            );
        }
        
        // Check for default theme
        $has_default_theme = false;
        $default_themes = array(
            'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo',
            'twentytwentyone', 'twentytwenty', 'twentynineteen'
        );
        
        $all_themes = wp_get_themes();
        foreach ($all_themes as $theme_slug => $theme) {
            if (in_array($theme_slug, $default_themes)) {
                $has_default_theme = true;
                break;
            }
        }
        
        if (!$has_default_theme) {
            $recommended_issues[] = array(
                'label' => 'Have a default theme available',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Security',
                    'color' => 'blue'
                ),
                'description' => 'Your site does not have any default theme. Default themes are used by WordPress automatically in case of errors with your normal theme.',
                'test' => 'default_theme'
            );
        } else {
            $good_items[] = array(
                'label' => 'A default theme is available',
                'status' => 'good',
                'test' => 'default_theme'
            );
        }
        
        // Check PHP modules
        $required_modules = array('curl', 'dom', 'exif', 'fileinfo', 'hash', 'json', 'mbstring', 'mysqli', 'openssl', 'pcre', 'imagick', 'xml', 'zip');
        $missing_modules = array();
        
        foreach ($required_modules as $module) {
            if (!extension_loaded($module)) {
                $missing_modules[] = $module;
            }
        }
        
        if (!empty($missing_modules)) {
            $recommended_issues[] = array(
                'label' => 'One or more recommended modules are missing',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Performance',
                    'color' => 'blue'
                ),
                'description' => 'The following PHP modules are recommended but not installed: ' . implode(', ', $missing_modules),
                'test' => 'php_modules'
            );
        } else {
            $good_items[] = array(
                'label' => 'All recommended PHP modules are installed',
                'status' => 'good',
                'test' => 'php_modules'
            );
        }
        
        // Check plugin updates
        $plugin_updates = get_plugin_updates();
        if (!empty($plugin_updates)) {
            $recommended_issues[] = array(
                'label' => count($plugin_updates) . ' plugin update(s) available',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Security',
                    'color' => 'blue'
                ),
                'description' => 'Keeping plugins up to date is important for security and performance.',
                'test' => 'plugin_updates'
            );
        } else {
            $good_items[] = array(
                'label' => 'All plugins are up to date',
                'status' => 'good',
                'test' => 'plugin_updates'
            );
        }
        
        // Check theme updates
        $theme_updates = get_theme_updates();
        if (!empty($theme_updates)) {
            $recommended_issues[] = array(
                'label' => count($theme_updates) . ' theme update(s) available',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Security',
                    'color' => 'blue'
                ),
                'description' => 'Keeping themes up to date is important for security and performance.',
                'test' => 'theme_updates'
            );
        } else {
            $good_items[] = array(
                'label' => 'All themes are up to date',
                'status' => 'good',
                'test' => 'theme_updates'
            );
        }
        
        // Check PHP version
        $php_version = phpversion();
        if (version_compare($php_version, '7.4', '<')) {
            $critical_issues[] = array(
                'label' => 'PHP version is outdated',
                'status' => 'critical',
                'badge' => array(
                    'label' => 'Security',
                    'color' => 'red'
                ),
                'description' => 'Your PHP version (' . $php_version . ') is outdated and no longer receives security updates.',
                'test' => 'php_version'
            );
        } elseif (version_compare($php_version, '8.0', '<')) {
            $recommended_issues[] = array(
                'label' => 'PHP version should be updated',
                'status' => 'recommended',
                'badge' => array(
                    'label' => 'Performance',
                    'color' => 'blue'
                ),
                'description' => 'Your PHP version (' . $php_version . ') works but upgrading to PHP 8.0 or newer is recommended.',
                'test' => 'php_version'
            );
        } else {
            $good_items[] = array(
                'label' => 'PHP version is up to date',
                'status' => 'good',
                'test' => 'php_version'
            );
        }
        
        // Combine all tests
        $all_tests = array_merge($critical_issues, $recommended_issues, $good_items);
        
        // Calculate counts
        $critical_count = count($critical_issues);
        $recommended_count = count($recommended_issues);
        $good_count = count($good_items);
        $total_tests = count($all_tests);
        
        // Calculate health score
        $health_score = $total_tests > 0 ? round(($good_count / $total_tests) * 100) : 0;
        
        // Determine overall status
        $overall_status = 'good';
        if ($critical_count > 0) {
            $overall_status = 'critical';
        } elseif ($recommended_count > 0) {
            $overall_status = 'recommended';
        }
        
        $site_health_data = array(
            'status' => $overall_status,
            'critical_issues' => $critical_count,
            'recommended_issues' => $recommended_count,
            'good_items' => $good_count,
            'total_tests' => $total_tests,
            'health_score' => $health_score,
            'critical' => $critical_issues,
            'recommended' => $recommended_issues,
            'good' => $good_items
        );
        
        // Cache for 10 minutes
        set_transient($cache_key, $site_health_data, 10 * MINUTE_IN_SECONDS);
        
        return rest_ensure_response($site_health_data);
    }
    
    public function get_pages_list($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('pages_list')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        $page_id = $request->get_param('id');
        $paged = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Max 100 items
        
        // Create cache key based on parameters
        $cache_key = 'sas_api_pages_list_' . md5(serialize(array($page_id, $paged, $per_page)));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        // If specific page ID is requested
        if ($page_id) {
            $page = get_post($page_id);
            if (!$page || $page->post_type !== 'page') {
                return new WP_Error('page_not_found', 'Page not found', array('status' => 404));
            }
            
            $page_data = array(
                $page->post_title => array(
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'featured_image' => has_post_thumbnail($page->ID),
                    'featured_image_url' => has_post_thumbnail($page->ID) ? get_the_post_thumbnail_url($page->ID, 'medium') : null,
                    'status' => $page->post_status,
                    'date' => $page->post_date,
                    'modified' => $page->post_modified,
                    'url' => get_permalink($page->ID)
                )
            );
            
            set_transient($cache_key, $page_data, 5 * MINUTE_IN_SECONDS);
            return rest_ensure_response($page_data);
        }
        
        // Get all pages with pagination
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $query = new WP_Query($args);
        $pages_list = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $page_id = get_the_ID();
                
                $pages_list[get_the_title()] = array(
                    'id' => $page_id,
                    'title' => get_the_title(),
                    'featured_image' => has_post_thumbnail(),
                    'featured_image_url' => has_post_thumbnail() ? get_the_post_thumbnail_url($page_id, 'medium') : null,
                    'status' => get_post_status(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'modified' => get_the_modified_date('Y-m-d H:i:s'),
                    'url' => get_permalink()
                );
            }
            wp_reset_postdata();
        }
        
        // Add pagination info
        $response_data = array(
            'total' => $query->found_posts,
            'per_page' => $per_page,
            'current_page' => $paged,
            'total_pages' => $query->max_num_pages,
            'pages' => $pages_list
        );
        
        set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
        return rest_ensure_response($response_data);
    }
    
    public function get_posts_list($request) {
        // Rate limiting check
        if (!$this->check_rate_limit('posts_list')) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        $post_id = $request->get_param('id');
        $paged = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Max 100 items
        
        // Create cache key based on parameters
        $cache_key = 'sas_api_posts_list_' . md5(serialize(array($post_id, $paged, $per_page)));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        // If specific post ID is requested
        if ($post_id) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'post') {
                return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
            }
            
            $post_data = array(
                $post->post_title => array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'featured_image' => has_post_thumbnail($post->ID),
                    'featured_image_url' => has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID, 'medium') : null,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'url' => get_permalink($post->ID),
                    'categories' => wp_get_post_categories($post->ID, array('fields' => 'names')),
                    'tags' => wp_get_post_tags($post->ID, array('fields' => 'names'))
                )
            );
            
            set_transient($cache_key, $post_data, 5 * MINUTE_IN_SECONDS);
            return rest_ensure_response($post_data);
        }
        
        // Get all posts with pagination
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        $posts_list = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $posts_list[get_the_title()] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'featured_image' => has_post_thumbnail(),
                    'featured_image_url' => has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'medium') : null,
                    'status' => get_post_status(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'modified' => get_the_modified_date('Y-m-d H:i:s'),
                    'url' => get_permalink(),
                    'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
                    'tags' => wp_get_post_tags($post_id, array('fields' => 'names'))
                );
            }
            wp_reset_postdata();
        }
        
        // Add pagination info
        $response_data = array(
            'total' => $query->found_posts,
            'per_page' => $per_page,
            'current_page' => $paged,
            'total_pages' => $query->max_num_pages,
            'posts' => $posts_list
        );
        
        set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
        return rest_ensure_response($response_data);
    }
    
    public function check_unused_js($request) {
        // Rate limiting check - lower limit for resource intensive operation
        if (!$this->check_rate_limit('unused_js', 10)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded. Please try again later.', array('status' => 429));
        }
        
        $scan_type = $request->get_param('scan_type');
        $start_time = microtime(true);
        
        try {
            switch ($scan_type) {
                case 'quick':
                    $result = $this->scan_quick_registry();
                    break;
                    
                case 'current':
                    $current_url = $request->get_param('current_url');
                    if (empty($current_url)) {
                        return new WP_Error('missing_url', 'Current URL is required for current page scan', array('status' => 400));
                    }
                    $result = $this->scan_current_page($current_url);
                    break;
                    
                case 'key_pages':
                    $result = $this->scan_key_pages();
                    break;
                    
                case 'full':
                    $max_pages = $request->get_param('max_pages');
                    $result = $this->scan_full_site($max_pages);
                    break;
                    
                default:
                    $result = $this->scan_quick_registry();
            }
            
            // Add execution time
            $result['scan_metadata']['execution_time'] = round(microtime(true) - $start_time, 3) . 's';
            
            return rest_ensure_response($result);
            
        } catch (Exception $e) {
            return new WP_Error('scan_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    private function scan_quick_registry() {
        global $wp_scripts;
        
        $results = array(
            'scan_metadata' => array(
                'scan_type' => 'quick',
                'timestamp' => current_time('mysql'),
                'pages_analyzed' => 0,
                'method' => 'registry_only'
            ),
            'summary' => array(
                'total_registered' => 0,
                'enqueued' => 0,
                'not_enqueued' => 0,
                'total_size' => 0
            ),
            'scripts' => array(
                'potentially_unused' => array(),
                'definitely_used' => array()
            ),
            'recommendations' => array()
        );
        
        if (empty($wp_scripts->registered)) {
            return $results;
        }
        
        $results['summary']['total_registered'] = count($wp_scripts->registered);
        
        foreach ($wp_scripts->registered as $handle => $script) {
            if (empty($script->src)) {
                continue;
            }
            
            $is_enqueued = in_array($handle, $wp_scripts->queue ?? array());
            $file_info = $this->get_script_info($handle, $script);
            
            if ($is_enqueued) {
                $results['summary']['enqueued']++;
                $results['scripts']['definitely_used'][] = $file_info;
            } else {
                $results['summary']['not_enqueued']++;
                $results['scripts']['potentially_unused'][] = $file_info;
            }
            
            if (is_numeric($file_info['size_bytes'])) {
                $results['summary']['total_size'] += $file_info['size_bytes'];
            }
        }
        
        // Generate recommendations
        $results['recommendations'] = $this->generate_quick_recommendations($results);
        
        // Format file size
        $results['summary']['total_size_formatted'] = size_format($results['summary']['total_size']);
        
        return $results;
    }
    
    private function scan_current_page($url) {
        $results = array(
            'scan_metadata' => array(
                'scan_type' => 'current',
                'timestamp' => current_time('mysql'),
                'pages_analyzed' => 1,
                'url_scanned' => $url,
                'method' => 'page_analysis'
            ),
            'summary' => array(
                'scripts_in_page' => 0,
                'scripts_in_registry' => 0,
                'unused_in_registry' => 0,
                'external_scripts' => 0
            ),
            'scripts' => array(
                'used_on_page' => array(),
                'unused_from_registry' => array(),
                'external' => array()
            ),
            'recommendations' => array()
        );
        
        // Fetch the page
        $response = wp_safe_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'SAS Hosting Scanner/1.0',
            'redirection' => 5
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Failed to fetch page: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200 && $response_code !== 301 && $response_code !== 302) {
            return new WP_Error('http_error', sprintf('HTTP %d error when fetching page', $response_code));
        }
        
        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return new WP_Error('empty_response', 'Page returned empty content');
        }
        
        // Parse scripts from HTML
        $page_scripts = $this->parse_scripts_from_html($html);
        $results['summary']['scripts_in_page'] = count($page_scripts);
        
        // Get registered scripts
        global $wp_scripts;
        $registered_handles = array();
        
        if (!empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (!empty($script->src)) {
                    $registered_handles[$this->normalize_url($script->src)] = array(
                        'handle' => $handle,
                        'script' => $script
                    );
                }
            }
        }
        
        $results['summary']['scripts_in_registry'] = count($registered_handles);
        
        // Analyze which scripts are used
        foreach ($page_scripts as $script_url) {
            $normalized_url = $this->normalize_url($script_url);
            $found_in_registry = false;
            
            foreach ($registered_handles as $reg_url => $reg_data) {
                if ($this->urls_match($normalized_url, $reg_url)) {
                    $script_info = $this->get_script_info($reg_data['handle'], $reg_data['script']);
                    $script_info['found_on_page'] = true;
                    $results['scripts']['used_on_page'][] = $script_info;
                    $found_in_registry = true;
                    break;
                }
            }
            
            if (!$found_in_registry) {
                $results['scripts']['external'][] = array(
                    'url' => $script_url,
                    'type' => $this->determine_script_type($script_url)
                );
                $results['summary']['external_scripts']++;
            }
        }
        
        // Find unused registered scripts
        foreach ($registered_handles as $reg_url => $reg_data) {
            $found_on_page = false;
            
            foreach ($page_scripts as $script_url) {
                if ($this->urls_match($this->normalize_url($script_url), $reg_url)) {
                    $found_on_page = true;
                    break;
                }
            }
            
            if (!$found_on_page) {
                $script_info = $this->get_script_info($reg_data['handle'], $reg_data['script']);
                $results['scripts']['unused_from_registry'][] = $script_info;
                $results['summary']['unused_in_registry']++;
            }
        }
        
        $results['recommendations'] = $this->generate_page_recommendations($results);
        
        return $results;
    }
    
    private function scan_key_pages() {
        $results = array(
            'scan_metadata' => array(
                'scan_type' => 'key_pages',
                'timestamp' => current_time('mysql'),
                'pages_analyzed' => 0,
                'method' => 'multi_page_analysis'
            ),
            'pages_scanned' => array(),
            'aggregated_data' => array(
                'all_scripts' => array(),
                'usage_frequency' => array(),
                'never_used' => array(),
                'always_used' => array()
            ),
            'summary' => array(
                'total_unique_scripts' => 0,
                'definitely_unused' => 0,
                'possibly_unused' => 0,
                'potential_savings' => 0
            ),
            'recommendations' => array()
        );
        
        // Define key pages to scan
        $pages_to_scan = $this->get_key_pages_to_scan();
        
        if (empty($pages_to_scan)) {
            return new WP_Error('no_pages', 'No pages found to scan');
        }
        
        $all_page_scripts = array();
        $script_usage_count = array();
        
        // Scan each page
        foreach ($pages_to_scan as $page_type => $page_url) {
            $page_result = $this->scan_single_page_simple($page_url);
            
            if (!is_wp_error($page_result)) {
                $results['pages_scanned'][$page_type] = array(
                    'url' => $page_url,
                    'scripts_found' => $page_result['count'],
                    'scan_time' => $page_result['scan_time']
                );
                
                $all_page_scripts[$page_type] = $page_result['scripts'];
                
                // Count script usage
                foreach ($page_result['scripts'] as $script_url) {
                    $normalized = $this->normalize_url($script_url);
                    $script_usage_count[$normalized] = ($script_usage_count[$normalized] ?? 0) + 1;
                }
                
                $results['scan_metadata']['pages_analyzed']++;
            }
        }
        
        // Get all registered scripts
        global $wp_scripts;
        $registered_scripts = array();
        
        if (!empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (!empty($script->src)) {
                    $normalized_url = $this->normalize_url($script->src);
                    $registered_scripts[$normalized_url] = $this->get_script_info($handle, $script);
                }
            }
        }
        
        // Analyze usage patterns
        foreach ($registered_scripts as $normalized_url => $script_info) {
            $usage_count = $script_usage_count[$normalized_url] ?? 0;
            $usage_percentage = $results['scan_metadata']['pages_analyzed'] > 0 
                ? round(($usage_count / $results['scan_metadata']['pages_analyzed']) * 100, 1)
                : 0;
            
            $script_info['usage_count'] = $usage_count;
            $script_info['usage_percentage'] = $usage_percentage;
            $script_info['pages_found_on'] = $usage_count . '/' . $results['scan_metadata']['pages_analyzed'];
            
            if ($usage_count === 0) {
                $results['aggregated_data']['never_used'][] = $script_info;
                $results['summary']['definitely_unused']++;
                
                if (is_numeric($script_info['size_bytes'])) {
                    $results['summary']['potential_savings'] += $script_info['size_bytes'];
                }
            } elseif ($usage_percentage < 30) {
                $results['aggregated_data']['usage_frequency']['rarely_used'][] = $script_info;
                $results['summary']['possibly_unused']++;
            } elseif ($usage_percentage >= 80) {
                $results['aggregated_data']['always_used'][] = $script_info;
            } else {
                $results['aggregated_data']['usage_frequency']['sometimes_used'][] = $script_info;
            }
        }
        
        $results['summary']['total_unique_scripts'] = count($registered_scripts);
        $results['summary']['potential_savings_formatted'] = size_format($results['summary']['potential_savings']);
        
        $results['recommendations'] = $this->generate_key_pages_recommendations($results);
        
        return $results;
    }
    
    private function scan_full_site($max_pages) {
        // Check if we should run this as a background job
        if ($max_pages > 20) {
            return $this->schedule_background_scan($max_pages);
        }
        
        $start_time = microtime(true);
        $results = array(
            'scan_metadata' => array(
                'scan_type' => 'full',
                'timestamp' => current_time('mysql'),
                'pages_analyzed' => 0,
                'max_pages' => $max_pages,
                'method' => 'full_site_crawl'
            ),
            'summary' => array(
                'total_urls_found' => 0,
                'pages_scanned' => 0,
                'unique_scripts' => 0,
                'definitely_unused' => 0,
                'optimization_score' => 0
            ),
            'unused_scripts' => array(),
            'script_coverage' => array(),
            'recommendations' => array(),
            'errors' => array()
        );
        
        // Get all site URLs
        $site_urls = $this->get_all_site_urls($max_pages);
        $results['summary']['total_urls_found'] = count($site_urls);
        
        if (empty($site_urls)) {
            return new WP_Error('no_urls', 'No URLs found to scan');
        }
        
        $all_scripts_usage = array();
        $scan_errors = array();
        
        // Scan each URL
        foreach (array_slice($site_urls, 0, $max_pages) as $url) {
            $page_result = $this->scan_single_page_simple($url);
            
            if (!is_wp_error($page_result)) {
                $results['summary']['pages_scanned']++;
                
                foreach ($page_result['scripts'] as $script_url) {
                    $normalized = $this->normalize_url($script_url);
                    if (!isset($all_scripts_usage[$normalized])) {
                        $all_scripts_usage[$normalized] = array(
                            'url' => $script_url,
                            'pages' => array(),
                            'count' => 0
                        );
                    }
                    $all_scripts_usage[$normalized]['pages'][] = $url;
                    $all_scripts_usage[$normalized]['count']++;
                }
            } else {
                $scan_errors[] = array(
                    'url' => $url,
                    'error' => $page_result->get_error_message()
                );
            }
            
            // Prevent timeout
            if ((microtime(true) - $start_time ?? 0) > 25) {
                $results['scan_metadata']['stopped_reason'] = 'timeout_prevention';
                break;
            }
        }
        
        // Analyze results
        global $wp_scripts;
        if (!empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (empty($script->src)) continue;
                
                $normalized_url = $this->normalize_url($script->src);
                $found_count = 0;
                
                foreach ($all_scripts_usage as $used_url => $usage_data) {
                    if ($this->urls_match($normalized_url, $used_url)) {
                        $found_count = $usage_data['count'];
                        break;
                    }
                }
                
                if ($found_count === 0) {
                    $script_info = $this->get_script_info($handle, $script);
                    $script_info['confidence'] = 'high';
                    $results['unused_scripts'][] = $script_info;
                    $results['summary']['definitely_unused']++;
                }
            }
        }
        
        $results['summary']['unique_scripts'] = count($all_scripts_usage);
        $results['errors'] = array_slice($scan_errors, 0, 10); // Limit errors
        
        // Calculate optimization score
        if ($results['summary']['unique_scripts'] > 0) {
            $used_count = $results['summary']['unique_scripts'] - $results['summary']['definitely_unused'];
            $results['summary']['optimization_score'] = round(($used_count / $results['summary']['unique_scripts']) * 100);
        }
        
        $results['recommendations'] = $this->generate_full_scan_recommendations($results);
        
        return $results;
    }
    
    // Helper methods
    private function get_script_info($handle, $script) {
        $file_path = $this->url_to_path($script->src);
        $size_bytes = 0;
        
        if ($file_path && file_exists($file_path)) {
            $size_bytes = filesize($file_path);
        }
        
        return array(
            'handle' => $handle,
            'url' => $script->src,
            'version' => $script->ver ?? 'none',
            'dependencies' => $script->deps ?? array(),
            'size' => size_format($size_bytes),
            'size_bytes' => $size_bytes,
            'type' => $this->determine_script_type($script->src),
            'location' => $this->determine_script_location($script->src)
        );
    }
    
    private function parse_scripts_from_html($html) {
        $scripts = array();
        
        // Match script tags with src attribute
        preg_match_all('/<script[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $script_url) {
                // Skip inline scripts and data URLs
                if (!empty($script_url) && strpos($script_url, 'data:') !== 0) {
                    $scripts[] = $script_url;
                }
            }
        }
        
        return array_unique($scripts);
    }
    
    private function normalize_url($url) {
        // Remove protocol
        $url = preg_replace('/^https?:\/\//', '', $url);
        
        // Remove domain for local URLs
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        $url = preg_replace('/^' . preg_quote($site_url, '/') . '/', '', $url);
        
        // Remove query strings
        $url = preg_replace('/\?.*$/', '', $url);
        
        return trim($url, '/');
    }
    
    private function urls_match($url1, $url2) {
        // Simple matching - can be improved
        return $url1 === $url2 || 
               strpos($url1, $url2) !== false || 
               strpos($url2, $url1) !== false;
    }
    
    private function determine_script_type($url) {
        if (strpos($url, 'jquery') !== false) return 'jquery';
        if (strpos($url, 'wp-includes') !== false) return 'wordpress-core';
        if (strpos($url, 'wp-content/plugins') !== false) return 'plugin';
        if (strpos($url, 'wp-content/themes') !== false) return 'theme';
        if (strpos($url, 'googleapis.com') !== false) return 'external-google';
        if (strpos($url, 'cdnjs.cloudflare.com') !== false) return 'external-cdn';
        if (preg_match('/^(https?:)?\/\//', $url)) return 'external-other';
        return 'local';
    }
    
    private function determine_script_location($url) {
        if (strpos($url, 'wp-admin') !== false) return 'admin';
        if (strpos($url, 'wp-includes') !== false) return 'core';
        if (strpos($url, 'wp-content/plugins') !== false) {
            preg_match('/wp-content\/plugins\/([^\/]+)/', $url, $matches);
            return 'plugin:' . ($matches[1] ?? 'unknown');
        }
        if (strpos($url, 'wp-content/themes') !== false) {
            preg_match('/wp-content\/themes\/([^\/]+)/', $url, $matches);
            return 'theme:' . ($matches[1] ?? 'unknown');
        }
        return 'other';
    }
    
    private function url_to_path($url) {
        if (empty($url)) return false;
        
        // Handle relative URLs
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = get_site_url() . '/' . ltrim($url, '/');
        }
        
        // Convert to path
        $site_url = get_site_url();
        $site_path = ABSPATH;
        
        if (strpos($url, $site_url) === 0) {
            return str_replace($site_url, $site_path, $url);
        }
        
        return false;
    }
    
    private function get_key_pages_to_scan() {
        $pages = array();
        
        // Homepage
        $pages['homepage'] = get_home_url();
        
        // Blog page
        $blog_page_id = get_option('page_for_posts');
        if ($blog_page_id) {
            $pages['blog'] = get_permalink($blog_page_id);
        }
        
        // Get a few recent posts
        $recent_posts = get_posts(array(
            'numberposts' => 2,
            'post_status' => 'publish'
        ));
        
        foreach ($recent_posts as $i => $post) {
            $pages['post_' . ($i + 1)] = get_permalink($post);
        }
        
        // Get a few pages
        $sample_pages = get_pages(array(
            'number' => 3,
            'sort_order' => 'DESC',
            'sort_column' => 'post_modified'
        ));
        
        foreach ($sample_pages as $i => $page) {
            $pages['page_' . ($i + 1)] = get_permalink($page);
        }
        
        // WooCommerce pages if active
        if (class_exists('WooCommerce')) {
            $shop_page_id = wc_get_page_id('shop');
            if ($shop_page_id > 0) {
                $pages['shop'] = get_permalink($shop_page_id);
            }
        }
        
        return array_unique(array_filter($pages));
    }
    
    private function scan_single_page_simple($url) {
        $start = microtime(true);
        
        $response = wp_safe_remote_get($url, array(
            'timeout' => 5,
            'sslverify' => true,
            'user-agent' => 'SAS JS Scanner/1.0',
            'redirection' => 5
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf('HTTP %d error', $response_code));
        }
        
        $html = wp_remote_retrieve_body($response);
        $scripts = $this->parse_scripts_from_html($html);
        
        return array(
            'scripts' => $scripts,
            'count' => count($scripts),
            'scan_time' => round(microtime(true) - $start, 3)
        );
    }
    
    private function get_all_site_urls($limit) {
        $urls = array();
        
        // Get homepage
        $urls[] = get_home_url();
        
        // Get all published posts
        $posts = get_posts(array(
            'numberposts' => min($limit / 2, 100),
            'post_status' => 'publish',
            'post_type' => array('post', 'page')
        ));
        
        foreach ($posts as $post) {
            $urls[] = get_permalink($post);
        }
        
        // Get archive pages
        $urls[] = get_post_type_archive_link('post');
        
        // Get category pages
        $categories = get_categories(array('number' => 10));
        foreach ($categories as $category) {
            $urls[] = get_category_link($category);
        }
        
        return array_unique(array_filter($urls));
    }
    
    private function schedule_background_scan($max_pages) {
        $scan_id = 'scan_' . wp_generate_password(12, false);
        
        // Store scan request
        set_transient('sas_js_scan_' . $scan_id, array(
            'status' => 'pending',
            'max_pages' => $max_pages,
            'started_at' => current_time('mysql')
        ), HOUR_IN_SECONDS);
        
        // Schedule the scan
        wp_schedule_single_event(time() + 5, 'sas_run_js_scan', array($scan_id));
        
        return array(
            'scan_id' => $scan_id,
            'status' => 'scheduled',
            'message' => 'Full site scan has been scheduled. Check back in a few minutes.',
            'check_status_url' => rest_url($this->namespace . '/performance/scan-status/' . $scan_id)
        );
    }
    
    // Recommendation generators
    private function generate_quick_recommendations($results) {
        $recommendations = array();
        
        if ($results['summary']['not_enqueued'] > 5) {
            $recommendations[] = array(
                'type' => 'optimization',
                'priority' => 'medium',
                'message' => sprintf('%d scripts are registered but not enqueued. Consider removing unused registrations.', $results['summary']['not_enqueued']),
                'action' => 'Run key_pages scan for more accurate analysis'
            );
        }
        
        if ($results['summary']['total_size'] > 1048576) { // 1MB
            $recommendations[] = array(
                'type' => 'performance',
                'priority' => 'high',
                'message' => sprintf('Total JavaScript size is %s. Consider optimization.', size_format($results['summary']['total_size'])),
                'action' => 'Review large scripts and implement code splitting'
            );
        }
        
        return $recommendations;
    }
    
    private function generate_page_recommendations($results) {
        $recommendations = array();
        
        if ($results['summary']['unused_in_registry'] > 0) {
            $recommendations[] = array(
                'type' => 'cleanup',
                'priority' => 'low',
                'message' => sprintf('%d registered scripts not used on this page', $results['summary']['unused_in_registry']),
                'action' => 'These may be used on other pages - run key_pages scan to verify'
            );
        }
        
        if ($results['summary']['external_scripts'] > 10) {
            $recommendations[] = array(
                'type' => 'performance',
                'priority' => 'medium',
                'message' => sprintf('%d external scripts detected', $results['summary']['external_scripts']),
                'action' => 'Consider hosting critical scripts locally'
            );
        }
        
        return $recommendations;
    }
    
    private function generate_key_pages_recommendations($results) {
        $recommendations = array();
        
        if ($results['summary']['definitely_unused'] > 0) {
            $recommendations[] = array(
                'type' => 'cleanup',
                'priority' => 'high',
                'message' => sprintf('%d scripts never used across key pages (%s potential savings)', 
                    $results['summary']['definitely_unused'],
                    $results['summary']['potential_savings_formatted']
                ),
                'action' => 'Safe to dequeue these scripts',
                'scripts' => array_slice(array_column($results['aggregated_data']['never_used'], 'handle'), 0, 5)
            );
        }
        
        if (!empty($results['aggregated_data']['usage_frequency']['rarely_used'])) {
            $recommendations[] = array(
                'type' => 'optimization',
                'priority' => 'medium',
                'message' => sprintf('%d scripts rarely used (less than 30%% of pages)', count($results['aggregated_data']['usage_frequency']['rarely_used'])),
                'action' => 'Consider conditional loading for these scripts'
            );
        }
        
        return $recommendations;
    }
    
    private function generate_full_scan_recommendations($results) {
        $recommendations = array();
        
        if ($results['summary']['optimization_score'] < 70) {
            $recommendations[] = array(
                'type' => 'critical',
                'priority' => 'high',
                'message' => sprintf('Optimization score: %d%% - Significant improvements possible', $results['summary']['optimization_score']),
                'action' => 'Review and remove unused scripts'
            );
        }
        
        if (!empty($results['errors']) && count($results['errors']) > 5) {
            $recommendations[] = array(
                'type' => 'warning',
                'priority' => 'medium',
                'message' => sprintf('%d pages had scan errors', count($results['errors'])),
                'action' => 'Some pages may be inaccessible or have issues'
            );
        }
        
        return $recommendations;
    }
    
    public function check_divi_supreme_modules($request) {
        try {
            // Check if Divi Supreme plugin is active
            if (!function_exists('is_plugin_active')) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            // Common Divi Supreme plugin paths
            $possible_plugins = array(
                'supreme-modules-pro-for-divi/supreme-modules-pro-for-divi.php',
                'supreme-modules-for-divi/supreme-modules-for-divi.php',
                'divi-supreme/divi-supreme.php',
                'divi-supreme-pro/divi-supreme-pro.php'
            );
            
            $plugin_found = false;
            
            // Check if any of the possible plugins is active
            foreach ($possible_plugins as $plugin) {
                if (is_plugin_active($plugin)) {
                    $plugin_found = true;
                    break;
                }
            }
            
            // Fallback: Check by class existence
            if (!$plugin_found) {
                $plugin_found = class_exists('Divi_Supreme') || class_exists('DiviSupreme');
            }
            
            // Return early if no Divi Supreme plugin found
            if (!$plugin_found) {
                return rest_ensure_response(array(
                    'message' => 'Divi Supreme plugin is not detected on this site.',
                    'active_modules' => array()
                ));
            }
            
            // Get Divi Supreme module settings
            $modules_settings = get_option('dsm_modules', false);
            
            // Check if settings have been saved
            $settings_configured = ($modules_settings !== false && is_array($modules_settings) && !empty($modules_settings));
            
            // Ensure modules_settings is an array
            if (!is_array($modules_settings)) {
                $modules_settings = array();
            }
            
            // Get all available modules
            $all_modules = $this->get_divi_supreme_modules_list();
            
            // Initialize active modules array
            $active_modules = array();
            
            // Process each module
            foreach ($all_modules as $module_key => $module_name) {
                // Sanitize module key
                $module_key = sanitize_key($module_key);
                
                // Convert module key to settings format (dsm_advanced_tabs -> AdvancedTabs)
                $settings_key = str_replace('dsm_', '', $module_key);
                $settings_key = str_replace('_', '', ucwords($settings_key, '_'));
                
                // Check if module is active
                if (isset($modules_settings[$settings_key]) && $modules_settings[$settings_key] === 'on') {
                    $active_modules[] = array(
                        'name' => sanitize_text_field($module_name),
                        'key' => $module_key
                    );
                }
            }
            
            // Build response
            if (!$settings_configured) {
                $response = array(
                    'status' => 'not_configured',
                    'message' => 'Divi Supreme settings have not been saved yet. Module status may not be accurate.',
                    'active_modules' => array(),
                    'note' => 'Please save the Divi Supreme module settings at least once to get accurate results.'
                );
            } elseif (empty($active_modules)) {
                $response = array(
                    'status' => 'configured',
                    'message' => 'No active Divi Supreme modules found. All modules are currently disabled.',
                    'active_modules' => array()
                );
            } else {
                $response = array(
                    'status' => 'configured',
                    'message' => sprintf('%d active Divi Supreme module%s found.', count($active_modules), count($active_modules) === 1 ? '' : 's'),
                    'active_modules' => $active_modules
                );
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            // Handle any errors gracefully
            return new WP_Error(
                'divi_supreme_error',
                'An error occurred while checking Divi Supreme modules.',
                array('status' => 500)
            );
        }
    }
    
    private function get_divi_supreme_modules_dynamically() {
        $modules = array();
        
        // Method 1: Check if DSM modules are registered in ET Builder
        if (class_exists('ET_Builder_Element')) {
            $all_modules = ET_Builder_Element::get_modules();
            
            foreach ($all_modules as $module_slug => $module_class) {
                // Filter only DSM modules
                if (strpos($module_slug, 'dsm_') === 0 || strpos($module_class, 'DSM_') === 0) {
                    // Try to get the module instance to get its name
                    if (class_exists($module_class)) {
                        try {
                            $module_instance = new $module_class();
                            $module_name = method_exists($module_instance, 'get_name') ? $module_instance->get_name() : $module_slug;
                            $modules[$module_slug] = $module_name;
                        } catch (Exception $e) {
                            // If instantiation fails, use the slug
                            $modules[$module_slug] = $this->format_module_name($module_slug);
                        }
                    }
                }
            }
        }
        
        // Method 2: Check Divi Supreme's own module registry
        if (empty($modules) && defined('DSM_VERSION')) {
            // Try to access DSM's module list directly
            global $dsm_modules;
            if (!empty($dsm_modules) && is_array($dsm_modules)) {
                foreach ($dsm_modules as $module_key => $module_data) {
                    $modules[$module_key] = isset($module_data['name']) ? $module_data['name'] : $this->format_module_name($module_key);
                }
            }
        }
        
        // Method 3: Check the options table for all DSM settings
        if (empty($modules)) {
            global $wpdb;
            $dsm_options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value 
                    FROM {$wpdb->prefix}options 
                    WHERE option_name LIKE %s 
                    AND option_name NOT LIKE %s",
                    'dsm\_%',
                    '%transient%'
                ),
                ARRAY_A
            );
            
            // Parse the settings to find module references
            foreach ($dsm_options as $option) {
                if ($option['option_name'] === 'dsm_settings_modules') {
                    $settings = maybe_unserialize($option['option_value']);
                    if (is_array($settings)) {
                        foreach ($settings as $module_key => $status) {
                            if (!isset($modules[$module_key])) {
                                $modules[$module_key] = $this->format_module_name($module_key);
                            }
                        }
                    }
                }
            }
        }
        
        return $modules;
    }
    
    private function format_module_name($module_key) {
        // Convert dsm_some_module to Divi Some Module
        $name = str_replace('dsm_', '', $module_key);
        $name = str_replace('_', ' ', $name);
        $name = ucwords($name);
        return 'Divi ' . $name;
    }
    
    private function get_divi_supreme_modules_list() {
        // Try to get modules from the database first
        $modules_from_db = get_option('dsm_modules', array());
        
        // Ensure it's an array
        if (!is_array($modules_from_db)) {
            $modules_from_db = array();
        }
        
        // If we have modules in the database, use them
        if (!empty($modules_from_db)) {
            $modules = array();
            
            foreach ($modules_from_db as $key => $status) {
                // Validate key is a string
                if (!is_string($key)) {
                    continue;
                }
                
                // Convert CamelCase to snake_case with dsm_ prefix
                $module_key = 'dsm_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                
                // Create readable module name
                $module_name = 'Divi ' . preg_replace('/(?<!^)([A-Z])/', ' $1', $key);
                
                // Sanitize values
                $module_key = sanitize_key($module_key);
                $module_name = sanitize_text_field($module_name);
                
                $modules[$module_key] = $module_name;
            }
            
            return $modules;
        }
        
        // Fallback to static list if database is empty
        return array(
            'dsm_advanced_tabs' => 'Divi Advanced Tabs',
            'dsm_animated_gradient_text' => 'Divi Animated Gradient Text',
            'dsm_badges' => 'Divi Text Badges',
            'dsm_before_after_image' => 'Divi Before After Image',
            'dsm_blob_image' => 'Divi Blob Image',
            'dsm_block_reveal_image' => 'Divi Block Reveal Image',
            'dsm_block_reveal_text' => 'Divi Block Reveal Text',
            'dsm_breadcrumbs' => 'Divi Breadcrumbs',
            'dsm_business_hours' => 'Divi Business Hours',
            'dsm_button' => 'Divi Button',
            'dsm_buttons' => 'Divi Buttons',
            'dsm_card' => 'Divi Card',
            'dsm_card_carousel' => 'Divi Card Carousel',
            'dsm_contact_form_7' => 'Divi Contact Form 7',
            'dsm_contact_form' => 'Divi Contact Form',
            'dsm_content_timeline' => 'Divi Content Timeline',
            'dsm_content_toggle' => 'Divi Content Toggle',
            'dsm_dual_heading' => 'Divi Dual Heading',
            'dsm_embed_google_map' => 'Divi Embed Google Map',
            'dsm_facebook_comments' => 'Divi Facebook Comments',
            'dsm_facebook_embedded_comments' => 'Divi Facebook Embedded Comments',
            'dsm_facebook_embedded_posts' => 'Divi Facebook Embedded Posts',
            'dsm_facebook_embedded_video' => 'Divi Facebook Embedded Video',
            'dsm_facebook_feed' => 'Divi Facebook Feed',
            'dsm_facebook_like_button' => 'Divi Facebook Like Button',
            'dsm_facebook_page' => 'Divi Facebook Page',
            'dsm_facebook_share' => 'Divi Facebook Share Button',
            'dsm_flipbox' => 'Divi Flipbox',
            'dsm_floating_multi_images' => 'Divi Floating Multi Images',
            'dsm_glitch_text' => 'Divi Glitch Text',
            'dsm_gradient_text' => 'Divi Gradient Text',
            'dsm_icon_divider' => 'Divi Icon Divider',
            'dsm_icon_list' => 'Divi Icon List',
            'dsm_image_accordion' => 'Divi Image Accordion',
            'dsm_image_carousel' => 'Divi Image Carousel',
            'dsm_image_hotspots' => 'Divi Image Hotspots',
            'dsm_image_hover_reveal' => 'Divi Image Hover Reveal',
            'dsm_inline_svg' => 'Divi Inline SVG',
            'dsm_lottie' => 'Divi Lottie',
            'dsm_mask_text' => 'Divi Mask Text',
            'dsm_menu' => 'Divi Menu',
            'dsm_perspective_text' => 'Divi Perspective Text',
            'dsm_popup' => 'Divi Popup',
            'dsm_price_list' => 'Divi Price List',
            'dsm_scroll_image' => 'Divi Scroll Image',
            'dsm_shapes' => 'Divi Shapes',
            'dsm_star_rating' => 'Divi Star Rating',
            'dsm_step_flow' => 'Divi Step Flow',
            'dsm_text_divider' => 'Divi Text Divider',
            'dsm_text_notation' => 'Divi Text Notation',
            'dsm_tilt_image' => 'Divi Tilt Image',
            'dsm_twitter_embedded_timeline' => 'Divi Twitter Embedded Timeline',
            'dsm_twitter_embedded_tweet' => 'Divi Twitter Embedded Tweet',
            'dsm_twitter_follow_button' => 'Divi Twitter Follow Button',
            'dsm_typing_effect' => 'Divi Typing Effect'
        );
    }
    
    public function scan_divi_disabled_elements($request) {
        try {
            // Check if Divi Builder is active
            if (!defined('ET_BUILDER_VERSION') && !class_exists('ET_Builder_Element')) {
                return rest_ensure_response(array(
                    'message' => 'Divi Builder is not active on this site.',
                    'pages_scanned' => 0,
                    'disabled_elements_found' => array()
                ));
            }
            
            $scan_type = $request->get_param('scan_type');
            $page_id = $request->get_param('page_id');
            
            // Get pages to scan based on scan type
            $pages_to_scan = array();
            
            if ($scan_type === 'single' && $page_id) {
                // Single page scan
                $page = get_post($page_id);
                if ($page && in_array($page->post_type, array('page', 'post'))) {
                    $pages_to_scan[] = $page;
                }
            } elseif ($scan_type === 'full') {
                // Full site scan - all pages and posts
                $pages_to_scan = get_posts(array(
                    'post_type' => array('page', 'post'),
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
            } else {
                // Quick scan - last 10 modified pages/posts
                $pages_to_scan = get_posts(array(
                    'post_type' => array('page', 'post'),
                    'posts_per_page' => 10,
                    'post_status' => 'publish',
                    'orderby' => 'modified',
                    'order' => 'DESC'
                ));
            }
            
            $disabled_elements = array();
            
            // Scan each page for disabled Divi elements
            foreach ($pages_to_scan as $page) {
                $content = $page->post_content;
                
                // Skip if no Divi shortcodes
                if (strpos($content, '[et_pb_') === false) {
                    continue;
                }
                
                $page_disabled_elements = array();
                
                // Check for disabled sections
                preg_match_all('/\[et_pb_section[^\]]*\]/', $content, $section_matches);
                foreach ($section_matches[0] as $section) {
                    if (strpos($section, 'disabled="on"') !== false || 
                        strpos($section, 'disabled_on="on|on|on"') !== false) {
                        $page_disabled_elements[] = array(
                            'type' => 'section',
                            'element' => 'Section',
                            'status' => 'disabled'
                        );
                    }
                }
                
                // Check for disabled rows
                preg_match_all('/\[et_pb_row[^\]]*\]/', $content, $row_matches);
                foreach ($row_matches[0] as $row) {
                    if (strpos($row, 'disabled="on"') !== false || 
                        strpos($row, 'disabled_on="on|on|on"') !== false) {
                        $page_disabled_elements[] = array(
                            'type' => 'row',
                            'element' => 'Row',
                            'status' => 'disabled'
                        );
                    }
                }
                
                // Check for disabled columns
                preg_match_all('/\[et_pb_column[^\]]*\]/', $content, $column_matches);
                foreach ($column_matches[0] as $column) {
                    if (strpos($column, 'disabled="on"') !== false || 
                        strpos($column, 'disabled_on="on|on|on"') !== false) {
                        $page_disabled_elements[] = array(
                            'type' => 'column',
                            'element' => 'Column',
                            'status' => 'disabled'
                        );
                    }
                }
                
                // Check for disabled modules
                preg_match_all('/\[et_pb_[a-z_]+(?![_section|_row|_column])[^\]]*\]/', $content, $module_matches);
                foreach ($module_matches[0] as $module) {
                    if (strpos($module, 'disabled="on"') !== false || 
                        strpos($module, 'disabled_on="on|on|on"') !== false) {
                        // Extract module type
                        preg_match('/\[et_pb_([a-z_]+)/', $module, $type_match);
                        $module_type = isset($type_match[1]) ? str_replace('_', ' ', $type_match[1]) : 'module';
                        
                        $page_disabled_elements[] = array(
                            'type' => 'module',
                            'element' => ucwords($module_type),
                            'status' => 'disabled'
                        );
                    }
                }
                
                if (!empty($page_disabled_elements)) {
                    $disabled_elements[] = array(
                        'page_id' => $page->ID,
                        'page_title' => $page->post_title,
                        'page_url' => get_permalink($page->ID),
                        'disabled_elements' => $page_disabled_elements,
                        'total_disabled' => count($page_disabled_elements)
                    );
                }
            }
            
            // Prepare response
            $total_disabled = 0;
            $summary = array();
            
            foreach ($disabled_elements as $page_data) {
                $total_disabled += $page_data['total_disabled'];
                
                // Count by type
                foreach ($page_data['disabled_elements'] as $element) {
                    $type = $element['type'];
                    if (!isset($summary[$type])) {
                        $summary[$type] = 0;
                    }
                    $summary[$type]++;
                }
            }
            
            $response = array(
                'scan_type' => $scan_type,
                'pages_scanned' => count($pages_to_scan),
                'pages_with_disabled_elements' => count($disabled_elements),
                'total_disabled_elements' => $total_disabled,
                'summary' => $summary,
                'details' => $disabled_elements
            );
            
            if ($total_disabled === 0) {
                $response['message'] = 'No disabled sections, rows, or modules found.';
            } else {
                $response['message'] = sprintf(
                    'Found %d disabled element%s across %d page%s.',
                    $total_disabled,
                    $total_disabled === 1 ? '' : 's',
                    count($disabled_elements),
                    count($disabled_elements) === 1 ? '' : 's'
                );
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            return new WP_Error(
                'scan_error',
                'An error occurred while scanning for disabled elements.',
                array('status' => 500)
            );
        }
    }
    
    /**
     * Check rate limiting for API endpoints
     * @param string $endpoint The endpoint being accessed
     * @return bool True if request is allowed, false if rate limit exceeded
     */
    private function check_rate_limit($endpoint, $max_requests = 30) {
        // Get IP address with fallback methods
        $ip = $this->get_client_ip();
        
        // For REST API requests, also consider the REST route
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $route = $_SERVER['REQUEST_URI'] ?? '';
            $ip = $ip . '_' . md5($route);
        }
        
        $transient_key = 'sas_api_rate_limit_' . md5($endpoint . '_' . $ip);
        
        $requests = get_transient($transient_key);
        if ($requests === false) {
            set_transient($transient_key, 1, MINUTE_IN_SECONDS);
            return true;
        }
        
        // Allow specified requests per minute
        if ($requests >= $max_requests) {
            // Add rate limit headers
            if (!headers_sent()) {
                header('X-RateLimit-Limit: ' . $max_requests);
                header('X-RateLimit-Remaining: 0');
                header('X-RateLimit-Reset: ' . (time() + MINUTE_IN_SECONDS));
                header('Retry-After: ' . MINUTE_IN_SECONDS);
            }
            return false;
        }
        
        set_transient($transient_key, $requests + 1, MINUTE_IN_SECONDS);
        
        // Add rate limit headers for successful requests
        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $max_requests);
            header('X-RateLimit-Remaining: ' . ($max_requests - $requests - 1));
            header('X-RateLimit-Reset: ' . (time() + MINUTE_IN_SECONDS));
        }
        
        return true;
    }
    
    /**
     * Get client IP address with multiple fallback methods
     * @return string The client IP address
     */
    private function get_client_ip() {
        // Check for various IP headers (in order of reliability)
        $ip_headers = array(
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback for local development
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        
        // Last resort - use a combination of factors for uniqueness
        return 'unknown_' . md5(
            ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
            ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
            ($_SERVER['SERVER_PORT'] ?? '')
        );
    }
    
    /**
     * Validate request origin
     * @return bool True if request origin is valid
     */
    private function validate_request_origin() {
        // Check referrer if available
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referrer)) {
            $allowed_domains = array(
                parse_url(get_site_url(), PHP_URL_HOST),
                'sitesatscale.com',
                'api.sitesatscale.com'
            );
            
            $referrer_host = parse_url($referrer, PHP_URL_HOST);
            foreach ($allowed_domains as $domain) {
                if (strpos($referrer_host, $domain) !== false) {
                    return true;
                }
            }
        }
        
        // Allow requests without referrer (direct API calls)
        return true;
    }
    
}

// Initialize the API endpoints with singleton pattern
if (!isset($GLOBALS['sas_api_endpoints_instance'])) {
    $GLOBALS['sas_api_endpoints_instance'] = new SAS_API_Endpoints();
    
    // Clear cache hooks with unique priority
    $priority_offset = 999990;
    
    // Use named functions to prevent duplicate hooks
    if (!has_action('activated_plugin', 'sas_hosting_clear_plugin_cache')) {
        function sas_hosting_clear_plugin_cache() {
            delete_transient('sas_api_plugin_list');
            delete_transient('sas_api_site_health_status');
        }
        add_action('activated_plugin', 'sas_hosting_clear_plugin_cache', $priority_offset);
        add_action('deactivated_plugin', 'sas_hosting_clear_plugin_cache', $priority_offset);
    }
    
    if (!has_action('after_switch_theme', 'sas_hosting_clear_theme_cache')) {
        function sas_hosting_clear_theme_cache() {
            delete_transient('sas_api_theme_list');
            delete_transient('sas_api_site_health_status');
        }
        add_action('after_switch_theme', 'sas_hosting_clear_theme_cache', $priority_offset);
    }
    
    if (!has_action('_core_updated_successfully', 'sas_hosting_clear_core_cache')) {
        function sas_hosting_clear_core_cache() {
            delete_transient('sas_api_site_health_status');
        }
        add_action('_core_updated_successfully', 'sas_hosting_clear_core_cache', $priority_offset);
    }
    
    if (!has_action('save_post', 'sas_hosting_clear_post_cache')) {
        function sas_hosting_clear_post_cache($post_id) {
            // Clear all pages cache
            global $wpdb;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_sas_api_pages_list_%'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_sas_api_posts_list_%'));
        }
        add_action('save_post', 'sas_hosting_clear_post_cache', $priority_offset);
    }
}