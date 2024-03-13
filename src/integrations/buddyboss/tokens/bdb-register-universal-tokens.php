<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Tokens\Token;

/**
 *
 */
class BDB_Register_Universal_Tokens {

	/**
	 * Integration ID.
	 *
	 * @var string
	 */
	protected $integration;

	/**
	 * Array of xProfile fields.
	 *
	 * @var array
	 */
	protected $fields;

	/**
	 * Option Key.
	 *
	 * @var string
	 */
	private $option_key = 'automator_bp_xprofile_fields';

	/**
	 * BP_Register_Universal_Tokens Constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Add hooks to listen to fields being saved or deleted.
		$this->register_cache_handlers();

		// Get all fields.
		$this->fields = $this->get_profile_fields();
		if ( empty( $this->fields ) ) {
			return;
		}

		// Loop through fields and define tokens.
		foreach ( $this->fields as $field_id => $field_name ) {
			new BDB_Universal_Token( $field_id, $field_name );
		}
	}

	/**
	 * Register cache handlers.
	 *
	 * @return void
	 */
	public function register_cache_handlers() {
		// Fires after field instance gets saved.
		add_action( 'xprofile_field_after_save', array( $this, 'set_cache' ) );
		// Fires after field instance gets deleted.
		add_action( 'xprofile_field_after_delete', array( $this, 'set_cache' ) );
	}

	/**
	 * Set cache.
	 *
	 * @return void
	 */
	public function set_cache() {

		global $wpdb;
		$fields_table    = $wpdb->base_prefix . 'bp_xprofile_fields';
		$xprofile_fields = $wpdb->get_results( "SELECT * FROM {$fields_table} WHERE parent_id = 0 ORDER BY field_order ASC" );
		$fields          = array();
		if ( ! empty( $xprofile_fields ) ) {
			foreach ( $xprofile_fields as $field ) {
				$fields[ $field->id ] = $field->name;
			}
		}

		$data = array(
			'time'   => time(),
			'fields' => $fields,
		);

		update_option( $this->option_key, $data, 'no' );
	}

	/**
	 * Get all profile fields.
	 *
	 * @return array
	 */
	public function get_profile_fields() {

		$data   = get_option( $this->option_key, array() );
		$time   = isset( $data['time'] ) ? $data['time'] : 0;
		$fields = isset( $data['fields'] ) ? $data['fields'] : array();

		// If cache is older than 1 hour, or fields are empty, refresh it.
		if ( ( time() - $time ) > 3600 || empty( $fields ) ) {
			$this->set_cache();
			$data = get_option( $this->option_key, array() );
		}

		return isset( $data['fields'] ) ? $data['fields'] : array();
	}
}
