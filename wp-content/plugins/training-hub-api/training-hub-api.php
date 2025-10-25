<?php
/**
 * Plugin Name: Training Hub API
 * Plugin URI: https://example.com/training-hub-api
 * Description: REST API plugin for Training Hub with JWT authentication
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: training-hub-api
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TRAINING_HUB_API_PATH', plugin_dir_path(__FILE__));
define('TRAINING_HUB_API_URL', plugin_dir_url(__FILE__));

// Include files
// require_once TRAINING_HUB_API_PATH . 'includes/class-training-hub-api.php';
// require_once TRAINING_HUB_API_PATH . 'includes/class-training-hub-settings.php';
require_once TRAINING_HUB_API_PATH . 'includes/custom-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/account-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/jwt-auth.php';
require_once TRAINING_HUB_API_PATH . 'includes/verification.php';
require_once TRAINING_HUB_API_PATH . 'includes/menu-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/about-page-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/e-learning-page-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/faq-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/tailored-course-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/success-stories-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/homepage-api.php';
require_once TRAINING_HUB_API_PATH . 'includes/subscribe-api.php';
// require_once TRAINING_HUB_API_PATH . 'includes/class-training-hub-database.php';

// Customize REST API URL prefix
add_filter('rest_url_prefix', function() {
    $custom_prefix = get_option('training_hub_api_prefix');
    return $custom_prefix ?: 'api'; // Default to 'api' instead of 'wp-json'
});

// Add CORS headers
add_action('init', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
});

// Handle preflight requests
add_action('init', function() {
    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
});

// Add admin settings page
add_action('admin_menu', function() {
    add_options_page(
        'Training Hub API Settings',
        'Training Hub API',
        'manage_options',
        'training-hub-api-settings',
        'training_hub_api_settings_page'
    );
});

// Settings page HTML
function training_hub_api_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['training_hub_api_prefix'])) {
        check_admin_referer('training_hub_api_nonce');
        $prefix = sanitize_text_field($_POST['training_hub_api_prefix']);
        $prefix = preg_replace('/[^a-z0-9\-_]/', '', $prefix);
        update_option('training_hub_api_prefix', $prefix);
        echo '<div class="notice notice-success"><p>API prefix updated successfully!</p></div>';
    }

    $current_prefix = get_option('training_hub_api_prefix') ?: 'api';
    $site_url = get_site_url();
    ?>
    <div class="wrap">
        <h1>Training Hub API Settings</h1>
        <form method="post">
            <?php wp_nonce_field('training_hub_api_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="prefix">REST API URL Prefix</label></th>
                    <td>
                        <input type="text" name="training_hub_api_prefix" id="prefix" value="<?php echo esc_attr($current_prefix); ?>" class="regular-text" />
                        <p class="description">
                            Current URL: <code><?php echo esc_html($site_url); ?>/<?php echo esc_html($current_prefix); ?>/training-hub/v1/</code>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2>API Endpoints</h2>
        <p>Base URL: <code><?php echo esc_html($site_url); ?>/<?php echo esc_html($current_prefix); ?>/training-hub/v1/</code></p>

        <h3>Authentication</h3>
        <ul>
            <li><code>POST /auth/register</code> - Register new user</li>
            <li><code>POST /auth/login</code> - Login user</li>
            <li><code>POST /auth/verify</code> - Verify token</li>
            <li><code>POST /auth/refresh</code> - Refresh token</li>
        </ul>

        <h3>Account (Requires JWT Token)</h3>
        <ul>
            <li><code>GET /account/profile</code> - Get profile</li>
            <li><code>POST /account/profile</code> - Update profile</li>
            <li><code>POST /account/change-password</code> - Change password</li>
            <li><code>GET /account/settings</code> - Get settings</li>
            <li><code>POST /account/settings</code> - Update settings</li>
            <li><code>POST /account/delete</code> - Delete account</li>
        </ul>

        <h3>Subscription Management</h3>
        <ul>
            <li><code>POST /subscribe</code> - Subscribe to newsletter</li>
            <li><code>GET /subscribers</code> - Get all subscribers (admin only)</li>
            <li><code>GET /confirm-subscription</code> - Confirm subscription (public)</li>
            <li><code>POST /unsubscribe</code> - Unsubscribe from newsletter</li>
        </ul>

        <h3>WordPress REST API</h3>
        <ul>
            <li><code>GET /<?php echo esc_html($current_prefix); ?>/wp/v2/programs</code> - Get all programs</li>
            <li><code>GET /<?php echo esc_html($current_prefix); ?>/wp/v2/programs/{id}</code> - Get single program</li>
            <li><code>GET /<?php echo esc_html($current_prefix); ?>/wp/v2/program-categories</code> - Get categories</li>
            <li><code>GET /<?php echo esc_html($current_prefix); ?>/wp/v2/users</code> - Get users</li>
        </ul>
    </div>
    <?php
}

// Plugin activation
register_activation_hook(__FILE__, function() {
    // Set default API prefix if not set
    if (!get_option('training_hub_api_prefix')) {
        update_option('training_hub_api_prefix', 'api');
    }
    flush_rewrite_rules();
});

// Plugin deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
