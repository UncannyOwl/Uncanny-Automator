<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Tokens\Universal_Token;

/**
 *
 */
class BP_Universal_Token extends Universal_Token {

	/**
	 * @var string
	 */
	protected $integration;

	/**
	 * @var
	 */
	protected $fields;

	/**
	 * @param $id
	 * @param $name
	 */
	public function __construct( $id = null, $name = null ) {

		if ( null === $id || null === $name ) {
			return;
		}

		$this->integration   = 'BP';
		$this->id            = (string) $id;
		$this->name          = $name;
		$this->requires_user = true;
		$this->cacheable     = false;

		parent::__construct();
	}

	/**
	 * @param $return
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$field_id = $pieces[2];

		// Change the user ID to the current iterated user in the context of a Loop.
		if ( isset( $replace_args['loop'] ) && is_array( $replace_args['loop'] ) && isset( $replace_args['loop']['user_id'] ) ) {
			$user_id = absint( $replace_args['loop']['user_id'] );
		}

		$field_data = xprofile_get_field_data( $field_id, $user_id );

		return apply_filters( 'automator_bp_xprofile_field_data', $field_data, $field_id, $user_id );
	}
}
