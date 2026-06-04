<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Tag_Fields
 *
 * Provides tag field configuration and parsing methods.
 *
 * @package Uncanny_Automator
 */
trait Mailchimp_Tag_Fields {

	/**
	 * Get tag select field configuration.
	 *
	 * This field listens to MCLIST changes to populate tag options via AJAX.
	 *
	 * @param string $option_code  The option code for the field (legacy: MCLISTTAGS).
	 * @param bool   $allow_custom Whether to allow custom tag values.
	 *
	 * @return array The field configuration.
	 */
	public function get_tags_select_config( $option_code, $allow_custom = false ) {
		$config = array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Tag', 'Mailchimp', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'remote_data'     => $this->helpers->remote_data_parent_config( 'tags', array( 'MCLIST' ) ),
		);

		if ( $allow_custom ) {
			$config['tokens']                   = true;
			$config['custom_value_description'] = esc_html_x(
				'Enter a tag name. If a matching tag does not exist a new one will be created.',
				'Mailchimp',
				'uncanny-automator'
			);
		}

		return $config;
	}

	/**
	 * Get tag from parsed action data.
	 *
	 * @param string $meta_key The meta key to retrieve the tag from.
	 *
	 * @return string The sanitized tag value.
	 * @throws \Exception If tag is empty.
	 */
	public function get_tag_from_parsed( $meta_key ) {
		$tag = sanitize_text_field( trim( $this->get_parsed_meta_value( $meta_key ) ) );

		if ( empty( $tag ) ) {
			throw new \Exception(
				esc_html_x( 'Tag is required.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $tag;
	}
}
