<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/jsontoimg
 * @since             1.0.0
 * @package           Jsontoimg
 *
 * @wordpress-plugin
 * Plugin Name:       jsontoimg
 * Plugin URI:        https://github.com/jsontoimg
 * Description:       Embed jsontoimg templates as signed images via shortcode or Gutenberg block.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Skndan
 * Author URI:        https://github.com/jsontoimg/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       jsontoimg
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'JSONTOIMG_VERSION', '1.0.0' );
define( 'JSONTOIMG_PLUGIN_FILE', __FILE__ );
define( 'JSONTOIMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-jsontoimg-activator.php
 */
function activate_jsontoimg() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jsontoimg-activator.php';
	Jsontoimg_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-jsontoimg-deactivator.php
 */
function deactivate_jsontoimg() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jsontoimg-deactivator.php';
	Jsontoimg_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_jsontoimg' );
register_deactivation_hook( __FILE__, 'deactivate_jsontoimg' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-jsontoimg.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_jsontoimg() {

	$plugin = new Jsontoimg();
	$plugin->run();

}
run_jsontoimg();
