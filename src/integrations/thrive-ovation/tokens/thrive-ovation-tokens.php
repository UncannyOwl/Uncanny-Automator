<?php

namespace Uncanny_Automator\Integrations\Thrive_Ovation;

/**
 * Class Thrive_Ovation_Tokens
 *
 * Token definitions and hydration for Thrive Ovation triggers.
 *
 * @package Uncanny_Automator\Integrations\Thrive_Ovation
 */
class Thrive_Ovation_Tokens {

	/**
	 * Helper container that owns this tokens collaborator.
	 *
	 * @var Thrive_Ovation_Helpers
	 */
	private $helpers;

	/**
	 * Constructor.
	 *
	 * @param Thrive_Ovation_Helpers $helpers The integration helper container.
	 */
	public function __construct( Thrive_Ovation_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Token definitions for the testimonial trigger.
	 *
	 * @return array[]
	 */
	public function testimonial_tokens() {

		return array(
			array(
				'tokenId'   => 'TESTIMONIAL_ID',
				'tokenName' => esc_html_x( 'Testimonial ID', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_TITLE',
				'tokenName' => esc_html_x( 'Testimonial title', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_CONTENT',
				'tokenName' => esc_html_x( 'Testimonial content', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_DATE',
				'tokenName' => esc_html_x( 'Date', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_AUTHOR',
				'tokenName' => esc_html_x( 'Full name', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_AUTHOR_EMAIL',
				'tokenName' => esc_html_x( 'Email', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_AUTHOR_ROLE',
				'tokenName' => esc_html_x( 'Role/Occupation', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_AUTHOR_WEBSITE',
				'tokenName' => esc_html_x( 'Website URL', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'TESTIMONIAL_STATUS',
				'tokenName' => esc_html_x( 'Testimonial status', 'Thrive Ovation', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate testimonial token values from the submitted payload.
	 *
	 * @param array $testimonial_data The testimonial payload from the hook.
	 *
	 * @return array Key-value pairs of tokenId => value (full keyset).
	 */
	public function hydrate_testimonial_tokens( $testimonial_data ) {

		$testimonial_data = is_array( $testimonial_data ) ? $testimonial_data : array();

		$testimonial_id = isset( $testimonial_data['testimonial_id'] ) ? (int) $testimonial_data['testimonial_id'] : 0;

		$testimonial_date = '';
		if ( 0 !== $testimonial_id ) {
			$testimonial_date = get_the_date( '', $testimonial_id );
			if ( false === $testimonial_date ) {
				$testimonial_date = '';
			}
		}

		$testimonial_status = '';
		if ( 0 !== $testimonial_id && defined( 'TVO_STATUS_META_KEY' ) && function_exists( 'tvo_get_testimonial_status_text' ) ) {
			$testimonial_status = tvo_get_testimonial_status_text(
				get_post_meta( $testimonial_id, TVO_STATUS_META_KEY, true )
			);
		}

		return array(
			'TESTIMONIAL_ID'             => $testimonial_id,
			'TESTIMONIAL_TITLE'          => isset( $testimonial_data['testimonial_title'] ) ? $testimonial_data['testimonial_title'] : '',
			'TESTIMONIAL_CONTENT'        => isset( $testimonial_data['testimonial_content'] ) ? $testimonial_data['testimonial_content'] : '',
			'TESTIMONIAL_DATE'           => $testimonial_date,
			'TESTIMONIAL_AUTHOR'         => isset( $testimonial_data['testimonial_author'] ) ? $testimonial_data['testimonial_author'] : '',
			'TESTIMONIAL_AUTHOR_EMAIL'   => isset( $testimonial_data['testimonial_author_email'] ) ? $testimonial_data['testimonial_author_email'] : '',
			'TESTIMONIAL_AUTHOR_ROLE'    => isset( $testimonial_data['testimonial_author_role'] ) ? $testimonial_data['testimonial_author_role'] : '',
			'TESTIMONIAL_AUTHOR_WEBSITE' => isset( $testimonial_data['testimonial_author_website'] ) ? $testimonial_data['testimonial_author_website'] : '',
			'TESTIMONIAL_STATUS'         => $testimonial_status,
		);
	}
}
