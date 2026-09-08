<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		if ( false === get_option( 'jsontoimg_base_url' ) ) {
			add_option( 'jsontoimg_base_url', 'https://app.jsontoimg.com' );
		}

		if ( false === get_option( 'jsontoimg_cache_version' ) ) {
			add_option( 'jsontoimg_cache_version', 1, '', false );
		}
	}

}
