<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wp_Fusion_Pro_Helpers;

/**
 * Class Wp_Fusion_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Fusion_Helpers {

	/**
	 * The options.
	 *
	 * @var Wp_Fusion_Helpers
	 */
	public $options;

	/**
	 * Pro helper.
	 *
	 * @var Wp_Fusion_Pro_Helpers
	 */
	public $pro;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options;

	/**
	 * Load any options.
	 *
	 * @var bool
	 */
	public $load_any_options = true;

	/**
	 * Learndash_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = true;

	}

	/**
	 * Fusion tags.
	 *
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
	 * Set options.
	 *
	 * @param Wp_Fusion_Helpers $options
	 */
	public function setOptions( Wp_Fusion_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro.
	 *
	 * @param Wp_Fusion_Pro_Helpers $pro
	 */
	public function setPro( Wp_Fusion_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

}
