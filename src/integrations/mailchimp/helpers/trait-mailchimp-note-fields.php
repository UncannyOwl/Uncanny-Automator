<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Note_Fields
 *
 * Provides note field configuration and parsing methods.
 *
 * @package Uncanny_Automator
 */
trait Mailchimp_Note_Fields {

	/**
	 * Get note textarea field configuration.
	 *
	 * @param string $option_code The option code for the field.
	 *
	 * @return array The field configuration.
	 */
	public function get_note_textarea_config( $option_code = 'MCNOTE' ) {
		return array(
			'option_code'      => $option_code,
			'label'            => esc_html_x( 'Note', 'Mailchimp', 'uncanny-automator' ),
			'input_type'       => 'textarea',
			'required'         => true,
			'tokens'           => true,
			'description'      => esc_html_x( 'Note length is limited to 1,000 characters.', 'Mailchimp', 'uncanny-automator' ),
			'supports_tinymce' => false,
		);
	}

	/**
	 * Get note from parsed action data.
	 *
	 * Strips HTML tags and truncates to maximum allowed length.
	 *
	 * @param string $meta_key The meta key to retrieve the note from.
	 *
	 * @return string The sanitized and truncated note.
	 * @throws \Exception If note is empty.
	 */
	public function get_note_from_parsed( $meta_key = 'MCNOTE' ) {
		$note = substr(
			wp_strip_all_tags( $this->get_parsed_meta_value( $meta_key ) ),
			0,
			1000
		);

		if ( empty( $note ) ) {
			throw new \Exception(
				esc_html_x( 'Note is required.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $note;
	}
}
