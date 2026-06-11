<?php
/**
 * Plugin Name: Toastmasters Portal
 * Description: Member dashboard, admin member management, VP Education scheduling, and meeting agenda tools for a Toastmasters club.
 * Version: 0.1.0
 * Author: Toastmasters Club of Pune North West
 * Text Domain: toastmasters-portal
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TMP_PLUGIN_FILE', __FILE__);
define('TMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TMP_VERSION', '0.4.1');

require_once TMP_PLUGIN_DIR . 'includes/class-tmp-activator.php';
require_once TMP_PLUGIN_DIR . 'includes/class-tmp-repository.php';
require_once TMP_PLUGIN_DIR . 'includes/class-tmp-shortcodes.php';
require_once TMP_PLUGIN_DIR . 'includes/class-tmp-rest-api.php';

register_activation_hook(__FILE__, array('TMP_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('TMP_Activator', 'deactivate'));

add_action('init', function () {
    TMP_Activator::maybe_upgrade();
    TMP_Shortcodes::init();
    TMP_REST_API::init();
});

// ── Custom login page ──────────────────────────────────────────────────────

// Priority 999 so we run after any other plugin that also filters login_url.
// Use string concatenation instead of add_query_arg to avoid double-encoding.
add_filter('login_url', function ($url, $redirect, $force_reauth) {
    $base = home_url('/member-login/');
    return $redirect ? $base . '?redirect_to=' . rawurlencode($redirect) : $base;
}, 999, 3);

// login_url filter (priority 999 above) already routes all code-generated login links
// to /member-login/. We intentionally do NOT intercept direct wp-login.php visits —
// doing so can create redirect loops with login-rename plugins (WPS Hide Login, etc.)
// and prevents WP admin from using the native login form.

// ── Admin: Google OAuth credential settings ────────────────────────────────

add_action('admin_menu', function () {
    add_options_page('Toastmasters Settings', 'Toastmasters', 'manage_options', 'tmp-settings', 'tmp_render_settings_page');
});

add_action('admin_init', function () {
    register_setting('tmp_options', 'tmp_google_client_id',     ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('tmp_options', 'tmp_google_client_secret', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_section('tmp_google_section', 'Google OAuth Login', null, 'tmp-settings');

    add_settings_field('tmp_google_client_id', 'Client ID', function () {
        printf(
            '<input type="text" name="tmp_google_client_id" value="%s" class="regular-text">',
            esc_attr(get_option('tmp_google_client_id', ''))
        );
    }, 'tmp-settings', 'tmp_google_section');

    add_settings_field('tmp_google_client_secret', 'Client Secret', function () {
        printf(
            '<input type="password" name="tmp_google_client_secret" value="%s" class="regular-text">',
            esc_attr(get_option('tmp_google_client_secret', ''))
        );
        echo '<p class="description">Register this callback URL in Google Cloud Console → Authorized redirect URIs:<br>'
            . '<code>' . esc_html(rest_url('toastmasters/v1/auth/google/callback')) . '</code></p>';
    }, 'tmp-settings', 'tmp_google_section');
});

function tmp_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Toastmasters Portal Settings</h1>'
        . '<form method="post" action="options.php">';
    settings_fields('tmp_options');
    do_settings_sections('tmp-settings');
    submit_button();
    echo '</form></div>';
}
