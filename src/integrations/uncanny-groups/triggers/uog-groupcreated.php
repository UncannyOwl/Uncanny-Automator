<?php

namespace Uncanny_Automator\Integrations\Uncanny_Groups;

use Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers as Uncanny_Toolkit_Helpers;

/**
 * Class UOG_GROUPCREATED
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Groups\Uog_Helpers $item_helpers
 */
class UOG_GROUPCREATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'GROUPCREATED', 'UOG' )
			->trigger_meta( 'UNCANNYGROUPS' )
			->hook( 'uo_new_group_created', 20, 2 )
			->hook( 'uo_new_group_purchased', 20, 2 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_sentence( esc_html_x( 'A group is created', 'Uncanny Groups', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A group is created', 'Uncanny Groups', 'uncanny-automator' ) );
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens = array(
			array(
				'tokenId'   => 'UNCANNYGROUP',
				'tokenName' => esc_html_x( 'Group title', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUP_ID',
				'tokenName' => esc_html_x( 'Group ID', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'UNCANNYGROUP_URL',
				'tokenName' => esc_html_x( 'Group URL', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUP_SEATS',
				'tokenName' => esc_html_x( 'Group seats', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'UNCANNYGROUP_COURSES',
				'tokenName' => esc_html_x( 'Group courses', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'UNCANNYGROUP_LEADER',
				'tokenName' => esc_html_x( 'Group leader email', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);

		if ( class_exists( '\Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers' ) && Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens[] = array(
				'tokenId'   => 'UNCANNYGROUP_SIGNUP_URL',
				'tokenName' => esc_html_x( 'Group signup URL', 'Uncanny Groups', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $group_id, $leader_id ) = $hook_args;

		if ( empty( $leader_id ) ) {
			$user_id = get_current_user_id();
		} else {
			$user_id = $leader_id;
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $group_id, $leader_id ) = $hook_args;

		if ( empty( $leader_id ) ) {
			$leader_id = get_current_user_id();
		}

		$leader       = get_userdata( $leader_id );
		$leader_email = false !== $leader ? $leader->user_email : '';

		$courses_ids = learndash_group_enrolled_courses( $group_id );
		$courses     = array();

		if ( ! empty( $courses_ids ) ) {
			foreach ( $courses_ids as $course_id ) {
				$courses[] = get_the_title( $course_id );
			}
		}

		$tokens = array(
			'UNCANNYGROUP'         => get_the_title( $group_id ),
			'UNCANNYGROUP_ID'      => $group_id,
			'UNCANNYGROUP_URL'     => get_permalink( $group_id ),
			'UNCANNYGROUP_SEATS'   => ulgm()->group_management->seat->total_seats( $group_id ),
			'UNCANNYGROUP_COURSES' => implode( ', ', $courses ),
			'UNCANNYGROUP_LEADER'  => $leader_email,
		);

		if ( class_exists( '\Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers' ) && Uncanny_Toolkit_Helpers::is_group_sign_up_activated() ) {
			$tokens['UNCANNYGROUP_SIGNUP_URL'] = Uncanny_Toolkit_Helpers::get_group_sign_up_url( $group_id );
		}

		return $tokens;
	}
}
