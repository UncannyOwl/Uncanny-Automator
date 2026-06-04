<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Popup_Maker_Helpers
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 */
class Popup_Maker_Helpers extends Abstract_Helpers {

	/**
	 * Remote-data handler: load all Popup Maker popups (no "Any" — actions only).
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/popup-maker/popups`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_popups( $request ): array {

		return $this->remote_data_success(
			automator_wp_query(
				array(
					'post_type'   => 'popup',
					'include_any' => false,
				)
			)
		);
	}

	/**
	 * Remote-data handler: load all Popup Maker popups with "Any popup" sentinel (triggers).
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/popup-maker/popups_any`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_popups_any( $request ): array {

		return $this->remote_data_success(
			automator_wp_query(
				array(
					'post_type'   => 'popup',
					'include_any' => true,
					'any_label'   => esc_html_x( 'Any popup', 'Popup Maker', 'uncanny-automator' ),
				)
			)
		);
	}

	/**
	 * Remote-data handler: load all enabled Popup Maker form providers with "Any provider" sentinel.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/popup-maker/form_providers`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_form_providers( $request ): array {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any provider', 'Popup Maker', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		foreach ( $this->get_form_provider_map() as $key => $label ) {
			$options[] = array(
				'text'  => $label,
				'value' => $key,
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: load all newsletter providers with "Any provider" sentinel.
	 *
	 * Mirrors `pum_get_option('newsletter_default_provider', 'none')` choices: the
	 * built-in `none` provider plus any extension-registered providers detected via
	 * `PUM_Newsletter_Providers::instance()->get_providers()`.
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/popup-maker/newsletter_providers`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_newsletter_providers( $request ): array {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any provider', 'Popup Maker', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		foreach ( $this->get_newsletter_provider_map() as $key => $label ) {
			$options[] = array(
				'text'  => $label,
				'value' => $key,
			);
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Get the form provider key → human-readable label map.
	 *
	 * Sourced from `PUM_Integrations::get_enabled_forms_selectlist()` when available; falls
	 * back to the canonical 19-provider key list documented in the scope doc so the
	 * builder dropdown is populated even when no provider is currently enabled.
	 *
	 * @return array<string,string>
	 */
	public function get_form_provider_map() {

		if ( class_exists( '\PUM_Integrations' ) && method_exists( '\PUM_Integrations', 'get_enabled_forms_selectlist' ) ) {
			$enabled = \PUM_Integrations::get_enabled_forms_selectlist();
			if ( ! empty( $enabled ) && is_array( $enabled ) ) {
				return $enabled;
			}
		}

		return $this->fallback_form_provider_labels();
	}

	/**
	 * Canonical form-provider labels (used when Popup Maker exposes none enabled at recipe-build time).
	 *
	 * @return array<string,string>
	 */
	private function fallback_form_provider_labels() {
		return array(
			'contactform7'    => esc_html_x( 'Contact Form 7', 'Popup Maker', 'uncanny-automator' ),
			'gravityforms'    => esc_html_x( 'Gravity Forms', 'Popup Maker', 'uncanny-automator' ),
			'wpforms'         => esc_html_x( 'WPForms', 'Popup Maker', 'uncanny-automator' ),
			'ninjaforms'      => esc_html_x( 'Ninja Forms', 'Popup Maker', 'uncanny-automator' ),
			'formidableforms' => esc_html_x( 'Formidable Forms', 'Popup Maker', 'uncanny-automator' ),
			'fluentforms'     => esc_html_x( 'Fluent Forms', 'Popup Maker', 'uncanny-automator' ),
			'forminator'      => esc_html_x( 'Forminator', 'Popup Maker', 'uncanny-automator' ),
			'elementor'       => esc_html_x( 'Elementor Forms', 'Popup Maker', 'uncanny-automator' ),
			'wsforms'         => esc_html_x( 'WS Form', 'Popup Maker', 'uncanny-automator' ),
			'happyforms'      => esc_html_x( 'HappyForms', 'Popup Maker', 'uncanny-automator' ),
			'htmlforms'       => esc_html_x( 'HTML Forms', 'Popup Maker', 'uncanny-automator' ),
			'kaliForms'       => esc_html_x( 'Kali Forms', 'Popup Maker', 'uncanny-automator' ),
			'calderaforms'    => esc_html_x( 'Caldera Forms', 'Popup Maker', 'uncanny-automator' ),
			'pirateforms'     => esc_html_x( 'Pirate Forms', 'Popup Maker', 'uncanny-automator' ),
			'bitform'         => esc_html_x( 'Bit Form', 'Popup Maker', 'uncanny-automator' ),
			'bricksbuilder'   => esc_html_x( 'Bricks Builder', 'Popup Maker', 'uncanny-automator' ),
			'beaverbuilder'   => esc_html_x( 'Beaver Builder', 'Popup Maker', 'uncanny-automator' ),
			'mc4wp'           => esc_html_x( 'MailChimp for WP', 'Popup Maker', 'uncanny-automator' ),
			'newsletter'      => esc_html_x( 'Newsletter (plugin)', 'Popup Maker', 'uncanny-automator' ),
		);
	}

	/**
	 * Resolve a form provider key to its human-readable label.
	 *
	 * @param string $key Provider key (e.g. 'contactform7').
	 *
	 * @return string
	 */
	public function get_form_provider_label( $key ) {

		$map = $this->get_form_provider_map();

		if ( isset( $map[ $key ] ) ) {
			return (string) $map[ $key ];
		}

		$fallback = $this->fallback_form_provider_labels();

		return isset( $fallback[ $key ] ) ? (string) $fallback[ $key ] : (string) $key;
	}

	/**
	 * Get the newsletter-provider key → label map.
	 *
	 * @return array<string,string>
	 */
	public function get_newsletter_provider_map() {

		$providers = array(
			'none' => esc_html_x( 'None (no upstream sync)', 'Popup Maker', 'uncanny-automator' ),
		);

		if ( class_exists( '\PUM_Newsletter_Providers' ) ) {
			$instance = \PUM_Newsletter_Providers::instance();
			if ( method_exists( $instance, 'get_providers' ) ) {
				$registered = $instance->get_providers();
				if ( is_array( $registered ) ) {
					foreach ( $registered as $key => $object ) {
						// PUM newsletter providers expose a public ->name (e.g. 'MailChimp'), not a label() method.
						if ( is_object( $object ) && isset( $object->name ) ) {
							$providers[ $key ] = (string) $object->name;
						} elseif ( is_object( $object ) && method_exists( $object, 'label' ) ) {
							$providers[ $key ] = (string) $object->label();
						} elseif ( is_string( $object ) ) {
							$providers[ $key ] = $object;
						}
					}
				}
			}
		}

		return $providers;
	}

	/**
	 * Resolve a newsletter-provider key to its human-readable label.
	 *
	 * @param string $key Provider key.
	 *
	 * @return string
	 */
	public function get_newsletter_provider_label( $key ) {

		$map = $this->get_newsletter_provider_map();

		return isset( $map[ $key ] ) ? (string) $map[ $key ] : (string) $key;
	}

	/**
	 * Build the popup-identity + counter token values for a given popup ID.
	 *
	 * Returns empty strings for every token when the popup ID is 0 or the post is
	 * missing. Wraps `pum_get_popup()` in `pum_is_popup()` per scope Gotcha #8.
	 *
	 * @param int $popup_id The popup post ID.
	 *
	 * @return array{POPUP_ID:int,POPUP_TITLE:string,POPUP_EDIT_URL:string,POPUP_OPEN_COUNT:int,POPUP_CONVERSION_COUNT:int}
	 */
	public function get_popup_token_values( $popup_id ) {

		$out = array(
			'POPUP_ID'               => (int) $popup_id,
			'POPUP_TITLE'            => '',
			'POPUP_EDIT_URL'         => '',
			'POPUP_OPEN_COUNT'       => 0,
			'POPUP_CONVERSION_COUNT' => 0,
		);

		if ( 0 === (int) $popup_id ) {
			return $out;
		}

		$out['POPUP_EDIT_URL'] = (string) get_edit_post_link( $popup_id, '' );

		if ( ! function_exists( 'pum_get_popup' ) || ! function_exists( 'pum_is_popup' ) ) {
			$post = get_post( $popup_id );
			if ( $post instanceof \WP_Post ) {
				$out['POPUP_TITLE'] = (string) $post->post_title;
			}
			return $out;
		}

		$popup = pum_get_popup( $popup_id );

		if ( ! pum_is_popup( $popup ) ) {
			$post = get_post( $popup_id );
			if ( $post instanceof \WP_Post ) {
				$out['POPUP_TITLE'] = (string) $post->post_title;
			}
			return $out;
		}

		$out['POPUP_TITLE'] = (string) $popup->get_title();

		if ( method_exists( $popup, 'get_event_count' ) ) {
			$out['POPUP_OPEN_COUNT']       = (int) $popup->get_event_count( 'open', 'current' );
			$out['POPUP_CONVERSION_COUNT'] = (int) $popup->get_event_count( 'conversion', 'current' );
		}

		return $out;
	}
}
