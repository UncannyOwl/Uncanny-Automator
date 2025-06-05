<?php

namespace Uncanny_Automator\Integrations\Mailster;

class Mailster_Helpers {

	/**
	 * Get all mailster lists.
	 *
	 * @param mixed $any_option The any option.
	 */
	public function get_all_mailster_lists( $any_option = false ) {
		$options = array();

		if ( true === $any_option ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any list', 'Mailster', 'uncanny-automator' ),
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
}
