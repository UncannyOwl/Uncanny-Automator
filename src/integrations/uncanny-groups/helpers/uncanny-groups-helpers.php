<?php

namespace Uncanny_Automator;

/**
 * Class Uncanny_Groups_Helpers
 *
 * @package Uncanny_Automator
 */
class Uncanny_Groups_Helpers {

	/**
	 * @var Uncanny_Groups_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Uncanny_Groups_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Uncanny_Groups_Helpers $options
	 */
	public function setOptions( Uncanny_Groups_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param bool $any_option
	 *
	 * @return mixed
	 */
	public function all_ld_groups( $label = null, $option_code = 'UOGROUP', $any_option = true ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Group', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'groups',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any group', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'relevant_tokens'          => array(
				$option_code                   => esc_attr__( 'Group title', 'uncanny-automator' ),
				$option_code . '_ID'           => esc_attr__( 'Group ID', 'uncanny-automator' ),
				$option_code . '_URL'          => esc_attr__( 'Group URL', 'uncanny-automator' ),
				$option_code . '_KEY'          => esc_attr__( 'Key redeemed', 'uncanny-automator' ),
				$option_code . '_KEY_BATCH_ID' => esc_attr__( 'Key batch ID', 'uncanny-automator' ),
			),
			'custom_value_description' => _x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_ld_groups', $option );
	}
}
