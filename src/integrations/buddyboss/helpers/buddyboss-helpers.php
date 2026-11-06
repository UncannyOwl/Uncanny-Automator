<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Buddyboss_Pro_Helpers;

/**
 * Class Buddyboss_Helpers
 *
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
	public $load_options = true;

	/**
	 * Buddyboss_Helpers constructor.
	 */
	public function __construct() {

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

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any group', 'uncanny-automator' ),
				'status'         => array( 'public', 'private', 'hidden' ),
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

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checks that BuddyBoss's groups table exists; a schema read that must not be cached.
			if ( $wpdb->query( $qry ) ) {

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- BuddyBoss's own table; the plugin offers no API for this query.
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}bp_groups WHERE status IN (" . implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) ) . ')',
						$args['status']
					)
				);

				if ( $results ) {
					foreach ( $results as $result ) {
						$options[ $result->id ] = $result->name;
					}
				}
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => _x( 'Group ID', 'BuddyBoss', 'uncanny-automator' ),
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
		return apply_filters( 'uap_option_all_buddyboss_groups', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_buddyboss_users( $label = null, $option_code = 'BDBUSERS', $args = array() ) {

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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
		return apply_filters( 'uap_option_all_buddyboss_users', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_buddyboss_forums( $label = null, $option_code = 'BDBFORUMS', $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any forum', 'uncanny-automator' ),
			)
		);
		if ( ! $label ) {
			$label = esc_attr__( 'Forum', 'uncanny-automator' );
		}

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options[- 1] = $args['uo_any_label'];
		}

		// With BuddyBoss's Forums component off there are no forums to list, and the field stays a select the recipe builder can load.
		if ( function_exists( 'bbp_get_forum_post_type' ) ) {
			$forum_args = array(
				'post_type'      => bbp_get_forum_post_type(),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'private' ),
			);

			$forums = Automator()->helpers->recipe->options->wp_query( $forum_args );
			if ( ! empty( $forums ) ) {
				foreach ( $forums as $key => $forum ) {
					$options[ $key ] = $forum;
				}
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code          => esc_attr__( 'Forum title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Forum ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Forum URL', 'uncanny-automator' ),
			),
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
		return apply_filters( 'uap_option_list_buddyboss_forums', $option );
	}
}
