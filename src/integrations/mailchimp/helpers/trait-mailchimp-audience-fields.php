<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Audience_Fields
 *
 * Provides audience/list field configuration and parsing methods.
 *
 * @package Uncanny_Automator
 */
trait Mailchimp_Audience_Fields {

	/**
	 * Get audience select field configuration.
	 *
	 * @return array The field configuration.
	 */
	public function get_audience_select_config() {
		return array(
			'option_code' => 'MCLIST',
			'label'       => esc_html_x( 'Audience', 'Mailchimp', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->helpers->remote_data_load_config( 'audiences' ),
		);
	}

	/**
	 * Get audience/list ID from parsed action data.
	 *
	 * @return string The sanitized list ID.
	 * @throws \Exception If audience is empty.
	 */
	public function get_audience_from_parsed() {
		$list_id = sanitize_text_field( $this->get_parsed_meta_value( 'MCLIST' ) );

		if ( empty( $list_id ) ) {
			throw new \Exception(
				esc_html_x( 'Audience is required.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $list_id;
	}
}
