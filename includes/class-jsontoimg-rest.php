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
}
