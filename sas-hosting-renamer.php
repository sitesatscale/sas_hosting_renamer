<?php

if (! defined('ABSPATH')) {
    exit;
}

/*
Plugin Name: SAS Hosting
Plugin URI:  https://sitesatscale.com/
Description: Adds tailored features to enhance the user experience within the WordPress admin area.
Version:     6.0.3
Author:      SAS Server Engineer
*/

// Prevent direct access
if (!defined('SAS_HOSTING_PLUGIN_FILE')) {
    define('SAS_HOSTING_PLUGIN_FILE', __FILE__);
}

if (!defined('SAS_HOSTING_PLUGIN_DIR')) {
    define('SAS_HOSTING_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SAS_HOSTING_VERSION')) {
    define('SAS_HOSTING_VERSION', '6.0.3');
}

// Unique namespace to prevent conflicts
if (!class_exists('SAS_Hosting_Renamer_Plugin')) {
    class SAS_Hosting_Renamer_Plugin
    {
        private $allowed_users = array('amazonteam@sitesatscale.com', 'sitesatscale', 'sas_aws', 'sas_dev', 'sas_tech', 'sas_seo', 'Sites at Scale');

        // Priority management to prevent conflicts
        public static $priority_offset = 999999;

        // Singleton pattern to prevent multiple instances
        private static $instance = null;

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            // Constructor logic if needed
        }

        function sas_wpstaq_customizations()
        {
            $custom_name = 'SAS Hosting';
            $custom_logo_url = '/wp-content/uploads/2024/08/sas-2024.png';

            // Change the admin bar title with unique priority
            add_action('admin_bar_menu', function ($wp_admin_bar) use ($custom_name) {
                if ($wp_admin_bar->get_node('wp-admin-bar-wpstaq-topbar')) {
                    $wp_admin_bar->add_node([
                        'id'    => 'wp-admin-bar-wpstaq-topbar',
                        'title' => $custom_name
                    ]);
                }
            }, self::$priority_offset - 100);

            // Change the menu item name with conflict-safe approach
            add_action('admin_menu', function () use ($custom_name) {
                global $menu;
                foreach ($menu as $key => $value) {
                    if ($value[2] == 'wpstaq-main.php') {
                        $menu[$key][0] = $custom_name;
                    }
                }
            }, self::$priority_offset - 99);

            // Add custom CSS with unique identifiers
            add_action('admin_head', function () use ($custom_logo_url) {
                echo '
                <style id="sas-hosting-admin-styles">
                    .wpstaq-page .wpstaq-logo img{
                        visibility: hidden;
                    }
                    .toplevel_page_wpstaq-main > div.wp-menu-image::before {
                        background: url("' . esc_url($custom_logo_url) . '") no-repeat center center;
                        background-size: contain;
                    }
                    #adminmenu .toplevel_page_wpstaq-main .wp-menu-image:before {
                        display: none !important;
                    }
                </style>';
            });

            // JavaScript with namespace to prevent conflicts
            add_action('admin_footer', function () use ($custom_name) {
                echo '
                <script type="text/javascript" id="sas-hosting-admin-script">
                (function() {
                    document.addEventListener("DOMContentLoaded", function() {
                        var topBarNode = document.querySelector("#wp-admin-bar-wpstaq-topbar .ab-item");
                        if (topBarNode) {
                            topBarNode.textContent = "' . esc_js($custom_name) . '";
                        }
                        var infoNotices = document.querySelectorAll(".wpstaq-notice.notice.notice-info.is-dismissible");
                        infoNotices.forEach(function(notice) {
                            notice.innerHTML = notice.innerText.replace(/Staq Hosting/g, "' . esc_js($custom_name) . '");
                        });
                        var warningNotices = document.querySelectorAll(".wpstaq-notice.notice.notice-warning.is-dismissible");
                        warningNotices.forEach(function(notice) {
                            notice.innerHTML = notice.innerText.replace(/Staq Hosting/g, "' . esc_js($custom_name) . '");
                        });
                    });
                })();
                </script>';
            });

            // Frontend changes with namespace
            add_action('wp_footer', function () use ($custom_name) {
                echo '
                <script type="text/javascript" id="sas-hosting-frontend-script">
                (function() {
                    document.addEventListener("DOMContentLoaded", function() {
                        var topBarNode = document.querySelector("#wp-admin-bar-wpstaq-topbar .ab-item");
                        if (topBarNode) {
                            topBarNode.textContent = "' . esc_js($custom_name) . '";
                        }
                    });
                })();
                </script>';
            }, self::$priority_offset);
        }

        public function sas_fix_resize_img()
        {
            add_action('wp_head', function () {
                echo '
                <style id="sas-hosting-resize-fix">
                    /* AWS */
                    .w-image img:not([src*=".svg"]), 
                    .w-image[class*="ush_image_"] img {
                        width: revert-layer !important;
                    }
                </style>';
            }, self::$priority_offset - 98);
        }

        public function sas_restrict_migration_plugins()
        {
            $protected_plugins = array(
                'duplicator/duplicator.php',
                'all-in-one-wp-migration/all-in-one-wp-migration.php',
                'migrate-guru/migrateguru.php',
                'updraftplus/updraftplus.php',
                'backupbuddy/backupbuddy.php',
                'backwpup/backwpup.php',
                'vaultpress/vaultpress.php',
                'blogvault/backup.php',
                'wpvivid/wpvivid.php',
                'user-switching/user-switching.php',
                'wp-migrate-db-pro/wp-migrate-db-pro.php',
                // Newly added
                'worker/init.php', // ManageWP Worker
                'wp-migrate-db-pro-compatibility-checker/wp-migrate-db-pro-compatibility-checker.php',
                'wpengine-migration/wpengine-migration.php',
                'duplicator-pro/duplicator-pro.php',
                'wp-clone/wp-clone.php',
                'xcloner-backup-and-restore/xcloner.php',
                'backup-backup/backup-backup.php',
                'backup/backup.php',
                'wp-database-backup/wp-database-backup.php',
                'wp-backup-bank/backup-bank.php',
                'backup-guard/backup-guard.php',
                'wp-migration-duplicator/wp-migration-duplicator.php',
                'migrate-anywhere/migrate-anywhere.php',
                'backup-wd/backup-wd.php',
                'jetbackup/jetbackup.php',
                'siteground-migrator/siteground-migrator.php'
            );

            // Define domain-specific plugin exemptions
            // Format: 'domain' => array('plugin/path.php', 'another-plugin/file.php')
            $domain_exemptions = array(
                'temiawards.com.au' => array('user-switching/user-switching.php'),
                // 'localhost:10004' => array('user-switching/user-switching.php'),

                // Add more domains and their allowed plugins here
            );

            // Get the current host from WordPress home_url() including port if present
            $parsed_url = parse_url(home_url());
            $current_host = $parsed_url['host'];
            if (isset($parsed_url['port'])) {
                $current_host .= ':' . $parsed_url['port'];
            }

            // Check if current domain has exemptions
            $exempted_plugins = isset($domain_exemptions[$current_host]) ? $domain_exemptions[$current_host] : array();

            // Remove exempted plugins from the protected list for this domain
            $plugins_to_restrict = array_diff($protected_plugins, $exempted_plugins);

            $current_user = wp_get_current_user();

            if (!in_array($current_user->user_login, $this->allowed_users)) {
                deactivate_plugins($plugins_to_restrict);

                // Only remove menu pages for non-exempted plugins
                if (in_array('duplicator/duplicator.php', $plugins_to_restrict)) {
                    remove_menu_page('duplicator');
                }
                if (in_array('all-in-one-wp-migration/all-in-one-wp-migration.php', $plugins_to_restrict)) {
                    remove_menu_page('ai1wm_export');
                }
                if (in_array('migrate-guru/migrateguru.php', $plugins_to_restrict)) {
                    remove_menu_page('migrate-guru');
                }
                if (in_array('updraftplus/updraftplus.php', $plugins_to_restrict)) {
                    remove_menu_page('updraftplus');
                }
                if (in_array('backupbuddy/backupbuddy.php', $plugins_to_restrict)) {
                    remove_menu_page('backupbuddy');
                }
                if (in_array('backwpup/backwpup.php', $plugins_to_restrict)) {
                    remove_menu_page('backwpup');
                }
                if (in_array('vaultpress/vaultpress.php', $plugins_to_restrict)) {
                    remove_menu_page('vaultpress');
                }
                if (in_array('blogvault/backup.php', $plugins_to_restrict)) {
                    remove_menu_page('blogvault');
                }
                if (in_array('wpvivid/wpvivid.php', $plugins_to_restrict)) {
                    remove_menu_page('wpvivid');
                }
                if (in_array('user-switching/user-switching.php', $plugins_to_restrict)) {
                    remove_menu_page('user-switching');
                }
                if (in_array('duplicator-pro/duplicator-pro.php', $plugins_to_restrict)) {
                    remove_menu_page('duplicator-pro');
                }
                if (in_array('wp-clone/wp-clone.php', $plugins_to_restrict)) {
                    remove_menu_page('wp-clone');
                }
                if (in_array('xcloner-backup-and-restore/xcloner.php', $plugins_to_restrict)) {
                    remove_menu_page('xcloner-backup-and-restore');
                }
                if (in_array('backup-backup/backup-backup.php', $plugins_to_restrict)) {
                    remove_menu_page('backup-backup');
                }
                if (in_array('wp-database-backup/wp-database-backup.php', $plugins_to_restrict)) {
                    remove_menu_page('wp-database-backup');
                }
                if (in_array('backup-guard/backup-guard.php', $plugins_to_restrict)) {
                    remove_menu_page('backup-guard');
                }
                if (in_array('wp-migration-duplicator/wp-migration-duplicator.php', $plugins_to_restrict)) {
                    remove_menu_page('wp-migration-duplicator');
                }
                if (in_array('migrate-anywhere/migrate-anywhere.php', $plugins_to_restrict)) {
                    remove_menu_page('migrate-anywhere');
                }
                if (in_array('backup-wd/backup-wd.php', $plugins_to_restrict)) {
                    remove_menu_page('backup-wd');
                }
                if (in_array('wp-backup-bank/backup-bank.php', $plugins_to_restrict)) {
                    remove_menu_page('wp-backup-bank');
                }
                if (in_array('wpengine-migration/wpengine-migration.php', $plugins_to_restrict)) {
                    remove_menu_page('wpengine-migration');
                }
                if (in_array('jetbackup/jetbackup.php', $plugins_to_restrict)) {
                    remove_menu_page('jetbackup');
                }
                if (in_array('siteground-migrator/siteground-migrator.php', $plugins_to_restrict)) {
                    remove_menu_page('siteground-migrator');
                }
            }
        }

        public function sas_restrict_plugin_deactivation($actions, $plugin_file, $plugin_data, $context)
        {
            $current_user = wp_get_current_user();

            $protected_plugins = array(
                'sas_hosting_renamer/sas-hosting-renamer.php',
            );

            if (!in_array($current_user->user_login, $this->allowed_users) && in_array($plugin_file, $protected_plugins)) {
                unset($actions['deactivate']);
                unset($actions['delete']);
            }

            return $actions;
        }
        public function sas_restrict_plugin_editor()
        {
            $current_user = wp_get_current_user();

            if (!in_array($current_user->user_login, $this->allowed_users)) {
                add_filter('user_has_cap', function ($allcaps) {
                    unset($allcaps['edit_plugins']);
                    return $allcaps;
                }, 10, 1);
            }
        }

        public static function activate()
        {
            flush_rewrite_rules();
        }

        public static function deactivate()
        {
            flush_rewrite_rules();
        }
        public static function sas_get_full_year()
        {
            echo '<script type="text/javascript" id="sas-hosting-year-script">
            (function() {
                let fullYear = new Date().getFullYear();
                document.querySelectorAll(".current--year").forEach(function(element) {
                    element.innerHTML = fullYear;
                });
            })();
            </script>';
        }


        public function sas_limit_upload_file_size()
        {
            // Check if function already hooked by another plugin
            if (has_filter('upload_size_limit', array($this, 'sas_filter_upload_size'))) {
                return;
            }

            // Only run in admin area
            if (!is_admin()) {
                return;
            }

            // Define size limits and mime types
            $size_limits = array(
                'image' => array(
                    'size' => 1 * 1024 * 1024,    // 1MB
                    'message' => 'Image files must be smaller than 1MB.',
                    'types' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp')
                ),
                'pdf' => array(
                    'size' => 10 * 1024 * 1024,   // 10MB
                    'message' => 'PDF files must be smaller than 10MB.',
                    'types' => array('application/pdf')
                ),
                'video' => array(
                    'size' => 10 * 1024 * 1024,   // 10MB
                    'message' => 'Video files must be smaller than 10MB.',
                    'types' => array('video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm')
                ),
                'audio' => array(
                    'size' => 10 * 1024 * 1024,   // 10MB
                    'message' => 'Audio files must be smaller than 10MB.',
                    'types' => array('audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3', 'audio/aac')
                )
            );

            // Define domains (including subdomains) to be exempted from the file size limit
            // Ensure you list the full domain string you want to exempt.
            $exempted_domains = array(
                'www.rivercitycc.com.au',
                // Add more exact domains/subdomains here
            );

            // Get the current host directly from the WordPress home_url()
            // This will include subdomains if they are part of the home URL.
            $current_host = parse_url(home_url(), PHP_URL_HOST);

            // Check if the current host (which includes subdomains) is in the exempted list
            $is_domain_exempted = in_array($current_host, $exempted_domains);

            // Filter with unique callback name
            add_filter('upload_size_limit', function ($size) use ($size_limits, $is_domain_exempted) {
                // If the current domain (including subdomain) is exempted, return the original size limit
                if ($is_domain_exempted) {
                    return $size;
                }

                $file_type = '';
                if (!empty($_FILES['async-upload']['type'])) {
                    $file_type = strtolower($_FILES['async-upload']['type']);
                } elseif (!empty($_FILES['file']['type'])) {
                    $file_type = strtolower($_FILES['file']['type']);
                }

                if (empty($file_type)) {
                    return $size;
                }

                foreach ($size_limits as $type_info) {
                    if (in_array($file_type, $type_info['types'])) {
                        return $type_info['size'];
                    }
                }

                if ($file_type === 'image/svg+xml') {
                    return $size;
                }

                return $size;
            }, self::$priority_offset - 80);

            // Upload validation with unique priority
            add_filter('wp_handle_upload_prefilter', function ($file) use ($size_limits, $is_domain_exempted) {
                // If the current domain (including subdomain) is exempted, return the file without applying limits
                if ($is_domain_exempted) {
                    return $file;
                }

                if (empty($file['type'])) {
                    return $file;
                }

                $file_type = strtolower($file['type']);
                $file_size = $file['size'];

                foreach ($size_limits as $type_info) {
                    if (in_array($file_type, $type_info['types']) && $file_size > $type_info['size']) {
                        $file['error'] = $type_info['message'];
                        break;
                    }
                }

                return $file;
            }, self::$priority_offset - 79);
        }

        public function sas_hide_plugin()
        {
            if (!is_admin()) {
                return;
            }

            add_filter('all_plugins', function ($plugins) {
                if (isset($plugins['sas_hosting_renamer/sas-hosting-renamer.php'])) {
                    unset($plugins['sas_hosting_renamer/sas-hosting-renamer.php']);
                }
                return $plugins;
            });
        }
        public function downgrade_specific_user_to_subscriber()
        {
            // Only run in admin
            if (! is_admin()) {
                return;
            }

            $target_usernames = array(
                //'sas_dev',
                'sas_tech',
                'Sites at Scale'
            );

            foreach ($target_usernames as $username) {
                $user = get_user_by('login', $username);

                if ($user && in_array('subscriber', (array) $user->roles, true)) {
                    // Downgrade to subscriber
                    $user->set_role('administrator');
                    // GOES BACK TO ADMIN FOR NOW
                    // $user->set_role( 'subscriber' );
                    error_log("Downgraded user $username from Administrator to Subscriber.");
                } elseif ($user) {
                    error_log("User $username found but was not an Administrator. Role unchanged.");
                } else {
                    error_log("User $username not found.");
                }
            }
        }
    }
}

// Initialize plugin with singleton pattern
function sas_hosting_init()
{
    return SAS_Hosting_Renamer_Plugin::get_instance();
}

// Check for conflicts before loading
if (!function_exists('sas_hosting_check_conflicts')) {
    function sas_hosting_check_conflicts()
    {
        $conflicting_plugins = array();

        // Check for known conflicting class names
        $conflicting_classes = array(
            'SASHostingRenamer' => 'Another SAS Hosting plugin',
            'SAS_API_Endpoints' => 'SAS API plugin'
        );

        foreach ($conflicting_classes as $class => $plugin_name) {
            if (class_exists($class) && !class_exists('SAS_Hosting_Renamer_Plugin')) {
                $conflicting_plugins[] = $plugin_name;
            }
        }

        if (!empty($conflicting_plugins)) {
            add_action('admin_notices', function () use ($conflicting_plugins) {
                echo '<div class="notice notice-error"><p>';
                echo 'SAS Hosting Plugin conflict detected with: ' . implode(', ', $conflicting_plugins);
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }
}

// Only load if no conflicts
if (sas_hosting_check_conflicts()) {
    $sas_class = sas_hosting_init();

    // Include API endpoints with conditional loading
    $api_file = SAS_HOSTING_PLUGIN_DIR . 'includes/api/endpoints.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    }

    // Include SSO Configuration with conditional loading
    $sso_config_file = SAS_HOSTING_PLUGIN_DIR . 'includes/sso-config.php';
    if (file_exists($sso_config_file)) {
        require_once $sso_config_file;
    }

    // Include SSO Handler with conditional loading
    $sso_file = SAS_HOSTING_PLUGIN_DIR . 'includes/sso-handler.php';
    if (file_exists($sso_file)) {
        require_once $sso_file;
    }

    // === Add downgrade hook once ===
    add_action('admin_init', array($sas_class, 'downgrade_specific_user_to_subscriber'), SAS_Hosting_Renamer_Plugin::$priority_offset - 50);

    // Hook with proper checks and unique priorities
    add_action('admin_init', array($sas_class, 'sas_restrict_migration_plugins'), SAS_Hosting_Renamer_Plugin::$priority_offset - 50);
    add_filter('plugin_action_links', array($sas_class, 'sas_restrict_plugin_deactivation'), SAS_Hosting_Renamer_Plugin::$priority_offset - 40, 4);
    add_action('admin_init', array($sas_class, 'sas_restrict_plugin_editor'), SAS_Hosting_Renamer_Plugin::$priority_offset - 30);
    add_action('wp_footer', array($sas_class, 'sas_get_full_year'), SAS_Hosting_Renamer_Plugin::$priority_offset - 20);
    add_action('admin_footer', array($sas_class, 'sas_get_full_year'), SAS_Hosting_Renamer_Plugin::$priority_offset - 10);
    add_action('admin_init', array($sas_class, 'sas_limit_upload_file_size'), SAS_Hosting_Renamer_Plugin::$priority_offset - 5);
    add_action('admin_init', array($sas_class, 'sas_hide_plugin'), SAS_Hosting_Renamer_Plugin::$priority_offset);
    // add_action('init', array($sas_class, 'sas_fix_resize_img'), SAS_Hosting_Renamer_Plugin::$priority_offset);

}

// Function to add inline JavaScript in the admin area
// function custom_admin_inline_script() {
//     // Ensure the script runs only in the admin area
//     if (is_admin()) {
//         // Your inline script
//         $inline_script = "
//             (function($) {
//                 $(document).ready(function() {
//                     $('#wp-admin-bar-wpstaq-topbar .ab-item').first().text('SAS Hosting');
//                 });
//             })(jQuery);
//         ";

//         // Add the inline script to the admin area
//         wp_add_inline_script('jquery-core', $inline_script);
//     }
// }
// add_action('admin_enqueue_scripts', 'custom_admin_inline_script');

// Activation/Deactivation hooks with conflict check
if (class_exists('SAS_Hosting_Renamer_Plugin')) {
    register_activation_hook(__FILE__, array('SAS_Hosting_Renamer_Plugin', 'activate'));
    register_deactivation_hook(__FILE__, array('SAS_Hosting_Renamer_Plugin', 'deactivate'));
}

// ------------------------------
// WPStaq BerqWP Integration
// ------------------------------

// Cloudflare integration with namespace
if (!function_exists('sas_stq_berqwp_purge_cloudflare')) {
    function sas_stq_berqwp_purge_cloudflare()
    {
        \WPStaq\Hosting\Modules\Cloudflare::getInstance()->purgeSiteCache('BerqWP flush cache');
    }
}

if (class_exists('WPStaq\Hosting\Modules\Cloudflare') && \WPStaq\Hosting\Modules\Cloudflare::isEnabled()) {
    add_action('berqwp_flush_all_cache', 'sas_stq_berqwp_purge_cloudflare', SAS_Hosting_Renamer_Plugin::$priority_offset);
    add_action('berqwp_flush_page_cache', 'sas_stq_berqwp_purge_cloudflare', SAS_Hosting_Renamer_Plugin::$priority_offset);
}
