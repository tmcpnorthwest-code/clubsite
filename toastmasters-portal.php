<?php
/**
 * Plugin Name: Toastmasters Portal
 * Description: Member dashboard, admin member management, VP Education scheduling, and meeting agenda tools for a Toastmasters club.
 * Version: 0.1.0
 * Author: SpeakWell Club
 * Text Domain: toastmasters-portal
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TMP_PLUGIN_FILE', __FILE__);
define('TMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TMP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TMP_VERSION', '0.24.2');

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
