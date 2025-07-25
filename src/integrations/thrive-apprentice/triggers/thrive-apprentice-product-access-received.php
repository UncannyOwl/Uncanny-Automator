<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_PRODUCT_ACCESS_RECEIVED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_PRODUCT_ACCESS_RECEIVED extends Trigger {

	protected $helper;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_PRODUCT_ACCESS_RECEIVED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_PRODUCT_ACCESS_RECEIVED_META';


	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	protected function setup_trigger() {

		$this->helper = array_shift( $this->dependencies );

		$this->set_integration( 'THRIVE_APPRENTICE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_pro( false );

		$this->set_is_login_required( true );

		// The action hook to attach this trigger into.
		$this->add_action( 'tva_user_receives_product_access' );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Product Name */
				esc_html_x( 'A user receives access to {{a product:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user receives access to {{a product}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Loads all options.
	 *
	 * @return array The list of options.
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Product', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->get_helper()->get_dropdown_options_products(true, true),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		list( $user, $product_id ) = $hook_args;

		if ( empty( $user ) || empty( $product_id ) ) {
			return false;
		}

		$this->set_user_id( absint( $user->ID ) );

		$product_id          = absint( $product_id );
		$selected_product_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// Match if any product is selected (-1) or if specific product matches
		return intval( '-1' ) === intval( $selected_product_id ) || (int) $selected_product_id === (int) $product_id;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'PRODUCT_ID'   => array(
				'name'      => esc_html_x( 'Product ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'PRODUCT_NAME' => array(
				'name'      => esc_html_x( 'Product name', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'PRODUCT_NAME',
				'tokenName' => esc_html_x( 'Product name', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $user, $product_id ) = $hook_args;

		if ( empty( $product_id ) ) {
			return array();
		}

		$product = new \TVA\Product( $product_id );

		return array(
			'PRODUCT_ID'   => $product_id,
			'PRODUCT_NAME' => $product->get_name(),
		);
	}
}
