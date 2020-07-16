<?php


namespace Uncanny_Automator;

/**
 * Class Bbpress_Helpers
 * @package Uncanny_Automator
 */
class Bbpress_Helpers {
	/**
	 * @var Bbpress_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Bbpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @param Bbpress_Helpers $options
	 */
	public function setOptions( Bbpress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Bbpress_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Bbpress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Bbpress_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_bbpress_forums( $label = null, $option_code = 'BBFORUMS' ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = __( 'Forum', 'uncanny-automator' );
		}

		$args = [
			'post_type'      => bbp_get_forum_post_type(),
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => [ 'publish', 'private' ],
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Forum title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Forum ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Forum URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_bbpress_forums', $option );
	}
}