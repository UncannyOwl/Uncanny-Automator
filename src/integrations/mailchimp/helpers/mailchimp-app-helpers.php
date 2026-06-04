<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

use Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Mailchimp_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Mailchimp_Api_Caller $api
 * @property Mailchimp_Webhooks $webhooks
 */
class Mailchimp_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Map to existing option name to preserve compatibility with pre-migration data.
		$this->set_credentials_option_name( '_uncannyowl_mailchimp_settings' );
	}

	/**
	 * Get account info extracted from credentials.
	 *
	 * Mailchimp stores account info (login->login_name, login->email) directly
	 * in credentials rather than a separate account option.
	 *
	 * @return array Account info with 'name' and 'email' keys.
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();

		if ( empty( $credentials ) ) {
			return array();
		}

		return array(
			'name'  => $credentials['account_name'] ?? $credentials['login']['login_name'] ?? '',
			'email' => $credentials['login']['email'] ?? $credentials['email'] ?? '',
		);
	}

	////////////////////////////////////////////////////////////
	// Recipe UI data methods
	////////////////////////////////////////////////////////////

	/**
	 * Get audience options.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_audiences( $request ): array {
		try {
			return $this->remote_data_success( $this->get_audiences( $request->is_refresh() ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get trigger audience options (audiences prefixed with "Any audience").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_trigger_audiences( $request ): array {
		try {
			$audiences = $this->get_audiences( $request->is_refresh() );
			$options   = array_merge(
				array(
					array(
						'value' => '-1',
						'text'  => esc_html_x( 'Any audience', 'Mailchimp', 'uncanny-automator' ),
					),
				),
				$audiences
			);

			return $this->remote_data_success( $options );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get all audience (lists) options.
	 *
	 * @param bool $refresh Whether to force refresh from API.
	 *
	 * @return array Array of audience options with 'value' and 'text' keys.
	 * @throws Exception If API call fails.
	 */
	private function get_audiences( $refresh = false ) {
		$option_key    = 'automator_mailchimp_audiences';
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$options = array();
		$lists   = $this->api->get_lists();

		if ( ! empty( $lists ) ) {
			foreach ( $lists as $list ) {
				$options[] = array(
					'value' => $list['id'],
					'text'  => $list['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get list groups/interests.
	 *
	 * Returns interests grouped by category in "Category > Interest" format.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups( $request ): array {
		$list_id = $request->get_field_value( 'MCLIST' );

		if ( empty( $list_id ) ) {
			return $this->remote_data_success( array() );
		}

		try {
			return $this->remote_data_success( $this->get_groups( $list_id, $request->is_refresh() ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get groups (interests) for a list.
	 *
	 * Returns interests grouped by category in "Category > Interest" format.
	 * Results are cached with the list ID in the option key.
	 *
	 * @param string $list_id The Mailchimp list ID.
	 * @param bool   $refresh Whether to force refresh from API.
	 *
	 * @return array Array of group options with 'value' and 'text' keys.
	 * @throws Exception If API call fails.
	 */
	private function get_groups( $list_id, $refresh = false ) {
		$option_key    = 'automator_mailchimp_groups_' . $list_id;
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$options    = array();
		$categories = $this->api->get_list_categories( $list_id );

		foreach ( $categories as $category ) {
			$interests = $this->api->get_interests( $list_id, $category['id'] );
			foreach ( $interests as $interest ) {
				$options[] = array(
					'value' => $interest['id'],
					'text'  => $category['title'] . ' > ' . $interest['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get list tags.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tags( $request ): array {
		$list_id = $request->get_field_value( 'MCLIST' );

		if ( empty( $list_id ) ) {
			return $this->remote_data_success( array() );
		}

		try {
			return $this->remote_data_success( $this->get_tags( $list_id, $request->is_refresh() ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get tags for a list.
	 *
	 * Tags are static segments in Mailchimp. Uses tag name as value since
	 * Mailchimp API expects names, not IDs for tag operations.
	 *
	 * @param string $list_id The Mailchimp list ID.
	 * @param bool   $refresh Whether to force refresh from API.
	 *
	 * @return array Array of tag options with 'value' and 'text' keys.
	 * @throws Exception If API call fails.
	 */
	private function get_tags( $list_id, $refresh = false ) {
		$option_key    = 'automator_mailchimp_tags_' . $list_id;
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$options = array();
		$tags    = $this->api->get_segments( $list_id, 'static' );

		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$options[] = array(
					'value' => $tag['name'],
					'text'  => $tag['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get segments from list.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_segments( $request ): array {
		$list_id = $request->get_field_value( 'MCLIST' );

		// Start with empty placeholder option for legacy compatibility.
		$placeholder = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Select a segment or tag', 'Mailchimp', 'uncanny-automator' ),
			),
		);

		if ( empty( $list_id ) ) {
			return $this->remote_data_success( $placeholder );
		}

		try {
			$segments = $this->get_segments( $list_id, $request->is_refresh() );

			return $this->remote_data_success( array_merge( $placeholder, $segments ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get segments for a list.
	 *
	 * Returns all segments (both saved and static) for a list.
	 *
	 * @param string $list_id The Mailchimp list ID.
	 * @param bool   $refresh Whether to force refresh from API.
	 *
	 * @return array Array of segment options with 'value' and 'text' keys.
	 * @throws Exception If API call fails.
	 */
	private function get_segments( $list_id, $refresh = false ) {
		$option_key    = 'automator_mailchimp_segments_' . $list_id;
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$options  = array();
		$segments = $this->api->get_segments( $list_id );

		if ( ! empty( $segments ) ) {
			foreach ( $segments as $segment ) {
				$options[] = array(
					'value' => $segment['id'],
					'text'  => $segment['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get templates.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_templates( $request ): array {
		// Start with empty placeholder option.
		$placeholder = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a template', 'Mailchimp', 'uncanny-automator' ),
			),
		);

		try {
			$templates = $this->get_templates( $request->is_refresh() );

			return $this->remote_data_success( array_merge( $placeholder, $templates ) );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get templates.
	 *
	 * Returns all templates from the Mailchimp account.
	 *
	 * @param bool $refresh Whether to force refresh from API.
	 *
	 * @return array Array of template options with 'value' and 'text' keys.
	 * @throws Exception If API call fails.
	 */
	private function get_templates( $refresh = false ) {
		$option_key    = 'automator_mailchimp_templates';
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$options   = array();
		$templates = $this->api->get_templates();

		if ( ! empty( $templates ) ) {
			foreach ( $templates as $template ) {
				$options[] = array(
					'value' => $template['id'],
					'text'  => $template['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get merge fields as repeater rows.
	 *
	 * Returns merge fields in a format suitable for populating repeater rows.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_merge_field_rows( $request ): array {
		$list_id = $request->get_field_value( 'MCLIST' );

		if ( empty( $list_id ) ) {
			return $this->remote_data_success( array(), 'rows' );
		}

		try {
			$merge_fields = $this->get_merge_fields( $list_id, $request->is_refresh() );
			$rows         = $this->format_merge_fields_for_repeater( $merge_fields );

			return $this->remote_data_success( $rows, 'rows' );
		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage(), 'rows' );
		}
	}

	/**
	 * Get merge fields for a list.
	 *
	 * Returns raw merge field data from API, cached with list ID.
	 * Public to allow token classes to access cached merge field data.
	 *
	 * @param string $list_id The Mailchimp list ID.
	 * @param bool   $refresh Whether to force refresh from API.
	 *
	 * @return array Array of merge field data from Mailchimp API.
	 * @throws Exception If API call fails.
	 */
	public function get_merge_fields( $list_id, $refresh = false ) {
		$option_key    = 'automator_mailchimp_merge_fields_' . $list_id;
		$refresh_check = $refresh ? 0 : DAY_IN_SECONDS;
		$option_data   = $this->get_app_option( $option_key, $refresh_check );

		if ( ! empty( $option_data['data'] ) && ! $option_data['refresh'] ) {
			return $option_data['data'];
		}

		$merge_fields = $this->api->get_merge_fields( $list_id );

		if ( empty( $merge_fields ) ) {
			$merge_fields = array();
		}

		$this->save_app_option( $option_key, $merge_fields );

		return $merge_fields;
	}

	/**
	 * Format merge fields for repeater rows.
	 *
	 * @param array $merge_fields Raw merge field data.
	 *
	 * @return array Array of repeater row data.
	 */
	private function format_merge_fields_for_repeater( $merge_fields ) {
		$rows = array();

		foreach ( $merge_fields as $field ) {
			$rows = array_merge( $rows, $this->build_field_rows( $field ) );
		}

		return $rows;
	}

	/**
	 * Build repeater rows for a merge field.
	 *
	 * Address fields are expanded into their subfields (addr1, addr2, city, state, zip, country).
	 *
	 * @param array $field The merge field data.
	 *
	 * @return array Array of row arrays with 'FIELD_NAME' and 'FIELD_VALUE' keys.
	 */
	private function build_field_rows( $field ) {
		$tag  = $field['tag'];
		$type = $field['type'] ?? '';

		// Address fields need to be expanded into subfields.
		if ( 'address' === $type ) {
			$address_subfields = array( 'addr1', 'addr2', 'city', 'state', 'zip', 'country' );
			$rows              = array();

			foreach ( $address_subfields as $subfield ) {
				$rows[] = $this->build_field_row( $tag . '_' . $subfield );
			}

			return $rows;
		}

		// Other fields are just one row.
		return array(
			$this->build_field_row( $tag ),
		);
	}

	/**
	 * Build a single field row.
	 *
	 * @param string $field_name The field name.
	 *
	 * @return array Array with 'FIELD_NAME' and 'FIELD_VALUE' keys.
	 */
	private function build_field_row( $field_name ) {
		return array(
			'FIELD_NAME'  => $field_name,
			'FIELD_VALUE' => '',
		);
	}

}
