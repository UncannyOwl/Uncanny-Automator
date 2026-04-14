<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\Automator;

/**
 * Class Linkedin_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_Api_Caller $api
 */
class Linkedin_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * The number of days to show the refresh token expiration notice.
	 *
	 * @var int
	 */
	const N_DAYS_REFRESH_TOKEN_EXPIRE_NOTICE = 30;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set additional properties for backward compatibility.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override default option names for backward compatibility with existing data.
		$this->set_credentials_option_name( 'automator_linkedin_client' );
		$this->set_account_option_name( 'automator_linkedin_connected_user' );
	}

	/**
	 * Get account info from stored credentials.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$account = parent::get_account_info();

		$defaults = array(
			'localizedLastName'  => '',
			'localizedFirstName' => '',
			'id'                 => '',
		);

		return wp_parse_args( $account, $defaults );
	}

	/**
	 * Prepare credentials for storage.
	 * Adds computed expiration timestamps.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Compute expiration timestamps from relative values.
		if ( ! empty( $credentials['expires_in'] ) ) {
			$credentials['expires_on'] = strtotime( current_time( 'mysql' ) ) + $credentials['expires_in'];
		}

		if ( ! empty( $credentials['refresh_token_expires_in'] ) ) {
			$credentials['refresh_token_expires_on'] = strtotime( current_time( 'mysql' ) ) + $credentials['refresh_token_expires_in'];
		}

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get LinkedIn page selector option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_page_option_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'LinkedIn Page', 'LinkedIn', 'uncanny-automator' ),
			'input_type'            => 'select',
			'supports_custom_value' => false,
			'required'              => true,
			'ajax'                  => array(
				'endpoint' => 'automator_linkedin_get_pages',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get message body textarea option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_message_option_config( $option_code ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_attr_x( 'Message', 'LinkedIn', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'supports_tokens' => true,
			'required'        => true,
		);
	}

	/**
	 * Get image URL/ID text option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_image_option_config( $option_code ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_attr_x( 'Image URL or Media library ID', 'LinkedIn', 'uncanny-automator' ),
			'description'     => esc_attr_x( 'The image must be in a JPG, JPEG or PNG format. The file name must not contain spaces and extended JPEG formats (such as MPO and JPS) are not supported.', 'LinkedIn', 'uncanny-automator' ),
			'input_type'      => 'text',
			'supports_tokens' => true,
			'required'        => true,
		);
	}

	/**
	 * Get content textarea option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_content_option_config( $option_code ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_attr_x( 'Content', 'LinkedIn', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'supports_tokens' => true,
			'required'        => true,
		);
	}

	////////////////////////////////////////////////////////////
	// Connection type helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get the stored connection type.
	 *
	 * @return string The connection type: 'business', 'personal', or 'both'.
	 */
	public function get_connection_type() {
		$valid_types = array( 'business', 'personal', 'both' );
		$type        = automator_get_option( $this->get_option_key( 'connection_type' ), 'business' );

		return in_array( $type, $valid_types, true ) ? $type : 'business';
	}

	/**
	 * Store the connection type.
	 *
	 * @param string $type The connection type: 'business', 'personal', or 'both'.
	 *
	 * @return void
	 */
	public function store_connection_type( $type ) {
		$valid_types = array( 'business', 'personal', 'both' );
		$type        = in_array( $type, $valid_types, true ) ? $type : 'business';

		automator_update_option( $this->get_option_key( 'connection_type' ), $type );
	}

	/**
	 * Get the connected user's personal profile as a destination option.
	 *
	 * Returns a single option array using the urn:li:person:{id} format
	 * compatible with the API proxy's post_publish and post_media_publish methods.
	 *
	 * @return array A single option array with 'text' and 'value', or empty array if unavailable.
	 */
	public function get_personal_profile_option() {
		$account = $this->get_account_info();

		if ( empty( $account['id'] ) ) {
			return array();
		}

		$display_name = trim(
			( $account['localizedFirstName'] ?? '' ) . ' ' . ( $account['localizedLastName'] ?? '' )
		);

		return array(
			'text'  => ! empty( $display_name )
				? sprintf(
					// translators: %s is the user's display name.
					esc_html_x( '%s (Personal Profile)', 'LinkedIn', 'uncanny-automator' ),
					$display_name
				)
				: esc_html_x( 'Personal Profile', 'LinkedIn', 'uncanny-automator' ),
			'value' => sprintf( 'urn:li:person:%s', $account['id'] ),
		);
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for getting LinkedIn destination options.
	 *
	 * Merges personal profile and/or business pages based on the
	 * stored connection type.
	 *
	 * @return void
	 */
	public function get_pages_ajax() {

		Automator()->utilities->ajax_auth_check();

		try {
			$connection_type = $this->get_connection_type();
			$options         = array();

			// Include personal profile for 'personal' or 'both'.
			if ( 'business' !== $connection_type ) {
				$profile = $this->get_personal_profile_option();
				if ( ! empty( $profile ) ) {
					$options[] = $profile;
				}
			}

			// Include business pages for 'business' or 'both'.
			if ( 'personal' !== $connection_type ) {
				try {
					$options = array_merge( $options, $this->get_pages( $this->is_ajax_refresh() ) );
				} catch ( \Exception $e ) {
					// For 'both', pages are optional if the personal profile is available.
					if ( empty( $options ) ) {
						throw $e;
					}
				}
			}

			$this->ajax_success( $options );

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Linkedin_App_Helpers::get_pages_ajax', true, 'linkedin' );
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * Get LinkedIn pages, with option data caching.
	 *
	 * @param bool $refresh Whether to force a refresh from the API.
	 *
	 * @return array Formatted page options.
	 * @throws \Exception If API call fails or no pages found.
	 */
	public function get_pages( $refresh = false ) {

		$option_key = $this->get_option_key( 'pages' );
		$cached     = $this->get_app_option( $option_key );

		// Return cached data if not refreshing and cache is still valid.
		if ( ! $refresh && ! $cached['refresh'] && ! empty( $cached['data'] ) ) {
			return $cached['data'];
		}

		try {
			$response = $this->api->api_request( 'get_pages' );
			$elements = $response['data']['elements'] ?? array();
		} catch ( \Exception $e ) {
			$elements = array();
		}

		if ( empty( $elements ) ) {
			throw new \Exception(
				esc_html_x( 'Unable to find any LinkedIn page with administrative access. Please try again later.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		// Format the raw response into option data.
		$pages = array();
		foreach ( (array) $elements as $element ) {
			$pages[] = array(
				'text'  => $element['organization~']['localizedName'] ?? '',
				'value' => $element['organization'] ?? '',
			);
		}

		// Cache the formatted data.
		$this->save_app_option( $option_key, $pages );

		return $pages;
	}

	////////////////////////////////////////////////////////////
	// Content formatting
	////////////////////////////////////////////////////////////

	/**
	 * Format post content for the LinkedIn API.
	 *
	 * Converts HTML line breaks to newlines and sanitizes the output.
	 *
	 * @param string $content The raw content.
	 *
	 * @return string
	 */
	public function format_post_content( $content = '' ) {
		return sanitize_textarea_field( str_replace( array( '<br />', '<br/>', '<br>' ), PHP_EOL, $content ) );
	}

	////////////////////////////////////////////////////////////
	// Token refresh management
	////////////////////////////////////////////////////////////

	/**
	 * Check refresh token expiration and show admin notice if needed.
	 *
	 * @return void
	 */
	public function check_refresh_token_expiration() {
		$credentials = $this->get_credentials();
		if ( empty( $credentials ) ) {
			return;
		}

		if ( ! $this->is_refresh_token_expiring( $credentials ) ) {
			return;
		}

		if ( ! empty( Automator()->utilities->fetch_live_integration_actions( 'LINKEDIN' ) ) ) {
			add_action( 'automator_show_internal_admin_notice', array( $this, 'admin_notice_show_reminder' ) );
		}
	}

	/**
	 * Check if the refresh token is expiring within N days.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return bool
	 */
	public function is_refresh_token_expiring( $credentials = array() ) {
		return $this->get_refresh_token_remaining_days( $credentials ) <= self::N_DAYS_REFRESH_TOKEN_EXPIRE_NOTICE;
	}

	/**
	 * Get the number of days until the refresh token expires.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return int
	 */
	public function get_refresh_token_remaining_days( $credentials = array() ) {
		$credentials       = ! empty( $credentials ) ? $credentials : $this->get_credentials();
		$expires_on        = absint( $credentials['refresh_token_expires_on'] ?? 0 );
		$seconds_remaining = $expires_on - strtotime( current_time( 'mysql' ) );
		$days_remaining    = floor( $seconds_remaining / DAY_IN_SECONDS );

		return (int) apply_filters( 'automator_linkedin_get_refresh_token_remaining_days', $days_remaining, $this );
	}

	/**
	 * Show admin notice for refresh token expiration.
	 *
	 * @return void
	 */
	public function admin_notice_show_reminder() {
		$days = $this->get_refresh_token_remaining_days();
		if ( $days <= 0 ) {

			printf(
				'<div class="notice notice-error"><p>%1$s <a href="%2$s">%3$s</a> %4$s</p></div>',
				esc_html_x(
					'Your LinkedIn access and refresh tokens have expired.',
					'Linkedin',
					'uncanny-automator'
				),
				esc_url( $this->get_settings_page_url() ),
				esc_html_x( 'Click here', 'Linkedin', 'uncanny-automator' ),
				esc_html_x( 'to reauthorize Uncanny Automator and continue using LinkedIn actions in your recipes.', 'Linkedin', 'uncanny-automator' )
			);

			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a> %4$s</p></div>',
			esc_html(
				sprintf(
					/* Translators: Admin notice */
					_n(
						'Your LinkedIn access and refresh tokens will expire in %s day.',
						'Your LinkedIn access and refresh tokens will expire in %s days.',
						$days,
						'uncanny-automator'
					),
					number_format_i18n( $days )
				)
			),
			esc_url( $this->get_settings_page_url() ),
			esc_html_x( 'Click here to reauthorize', 'Linkedin', 'uncanny-automator' ),
			esc_html_x( 'to continue using your LinkedIn account in your recipes.', 'Linkedin', 'uncanny-automator' )
		);
	}
}
