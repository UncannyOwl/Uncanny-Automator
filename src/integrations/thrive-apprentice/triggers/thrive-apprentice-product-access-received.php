<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_PRODUCT_ACCESS_RECEIVED
 *
 */
class THRIVE_APPRENTICE_PRODUCT_ACCESS_RECEIVED extends Trigger {

	const TRIGGER_CODE = 'THRIVE_APPRENTICE_PRODUCT_ACCESS_RECEIVED';
	const TRIGGER_META = 'THRIVE_APPRENTICE_PRODUCT_ACCESS_RECEIVED_META';

	/**
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup trigger.
	 */
	protected function setup_trigger() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );

		$this->add_action( 'tva_user_receives_product_access' );
		$this->set_action_args_count( 2 );

		$this->set_sentence(
			// translators: %1$s: Product Name
			sprintf(
				esc_html_x( 'A user receives access to {{a product:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user receives access to {{a product}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Product', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_products( true, true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function validate( $trigger, $hook_args ) {
		list( $user, $product ) = $hook_args;

		if ( empty( $user ) || empty( $product ) ) {
			return false;
		}

		$selected_product_id = intval( $trigger['meta'][ $this->get_trigger_meta() ] );
		$user_id             = $user instanceof \WP_User ? $user->ID : intval( $user );

		$this->set_user_id( $user_id );

		if ( -1 === $selected_product_id ) {
			return true;
		}

		$product_id = $this->get_product_id( $product );

		return $product_id === $selected_product_id;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $user, $product ) = $hook_args;

		$product_data = $this->get_product_data( $product );

		return array(
			'PRODUCT_ID'    => $product_data['id'],
			'PRODUCT_TITLE' => $product_data['title'],
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $tokens The destination.
	 * @return mixed
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'PRODUCT_ID'    => array(
				'name'      => esc_html_x( 'Product ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'PRODUCT_TITLE' => array(
				'name'      => esc_html_x( 'Product title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'PRODUCT_TITLE',
				'tokenName' => esc_html_x( 'Product title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Extract product ID from various product object types
	 *
	 * @param mixed $product
	 * @return int|null
	 */
	private function get_product_id( $product ) {
		// TVA\Product object
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return intval( $product->get_id() );
		}

		// WP_Term object (product term)
		if ( $product instanceof \WP_Term && 'tva_product' === $product->taxonomy ) {
			return intval( $product->term_id );
		}

		// Numeric value
		if ( is_numeric( $product ) ) {
			return intval( $product );
		}

		// Array with ID
		if ( is_array( $product ) && isset( $product['ID'] ) ) {
			return intval( $product['ID'] );
		}

		// Array with term_id
		if ( is_array( $product ) && isset( $product['term_id'] ) ) {
			return intval( $product['term_id'] );
		}

		// TVA\Product object with get_term_id method
		if ( is_object( $product ) && method_exists( $product, 'get_term_id' ) ) {
			return intval( $product->get_term_id() );
		}

		// Object with term_id property
		if ( is_object( $product ) && isset( $product->term_id ) ) {
			return intval( $product->term_id );
		}

		return null;
	}

	/**
	 * Extract product data (ID and title) from various product object types
	 *
	 * @param mixed $product
	 * @return array
	 */
	private function get_product_data( $product ) {
		$product_id    = null;
		$product_title = '';

		// TVA\Product object
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$product_id    = $product->get_id();
			$product_title = method_exists( $product, 'get_name' ) ? $product->get_name() : '';
		} elseif ( $product instanceof \WP_Term && 'tva_product' === $product->taxonomy ) {
			// WP_Term object (product term)
			$product_id    = $product->term_id;
			$product_title = $product->name;
		} elseif ( is_numeric( $product ) ) {
			// Numeric value
			$product_id    = intval( $product );
			$product_term  = get_term( $product_id, 'tva_product' );
			$product_title = $product_term instanceof \WP_Term ? $product_term->name : '';
		} elseif ( is_array( $product ) && isset( $product['ID'] ) ) {
			// Array with ID
			$product_id    = intval( $product['ID'] );
			$product_title = isset( $product['name'] ) ? $product['name'] : '';
		} elseif ( is_array( $product ) && isset( $product['term_id'] ) ) {
			// Array with term_id
			$product_id    = intval( $product['term_id'] );
			$product_title = isset( $product['name'] ) ? $product['name'] : '';
		} elseif ( is_object( $product ) && method_exists( $product, 'get_term_id' ) ) {
			// TVA\Product object with get_term_id method
			$product_id    = $product->get_term_id();
			$product_title = method_exists( $product, 'get_name' ) ? $product->get_name() : '';
		} elseif ( is_object( $product ) && isset( $product->term_id ) ) {
			// Object with term_id property
			$product_id    = $product->term_id;
			$product_title = isset( $product->name ) ? $product->name : '';
		}

		return array(
			'id'    => $product_id,
			'title' => $product_title,
		);
	}
}
