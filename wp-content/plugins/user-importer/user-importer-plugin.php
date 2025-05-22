<?php
/*
Plugin Name: Custom User Importer
Plugin URI: Your Plugin URI
Description: A custom plugin to import users via CSV/XML with batch processing and import history.
Version: 1.0
Author: Deepak Agarwal
Author URI: Your Website
License: GPL2
*/

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

define('CUI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CUI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once CUI_PLUGIN_DIR . 'includes/class-cui-admin.php';
require_once CUI_PLUGIN_DIR . 'includes/class-cui-importer.php';
require_once CUI_PLUGIN_DIR . 'includes/class-cui-history.php';
require_once CUI_PLUGIN_DIR . 'includes/class-cui-ajax-handler.php';

// Initialize the plugin
function cui_plugin_init()
{
	new CUI_Admin();
	new CUI_AJAX_Handler();
	// Any other global initializations
}
add_action('plugins_loaded', 'cui_plugin_init');

// Register activation hook
register_activation_hook(__FILE__, 'cui_activate_plugin');
function cui_activate_plugin()
{
	CUI_History::create_table();
}

// Register deactivation hook (optional, for cleanup)
// register_deactivation_hook( __FILE__, 'cui_deactivate_plugin' );
// function cui_deactivate_plugin() {
//     // Cleanup tasks if any
// }