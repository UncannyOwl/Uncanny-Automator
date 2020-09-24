<?php

namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Buddyboss_Pro_Helpers;

/**
 * Class Buddyboss_Helpers
 * @package Uncanny_Automator
 */
class Buddyboss_Helpers {
	/**
	 * @var Buddyboss_Helpers
	 */
	public $options;

	/**
	 * @var Buddyboss_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Buddyboss_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Buddyboss_Pro_Helpers $pro
	 */
	public function setPro( Buddyboss_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Buddyboss_Helpers $options
	 */
	public function setOptions( Buddyboss_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddyboss_groups( $label = null, $option_code = 'BDBGROUPS', $args = array() ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		$args = wp_parse_args( $args, array(
			'uo_include_any' => false,
			'uo_any_label'   =>  esc_attr__( 'Any group', 'uncanny-automator' ),
			'status'         => array( 'public' ),
		) );

		if ( ! $label ) {
			$label =  esc_attr__( 'Group', 'uncanny-automator' );
		}

		global $wpdb;
		$qry     = "SHOW TABLES LIKE '{$wpdb->prefix}bp_groups';";
		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			if ( $wpdb->query( $qry ) ) {
				// previous solution was not preparing correct query
				$in_str_arr = array_fill( 0, count( $args['status'] ), '%s' );
				$in_str     = join( ',', $in_str_arr );
				$group_qry  = $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bp_groups WHERE status IN ($in_str)",
					$args['status']
				);

				$results = $wpdb->get_results( $group_qry );

				if ( $results ) {
					foreach ( $results as $result ) {
						$options[ $result->id ] = $result->name;
					}
				}
			}
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
			'custom_value_description' => _x( 'Group ID', 'BuddyBoss', 'uncanny-automator' )
		];


		return apply_filters( 'uap_option_all_buddyboss_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddyboss_users( $label = null, $option_code = 'BDBUSERS', $args = array() ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label =  esc_attr__( 'User', 'uncanny-automator' );
		}

		$args = wp_parse_args( $args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   =>  esc_attr__( 'Any user', 'uncanny-automator' ),
			)
		);

		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[ - 1 ] = $args['uo_any_label'];
			}

			$users = $uncanny_automator->helpers->recipe->wp_users();

			foreach ( $users as $user ) {
				$options[ $user->ID ] = $user->display_name;
			}
		}

		$option = [
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $options,
			'custom_value_description' => esc_attr__( 'User ID', 'uncanny-automator' )
		];


		return apply_filters( 'uap_option_all_buddyboss_users', $option );
	}

}