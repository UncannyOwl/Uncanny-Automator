<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Action;

/**
 * Class SAVETO_WISHLIST_CREATE_WISHLIST
 *
 * Action: Create {{a wishlist}} for the user.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_CREATE_WISHLIST extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SAVETO_WISHLIST' );
		$this->set_action_code( 'SAVETO_WISHLIST_CREATE_WISHLIST' );
		$this->set_action_meta( 'WISHLIST_NAME' );
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Wishlist name */
				esc_html_x( 'Create {{a wishlist:%1$s}}', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a wishlist}}', 'SaveTo Wishlist', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Wishlist name', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'WISHLIST_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code' => 'WISHLIST_IS_DEFAULT',
				'label'       => esc_html_x( "Set as the user's default wishlist", 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'required'    => false,
				'default_value' => false,
			),
			array(
				'option_code' => 'WISHLIST_IS_PUBLIC',
				'label'       => esc_html_x( 'Make wishlist public', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'required'    => false,
				'default_value' => false,
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'WISHLIST_ID'        => array(
				'name' => esc_html_x( 'Wishlist ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'int',
			),
			'WISHLIST_URL_CODE'  => array(
				'name' => esc_html_x( 'Wishlist URL code', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WISHLIST_NAME_OUT'  => array(
				'name' => esc_html_x( 'Wishlist name', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! $this->item_helpers->saveto_lite_active() ) {
			$this->add_log_error( 'SaveTo Wishlist Lite is not active.' );
			return false;
		}

		$user_id    = absint( $user_id );
		$name       = trim( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$desc       = wp_kses_post( $parsed['WISHLIST_DESCRIPTION'] ?? '' );
		$is_default = $this->parse_truthy( $parsed['WISHLIST_IS_DEFAULT'] ?? '' );
		$is_public  = $this->parse_truthy( $parsed['WISHLIST_IS_PUBLIC'] ?? '' );

		if ( $user_id <= 0 ) {
			$this->add_log_error( 'A valid user is required.' );
			return false;
		}

		if ( '' === $name ) {
			$this->add_log_error( 'A wishlist name is required.' );
			return false;
		}

		$collection = \SaveToWishlist\Classes\Factories\Collections::instance()->save_collection(
			array(
				'user_id'     => $user_id,
				'name'        => $name,
				'description' => $desc,
				'status'      => 'publish',
				'is_default'  => $is_default ? 1 : 0,
				'is_public'   => $is_public ? 1 : 0,
			)
		);

		if ( empty( $collection ) || empty( $collection->id ) ) {
			$this->add_log_error( 'Failed to create wishlist.' );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'WISHLIST_ID'       => (int) $collection->id,
				'WISHLIST_URL_CODE' => isset( $collection->url_code ) ? (string) $collection->url_code : '',
				'WISHLIST_NAME_OUT' => isset( $collection->name ) ? (string) $collection->name : '',
			)
		);

		return true;
	}

	/**
	 * Coerce assorted truthy representations (1, "1", "yes", true) to bool.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private function parse_truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : $value;
		return in_array( $value, array( 1, '1', 'yes', 'true', 'on' ), true );
	}
}
