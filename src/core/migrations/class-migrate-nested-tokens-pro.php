<?php

namespace Uncanny_Automator\Migrations;

/**
 * Migrate_Nested_Tokens_Pro.
 *
 * @package Uncanny_Automator
 */
class Migrate_Nested_Tokens_Pro extends Tokens_Migration {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct( $name ) {
		parent::__construct( $name );
		add_filter( 'automator_recipe_part_meta_value', array( $this, 'replace_strings_in_imports' ), 10, 4 );
	}

	/**
	 * strings_to_replace
	 *
	 * @return array
	 */
	public function strings_to_replace() {

		return array(
			'{{AUTOLOGINLINK}}' => '{{UT:ADVANCED:AUTOLOGIN_LINK}}',
		);
	}

	/**
	 * conditions_met
	 *
	 * @return bool
	 */
	public function conditions_met() {

		// Check if Automator Pro is active
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return false;
		}

		// Check if the Pro version is greater than or equal to 6.2
		return version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '6.2', '>=' );
	}
}

new Migrate_Nested_Tokens_Pro( '6.5_nested_tokens_pro' );
