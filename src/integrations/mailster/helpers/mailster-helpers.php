<?php

namespace Uncanny_Automator\Integrations\Mailster;

class Mailster_Helpers {

	/**
	 * Get all mailster lists.
	 *
	 * @param mixed $any_option The any list option.
	 * @param mixed $all_option The all lists option.
	 */
	public function get_all_mailster_lists( $any_option = false, $all_option = false ) {
		$options = array();

		if ( true === $any_option ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any list', 'Mailster', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $all_option ) {
			$options[] = array(
				'text'  => esc_html_x( 'All lists', 'Mailster', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$lists = mailster( 'lists' )->get();
		foreach ( $lists as $k => $list ) {
			$options[] = array(
				'text'  => $list->name,
				'value' => $list->ID,
			);
		}

		return $options;
	}

	/**
	 * Get all mailster campaigns.
	 *
	 * @param mixed $any_option The any option.
	 * @param bool $all_option
	 *
	 * @return array
	 */
	public function get_all_mailster_campaigns( $any_option = false, $all_option = false ) {
		$options = array();

		if ( true === $any_option ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any campaign', 'Mailster', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		if ( true === $all_option ) {
			$options[] = array(
				'text'  => esc_html_x( 'All campaigns', 'Mailster', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$campaigns = mailster( 'campaigns' )->get_campaigns(
			array(
				'orderby'     => 'post_title',
				'order'       => 'ASC',
				'post_status' => array( 'active', 'finished' ),
			)
		);
		foreach ( $campaigns as $k => $campaign ) {
			$options[] = array(
				'text'  => $campaign->post_title,
				'value' => $campaign->ID,
			);
		}

		return $options;
	}
}
