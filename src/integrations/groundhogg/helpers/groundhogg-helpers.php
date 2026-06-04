<?php

namespace Uncanny_Automator\Integrations\Groundhogg;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Groundhogg_Helpers
 *
 * @package Uncanny_Automator\Integrations\Groundhogg
 */
class Groundhogg_Helpers extends Abstract_Helpers {

	/**
	 * Get the Groundhogg utils instance.
	 *
	 * @return object The Groundhogg Plugin utils.
	 * @throws \Exception If Groundhogg is not available.
	 */
	public function groundhogg_utils() {
		if ( ! class_exists( '\Groundhogg\Plugin' ) || empty( \Groundhogg\Plugin::$instance ) ) {
			throw new \Exception( esc_html_x( 'Groundhogg is not available.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return \Groundhogg\Plugin::$instance->utils;
	}

	/**
	 * Get a Groundhogg database table instance.
	 *
	 * @param string $table The table name (e.g. 'tags', 'contacts').
	 *
	 * @return object The Groundhogg DB table instance.
	 */
	public function groundhogg_db( $table ) {
		if ( ! function_exists( '\Groundhogg\get_db' ) ) {
			throw new \Exception( esc_html_x( 'Groundhogg is not available.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return \Groundhogg\get_db( $table );
	}

	/**
	 * Get the Groundhogg Preferences class.
	 *
	 * @return string The fully qualified Preferences class name.
	 * @throws \Exception If the Preferences class is not available.
	 */
	public function groundhogg_preferences() {
		if ( ! class_exists( '\Groundhogg\Preferences' ) ) {
			throw new \Exception( esc_html_x( 'Groundhogg Preferences is not available.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return '\Groundhogg\Preferences';
	}

	/**
	 * Get all tags as modern option format.
	 *
	 * @return array
	 */
	private function get_tag_options() {
		try {
			$results = $this->groundhogg_db( 'tags' )->query( array() );
		} catch ( \Exception $e ) {
			return array();
		}

		$options = array();

		foreach ( $results as $tag ) {
			$options[] = array(
				'value' => $tag->tag_id,
				'text'  => $tag->tag_name,
			);
		}

		return $options;
	}

	/**
	 * Tag select field definition for actions (no "Any" option).
	 *
	 * @param string $option_code The option code for the field.
	 *
	 * @return array
	 */
	public function action_tag_option_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Tag', 'Groundhogg', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'tags_strict' ),
		);
	}

	/**
	 * Contact email field definition for actions.
	 *
	 * @return array
	 */
	public function contact_email_option_config() {
		return array(
			'option_code' => 'CONTACT_EMAIL',
			'label'       => esc_html_x( 'Contact email', 'Groundhogg', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Validate an email and return the matching Groundhogg contact.
	 *
	 * @param string $email The email address to validate and look up.
	 *
	 * @return \Groundhogg\Contact
	 * @throws \Exception If the email is invalid or the contact is not found.
	 */
	public function get_contact_by_email( $email ) {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			throw new \Exception( esc_html_x( 'Invalid email address.', 'Groundhogg', 'uncanny-automator' ) );
		}

		$contact = $this->groundhogg_utils()->get_contact( $email, false );

		if ( ! $contact ) {
			throw new \Exception( esc_html_x( 'Contact was not found.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return $contact;
	}

	/**
	 * Get a Groundhogg contact by WordPress user ID.
	 *
	 * @param int $user_id The WordPress user ID.
	 *
	 * @return \Groundhogg\Contact
	 * @throws \Exception If Groundhogg is unavailable or the contact is not found.
	 */
	public function get_contact_by_user_id( $user_id ) {
		if ( 0 === absint( $user_id ) ) {
			throw new \Exception( esc_html_x( 'Invalid user ID.', 'Groundhogg', 'uncanny-automator' ) );
		}

		$contact = $this->groundhogg_utils()->get_contact( absint( $user_id ), true );

		if ( ! $contact ) {
			throw new \Exception( esc_html_x( 'Contact was not found.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return $contact;
	}

	/**
	 * Create a Groundhogg Contact instance.
	 *
	 * Accepts a contact ID or email. Does not validate existence —
	 * caller decides whether to create or require an existing contact.
	 *
	 * @param int|string $identifier Contact ID or email.
	 *
	 * @return \Groundhogg\Contact
	 * @throws \Exception If the Contact class is unavailable.
	 */
	public function new_contact( $identifier ) {
		if ( ! class_exists( '\Groundhogg\Contact' ) ) {
			throw new \Exception( esc_html_x( 'Groundhogg is not available.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return new \Groundhogg\Contact( $identifier );
	}

	/**
	 * Get an existing Groundhogg contact by contact ID.
	 *
	 * @param int $contact_id The Groundhogg contact ID.
	 *
	 * @return \Groundhogg\Contact
	 * @throws \Exception If the Contact class is unavailable or the contact doesn't exist.
	 */
	public function get_contact_by_id( $contact_id ) {
		$contact = $this->new_contact( $contact_id );

		if ( ! $contact->exists() ) {
			throw new \Exception( esc_html_x( 'Contact was not found.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return $contact;
	}

	/**
	 * Resolve a tag ID, creating the tag by name if it doesn't exist.
	 *
	 * @param string|int $tag_id The tag ID or name.
	 *
	 * @return int The resolved tag ID.
	 */
	public function resolve_tag_id( $tag_id ) {
		$tags_db = $this->groundhogg_db( 'tags' );

		if ( false === $tags_db->exists( $tag_id ) ) {
			$tag_id = $tags_db->add( array( 'tag_name' => $tag_id ) );
		}

		return absint( $tag_id );
	}

	/**
	 * Require a tag ID to exist. Throws if it doesn't.
	 *
	 * @param string|int $tag_id The tag ID.
	 *
	 * @return int The validated tag ID.
	 * @throws \Exception If the tag does not exist.
	 */
	public function require_tag_id( $tag_id ) {
		$tags_db = $this->groundhogg_db( 'tags' );

		if ( false === $tags_db->exists( $tag_id ) ) {
			throw new \Exception( esc_html_x( 'Tag does not exist.', 'Groundhogg', 'uncanny-automator' ) );
		}

		return absint( $tag_id );
	}

	/**
	 * Remote-data handler: fetch tags for action fields (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tags_strict( $request ): array {
		return $this->remote_data_success( $this->get_tag_options() );
	}

	/**
	 * Remote-data handler: fetch tags for trigger fields (with "Any tag" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tags( $request ): array {
		$options = $this->get_tag_options();

		array_unshift(
			$options,
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any tag', 'Groundhogg', 'uncanny-automator' ),
			)
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: fetch custom properties as transposed repeater fields.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_custom_properties( $request ): array {
		if ( ! class_exists( '\Groundhogg\Properties' ) || ! method_exists( '\Groundhogg\Properties', 'instance' ) ) {
			// Preserve the nested field_properties.fields shape on errors so
			// downstream consumers can read `field_properties.fields` without
			// branching on success/error. The generic remote_data_error()
			// would flatten this to `field_properties => []`.
			return array(
				'success'          => false,
				'error'            => esc_html_x( 'Custom properties are not available.', 'Groundhogg', 'uncanny-automator' ),
				'field_properties' => array( 'fields' => array() ),
			);
		}

		$properties = \Groundhogg\Properties::instance();
		$tabs       = $properties->get_tabs();
		$groups     = $properties->get_groups();
		$fields     = $properties->get_fields();

		if ( empty( $fields ) ) {
			return array(
				'success'          => false,
				'error'            => esc_html_x( 'No custom properties defined.', 'Groundhogg', 'uncanny-automator' ),
				'field_properties' => array( 'fields' => array() ),
			);
		}

		// Build lookup maps for tab/group names.
		$tab_names = wp_list_pluck( $tabs, 'name', 'id' );
		$group_map = array();
		foreach ( $groups as $group ) {
			$group_map[ $group['id'] ] = array(
				'name' => $group['name'] ?? '',
				'tab'  => $group['tab'] ?? '',
			);
		}

		$repeater_fields = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || empty( $field['label'] ) ) {
				continue;
			}

			// Build prefixed label: Tab > Group > Field.
			$label_parts = array();
			$group_info  = $group_map[ $field['group'] ] ?? null;

			if ( $group_info ) {
				$tab_name = $tab_names[ $group_info['tab'] ] ?? '';
				if ( ! empty( $tab_name ) ) {
					$label_parts[] = $tab_name;
				}
				if ( ! empty( $group_info['name'] ) ) {
					$label_parts[] = $group_info['name'];
				}
			}

			$label_parts[] = $field['label'];
			$label         = implode( ' > ', $label_parts );

			$automator_type = $this->map_groundhogg_field_type( $field['type'] ?? 'text' );

			$field_config = array(
				'option_code'     => $field['name'],
				'label'           => $label,
				'input_type'      => $automator_type,
				'supports_tokens' => true,
				'required'        => false,
			);

			// Add options for select-type fields.
			if ( 'select' === $automator_type && ! empty( $field['options'] ) ) {
				$field_type  = $field['type'] ?? '';
				$is_multiple = 'checkboxes' === $field_type || ( 'dropdown' === $field_type && ! empty( $field['multiple'] ) );

				$field_config['supports_custom_value'] = true;
				$field_config['options']               = automator_array_as_options(
					array_combine( $field['options'], $field['options'] )
				);

				if ( $is_multiple ) {
					$field_config['supports_multiple_values'] = true;
				} else {
					$field_config['default_value'] = '';
					$field_config['options']       = array_merge(
						array(
							array(
								'value' => '',
								'text'  => esc_html_x( 'Select option', 'Groundhogg', 'uncanny-automator' ),
							),
						),
						$field_config['options'],
						array(
							array(
								'value' => '[DELETE]',
								'text'  => esc_html_x( 'Delete value', 'Groundhogg', 'uncanny-automator' ),
							),
						)
					);
				}
			}

			$repeater_fields[] = $field_config;
		}

		return $this->remote_data_success( array( 'fields' => $repeater_fields ), 'field_properties' );
	}

	/**
	 * Map Groundhogg field type to Automator input type.
	 *
	 * @param string $type The Groundhogg field type.
	 *
	 * @return string The Automator input type.
	 */
	private function map_groundhogg_field_type( $type ) {
		$map = array(
			'text'       => 'text',
			'textarea'   => 'textarea',
			'number'     => 'text',
			'url'        => 'url',
			'email'      => 'email',
			'tel'        => 'text',
			'date'       => 'date',
			'time'       => 'time',
			'datetime'   => 'text',
			'dropdown'   => 'select',
			'checkboxes' => 'select',
			'radio'      => 'select',
			'html'       => 'textarea',
		);

		return $map[ $type ] ?? 'text';
	}

	/**
	 * Remote-data handler: fetch opt-in statuses from Groundhogg.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_optin_statuses( $request ): array {
		try {
			$preferences = $this->groundhogg_preferences();
		} catch ( \Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}

		$options = array_merge(
			array(
				array(
					'value' => '',
					'text'  => esc_html_x( 'Leave unchanged', 'Groundhogg', 'uncanny-automator' ),
				),
			),
			automator_array_as_options( $preferences::get_preference_names() )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote-data handler: fetch countries list from Groundhogg.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_countries( $request ): array {
		try {
			$countries = $this->groundhogg_utils()->location->get_countries_list();
		} catch ( \Exception $e ) {
			$countries = array();
		}

		$options = array_merge(
			array(
				array(
					'value' => '',
					'text'  => esc_html_x( 'Select a country', 'Groundhogg', 'uncanny-automator' ),
				),
			),
			automator_array_as_options( $countries )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Get distinct contact meta keys as option format.
	 *
	 * Queries the contactmeta table for all unique keys, excluding
	 * system/internal keys that Groundhogg manages via dedicated UI.
	 *
	 * @return array
	 */
	public function get_meta_key_options() {
		global $wpdb;

		$table = $wpdb->prefix . 'gh_contactmeta';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe, built from $wpdb->prefix.
		$keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$table} ORDER BY meta_key ASC" );

		if ( ! is_array( $keys ) ) {
			return array();
		}

		$exclusions = $this->get_system_meta_keys();
		$options    = array();

		foreach ( $keys as $key ) {
			if ( str_starts_with( $key, '_' ) || in_array( $key, $exclusions, true ) ) {
				continue;
			}

			$options[] = array(
				'value' => $key,
				'text'  => $key,
			);
		}

		return $options;
	}

	/**
	 * Get system meta keys excluded from the custom meta dropdown.
	 *
	 * Attempts to get the list from Groundhogg's Contacts_Page class.
	 * Falls back to applying the same filter with a known default so
	 * extensions can still add exclusions.
	 *
	 * @return array
	 */
	private function get_system_meta_keys() {
		static $exclusions = null;

		if ( null !== $exclusions ) {
			return $exclusions;
		}

		// Mirror Groundhogg's own exclusion list from Contacts_Page::get_meta_key_exclusions().
		// We replicate the defaults here instead of instantiating the admin page class,
		// which causes side effects (script enqueues, admin hook registration) during AJAX.
		// The same filter is applied so extensions can add their own exclusions.
		$exclusions = apply_filters(
			'groundhogg/admin/contacts/exclude_meta_list', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Groundhogg's own filter name.
			array(
				'alternate_emails',
				'alternate_phones',
				'birthday',
				'birthday_month',
				'birthday_day',
				'birthday_year',
				'lead_source',
				'source_page',
				'page_source',
				'terms_agreement',
				'terms_agreement_date',
				'gdpr_consent',
				'gdpr_consent_date',
				'marketing_consent',
				'marketing_consent_date',
				'mobile_phone',
				'primary_phone',
				'primary_phone_extension',
				'street_address_1',
				'street_address_2',
				'time_zone',
				'times_logged_in',
				'user_login',
				'city',
				'postal_zip',
				'region',
				'country',
				'notes',
				'files',
				'ip_address',
				'last_optin',
				'last_sent',
				'country_name',
				'region_code',
				'locale',
			)
		);

		// Also exclude custom property field names — those are
		// handled by the dedicated Custom properties repeater.
		if ( class_exists( '\Groundhogg\Properties' ) && method_exists( '\Groundhogg\Properties', 'instance' ) ) {
			$fields = \Groundhogg\Properties::instance()->get_fields();
			foreach ( $fields as $field ) {
				if ( ! empty( $field['name'] ) ) {
					$exclusions[] = $field['name'];
				}
			}
		}

		return $exclusions;
	}
}
