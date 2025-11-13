<?php

namespace Uncanny_Automator\Integrations\Presto;

use Uncanny_Automator_Pro\Presto_Pro_Helpers;

// Backwards compatibility aliases for Pro integration
class_alias( 'Uncanny_Automator\Integrations\Presto\Presto_Helpers', 'Uncanny_Automator\Presto_Helpers' );

/**
 * Class Presto_Helpers
 *
 * @package Uncanny_Automator
 */
class Presto_Helpers {

	/**
	 * @var Presto_Helpers
	 */
	public $options;

	/**
	 * @var Presto_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Presto_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get all Presto videos in new framework format
	 *
	 * @param bool $include_any Whether to include "Any video" option
	 * @return array Array of video options in value/text format
	 */
	public function get_all_presto_videos( $include_any = true ) {
		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any video', 'Presto', 'uncanny-automator' ),
			);
		}

		if ( Automator()->helpers->recipe->load_helpers ) {
			// Add videos.
			$videos = ( new \PrestoPlayer\Models\Video() )->all();
			if ( $videos ) {
				foreach ( $videos as $video ) {
					$options[] = array(
						'value' => $video->__get( 'id' ),
						'text'  => $video->__get( 'title' ),
					);
				}
			}

			// Check for hub videos.
			$hubs = ( new \PrestoPlayer\Models\ReusableVideo() )->all();
			if ( $hubs ) {
				foreach ( $hubs as $hub ) {
					// Get the actual video from hub embed.
					$video = $this->normalize_video_data( $hub->ID );
					if ( ! $video ) {
						continue;
					}
					// Check if video already exists to avoid duplicates
					$exists = false;
					foreach ( $options as $option ) {
						if ( $option['value'] === $video->id ) {
							$exists = true;
							break;
						}
					}
					if ( ! $exists ) {
						$options[] = array(
							'value' => $video->id,
							'text'  => $video->title,
						);
					}
				}
			}
		}

		// Sort by text (title) alphabetically
		usort(
			$options,
			function ( $a, $b ) {
				return strcasecmp( $a['text'], $b['text'] );
			}
		);

		return $options;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function list_presto_videos( $label = null, $option_code = 'PRESTOVIDEO', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_html_x( 'Video', 'Presto', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';

		$options = $this->get_all_presto_videos( true );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                 => esc_html_x( 'Video title', 'Presto', 'uncanny-automator' ),
				$option_code . '_ID'         => esc_html_x( 'Video ID', 'Presto', 'uncanny-automator' ),
				$option_code . '_POST_TITLE' => esc_html_x( 'Media hub title', 'Presto', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_presto_videos', $option );
	}

	/**
	 * Normalize video ID data from hub post IDs.
	 *
	 * @param $video_id
	 *
	 * @return mixed bool|object
	 */
	public function normalize_video_data( $video_id ) {

		$video = new \PrestoPlayer\Models\Video( $video_id );

		// Check if video is a hub video.
		if ( empty( $video->__get( 'post_id' ) ) ) {
			$hub          = new \PrestoPlayer\Models\ReusableVideo( $video_id );
			$attrs        = $hub->getAttributes();
			$hub_video_id = isset( $attrs['id'] ) ? $attrs['id'] : false;
			if ( $hub_video_id ) {
				$video = new \PrestoPlayer\Models\Video( $hub_video_id );
			}
		}

		if ( empty( $video->__get( 'post_id' ) ) ) {
			return false;
		}

		// Return normalized video data.
		return (object) array(
			'id'     => $video->__get( 'id' ),
			'title'  => $video->__get( 'title' ),
			'hub_id' => $video->__get( 'post_id' ),
		);
	}

	/**
	 * Get video ID from normalized video data.
	 *
	 * @param $video_id
	 *
	 * @return bool
	 */
	public function get_normalized_video_id( $video_id ) {
		$video = $this->normalize_video_data( $video_id );
		return $video ? $video->id : false;
	}
}
