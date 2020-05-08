<?php


namespace Uncanny_Automator;


class Edd_Helpers {
	/**
	 * @var Edd_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Edd_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Edd_Helpers $options
	 */
	public function setOptions( Edd_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Edd_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Edd_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true ) {

		if ( ! $label ) {
			$label = __( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'download',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, $any_option, __( 'Any download', 'uncanny-automator' ) );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			// to setup example, lets define the value the child will be based on
			'current_value'   => false,
			'validation_type' => 'text',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Download title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Download ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Download URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_edd_downloads', $option );
	}

}