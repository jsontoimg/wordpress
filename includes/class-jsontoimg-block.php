<?php

/**
 * Gutenberg block registration.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 */

/**
 * Registers the jsontoimg/image dynamic block.
 *
 * @since      1.0.0
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Block {

	/**
	 * Register the block from compiled block.json.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		$block_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/js/block-build';
		$manifest  = $block_dir . '/block.json';

		if ( ! file_exists( $manifest ) ) {
			return;
		}

		register_block_type(
			$block_dir,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render: signed <img>, no API key in HTML.
	 *
	 * @since  1.0.0
	 * @param  array    $attributes Block attributes.
	 * @param  string   $content    Block inner content.
	 * @param  WP_Block $block      Block instance.
	 * @return string
	 */
	public function render( $attributes, $content = '', $block = null ) {
		unset( $content, $block );

		$attributes = wp_parse_args(
			$attributes,
			array(
				'template' => '',
				'format'   => 'png',
				'layers'   => array(),
				'alt'      => '',
				'width'    => '',
				'height'   => '',
			)
		);

		if ( '' === $attributes['template'] ) {
			return '';
		}

		$layers = jsontoimg_compact_layers( $attributes['layers'] );

		wp_enqueue_style( 'jsontoimg' );

		$html = jsontoimg_render_image(
			array(
				'template' => $attributes['template'],
				'format'   => $attributes['format'],
				'layers'   => $layers,
				'alt'      => $attributes['alt'],
				'class'    => '',
				'width'    => $attributes['width'],
				'height'   => $attributes['height'],
			)
		);

		if ( '' === $html ) {
			return '';
		}

		if ( 0 === strpos( $html, '<!--' ) ) {
			return $html;
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'jsontoimg-block',
			)
		);

		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
}
