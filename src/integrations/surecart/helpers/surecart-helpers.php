<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use SureCart\Models\Product;

/**
 * Class SureCart_Helpers
 *
 * @package Uncanny_Automator
 */
class SureCart_Helpers {

	public function get_products_dropdown( $add_any = true ) {

		$options = array();

		if ( $add_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => __( 'Any product', 'uncanny-automator' ),
			);
		}

		$products = Product::get();

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
			'label'                 => esc_attr__( 'Product', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => true,
			'options'               => $options,
		);

		return $dropdown;

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
