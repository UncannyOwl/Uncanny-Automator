<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Buddypress_Pro_Helpers;

/**
 * Class Buddypress_Helpers
 *
 * @package Uncanny_Automator
 */
class Buddypress_Helpers {
	/**
	 * @var Buddypress_Helpers
	 */
	public $options;

	/**
	 * @var Buddypress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Buddypress_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_select_topic_from_forum_BDBTOPICREPLY', array( $this, 'select_topic_fields_func' ) );
	}

	/**
	 * @param Buddypress_Pro_Helpers $pro
	 */
	public function setPro( Buddypress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Buddypress_Helpers $options
	 */
	public function setOptions( Buddypress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddypress_groups( $label = null, $option_code = 'BPGROUPS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any group', 'uncanny-automator' ),
				'status'         => array( 'public' ),
			)
		);

		if ( ! $label ) {
			$label = esc_attr__( 'Group', 'uncanny-automator' );
		}

		global $wpdb;
		$qry     = "SHOW TABLES LIKE '{$wpdb->prefix}bp_groups';";
		$options = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[- 1] = $args['uo_any_label'];
			}

			if ( $wpdb->query( $qry ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				// Previous solution was not preparing correct query.
				$in_str_arr = array_fill( 0, count( $args['status'] ), '%s' );
				$in_str     = join( ',', $in_str_arr );

				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}bp_groups WHERE status IN ($in_str)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$args['status']
					)
				);

				if ( $results ) {
					foreach ( $results as $result ) {
						$options[ $result->id ] = $result->name;
					}
				}
			}
		}//end if

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => _x( 'Group ID', 'BuddyPress', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_buddypress_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddypress_users( $label = null, $option_code = 'BPUSERS', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'User', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any user', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			if ( $args['uo_include_any'] ) {
				$options[- 1] = $args['uo_any_label'];
			}

			$users = Automator()->helpers->recipe->wp_users();

			foreach ( $users as $user ) {
				$options[ $user->ID ] = $user->display_name;
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'User ID', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_all_buddypress_users', $option );
	}

	/**
	 * Return all the specific topics of a forum in ajax call
	 */
	public function select_topic_fields_func() {

		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( isset( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$fields[] = array(
				'value' => - 1,
				'text'  => __( 'Any topic', 'uncanny-automator' ),
			);

			$value = automator_filter_input( 'value', INPUT_POST );

			$forum_id = (int) $value;

			if ( $forum_id > 0 ) {
				$args = array(
					'post_type'      => bbp_get_topic_post_type(),
					'post_parent'    => $forum_id,
					'post_status'    => array_keys( get_post_stati() ),
					'posts_per_page' => 9999,
				);

				$topics = Automator()->helpers->recipe->wp_query( $args );

				if ( ! empty( $topics ) ) {
					foreach ( $topics as $input_id => $input_title ) {
						$fields[] = array(
							'value' => $input_id,
							'text'  => $input_title,
						);
					}
				}
			}
		}//end if
		echo wp_json_encode( $fields );
		die();
	}
}
