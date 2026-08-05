<?php
/*
Plugin Name: Toastmasters Club Manager
Plugin URI: https://yourclub.org
Description: Speech scheduling, role allocation, mentor assignment and agenda management for Toastmasters clubs.
Version: 0.1.0
Author: Fleek Finance
*/

if (!defined('ABSPATH')) {
    exit;
}

define('TM_PLUGIN_VERSION', '0.1.0');
define('TM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TM_PLUGIN_DIR . 'includes/class-db.php';
require_once TM_PLUGIN_DIR . 'includes/class-shortcodes.php';

register_activation_hook(
    __FILE__,
    ['TM_DB', 'activate']
);

add_action('plugins_loaded', function () {

    new TM_Shortcodes();

});

require_once TM_PLUGIN_DIR . 'admin/admin-menu.php';