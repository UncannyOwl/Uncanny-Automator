<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Paid_Memberships_Pro_Pro_Helpers;

/**
 * Class Paid_Memberships_Pro_Helpers
 * @package Uncanny_Automator
 */
class Paid_Memberships_Pro_Helpers {

	/**
	 * @var Paid_Memberships_Pro_Helpers
	 */
	public $options;

	/**
	 * @var Paid_Memberships_Pro_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Paid_Memberships_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Paid_Memberships_Pro_Pro_Helpers $pro
	 */
	public function setPro( Paid_Memberships_Pro_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Paid_Memberships_Pro_Helpers $options
	 */
	public function setOptions( Paid_Memberships_Pro_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param null   $label
	 * @param string $option_code
	 *
	 * @return mixed|void
	 */
	public function all_memberships( $label = null, $option_code = 'PMPMEMBERSHIP' ) {
		if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Membership', 'uncanny-automator' );
		}

		global $wpdb;
		$qry     = "SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id ASC";
		$levels  = $wpdb->get_results( $qry );
		$options = array();
		if ( $levels ) {
			$options['-1'] = esc_attr__( 'Any membership', 'uncanny-automator' );
			foreach ( $levels as $level ) {
				$options[ $level->id ] = $level->name;
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code         => esc_attr__( 'Membership title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Membership ID', 'uncanny-automator' ),
				//$option_code . '_URL' =>  esc_attr__( 'Product URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_pmp_memberships', $option );
	}
}
