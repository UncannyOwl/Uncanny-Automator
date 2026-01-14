<?php

namespace Uncanny_Automator\Integrations\Wpjm;

use Uncanny_Automator\Integrations\Wpjm\Wpjm_Helpers;

/**
 * Class Wpjm_Token_Manager
 *
 * Centralized token management for WP Job Manager integration (Free)
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Token_Manager {

	/**
	 * Get job-related tokens
	 *
	 * @return array
	 */
	public static function get_job_tokens( $token_identifier = 'WPJMJOBAPPLICATION', $add_basic_tokens = false ) {
		$basic_tokens = array();

		if ( true === $add_basic_tokens ) {
			$basic_tokens = array(
				array(
					'tokenId' => 'WPJMJOBID',
					'tokenName' => esc_html_x( 'Job ID', 'WP Job Manager', 'uncanny-automator' ),
					'tokenType' => 'int',
					'tokenIdentifier' => $token_identifier,
				),
				array(
					'tokenId' => 'WPJMJOBTITLE',
					'tokenName' => esc_html_x( 'Job title', 'WP Job Manager', 'uncanny-automator' ),
					'tokenType' => 'text',
					'tokenIdentifier' => $token_identifier,
				),
				array(
					'tokenId' => 'WPJMJOBURL',
					'tokenName' => esc_html_x( 'Job URL', 'WP Job Manager', 'uncanny-automator' ),
					'tokenType' => 'url',
					'tokenIdentifier' => $token_identifier,
				),
			);
		}
		$job_tokens = array(
			array(
				'tokenId' => 'WPJMJOBLOCATION',
				'tokenName' => esc_html_x( 'Location', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBDESCRIPTION',
				'tokenName' => esc_html_x( 'Job description', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBTYPE',
				'tokenName' => esc_html_x( 'Job type', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBCATEGORIES',
				'tokenName' => esc_html_x( 'Job categories', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBAPPURL',
				'tokenName' => esc_html_x( 'Application email/URL', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBCOMPANYNAME',
				'tokenName' => esc_html_x( 'Company name', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBWEBSITE',
				'tokenName' => esc_html_x( 'Website', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBTAGLINE',
				'tokenName' => esc_html_x( 'Tagline', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBVIDEO',
				'tokenName' => esc_html_x( 'Video', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBTWITTER',
				'tokenName' => esc_html_x( 'Twitter username', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBLOGOURL',
				'tokenName' => esc_html_x( 'Logo URL', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBOWNERNAME',
				'tokenName' => esc_html_x( 'Job owner username', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBOWNEREMAIL',
				'tokenName' => esc_html_x( 'Job owner email', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBOWNERFIRSTNAME',
				'tokenName' => esc_html_x( 'Job owner first name', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMJOBOWNERLASTNAME',
				'tokenName' => esc_html_x( 'Job owner last name', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
		);

		return array_merge( $basic_tokens, $job_tokens );
	}

	/**
	 * Get application-related tokens
	 *
	 * @return array
	 */
	public static function get_application_tokens( $token_identifier = 'WPJMJOBAPPLICATIONID' ) {
		$common_tokens = array(
			array(
				'tokenId' => 'WPJMJOBAPPLICATIONID',
				'tokenName' => esc_html_x( 'Application ID', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'int',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMAPPLICATIONNAME',
				'tokenName' => esc_html_x( 'Candidate name', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMAPPLICATIONMESSAGE',
				'tokenName' => esc_html_x( 'Application message', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMAPPLICATIONCV',
				'tokenName' => esc_html_x( 'CV', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
			array(
				'tokenId' => 'WPJMAPPLICATIONEMAIL',
				'tokenName' => esc_html_x( 'Candidate email', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
				'tokenIdentifier' => $token_identifier,
			),
		);

		$form_fields = self::get_job_application_form_fields();
		if ( ! empty( $form_fields ) ) {
			foreach ( $form_fields as $key => $field ) {
				$common_tokens[] = array(
					'tokenId' => 'WPJMAPPLICATIONFIELD_' . $key,
					'tokenName' => esc_html_x( 'Application field: ' . ucfirst( strtolower( $field['label'] ) ), 'WP Job Manager', 'uncanny-automator' ),
					'tokenType' => 'text',
					'tokenIdentifier' => $token_identifier,
				);
			}
		}

		return $common_tokens;
	}

	/**
	 * Get resume-related tokens
	 *
	 * @return array
	 */
	public static function get_resume_tokens() {
		return array(
			array(
				'tokenId' => 'WPJM_RESUME_ID',
				'tokenName' => esc_html_x( 'Resume ID', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId' => 'WPJM_RESUME_URL',
				'tokenName' => esc_html_x( 'Resume URL', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId' => 'WPJMRESUMECONTENT',
				'tokenName' => esc_html_x( 'Resume content', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMECATEGORIES',
				'tokenName'       => esc_html_x( 'Resume categories', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMENAME',
				'tokenName'       => esc_html_x( 'Candidate name', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEEMAIL',
				'tokenName'       => esc_html_x( 'Candidate email', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEPROTITLE',
				'tokenName'       => esc_html_x( 'Professional title', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMELOCATION',
				'tokenName'       => esc_html_x( 'Location', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEPHOTO',
				'tokenName'       => esc_html_x( 'Photo', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEVIDEO',
				'tokenName'       => esc_html_x( 'Video', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEURLS',
				'tokenName'       => esc_html_x( 'URL(s)', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEEDUCATION',
				'tokenName'       => esc_html_x( 'Education', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
			array(
				'tokenId'         => 'WPJMRESUMEEXPERIENCE',
				'tokenName'       => esc_html_x( 'Experience', 'WP Job Manager', 'uncanny-automator' ),
				'tokenType'       => 'text',
			),
		);
	}


	/**
	 * Hydrate job tokens
	 *
	 * @param int $job_id Job ID
	 * @param string $token_id Token ID
	 * @return string
	 */
	public static function hydrate_job_tokens( $job_id, $token_id ) {
		$job = get_post( $job_id );
		if ( ! $job ) {
			return array();
		}

		$job_types = wpjm_get_the_job_types( $job );
		$types     = array();
		if ( ! empty( $job_types ) ) {
			foreach ( $job_types as $type ) {
				$types[] = $type->name;
			}
		}

		$method       = get_the_job_application_method( $job );
		$method_value = '';
		if ( ! empty( $method ) ) {
			if ( 'email' === $method->type ) {
				$method_value = $method->email;
			} elseif ( 'url' === $method->type ) {
				$method_value = $method->url;
			}
		}

		$token_value = '';

		try {
			switch ( $token_id ) {
				case 'WPJMJOBID':
				case 'WPJMJOBAPPLICATION_ID':
				case 'WPJMSPECIFICJOB_ID':
					$token_value = $job->ID;
					break;

				case 'WPJMJOBTITLE':
				case 'WPJMJOBAPPLICATION':
				case 'WPJMSPECIFICJOB':
					$token_value = wpjm_get_the_job_title( $job );
					break;

				case 'WPJMJOBLOCATION':
					$token_value = get_the_job_location( $job );
					break;

				case 'WPJMJOBDESCRIPTION':
					$token_value = wpjm_get_the_job_description( $job );
					break;

				case 'WPJMJOBAPPURL':
					$token_value = $method_value;
					break;

				case 'WPJMJOBCOMPANYNAME':
					$token_value = get_the_company_name( $job );
					break;

				case 'WPJMJOBWEBSITE':
					$token_value = get_the_company_website( $job );
					break;

				case 'WPJMJOBTAGLINE':
					$token_value = get_the_company_tagline( $job );
					break;

				case 'WPJMJOBVIDEO':
					$token_value = get_the_company_video( $job );
					break;

				case 'WPJMJOBTWITTER':
					$token_value = get_the_company_twitter( $job );
					break;

				case 'WPJMJOBLOGOURL':
					$token_value = get_the_company_logo( $job );
					break;

				case 'WPJMJOBTYPE':
				case 'WPJMAPPJOBTYPE':
				case 'WPJMSPECIFICJOBTYPE':
					$token_value = implode( ', ', $types );
					break;

				case 'WPJMJOBCATEGORIES':
					$token_value = self::get_taxonomy_categories_string( $job_id, 'job_listing_category' );
					break;

				case 'WPJMJOBOWNERNAME':
					$token_value = get_the_author_meta( 'user_login', $job->post_author );
					break;

				case 'WPJMJOBOWNEREMAIL':
					$token_value = get_the_author_meta( 'user_email', $job->post_author );
					break;

				case 'WPJMJOBOWNERFIRSTNAME':
					$token_value = get_the_author_meta( 'first_name', $job->post_author );
					break;

				case 'WPJMJOBOWNERLASTNAME':
					$token_value = get_the_author_meta( 'last_name', $job->post_author );
					break;

				case 'WPJMJOBURL':
				case 'WPJMJOBAPPLICATION_URL':
				case 'WPJMSPECIFICJOB_URL':
					$token_value = get_permalink( $job->ID );
					break;

				case 'WPJMAPPLICATIONOWNERID':
					$token_value = $job->post_author;
					break;

			}
		} catch ( \Exception $e ) {
			automator_log( 'WP Job Manager Job Token Error', $e->getMessage() );
		}

		return $token_value;
	}

	/**
	 * Hydrate application tokens
	 *
	 * @param int $application_id Application ID
	 * @param string $token_id Token ID
	 * @return string
	 */
	public static function hydrate_application_tokens( $application_id, $token_id ) {
		$application = get_post( $application_id );
		if ( ! $application ) {
			return array();
		}

		$candidate_email = get_post_meta( $application_id, '_candidate_email', true );
		if ( empty( $candidate_email ) ) {
			$author = get_user_by( 'ID', $application->post_author );
			if ( $author instanceof \WP_User ) {
				$candidate_email = $author->user_email;
			}
		}

		$attachment  = get_post_meta( $application_id, '_attachment', true );
		$attachments = '';
		if ( ! empty( $attachment ) ) {
			$attachment  = maybe_unserialize( $attachment );
			$attachments = is_array( $attachment ) ? join( ', ', $attachment ) : $attachment;
		}

		if ( true === str_starts_with( $token_id, 'WPJMAPPLICATIONFIELD_' ) ) {
			$field_key = str_replace( 'WPJMAPPLICATIONFIELD_', '', $token_id );
			if ( 'candidate_name' === $field_key ) {
				$token_value = $application->post_title;
			} elseif ( 'application_message' === $field_key ) {
				$token_value = $application->post_content;
			} else {
				$token_value = get_post_meta( $application_id, '_' . $field_key, true );
			}

			return $token_value;
		}

		$token_value = '';

		try {
			switch ( $token_id ) {
				case 'WPJMJOBAPPLICATIONID':
					$token_value = $application_id;
					break;
				case 'WPJMAPPLICATIONNAME':
					$token_value = $application->post_title;
					break;
				case 'WPJMAPPLICATIONMESSAGE':
					$token_value = $application->post_content;
					break;
				case 'WPJMAPPLICATIONCV':
					$token_value = $attachments;
					break;
				case 'WPJMAPPLICATIONEMAIL':
					$token_value = $candidate_email;
					break;
				case 'WPJMAPPLICATIONCANDIDATEID':
					$token_value = $application->post_author;
					break;
			}
		} catch ( \Exception $e ) {
			automator_log( 'WP Job Manager Application Token Error', $e->getMessage() );
		}

			return $token_value;
	}

	/**
	 * Hydrate resume tokens
	 *
	 * @param int $resume_id Resume ID
	 * @return array
	 */
	public static function hydrate_resume_tokens( $resume_id ) {
		$resume = get_post( $resume_id );
		if ( ! $resume ) {
			return array();
		}

		$links       = get_resume_links( $resume );
		$resume_urls = '<ul class="resume-links">';
		if ( ! empty( $links ) ) {
			foreach ( $links as $key => $link ) {
				$resume_urls .= '<li class="resume-link resume-link-www"><a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '">' . $link['name'] . '</a></li>';
			}
		}
		$resume_urls .= '</ul>';

		$education            = get_post_meta( $resume->ID, '_candidate_education', true );
		$resume_education_str = '';
		if ( ! empty( $education ) ) {
			foreach ( $education as $key => $item ) {
				// translators: Placeholder is location of education experience.
				$resume_education_str .= sprintf( esc_html_x( 'Location: %s', 'WP Job Manager', 'uncanny-automator' ), $item['location'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is date of education experience.
				$resume_education_str .= sprintf( esc_html_x( 'Date: %s', 'WP Job Manager', 'uncanny-automator' ), $item['date'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is qualifications/degrees of education experience.
				$resume_education_str .= sprintf( esc_html_x( 'Qualification: %s', 'WP Job Manager', 'uncanny-automator' ), $item['qualification'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is notes for education experience.
				$resume_education_str .= sprintf( esc_html_x( 'Notes: %s', 'WP Job Manager', 'uncanny-automator' ), $item['notes'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				$resume_education_str .= '<br>';
			}
		}

		$experience            = get_post_meta( $resume->ID, '_candidate_experience', true );
		$resume_experience_str = '';
		if ( ! empty( $experience ) ) {
			foreach ( $experience as $key => $item ) {
				// translators: Placeholder is employer name of experience.
				$resume_experience_str .= sprintf( esc_html_x( 'Employer: %s', 'WP Job Manager', 'uncanny-automator' ), $item['employer'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is date of experience.
				$resume_experience_str .= sprintf( esc_html_x( 'Date: %s', 'WP Job Manager', 'uncanny-automator' ), $item['date'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is job title of experience.
				$resume_experience_str .= sprintf( esc_html_x( 'Job title: %s', 'WP Job Manager', 'uncanny-automator' ), $item['job_title'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				// translators: Placeholder is notes for experience.
				$resume_experience_str .= sprintf( esc_html_x( 'Notes: %s', 'WP Job Manager', 'uncanny-automator' ), $item['notes'] ) . '<br>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				$resume_experience_str .= '<br>'; // Extra line between experiences
			}
		}

		return array(
			'WPJM_RESUME_ID' => $resume_id,
			'WPJM_RESUME_URL' => get_permalink( $resume_id ),
			'WPJMRESUMENAME' => $resume->post_title,
			'WPJMRESUMEEMAIL' => get_post_meta( $resume_id, '_candidate_email', true ),
			'WPJMRESUMECATEGORIES' => self::get_taxonomy_categories_string( $resume_id, 'resume_category' ),
			'WPJMRESUMECONTENT' => $resume->post_content,
			'WPJMRESUMEURLS' => $resume_urls,
			'WPJMRESUMEPROTITLE' => get_the_candidate_title( $resume ),
			'WPJMRESUMELOCATION' => get_the_candidate_location( $resume ),
			'WPJMRESUMEPHOTO' => get_the_candidate_photo( $resume ),
			'WPJMRESUMEVIDEO' => get_the_candidate_video( $resume ),
			'WPJMRESUMEEDUCATION' => rtrim( $resume_education_str, '<br>' ),
			'WPJMRESUMEEXPERIENCE' => rtrim( $resume_experience_str, '<br>' ),
		);
	}

		/**
	 * Get job categories as string
	 *
	 * @param int $job_id Job ID
	 * @return string
	 */
	private static function get_taxonomy_categories_string( $id, $taxonomy ) {
		if ( empty( $id ) ) {
			return '';
		}

		$categories = array();

		$terms = wp_get_object_terms( $id, $taxonomy );

		if ( ! is_wp_error( $terms ) ) {
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = $term->name;
				}
				// Sort alphabetically.
				sort( $categories );
			}
		}

		return ! empty( $categories ) ? implode( ', ', $categories ) : '';
	}

	/**
	 * Get all the job application form fields
	 *
	 * @return array
	 */
	private static function get_job_application_form_fields() {
		$fields = array();

		if ( function_exists( 'get_job_application_form_fields' ) ) {
			$all_fields = get_job_application_form_fields();
			$defaults   = apply_filters(
				'automator_wp_job_manager_application_fields_defaults',
				array(
					'full-name',
					'email-address',
					'message',
					'online-resume',
					'upload-cv',
				)
			);
			foreach ( $all_fields as $key => $field ) {
				if ( in_array( $key, $defaults, true ) || 'file' === $field['type'] ) {
					continue;
				}

				if ( apply_filters( 'automator_wp_job_manager_application_add_field_token_' . $key, true, $field ) ) {
					$fields[ $key ] = $field;
				}
			}
		}

		return $fields;
	}
}
