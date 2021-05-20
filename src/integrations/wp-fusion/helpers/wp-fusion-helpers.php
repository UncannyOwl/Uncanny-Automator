<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Wp_Fusion_Pro_Helpers;

/**
 * Class Wp_Fusion_Helpers
 * @package Uncanny_Automator
 */
class Wp_Fusion_Helpers {
	/**
	 * @var Wp_Fusion_Helpers
	 */
	public $options;

	/**
	 * @var Wp_Fusion_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @var bool
	 */
	public $load_any_options = true;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param string $label
	 * @param string $trigger_meta
	 *
	 * @return mixed
	 */
	public static function fusion_tags( $label = '', $trigger_meta = '' ) {

		if ( empty( $label ) ) {
			$label = __( 'Tag', 'uncanny-automator' );
		}

		$tags    = wp_fusion()->settings->get( 'available_tags' );
		$options = array();
		if ( $tags ) {
			foreach ( $tags as $t_id => $tag ) {
				if ( is_array( $tag ) && isset( $tag['label'] ) ) {
					$options[ $t_id ] = $tag['label'];
				} else {
					$options[ $t_id ] = $tag;
				}
			}
		}

		$option = array(
			'option_code' => $trigger_meta,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
		);

		return apply_filters( 'uap_option_wp_fusion_tags', $option );
	}

	/**
	 * @param Wp_Fusion_Helpers $options
	 */
	public function setOptions( Wp_Fusion_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Wp_Fusion_Pro_Helpers $pro
	 */
	public function setPro( Wp_Fusion_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

}
