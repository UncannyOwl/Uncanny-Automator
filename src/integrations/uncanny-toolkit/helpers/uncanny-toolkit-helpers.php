<?php

namespace Uncanny_Automator;

/**
 * Uncanny Toolkit Integration Helpers file
 */
class Uncanny_Toolkit_Helpers {

	/**
	 * @var
	 */
	public $options;
	/**
	 * @var
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Checks if the Toolkit Pro Group Sign Up module is activated.
	 *
	 * @var mixed null || bool once checked
	 */
	public static $is_groups_sign_up_activated = null;

	/**
	 *
	 */
	public function __construct() {

		$this->load_options = true;
	}

	/**
	 *
	 * Building default tokens for the triggers
	 *
	 * @param array $values
	 * @param array $headers
	 * @param array $keys
	 * @param int $user_id
	 *
	 * @return array|false
	 */
	public static function build_token_data( $values, $headers, $keys, $user_id ) {

		$courses      = self::get_courses_from_data( $values, $keys );
		$groups       = self::get_groups_from_data( $values, $keys );
		$group_leader = self::get_group_leader_from_data( $values, $keys );
		$combine      = array_combine( $headers, $values );

		$combine['learndash_course_titles']       = $courses['course_titles'];
		$combine['learndash_course_ids']          = $courses['course_ids'];
		$combine['learndash_group_titles']        = $groups['group_titles'];
		$combine['learndash_group_ids']           = $groups['group_ids'];
		$combine['learndash_group_leader_titles'] = $group_leader['group_titles'];
		$combine['learndash_group_leader_ids']    = $group_leader['group_ids'];
		$combine['user_id']                       = $user_id;

		return $combine;
	}

	/**
	 * Course Meta
	 *
	 * @param $values
	 * @param $keys
	 *
	 * @return array[]
	 */
	public static function get_courses_from_data( $values, $keys ) {
		$ids    = array();
		$titles = array();
		$return = array(
			'course_ids'    => $ids,
			'course_titles' => $titles,
		);

		if ( ! isset( $keys['learndash_courses'] ) ) {
			return $return;
		}

		$k = $keys['learndash_courses'];
		if ( ! isset( $values[ $k ] ) ) {
			return $return;
		}
		if ( empty( $values[ $k ] ) ) {
			return $return;
		}
		$learndash_courses = explode( ';', $values[ $k ] );
		if ( empty( $learndash_courses ) || is_numeric( $values[ $k ] ) ) {
			$ids[]    = $values[ $k ];
			$titles[] = get_the_title( $values[ $k ] );
		} else {
			foreach ( $learndash_courses as $g ) {
				$ids[]    = $g;
				$titles[] = get_the_title( $g );
			}
		}

		return array(
			'course_ids'    => $ids,
			'course_titles' => $titles,
		);
	}

	/**
	 * Group meta
	 *
	 * @param $values
	 * @param $keys
	 *
	 * @return array[]
	 */
	public static function get_groups_from_data( $values, $keys ) {
		$ids    = array();
		$titles = array();
		$return = array(
			'group_ids'    => $ids,
			'group_titles' => $titles,
		);

		if ( ! isset( $keys['learndash_groups'] ) ) {
			return $return;
		}

		$k = $keys['learndash_groups'];
		if ( ! isset( $values[ $k ] ) ) {
			return $return;
		}
		if ( empty( $values[ $k ] ) ) {
			return $return;
		}
		$learndash_groups = explode( ';', $values[ $k ] );
		if ( empty( $learndash_groups ) || is_numeric( $values[ $k ] ) ) {
			$ids[]    = $values[ $k ];
			$titles[] = get_the_title( $values[ $k ] );
		} else {
			foreach ( $learndash_groups as $g ) {
				$ids[]    = $g;
				$titles[] = get_the_title( $g );
			}
		}

		return array(
			'group_ids'    => $ids,
			'group_titles' => $titles,
		);
	}

	/**
	 * Group leader meta
	 *
	 * @param $values
	 * @param $keys
	 *
	 * @return array[]
	 */
	public static function get_group_leader_from_data( $values, $keys ) {
		$ids    = array();
		$titles = array();
		$return = array(
			'group_ids'    => $ids,
			'group_titles' => $titles,
		);

		if ( ! isset( $keys['group_leader'] ) ) {
			return $return;
		}

		$k = $keys['group_leader'];
		if ( ! isset( $values[ $k ] ) ) {
			return $return;
		}
		if ( empty( $values[ $k ] ) ) {
			return $return;
		}
		$learndash_groups = explode( ';', $values[ $k ] );
		if ( empty( $learndash_groups ) || is_numeric( $values[ $k ] ) ) {
			$ids[]    = $values[ $k ];
			$titles[] = get_the_title( $values[ $k ] );
		} else {
			foreach ( $learndash_groups as $g ) {
				$ids[]    = $g;
				$titles[] = get_the_title( $g );
			}
		}

		return array(
			'group_ids'    => $ids,
			'group_titles' => $titles,
		);
	}

	/**
	 * @param Uncanny_Toolkit_Helpers $options
	 */
	public function setOptions( Uncanny_Toolkit_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Uncanny_Toolkit_Pro_Helpers $pro
	 */
	public function setPro( Uncanny_Toolkit_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Check if Toolkit Pro Group Sign Up module is activated
	 */
	public static function is_group_sign_up_activated() {

		if ( is_null( self::$is_groups_sign_up_activated ) ) {
			if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
				self::$is_groups_sign_up_activated = false;
			} else {
				$active_modules                    = get_option( 'uncanny_toolkit_active_classes', true );
				self::$is_groups_sign_up_activated = ! empty( $active_modules['uncanny_pro_toolkit\LearnDashGroupSignUp'] );
			}
		}

		return self::$is_groups_sign_up_activated;
	}

	/**
	 * Return the generated Group Sign Up URL.
	 *
	 * @param int $group_id Group ID.
	 *
	 * @return string
	 */
	public static function get_group_sign_up_url( $group_id ) {
		// Validate Post Type.
		if ( 'groups' !== get_post_type( $group_id ) ) {
			return '';
		}

		// Generate the group key from the ID.
		$group_key = str_replace( array( ' ', '.', '[', '-' ), '_', crypt( $group_id, 'uncanny-group' ) );
		$slug      = get_post_field( 'post_name', $group_id, 'raw' );
		// Generate the signup URL.
		return add_query_arg(
			array(
				'gid' => $group_id,
			),
			site_url( 'sign-up/' . $slug . '/' )
		) . '&' . $group_key;
	}
}
