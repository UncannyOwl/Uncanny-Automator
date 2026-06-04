<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Ut_Helpers
 *
 * @package Uncanny_Automator
 */
class Ut_Helpers extends Abstract_Helpers {

	/**
	 * Ut_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Build token data from CSV import values.
	 *
	 * Combines CSV data with user ID, courses, groups, and group leader data
	 * into a single token array.
	 *
	 * @param array $values  The CSV row values.
	 * @param array $headers The CSV column headers.
	 * @param array $keys    The column key mappings.
	 * @param int   $user_id The imported user ID.
	 *
	 * @return array|false The combined token data or false on failure.
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
	 * Extract course IDs and titles from CSV import data.
	 *
	 * @param array $values The CSV row values.
	 * @param array $keys   The column key mappings.
	 *
	 * @return array{course_ids: array, course_titles: array}
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

		if ( ! isset( $values[ $k ] ) || empty( $values[ $k ] ) ) {
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
	 * Extract group IDs and titles from CSV import data.
	 *
	 * @param array $values The CSV row values.
	 * @param array $keys   The column key mappings.
	 *
	 * @return array{group_ids: array, group_titles: array}
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

		if ( ! isset( $values[ $k ] ) || empty( $values[ $k ] ) ) {
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
	 * Extract group leader group IDs and titles from CSV import data.
	 *
	 * @param array $values The CSV row values.
	 * @param array $keys   The column key mappings.
	 *
	 * @return array{group_ids: array, group_titles: array}
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

		if ( ! isset( $values[ $k ] ) || empty( $values[ $k ] ) ) {
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
	 * Check if Group Sign Up module is activated in Toolkit Pro.
	 *
	 * @return bool
	 */
	public static function is_group_sign_up_activated() {
		static $is_activated = null;

		if ( null !== $is_activated ) {
			return $is_activated;
		}

		if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
			$is_activated = false;
			return $is_activated;
		}

		$active_classes = get_option( 'uncanny_toolkit_active_classes', array() );

		if ( ! is_array( $active_classes ) ) {
			$is_activated = false;
			return $is_activated;
		}

		$is_activated = in_array( 'uncanny_pro_toolkit\LearnDashGroupSignUp', $active_classes, true );

		return $is_activated;
	}

	/**
	 * Get the Group Sign Up URL for a group.
	 *
	 * @param int $group_id The group post ID.
	 *
	 * @return string The signup URL or empty string.
	 */
	public static function get_group_sign_up_url( $group_id ) {

		if ( 'groups' !== get_post_type( $group_id ) ) {
			return '';
		}

		$slug          = get_post_field( 'post_name', $group_id );
		$encrypted_key = crypt( $group_id, 'uncanny-group' );
		$url           = site_url( 'sign-up/' . $slug . '/' );
		$url           = add_query_arg( 'gid', $group_id, $url );
		$url          .= $encrypted_key;

		return $url;
	}

	// =========================================================================
	// Remote-data handlers — entity dropdowns served via REST.
	//
	// Toolkit's triggers/actions operate on LearnDash courses and groups but
	// route through this helper so the wire shape stays self-contained
	// (`/wp-json/uap/v2/remote-data/uncannytoolkit/{segment}`). No cross-
	// integration coupling — each handler queries the LD post type directly.
	// =========================================================================

	/**
	 * Remote-data handler: Load LearnDash courses (with "Any course").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-courses', true, esc_html_x( 'Any course', 'Uncanny Toolkit', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote-data handler: Load LearnDash courses (no "Any" option).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'sfwd-courses', false )
		);
	}

	/**
	 * Remote-data handler: Load LearnDash groups (with "Any group").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_groups( $request ): array {
		return $this->remote_data_success(
			$this->build_post_type_options( 'groups', true, esc_html_x( 'Any group', 'Uncanny Toolkit', 'uncanny-automator' ) )
		);
	}

	/**
	 * Build options for a LearnDash post type.
	 *
	 * @param string $post_type   The post type slug.
	 * @param bool   $include_any Whether to prepend an "Any" option.
	 * @param string $any_label   Label for the Any option.
	 *
	 * @return array
	 */
	private function build_post_type_options( $post_type, $include_any = true, $any_label = '' ) {

		return automator_wp_query(
			array(
				'post_type'   => $post_type,
				'include_any' => $include_any,
				'any_label'   => $any_label,
			)
		);
	}
}
