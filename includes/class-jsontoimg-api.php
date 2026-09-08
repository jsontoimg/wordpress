<?php

/**
 * jsontoimg Render API client.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 */

/**
 * HTTP client for /api/v1 using stored or explicit credentials.
 *
 * @since      1.0.0
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Api {

	/**
	 * Default API origin.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DEFAULT_BASE_URL = 'https://app.jsontoimg.com';

	/**
	 * API key used for this client instance.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $api_key;

	/**
	 * Normalized API origin (no trailing slash, no /api/v1).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $base_url;

	/**
	 * @since 1.0.0
	 * @param string|null $api_key  Optional override. Null reads the saved option.
	 * @param string|null $base_url Optional override. Null reads the saved option.
	 */
	public function __construct( $api_key = null, $base_url = null ) {
		$this->api_key  = null !== $api_key ? (string) $api_key : self::get_stored_api_key();
		$this->base_url = self::normalize_base_url(
			null !== $base_url ? $base_url : self::get_stored_base_url()
		);
	}

	/**
	 * API key saved in options.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_stored_api_key() {
		return (string) get_option( 'jsontoimg_api_key', '' );
	}

	/**
	 * Base URL saved in options.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_stored_base_url() {
		return (string) get_option( 'jsontoimg_base_url', self::DEFAULT_BASE_URL );
	}

	/**
	 * Strip trailing slash and optional /api/v1 or /mcp suffix.
	 *
	 * @since  1.0.0
	 * @param  string $raw Raw origin or API URL.
	 * @return string
	 */
	public static function normalize_base_url( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $raw ) {
			$raw = self::DEFAULT_BASE_URL;
		}

		$raw = untrailingslashit( $raw );
		$raw = preg_replace( '#/(api/v1|mcp)$#', '', $raw );

		return untrailingslashit( $raw );
	}

	/**
	 * Whether this client has an API key.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function has_api_key() {
		return '' !== $this->api_key;
	}

	/**
	 * Normalized origin for this client.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * List templates in the API key's workspace.
	 *
	 * @since  1.0.0
	 * @param  array $query Optional query: project, limit, offset.
	 * @return array|WP_Error
	 */
	public function list_templates( $query = array() ) {
		$query = wp_parse_args(
			$query,
			array(
				'limit'  => 100,
				'offset' => 0,
			)
		);

		$query = array_filter(
			$query,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);

		return $this->request( 'GET', '/templates', array( 'query' => $query ) );
	}

	/**
	 * Get named layers and overridable attributes for a template.
	 *
	 * @since  1.0.0
	 * @param  string     $id      Template ID.
	 * @param  int|string $version Optional template version.
	 * @return array|WP_Error
	 */
	public function get_template_schema( $id, $version = null ) {
		$id = rawurlencode( (string) $id );
		$args = array();
		if ( null !== $version && '' !== $version ) {
			$args['query'] = array( 'version' => $version );
		}

		return $this->request( 'GET', '/templates/' . $id . '/schema', $args );
	}

	/**
	 * Mint a signed GET /api/v1/img/{template} URL.
	 *
	 * @since  1.0.0
	 * @param  array $body SignImageRequest fields.
	 * @return array|WP_Error
	 */
	public function sign_image_url( $body ) {
		return $this->request(
			'POST',
			'/img/sign',
			array(
				'body' => $body,
			)
		);
	}

	/**
	 * Verify credentials with GET /templates?limit=1.
	 *
	 * @since  1.0.0
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$result = $this->list_templates(
			array(
				'limit'  => 1,
				'offset' => 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Perform an authenticated JSON request against /api/v1.
	 *
	 * @since  1.0.0
	 * @param  string $method HTTP method.
	 * @param  string $path   Path relative to /api/v1.
	 * @param  array  $args   Optional query, body, timeout.
	 * @return array|WP_Error
	 */
	public function request( $method, $path, $args = array() ) {
		if ( ! $this->has_api_key() ) {
			return new WP_Error(
				'jsontoimg_missing_key',
				__( 'jsontoimg API key is not configured.', 'jsontoimg' ),
				array( 'status' => 400 )
			);
		}

		$path = '/' . ltrim( (string) $path, '/' );
		$url  = $this->base_url . '/api/v1' . $path;

		if ( ! empty( $args['query'] ) && is_array( $args['query'] ) ) {
			$url = add_query_arg( $args['query'], $url );
		}

		$request = array(
			'method'  => strtoupper( $method ),
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( isset( $args['body'] ) ) {
			$request['body'] = wp_json_encode( $args['body'] );
		}

		return $this->parse_response( wp_remote_request( $url, $request ) );
	}

	/**
	 * Convert a wp_remote_* response into an array or WP_Error.
	 *
	 * @since  1.0.0
	 * @param  array|WP_Error $response HTTP response.
	 * @return array|WP_Error
	 */
	protected function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'API %d', 'jsontoimg' ),
				$code
			);

			if ( is_array( $body ) ) {
				if ( ! empty( $body['error'] ) ) {
					$message = (string) $body['error'];
				} elseif ( ! empty( $body['message'] ) ) {
					$message = (string) $body['message'];
				}
			} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$message = trim( $raw );
			}

			return new WP_Error(
				'jsontoimg_api',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		if ( null === $body && '' !== trim( (string) $raw ) ) {
			return new WP_Error(
				'jsontoimg_api',
				__( 'The API returned a non-JSON response.', 'jsontoimg' ),
				array( 'status' => $code )
			);
		}

		return is_array( $body ) ? $body : array();
	}
}
