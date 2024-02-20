<?php

namespace Uncanny_Automator;

/**
 * Class Groundhogg_Helpers
 *
 * @package Uncanny_Automator
 */
class Groundhogg_Helpers {

	/**
	 * Get all tags.
	 *
	 * @return array|mixed|void
	 */
	public static function get_tag_options() {

		$tags    = array();
		$options = array();

		try {
			$tags = \Groundhogg\get_db( 'tags' )->query( array() );
		} catch ( \Error $e ) {
			automator_log( $e->getMessage(), $tags, AUTOMATOR_DEBUG_MODE, 'groundhogg' );
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), $tags, AUTOMATOR_DEBUG_MODE, 'groundhogg' );
		}

		if ( empty( $tags ) ) {
			return $options;
		}

		foreach ( $tags as $tag ) {
			$options[] = array(
				'value' => $tag->tag_id,
				'text'  => $tag->tag_name,
			);
		}

		return $options;
	}

}
