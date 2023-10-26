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

		if ( ! $label ) {
			$label = esc_attr__( 'Recipe', 'uncanny-automator' );
		}

		// post query arguments.
		global $wpdb;
		$results       = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_status FROM $wpdb->posts WHERE post_status IN ('publish', 'draft') AND post_type = %s ORDER BY post_title ASC", 'uo-recipe' ) );
		$options       = array();
		$options['-1'] = esc_attr__( 'Any recipe', 'uncanny-automator' );
		if ( $results ) {
			foreach ( $results as $result ) {
				$options[ $result->ID ] = sprintf( '%s (%s)', $result->post_title, $result->post_status );
			}
		}
		//$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any recipe', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(),
			//'custom_value_description' => esc_attr__( 'Recipe slug', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_get_recipes', $option );
	}
}
