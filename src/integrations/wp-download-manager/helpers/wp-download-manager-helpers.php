<?php

namespace Uncanny_Automator;

/**
 * Class Wp_Download_Manager_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Download_Manager_Helpers {
	/**
	 * @param $option_code
	 * @param $add_any
	 *
	 * @return array|mixed|void
	 */
	public function get_all_wpmd_files( $option_code, $is_any = false, $is_all = false ) {

		$options = array();

		if ( true === $is_any ) {
			$options['-1'] = __( 'Any file', 'uncanny-automator' );
		}

		if ( true === $is_all ) {
			$options['-1'] = __( 'All files', 'uncanny-automator' );
		}
		$args  = array(
			'post_type'      => 'wpdmpro',
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);
		$files = get_posts( $args );
		if ( ! is_wp_error( $files ) ) {
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					$options[ $file->ID ] = esc_html( $file->post_title );
				}
			}
		}

		$option = array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			/* translators: HTTP request method */
			'label'                 => esc_attr__( 'File', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'relevant_tokens'       => array(),
			'options'               => $options,
		);

		return apply_filters( 'uap_option_get_all_wpmd_files', $option );
	}
}
