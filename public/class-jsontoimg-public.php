<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Registers the [jsontoimg] shortcode.
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/public
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the [jsontoimg] shortcode and optional frontend style.
	 *
	 * @since 1.0.0
	 */
	public function register_shortcode() {
		add_shortcode( 'jsontoimg', array( $this, 'render_shortcode' ) );
		wp_register_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/jsontoimg-public.css',
			array(),
			$this->version
		);
	}

	/**
	 * Render a signed <img> from shortcode attributes.
	 *
	 * @since  1.0.0
	 * @param  array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'template' => '',
				'format'   => 'png',
				'alt'      => '',
				'class'    => '',
				'width'    => '',
				'height'   => '',
				'layers'   => '',
			),
			$atts,
			'jsontoimg'
		);

		$layers = array();
		if ( is_string( $atts['layers'] ) && '' !== trim( $atts['layers'] ) ) {
			$decoded = json_decode( $atts['layers'], true );
			if ( is_array( $decoded ) ) {
				$layers = $decoded;
			} elseif ( current_user_can( 'manage_options' ) ) {
				return '<!-- jsontoimg: ' . esc_html__( 'layers must be valid JSON.', 'jsontoimg' ) . ' -->';
			} else {
				return '';
			}
		}

		wp_enqueue_style( $this->plugin_name );

		return jsontoimg_render_image(
			array(
				'template' => $atts['template'],
				'format'   => $atts['format'],
				'layers'   => $layers,
				'alt'      => $atts['alt'],
				'class'    => $atts['class'],
				'width'    => $atts['width'],
				'height'   => $atts['height'],
			)
		);
	}

}
