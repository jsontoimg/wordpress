<?php

/**
 * Public PHP helpers for themes and other plugins.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Return a Render API client using saved settings, or explicit credentials.
 *
 * @since  1.0.0
 * @param  string|null $api_key  Optional API key override.
 * @param  string|null $base_url Optional origin override.
 * @return Jsontoimg_Api
 */
function jsontoimg_get_client( $api_key = null, $base_url = null ) {
	return new Jsontoimg_Api( $api_key, $base_url );
}

/**
 * Whether a saved API key exists.
 *
 * @since  1.0.0
 * @return bool
 */
function jsontoimg_has_api_key() {
	return '' !== Jsontoimg_Api::get_stored_api_key();
}

/**
 * Increment the signed-URL cache generation.
 *
 * @since 1.0.0
 */
function jsontoimg_bump_cache_version() {
	$current = (int) get_option( 'jsontoimg_cache_version', 1 );
	update_option( 'jsontoimg_cache_version', $current + 1, false );
}

/**
 * Mint (and cache) a signed image URL for a template.
 *
 * @since  1.0.0
 * @param  string $template Template ID.
 * @param  array  $args     Optional format, layers, variables, expiresIn, version, scale, transparent.
 * @return string|WP_Error  Signed URL or error.
 */
function jsontoimg_sign_url( $template, $args = array() ) {
	$template = is_string( $template ) ? trim( $template ) : '';
	if ( '' === $template ) {
		return new WP_Error(
			'jsontoimg_missing_template',
			__( 'Template ID is required.', 'jsontoimg' ),
			array( 'status' => 400 )
		);
	}

	$args = is_array( $args ) ? $args : array();
	$body = array( 'template' => $template );

	if ( ! empty( $args['format'] ) ) {
		$body['format'] = sanitize_key( $args['format'] );
	}

	if ( ! empty( $args['layers'] ) && is_array( $args['layers'] ) ) {
		$layers = jsontoimg_compact_layers( $args['layers'] );
		if ( ! empty( $layers ) ) {
			$invalid = jsontoimg_invalid_image_url( $layers );
			if ( $invalid ) {
				return new WP_Error(
					'jsontoimg_invalid_image_url',
					$invalid,
					array( 'status' => 400 )
				);
			}
			$body['layers'] = $layers;
		}
	}

	if ( ! empty( $args['variables'] ) && is_array( $args['variables'] ) ) {
		$body['variables'] = $args['variables'];
	}

	if ( isset( $args['expiresIn'] ) && '' !== $args['expiresIn'] && null !== $args['expiresIn'] ) {
		$body['expiresIn'] = (int) $args['expiresIn'];
	}

	if ( isset( $args['expires_in'] ) && ! isset( $body['expiresIn'] ) && '' !== $args['expires_in'] ) {
		$body['expiresIn'] = (int) $args['expires_in'];
	}

	if ( isset( $args['version'] ) && '' !== $args['version'] && null !== $args['version'] ) {
		$body['version'] = $args['version'];
	}

	if ( isset( $args['scale'] ) && '' !== $args['scale'] && null !== $args['scale'] ) {
		$body['scale'] = (float) $args['scale'];
	}

	if ( isset( $args['transparent'] ) ) {
		$body['transparent'] = (bool) $args['transparent'];
	}

	$client        = jsontoimg_get_client();
	$cache_version = (int) get_option( 'jsontoimg_cache_version', 1 );
	$cache_key     = 'jsontoimg_sign_' . md5(
		wp_json_encode(
			array(
				$client->get_base_url(),
				$cache_version,
				$body,
			)
		)
	);

	$cached = get_transient( $cache_key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$result = $client->sign_image_url( $body );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$url = '';
	if ( is_array( $result ) && ! empty( $result['url'] ) && is_string( $result['url'] ) ) {
		$url = $result['url'];
	}

	if ( '' === $url ) {
		return new WP_Error(
			'jsontoimg_sign_failed',
			__( 'The API did not return a signed URL.', 'jsontoimg' ),
			array( 'status' => 502 )
		);
	}

	$ttl = (int) apply_filters( 'jsontoimg_sign_url_ttl', WEEK_IN_SECONDS, $body, $result );
	if ( ! empty( $body['expiresIn'] ) ) {
		$ttl = max( 60, (int) $body['expiresIn'] - 60 );
	}

	set_transient( $cache_key, $url, $ttl );

	return $url;
}

/**
 * List templates using saved credentials.
 *
 * @since  1.0.0
 * @param  array $args Optional query: project, limit, offset.
 * @return array|WP_Error
 */
function jsontoimg_list_templates( $args = array() ) {
	return jsontoimg_get_client()->list_templates( $args );
}

/**
 * Coerce JSON objects from the block editor into associative arrays.
 *
 * @since  1.0.0
 * @param  mixed $value Layers or variables.
 * @return array
 */
function jsontoimg_normalize_assoc( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}

	if ( is_object( $value ) ) {
		$decoded = json_decode( wp_json_encode( $value ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	return array();
}

/**
 * Drop empty layer attributes so we do not send blank image_url/text values.
 *
 * @since  1.0.0
 * @param  array $layers Layer override map.
 * @return array
 */
function jsontoimg_compact_layers( $layers ) {
	$layers  = jsontoimg_normalize_assoc( $layers );
	$cleaned = array();

	foreach ( $layers as $name => $override ) {
		if ( ! is_string( $name ) || '' === $name ) {
			continue;
		}

		$override = jsontoimg_normalize_assoc( $override );
		$row      = array();
		foreach ( $override as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			$text = trim( (string) $value );
			if ( '' === $text ) {
				continue;
			}
			$row[ $key ] = $text;
		}

		if ( ! empty( $row ) ) {
			$cleaned[ $name ] = $row;
		}
	}

	return $cleaned;
}

/**
 * Same rule as the Render API: https with .png/.jpg/.jpeg/.webp, or an Assets path.
 *
 * @since  1.0.0
 * @param  string $value Image URL.
 * @return bool
 */
function jsontoimg_is_allowed_image_url( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return false;
	}

	if ( preg_match( '#^/api/uploads/files/[^/?#]+$#', $value ) ) {
		return true;
	}

	$parts = wp_parse_url( $value );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['path'] ) ) {
		return false;
	}

	$scheme = strtolower( (string) $parts['scheme'] );
	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return false;
	}

	return (bool) preg_match( '/\.(png|jpe?g|webp)$/i', $parts['path'] );
}

/**
 * @since  1.0.0
 * @param  array $layers Compact layer overrides.
 * @return string|null   Error message, or null if valid.
 */
function jsontoimg_invalid_image_url( $layers ) {
	foreach ( $layers as $override ) {
		if ( ! is_array( $override ) || empty( $override['image_url'] ) ) {
			continue;
		}
		if ( ! jsontoimg_is_allowed_image_url( $override['image_url'] ) ) {
			return __( 'image_url must be https with .png/.jpg/.jpeg/.webp, or an Assets path /api/uploads/files/{id}. Placeholder hosts without a file extension (and many placeholder CDNs) are rejected.', 'jsontoimg' );
		}
	}

	return null;
}

/**
 * Escape a signed GET /api/v1/img URL for an img src.
 *
 * WordPress esc_url() strips { } " which breaks the layers JSON query param.
 *
 * @since  1.0.0
 * @param  string $url Signed URL from the API.
 * @return string
 */
function jsontoimg_esc_signed_src( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return '';
	}

	$scheme = strtolower( (string) $parts['scheme'] );
	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return '';
	}

	if ( 0 !== strpos( (string) $parts['path'], '/api/v1/img/' ) ) {
		return '';
	}

	$origin_host = wp_parse_url( jsontoimg_get_client()->get_base_url(), PHP_URL_HOST );
	if ( is_string( $origin_host ) && '' !== $origin_host && 0 !== strcasecmp( $origin_host, (string) $parts['host'] ) ) {
		return '';
	}

	return esc_attr( $url );
}

/**
 * Whether a URL is a signed jsontoimg image GET.
 *
 * @since  1.0.0
 * @param  string $url Candidate URL.
 * @return bool
 */
function jsontoimg_is_signed_img_url( $url ) {
	$parts = wp_parse_url( (string) $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return false;
	}

	$scheme = strtolower( (string) $parts['scheme'] );
	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return false;
	}

	if ( 0 !== strpos( (string) $parts['path'], '/api/v1/img/' ) ) {
		return false;
	}

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		wp_parse_str( (string) $parts['query'], $query );
	}

	return ! empty( $query['sig'] );
}

/**
 * File extension from a Content-Type header.
 *
 * @since  1.0.0
 * @param  string $content_type MIME type.
 * @return string|null
 */
function jsontoimg_extension_for_mime( $content_type ) {
	$mime = strtolower( trim( explode( ';', (string) $content_type )[0] ) );
	$map  = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/jpg'  => 'jpg',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
	);

	return isset( $map[ $mime ] ) ? $map[ $mime ] : null;
}

/**
 * Download a signed image, retrying HTTP 503 while the render finishes.
 *
 * @since  1.0.0
 * @param  string $url     Signed GET URL.
 * @param  int    $timeout Seconds to wait (default 60).
 * @return array|WP_Error  tmp_name, filename, type.
 */
function jsontoimg_download_signed_image( $url, $timeout = 60 ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( ! jsontoimg_is_signed_img_url( $url ) ) {
		return new WP_Error(
			'jsontoimg_invalid_signed_url',
			__( 'The signed image URL is invalid.', 'jsontoimg' ),
			array( 'status' => 400 )
		);
	}

	$timeout  = max( 5, (int) $timeout );
	$deadline = time() + $timeout;
	$last     = null;

	while ( time() < $deadline ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => min( 30, max( 5, $deadline - time() ) ),
				'redirection' => 5,
				'headers'     => array(
					'Accept' => 'image/png,image/jpeg,image/webp,image/avif',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$last = $response;
			break;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 503 === $code ) {
			$wait = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			$wait = $wait > 0 ? min( $wait, 5 ) : 2;
			if ( time() + $wait >= $deadline ) {
				$last = new WP_Error(
					'jsontoimg_render_timeout',
					__( 'The render did not finish in time. Try again in a moment.', 'jsontoimg' ),
					array( 'status' => 504 )
				);
				break;
			}
			sleep( $wait );
			continue;
		}

		if ( $code < 200 || $code >= 300 ) {
			$body    = wp_remote_retrieve_body( $response );
			$decoded = json_decode( $body, true );
			$message = __( 'Could not download the rendered image.', 'jsontoimg' );
			if ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
				$message = (string) $decoded['error'];
			}
			return new WP_Error(
				'jsontoimg_download_failed',
				$message,
				array( 'status' => $code )
			);
		}

		$type = wp_remote_retrieve_header( $response, 'content-type' );
		$ext  = jsontoimg_extension_for_mime( $type );
		if ( ! $ext ) {
			return new WP_Error(
				'jsontoimg_not_image',
				__( 'The render did not return an image file.', 'jsontoimg' ),
				array( 'status' => 502 )
			);
		}

		$tmp = wp_tempnam( 'jsontoimg' );
		if ( ! $tmp ) {
			return new WP_Error(
				'jsontoimg_temp_file',
				__( 'Could not create a temporary file.', 'jsontoimg' ),
				array( 'status' => 500 )
			);
		}

		$written = file_put_contents( $tmp, wp_remote_retrieve_body( $response ) );
		if ( false === $written ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'jsontoimg_temp_file',
				__( 'Could not write the downloaded image.', 'jsontoimg' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'tmp_name' => $tmp,
			'filename' => 'jsontoimg-' . gmdate( 'Ymd-His' ) . '.' . $ext,
			'type'     => explode( ';', (string) $type )[0],
		);
	}

	return $last instanceof WP_Error
		? $last
		: new WP_Error(
			'jsontoimg_render_timeout',
			__( 'The render did not finish in time. Try again in a moment.', 'jsontoimg' ),
			array( 'status' => 504 )
		);
}

/**
 * Sideload a signed render into the Media Library and optionally set it as featured.
 *
 * @since  1.0.0
 * @param  string $url     Signed image URL.
 * @param  int    $post_id Post to attach to.
 * @param  string $alt     Attachment alt / description.
 * @param  bool   $featured Whether to set as featured image.
 * @return int|WP_Error    Attachment ID.
 */
function jsontoimg_sideload_signed_image( $url, $post_id, $alt = '', $featured = true ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$download = jsontoimg_download_signed_image( $url );
	if ( is_wp_error( $download ) ) {
		return $download;
	}

	$file_array = array(
		'name'     => $download['filename'],
		'tmp_name' => $download['tmp_name'],
		'type'     => $download['type'],
	);

	$attachment_id = media_handle_sideload( $file_array, $post_id, $alt );
	if ( is_wp_error( $attachment_id ) ) {
		if ( file_exists( $file_array['tmp_name'] ) ) {
			wp_delete_file( $file_array['tmp_name'] );
		}
		return $attachment_id;
	}

	if ( '' !== $alt ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	if ( $featured ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}

	return (int) $attachment_id;
}

/**
 * Sanitize a width/height attribute (pixels or a simple CSS length).
 *
 * @since  1.0.0
 * @param  mixed $value Raw dimension.
 * @return string
 */
function jsontoimg_sanitize_dimension( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^\d+$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^\d+(?:\.\d+)?(px|%|em|rem)$/', $value ) ) {
		return $value;
	}

	return '';
}

/**
 * Build a sanitized class attribute string.
 *
 * @since  1.0.0
 * @param  string $class Space-separated class names.
 * @return string
 */
function jsontoimg_sanitize_class_attr( $class ) {
	$parts = preg_split( '/\s+/', (string) $class );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$classes = array_filter( array_map( 'sanitize_html_class', $parts ) );

	return implode( ' ', $classes );
}

/**
 * Render an escaped <img> for a signed template URL.
 *
 * On failure returns an HTML comment for manage_options users, otherwise empty.
 *
 * @since  1.0.0
 * @param  array $args template, format, layers, variables, alt, class, width, height.
 * @return string
 */
function jsontoimg_render_image( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'template'  => '',
			'format'    => 'png',
			'layers'    => array(),
			'variables' => array(),
			'alt'       => '',
			'class'     => '',
			'width'     => '',
			'height'    => '',
		)
	);

	$signed = jsontoimg_sign_url(
		$args['template'],
		array(
			'format'    => $args['format'],
			'layers'    => jsontoimg_compact_layers( $args['layers'] ),
			'variables' => jsontoimg_normalize_assoc( $args['variables'] ),
		)
	);

	if ( is_wp_error( $signed ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<!-- jsontoimg: ' . esc_html( $signed->get_error_message() ) . ' -->';
		}

		return '';
	}

	$classes = array( 'jsontoimg-image' );
	$extra   = jsontoimg_sanitize_class_attr( $args['class'] );
	if ( '' !== $extra ) {
		$classes[] = $extra;
	}

	$attr = array(
		'src'   => jsontoimg_esc_signed_src( $signed ),
		'alt'   => esc_attr( sanitize_text_field( $args['alt'] ) ),
		'class' => esc_attr( implode( ' ', $classes ) ),
	);

	$width = jsontoimg_sanitize_dimension( $args['width'] );
	if ( '' !== $width ) {
		$attr['width'] = esc_attr( $width );
	}

	$height = jsontoimg_sanitize_dimension( $args['height'] );
	if ( '' !== $height ) {
		$attr['height'] = esc_attr( $height );
	}

	$html = '<img';
	foreach ( $attr as $name => $value ) {
		$html .= ' ' . $name . '="' . $value . '"';
	}
	$html .= ' />';

	return $html;
}
