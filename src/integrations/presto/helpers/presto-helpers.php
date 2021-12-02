<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Presto_Pro_Helpers;

/**
 * Class Presto_Helpers
 *
 * @package Uncanny_Automator
 */
class Presto_Helpers {
	/**
	 * @var Presto_Helpers
	 */
	public $options;

	/**
	 * @var Presto_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Presto_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Presto_Helpers $options
	 */
	public function setOptions( Presto_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Presto_Pro_Helpers $pro
	 */
	public function setPro( Presto_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function list_presto_videos( $label = null, $option_code = 'PRESTOVIDEO', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Video', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';

		$options = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			$videos = ( new \PrestoPlayer\Models\Video() )->all();
			if ( $videos ) {
				foreach ( $videos as $video ) {
					$options[ $video->__get( 'id' ) ] = $video->__get( 'title' );
				}
			}
		}

		natcasesort( $options );

		$options = array( '-1' => __( 'Any video', 'uncanny-automator' ) ) + $options;

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr__( 'Video title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Video ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_presto_videos', $option );
	}
}
