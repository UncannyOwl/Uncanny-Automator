<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Edd_Pro_Helpers;

/**
 * Class Edd_Helpers
 * @package Uncanny_Automator
 */
class Edd_Helpers {
	/**
	 * @var Edd_Helpers
	 */
	public $options;

	/**
	 * @var Edd_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Edd_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Edd_Helpers $options
	 */
	public function setOptions( Edd_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Edd_Pro_Helpers $pro
	 */
	public function setPro( Edd_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_edd_downloads( $label = null, $option_code = 'EDDPRODUCTS', $any_option = true ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => 'download',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];


		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any download', 'uncanny-automator' ) );

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
				$option_code          => esc_attr__( 'Download title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Download ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Download URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Download featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Download featured image URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_edd_downloads', $option );
	}

}
