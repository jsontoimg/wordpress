<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'jsontoimg_api_key' );
delete_option( 'jsontoimg_base_url' );
delete_option( 'jsontoimg_cache_version' );
