<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\SureCart;

class_alias( 'Uncanny_Automator\Integrations\SureCart\SureCart_Helpers', 'Uncanny_Automator\SureCart_Helpers' );

use SureCart\Models\Product;

/**
 * Class SureCart_Helpers
 *
 * @package Uncanny_Automator
 */
class SureCart_Helpers {

	/**
	 * get_products_dropdown
	 *
	 * @param  mixed $add_any
	 * @return array
	 */
	public function get_products_dropdown( $add_any = true ) {

		$options = array();

		if ( $add_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any product', 'Surecart', 'uncanny-automator' ),
			);
		}

		/** @var \SureCart\Models\Product[] $products */
		$products = class_exists( 'SureCart\Models\Product' ) ? Product::get() : array();

		foreach ( $products as $product ) {
			$options[] = array(
				'value' => $product->id,
				'text'  => $product->name,
			);
		}

		usort( $options, 'automator_sort_options' );

		$dropdown = array(
			'input_type'            => 'select',
			'option_code'           => 'PRODUCT',
			'label'                 => esc_attr_x( 'Product', 'Surecart', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => true,
			'options'               => $options,
		);

		return $dropdown;
	}

	/**
	 * get_products_dropdown_options
	 *
	 * @param  mixed $add_any
	 * @return array
	 */
	public function get_products_dropdown_options( $add_any = true ) {
		$options = array();

		if ( $add_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any product', 'Surecart', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		/** @var \SureCart\Models\Product[] $products */
		$products = class_exists( 'SureCart\Models\Product' ) ? Product::get() : array();

		foreach ( $products as $product ) {
			$options[] = array(
				'text'  => $product->name,
				'value' => (string) $product->id,
			);
		}

		usort( $options, 'automator_sort_options' );

		return $options;
	}

	/**
	 * Get WordPress user ID from SureCart customer ID
	 *
	 * @param string $customer_id The SureCart customer ID
	 * @return int|null WordPress user ID if found, null otherwise
	 */
	public function get_user_id_from_customer( $customer_id ) {
		if ( empty( $customer_id ) ) {
			return null;
		}

		if ( ! class_exists( 'SureCart\Models\User' ) ) {
			return null;
		}

		try {
			$wp_user = \SureCart\Models\User::findByCustomerId( $customer_id );
			if ( $wp_user && ! empty( $wp_user->ID ) ) {
				return absint( $wp_user->ID );
			}
		} catch ( \Exception $e ) {
			// Log error if needed but don't break the flow
			return null;
		}

		return null;
	}

	/**
	 * support_link
	 *
	 * @return string
	 */
	public function support_link( $code ) {
		return Automator()->get_author_support_link( $code, 'integration/surecart/' );
	}
}
