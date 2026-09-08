<?php

/**
 * REST API routes for the block editor.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 */

/**
 * Registers jsontoimg/v1 routes used by the Gutenberg block.
 *
 * @since      1.0.0
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Rest {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NAMESPACE = 'jsontoimg/v1';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'limit'   => array(
						'type'    => 'integer',
						'default' => 100,
						'minimum' => 1,
						'maximum' => 100,
					),
					'offset'  => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
					'project' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/templates/(?P<id>[^/]+)/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_template_schema' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sign' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'template'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'format'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'layers'    => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
					'variables' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/featured-image',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_featured_image' ),
				'permission_callback' => array( $this, 'can_set_featured_image' ),
				'args'                => array(
					'postId'    => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'template'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'format'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'layers'    => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
					'alt'       => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * @since  1.0.0
	 * @return bool
	 */
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_set_featured_image( $request ) {
		$body    = $request->get_json_params();
		$post_id = isset( $body['postId'] ) ? (int) $body['postId'] : (int) $request->get_param( 'postId' );
		if ( $post_id < 1 ) {
			return new WP_Error(
				'jsontoimg_missing_post',
				__( 'Save the post before setting a featured image.', 'jsontoimg' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return new WP_Error(
				'jsontoimg_no_thumbnail',
				__( 'This post type does not support a featured image.', 'jsontoimg' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_templates( $request ) {
		$query = array(
			'limit'  => (int) $request->get_param( 'limit' ),
			'offset' => (int) $request->get_param( 'offset' ),
		);

		$project = $request->get_param( 'project' );
		if ( is_string( $project ) && '' !== $project ) {
			$query['project'] = $project;
		}

		$result = jsontoimg_list_templates( $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_template_schema( $request ) {
		$result = jsontoimg_get_client()->get_template_schema( $request->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sign( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$template = isset( $body['template'] ) ? $body['template'] : $request->get_param( 'template' );
		$format   = isset( $body['format'] ) ? $body['format'] : $request->get_param( 'format' );
		$layers   = isset( $body['layers'] ) ? $body['layers'] : $request->get_param( 'layers' );
		$variables = isset( $body['variables'] ) ? $body['variables'] : $request->get_param( 'variables' );

		$result = jsontoimg_sign_url(
			$template,
			array(
				'format'    => $format,
				'layers'    => jsontoimg_compact_layers( $layers ),
				'variables' => jsontoimg_normalize_assoc( $variables ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'url' => $result,
			)
		);
	}

	/**
	 * Mint, download, sideload, and set the post thumbnail.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_featured_image( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$post_id  = isset( $body['postId'] ) ? (int) $body['postId'] : (int) $request->get_param( 'postId' );
		$template = isset( $body['template'] ) ? $body['template'] : $request->get_param( 'template' );
		$format   = isset( $body['format'] ) ? $body['format'] : $request->get_param( 'format' );
		$layers   = isset( $body['layers'] ) ? $body['layers'] : $request->get_param( 'layers' );
		$alt      = isset( $body['alt'] ) ? $body['alt'] : $request->get_param( 'alt' );

		$signed = jsontoimg_sign_url(
			$template,
			array(
				'format' => $format,
				'layers' => jsontoimg_compact_layers( $layers ),
			)
		);
		if ( is_wp_error( $signed ) ) {
			return $signed;
		}

		$attachment_id = jsontoimg_sideload_signed_image( $signed, $post_id, is_string( $alt ) ? $alt : '', true );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response(
			array(
				'attachmentId' => $attachment_id,
				'url'          => wp_get_attachment_url( $attachment_id ),
			)
		);
	}
}
