<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Uoa_Pro_Helpers;
use WP_Error;

/**
 * Class Uoa_Helpers
 *
 * @package Uncanny_Automator
 */
class Uoa_Helpers {
	/**
	 * @var Uoa_Helpers
	 */
	public $options;
	/**
	 * @var Uoa_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Uoa_Helpers $options
	 */
	public function setOptions( Uoa_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Uoa_Pro_Helpers $pro
	 */
	public function setPro( Uoa_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function get_recipes( $label = null, $option_code = 'UOARECIPE', $any_option = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Recipe', 'uncanny-automator' );
		}

		// post query arguments.
		$args = array(
			'post_type'      => 'uo-recipe',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any recipe', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Recipe slug', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_get_recipes', $option );
	}
}
