<?php

/**
 * Admin settings for the jsontoimg API connection.
 *
 * @link       https://github.com/jsontoimg
 * @since      1.0.0
 *
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 */

/**
 * Registers the Settings → jsontoimg page and option sanitization.
 *
 * @since      1.0.0
 * @package    Jsontoimg
 * @subpackage Jsontoimg/includes
 * @author     Skndan <dev@skndan.com>
 */
class Jsontoimg_Settings {

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const PAGE_SLUG = 'jsontoimg';

	/**
	 * Register the options page under Settings.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'jsontoimg', 'jsontoimg' ),
			__( 'jsontoimg', 'jsontoimg' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'jsontoimg',
			'jsontoimg_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'jsontoimg',
			'jsontoimg_base_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_base_url' ),
				'default'           => Jsontoimg_Api::DEFAULT_BASE_URL,
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'jsontoimg_api',
			__( 'API connection', 'jsontoimg' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'jsontoimg_api_key',
			__( 'API key', 'jsontoimg' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			'jsontoimg_api'
		);

		add_settings_field(
			'jsontoimg_base_url',
			__( 'Base URL', 'jsontoimg' ),
			array( $this, 'render_base_url_field' ),
			self::PAGE_SLUG,
			'jsontoimg_api'
		);
	}

	/**
	 * Section help text.
	 *
	 * @since 1.0.0
	 */
	public function render_section_intro() {
		$keys_url = 'https://app.jsontoimg.com/integrations/api-keys';
		echo '<p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to the jsontoimg API keys page. */
				__( 'Create a key under <a href="%s" target="_blank" rel="noopener noreferrer">Integrations → API keys</a>. The key never appears in frontend HTML.', 'jsontoimg' ),
				esc_url( $keys_url )
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
		echo '</p>';
	}

	/**
	 * API key password field.
	 *
	 * @since 1.0.0
	 */
	public function render_api_key_field() {
		$has_key = jsontoimg_has_api_key();
		?>
		<input type="password" class="regular-text" id="jsontoimg_api_key" name="jsontoimg_api_key" value="" autocomplete="new-password" />
		<p class="description jsontoimg-key-status">
			<?php
			if ( $has_key ) {
				esc_html_e( 'A key is saved. Leave this blank to keep it, or enter a new key to replace it.', 'jsontoimg' );
			} else {
				esc_html_e( 'No key is saved yet.', 'jsontoimg' );
			}
			?>
		</p>
		<label>
			<input type="checkbox" name="jsontoimg_clear_api_key" value="1" />
			<?php esc_html_e( 'Remove the saved API key', 'jsontoimg' ); ?>
		</label>
		<?php
	}

	/**
	 * Base URL text field.
	 *
	 * @since 1.0.0
	 */
	public function render_base_url_field() {
		$value = Jsontoimg_Api::normalize_base_url( Jsontoimg_Api::get_stored_base_url() );
		?>
		<input type="url" class="regular-text" id="jsontoimg_base_url" name="jsontoimg_base_url" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( Jsontoimg_Api::DEFAULT_BASE_URL ); ?>" />
		<p class="description">
			<?php esc_html_e( 'API origin. Trailing /api/v1 is stripped. Leave the default unless you self-host.', 'jsontoimg' ); ?>
		</p>
		<?php
	}

	/**
	 * Keep the existing key when the password field is left blank.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		$clear = isset( $_POST['jsontoimg_clear_api_key'] ) && '1' === $_POST['jsontoimg_clear_api_key']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $clear ) {
			if ( jsontoimg_has_api_key() ) {
				jsontoimg_bump_cache_version();
			}
			return '';
		}

		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return Jsontoimg_Api::get_stored_api_key();
		}

		$sanitized = sanitize_text_field( $value );
		if ( $sanitized !== Jsontoimg_Api::get_stored_api_key() ) {
			jsontoimg_bump_cache_version();
		}

		return $sanitized;
	}

	/**
	 * Normalize and persist the API origin.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_base_url( $value ) {
		$value     = is_string( $value ) ? trim( $value ) : '';
		$sanitized = Jsontoimg_Api::normalize_base_url( esc_url_raw( $value ) );
		$previous  = Jsontoimg_Api::normalize_base_url( Jsontoimg_Api::get_stored_base_url() );

		if ( $sanitized !== $previous ) {
			jsontoimg_bump_cache_version();
		}

		return $sanitized;
	}

	/**
	 * Settings page markup.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/jsontoimg-admin-display.php';
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @since  1.0.0
	 * @param  array $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'jsontoimg' ) . '</a>'
		);

		return $links;
	}

	/**
	 * AJAX: test credentials (optional unsaved form values).
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'jsontoimg_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to do this.', 'jsontoimg' ) ),
				403
			);
		}

		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : '';

		$client = jsontoimg_get_client(
			'' !== $api_key ? $api_key : null,
			'' !== $base_url ? $base_url : null
		);

		$result = $client->test_connection();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Connection successful.', 'jsontoimg' ) )
		);
	}
}
