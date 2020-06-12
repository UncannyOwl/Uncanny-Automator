<?php


namespace Uncanny_Automator;


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
	 * @var \Uncanny_Automator_Pro\Paid_Memberships_Pro_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param \Uncanny_Automator_Pro\Paid_Memberships_Pro_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Paid_Memberships_Pro_Pro_Helpers $pro ): void {
		$this->pro = $pro;
	}

	/**
	 * @param Paid_Memberships_Pro_Helpers $options
	 */
	public function setOptions( Paid_Memberships_Pro_Helpers $options ): void {
		$this->options = $options;
	}

	/**
	 * @param null $label
	 * @param string $option_code
	 *
	 * @return mixed|void
	 */
	public function all_memberships( $label = null, $option_code = 'PMPMEMBERSHIP' ) {
		if ( ! $label ) {
			$label = __( 'Membership', 'uncanny-automator' );
		}

		global $wpdb;
		$qry     = "SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id ASC";
		$levels  = $wpdb->get_results( $qry );
		$options = [];
		if ( $levels ) {
			$options['-1'] = __( 'Any membership', 'uncanny-automator' );
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
				$option_code         => __( 'Membership title', 'uncanny-automator' ),
				$option_code . '_ID' => __( 'Membership ID', 'uncanny-automator' ),
				//$option_code . '_URL' => __( 'Product URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_pmp_memberships', $option );
	}
}