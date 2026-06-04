<?php

namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;
/**
 * Class Brevo_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Brevo_App_Helpers $helpers
 */
class Brevo_Api_Caller extends Api_Caller {

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the default credential request key until migration to vault.
		$this->set_credential_request_key( 'api-key' );
	}

	/**
	 * Get account info.
	 *
	 * @param  array $account - Default account data.
	 *
	 * @return array
	 */
	public function get_account_info( $account ) {

		// Get account from API.
		try {
			$response = $this->api_request( 'get_account' );
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();

			// Check if this is an IP blocking error.
			if ( false !== strpos( $error_message, 'unrecognised' ) ) {
				$account['error'] = 'unauthorized-ip';
				return $account;
			}

			$account['error'] = ! empty( $error_message ) ? $error_message : esc_html_x( 'Brevo API Error', 'Brevo', 'uncanny-automator' );
			return $account;
		}

		// Success.
		if ( ! empty( $response['data']['companyName'] ) ) {
			$account['company'] = $response['data']['companyName'];
			$account['email']   = $response['data']['email'];
			$account['status']  = 'success';
		}

		// Check for invalid key.
		if ( ! empty( $response['data']['code'] ) ) {
			if ( 'unauthorized' === $response['data']['code'] ) {
				$account['status'] = '';
				// Check if error is in regards to IP address.
				$account['error'] = false !== strpos( $response['data']['error'], 'unrecognised' )
					? 'unauthorized-ip'
					: $this->helpers->get_invalid_key_message() . $this->helpers->get_credentials();
			}
		}

		return $account;
	}

	/**
	 * Get Contact.
	 *
	 * @param  mixed (ID, Email or SMS) $identifier
	 *
	 * @return array
	 */
	public function get_contact( $identifier ) {
		$body = array(
			'action'     => 'get_contact',
			'identifier' => $identifier,
		);

		$response = $this->api_request( $body, null, array( 'exclude_error_check' => true ) );

		return ! empty( $response['data']['id'] ) ? $response['data'] : false;
	}

	/**
	 * Create contact.
	 *
	 * @param  string $email
	 * @param  array $attributes
	 * @param  bool $update_enabled
	 * @param  array $action_data
	 *
	 * @return array
	 */
	public function create_contact( $email, $attributes, $update_enabled, $action_data ) {

		$contact = array(
			'attributes'    => $attributes,
			'updateEnabled' => $update_enabled ? true : false,
			'email'         => $email,
		);

		if ( empty( $contact['attributes'] ) ) {
			unset( $contact['attributes'] );
		}

		$body = array(
			'action'  => 'create_contact',
			'contact' => wp_json_encode( $contact ),
		);

		$response = $this->api_request( $body, $action_data );

		return $response;
	}

	/**
	 * Create contact with double optin.
	 *
	 * @param  string $email
	 * @param  array $attributes
	 * @param  int $template_id
	 * @param  string $redirect_url
	 * @param  int $list_id
	 * @param  bool $update_enabled
	 */
	public function create_contact_with_double_optin( $email, $attributes, $template_id, $redirect_url, $list_id, $update_existing, $action_data ) {

		// Check if contact exists.
		$contact = $this->get_contact( $email );
		if ( ! empty( $contact ) ) {
			if ( ! $update_existing ) {
				throw new Exception(
					esc_html_x( 'Contact with that email already exists', 'Brevo', 'uncanny-automator' )
				);
			}
			// TODO REVIEW - could compare attributes and update only if needed.
			return $this->create_contact( $email, $attributes, $update_existing, $action_data );
		}

		// Create contact DOI.
		$body = array(
			'action'  => 'create_contact_with_doi',
			'contact' => wp_json_encode(
				array(
					'attributes'     => $attributes,
					'email'          => $email,
					'templateId'     => (int) $template_id,
					'redirectionUrl' => $redirect_url,
					'includeListIds' => array( (int) $list_id ),
				)
			),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Delete contact.
	 *
	 * @param  string $email
	 *
	 * @return array
	 */
	public function delete_contact( $email, $action_data ) {

		$body = array(
			'action'     => 'delete_contact',
			'identifier' => $email,
		);

		$response = $this->api_request( $body, $action_data );

		return $response;
	}

	/**
	 * Add contact to list.
	 *
	 * @param  string $email
	 * @param  int $list_id
	 *
	 * @return array
	 */
	public function add_contact_to_list( $email, $list_id, $action_data ) {

		$emails = array( 'emails' => array( $email ) );

		$body = array(
			'action'      => 'add_contact_to_list',
			'identifiers' => wp_json_encode( $emails ),
			'list_id'     => (int) $list_id,
		);

		$response = $this->api_request( $body, $action_data );

		return $response;
	}

	/**
	 * Remove contact from list.
	 *
	 * @param  string $email
	 * @param  int $list_id
	 *
	 * @return array
	 */
	public function remove_contact_from_list( $email, $list_id, $action_data ) {

		$emails = array( 'emails' => array( $email ) );

		$body = array(
			'action'      => 'remove_contact_from_list',
			'identifiers' => wp_json_encode( $emails ),
			'list_id'     => (int) $list_id,
		);

		$response = $this->api_request( $body, $action_data );

		return $response;
	}

	/**
	 * Get contact attributes.
	 *
	 * @param bool $force_refresh When true, bypasses the cache and re-fetches from the API.
	 *
	 * @return array
	 */
	public function get_contact_attributes( $force_refresh = false ) {

		if ( ! $force_refresh ) {
			$cached = $this->helpers->get_app_option( $this->helpers->get_option_key( 'attributes' ) );
			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] ) {
				return $cached['data'];
			}
		}

		try {
			$response = $this->api_request( 'get_contact_attributes' );

			if ( ! isset( $response['data']['attributes'] ) ) {
				throw new Exception(
					esc_html_x( 'No attributes were returned from the API', 'Brevo API', 'uncanny-automator' )
				);
			}
		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), 'Brevo::get_contact_attributes Error', true, 'brevo' );
			return false;
		}

		$defaults   = array( 'FIRSTNAME', 'LASTNAME', 'SMS', 'DOUBLE_OPT-IN', 'OPT_IN' );
		$attributes = array();
		foreach ( $response['data']['attributes'] as $attribute ) {
			// Add check for Multi-Choice and Category ( enumeration ) select options.
			$type     = $attribute['type'] ?? '';
			$type     = empty( $type ) && isset( $attribute['enumeration'] ) ? 'select' : $type;
			$multiple = 'multiple-choice' === $type;
			$type     = $multiple ? 'select' : $type;
			$options  = false;

			if ( 'global' === $attribute['category'] || ! empty( $attribute['calculatedValue'] ) || empty( $type ) ) {
				continue;
			}
			if ( in_array( $attribute['name'], $defaults, true ) ) {
				// Ignore default attributes.
				continue;
			}

			if ( 'float' === $type || 'id' === $type ) {
				$type = 'number';
			}
			if ( 'boolean' === $type ) {
				$type = 'checkbox';
			}
			if ( 'select' === $type ) {
				$options = $multiple ? $attribute['multiCategoryOptions'] : $attribute['enumeration'];
			}

			$attributes[] = array(
				'value'    => $attribute['name'],
				'text'     => $attribute['name'],
				'type'     => $type,
				'options'  => $options,
				'multiple' => $multiple,
			);
		}

		usort(
			$attributes,
			function ( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		$this->helpers->save_app_option( $this->helpers->get_option_key( 'attributes' ), $attributes );

		return $attributes;
	}

	/**
	 * Fetch raw writable Brevo contact attributes.
	 *
	 * Returns the unmodified Brevo attribute objects (name, category, type, enumeration,
	 * multiCategoryOptions, ...) filtered only to writable entries — i.e. excluding
	 * global / calculatedValue / type-less entries. Unlike get_contact_attributes(), this
	 * keeps the language-localized default attributes (FIRSTNAME / VORNAME / PRENOM / SMS
	 * / …) so the new transposed repeater can render an input per attribute.
	 *
	 * @param bool $force_refresh When true, bypasses the cache and re-fetches from the API.
	 *
	 * @return array Raw Brevo attribute objects, or false on API error.
	 */
	public function fetch_raw_contact_attributes( $force_refresh = false ) {

		if ( ! $force_refresh ) {
			$cached = $this->helpers->get_app_option( $this->helpers->get_option_key( 'attributes_raw' ) );
			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] ) {
				return $cached['data'];
			}
		}

		try {
			$response = $this->api_request( 'get_contact_attributes' );

			if ( ! isset( $response['data']['attributes'] ) ) {
				throw new Exception(
					esc_html_x( 'No attributes were returned from the API', 'Brevo API', 'uncanny-automator' )
				);
			}
		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), 'Brevo::fetch_raw_contact_attributes Error', true, 'brevo' );
			return false;
		}

		// Brevo's internal consent-tracking attribute names are English-coded and
		// stable across all account languages (unlike FIRSTNAME → VORNAME / PRENOM).
		// Exclude them from the manual-attributes repeater — DOUBLE_OPT-IN is
		// maintained by Brevo's own DOI flow; OPT_IN is set by external sign-up
		// integrations. Recipe authors who want to gate DOI should use this
		// action's dedicated Double-opt-in fields.
		$system_excluded = array( 'DOUBLE_OPT-IN', 'OPT_IN' );

		$writable = array();
		foreach ( $response['data']['attributes'] as $attribute ) {
			// Skip system-managed attributes (cannot be set via API).
			if ( 'global' === ( $attribute['category'] ?? '' ) || ! empty( $attribute['calculatedValue'] ) ) {
				continue;
			}
			if ( in_array( $attribute['name'] ?? '', $system_excluded, true ) ) {
				continue;
			}
			// Skip user-type attributes — they reference internal Brevo team-member IDs.
			// Surfacing them as a text input would require recipe authors to memorize
			// numeric user IDs. Revisit if/when we add a Brevo users remote_data source.
			if ( 'user' === ( $attribute['type'] ?? '' ) ) {
				continue;
			}
			$type = $attribute['type'] ?? '';
			// Some category attributes arrive type-less but with an enumeration; treat as category.
			if ( empty( $type ) && isset( $attribute['enumeration'] ) ) {
				$attribute['type'] = 'category';
			}
			if ( empty( $attribute['type'] ) ) {
				continue;
			}
			$writable[] = $attribute;
		}

		usort(
			$writable,
			function ( $a, $b ) {
				return strcmp( $a['name'] ?? '', $b['name'] ?? '' );
			}
		);

		$this->helpers->save_app_option( $this->helpers->get_option_key( 'attributes_raw' ), $writable );

		return $writable;
	}

	/**
	 * Get templates.
	 *
	 * @param bool $force_refresh When true, bypasses the cache and re-fetches from the API.
	 *
	 * @return array
	 */
	public function get_templates( $force_refresh = false ) {
		return $this->get_cached_options( 'templates', $force_refresh );
	}

	/**
	 * Get list options.
	 *
	 * @param bool $force_refresh When true, bypasses the cache and re-fetches from the API.
	 *
	 * @return array
	 */
	public function get_lists( $force_refresh = false ) {
		return $this->get_cached_options( 'lists', $force_refresh );
	}

	/**
	 * Get cached options — read from app_option or fall back to a paged API fetch and persist.
	 *
	 * @param string $type          'templates' or 'lists' — also used as the option-key suffix.
	 * @param bool   $force_refresh When true, bypasses the cache and re-fetches from the API.
	 *
	 * @return array
	 */
	public function get_cached_options( $type, $force_refresh = false ) {

		if ( ! $force_refresh ) {
			$cached = $this->helpers->get_app_option( $this->helpers->get_option_key( $type ) );
			if ( ! empty( $cached['data'] ) && ! $cached['refresh'] ) {
				return $cached['data'];
			}
		}

		$error_id = "sync_{$type}";

		try {
			$response = 'templates' === $type
				? $this->api_request( 'get_templates' )
				: $this->api_request( 'get_lists' );

			$items = ! empty( $response['data'][ $type ] ) ? $response['data'][ $type ] : array();
			if ( empty( $items ) ) {
				throw new Exception(
					sprintf(
						// translators: %s - type of item templates or lists
						esc_html_x( 'No %s were found', 'Brevo API', 'uncanny-automator' ),
						$type
					)
				);
			}
		} catch ( Exception $e ) {
			automator_log( $e->getMessage(), "Brevo::{$error_id} Error", true, 'brevo' );
			return false;
		}

		// Generate options array.
		$options = array();
		foreach ( $items as $item ) {
			$options[] = array(
				'value' => $item['id'],
				'text'  => $item['name'],
			);
		}

		// Sort by text value.
		usort(
			$options,
			function ( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		$this->helpers->save_app_option( $this->helpers->get_option_key( $type ), $options );

		return $options;
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 * @param  array $args - Any additional args passed to the caller
	 *
	 * @todo - Update to use newer error handling register_error_messages()
	 * Right now this just overrides the default handling in the abstract and is untested
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		if ( ! empty( $response['data']['error'] ) ) {
			throw new Exception( esc_html( $response['data']['error'] ), 400 );
		}

		// Check for specific error codes.
		if ( isset( $response['data']['code'] ) && ! empty( $response['data']['code'] ) ) {
			if ( 'unauthorized' === $response['data']['code'] ) {
				// Check for IP whitelist blocking in the message
				$error_message = isset( $response['data']['message'] ) ? $response['data']['message'] : '';
				if ( false !== strpos( $error_message, 'unrecognised' ) ) {
					throw new Exception(
						sprintf(
							// translators: %s: Link to Brevo security page
							esc_html_x( 'Brevo request blocked due to unknown IP address. Please [visit Security → Authorized IPs](%s) to deactivate blocking.', 'Brevo', 'uncanny-automator' ),
							esc_url( $this->helpers->get_const( 'BREVO_IP_SECURITY_LINK' ) )
						),
						400
					);
				}

				throw new Exception( esc_html( $this->helpers->get_invalid_key_message() ), 400 );
			}
		}

		// General status code check (after specific error code handling)
		if ( $response['statusCode'] >= 400 ) {
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : esc_html_x( 'Brevo API Error', 'Brevo', 'uncanny-automator' );
			throw new Exception( esc_html( $message ), 400 );
		}
	}
}
