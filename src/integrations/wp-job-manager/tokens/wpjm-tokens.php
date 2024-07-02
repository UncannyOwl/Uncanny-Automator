<?php

namespace Uncanny_Automator;

use WP_User;

/**
 * Class Wpjm_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpjm_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPJM';

	public function __construct() {
		add_filter( 'automator_maybe_trigger_wpjm_wpjmjobtype_tokens', array( $this, 'wpjm_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_pre_tokens', array( $this, 'wpjm_resume_possible_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_wpjm_wpjmjobapplication_tokens',
			array(
				$this,
				'wpjm_jobapplication_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpjm_token' ), 20, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpjm_token_form_fields' ), 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'WP_Job_Manager' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function wpjm_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'WPJMJOBCATEGORIES',
				'tokenName'       => __( 'Job categories', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERNAME',
				'tokenName'       => __( 'Job owner username', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNEREMAIL',
				'tokenName'       => __( 'Job owner email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERFIRSTNAME',
				'tokenName'       => __( 'Job owner first name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERLASTNAME',
				'tokenName'       => __( 'Job owner last name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBTITLE',
				'tokenName'       => __( 'Job title', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBID',
				'tokenName'       => __( 'Job ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBLOCATION',
				'tokenName'       => __( 'Location', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBDESCRIPTION',
				'tokenName'       => __( 'Job description', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBAPPURL',
				'tokenName'       => __( 'Application email/URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBCOMPANYNAME',
				'tokenName'       => __( 'Company name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBWEBSITE',
				'tokenName'       => __( 'Website', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBTAGLINE',
				'tokenName'       => __( 'Tagline', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBVIDEO',
				'tokenName'       => __( 'Video', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBTWITTER',
				'tokenName'       => __( 'Twitter username', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
			array(
				'tokenId'         => 'WPJMJOBLOGOURL',
				'tokenName'       => __( 'Logo URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMSUBMITJOB',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function wpjm_jobapplication_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		// Default Standard tokens.
		$fields = array(
			array(
				'tokenId'         => 'WPJMAPPLICATIONNAME',
				'tokenName'       => __( 'Candidate name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATIONID',
			),
			array(
				'tokenId'         => 'WPJMAPPLICATIONEMAIL',
				'tokenName'       => __( 'Candidate email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATIONID',
			),
			array(
				'tokenId'         => 'WPJMAPPLICATIONMESSAGE',
				'tokenName'       => __( 'Message', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATIONID',
			),
			array(
				'tokenId'         => 'WPJMAPPLICATIONCV',
				'tokenName'       => __( 'CV', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATIONID',
			),
			array(
				'tokenId'         => 'WPJMJOBTYPE',
				'tokenName'       => __( 'Job type', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERNAME',
				'tokenName'       => __( 'Job owner username', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNEREMAIL',
				'tokenName'       => __( 'Job owner email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERFIRSTNAME',
				'tokenName'       => __( 'Job owner first name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBOWNERLASTNAME',
				'tokenName'       => __( 'Job owner last name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBLOCATION',
				'tokenName'       => __( 'Location', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBDESCRIPTION',
				'tokenName'       => __( 'Job description', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBAPPURL',
				'tokenName'       => __( 'Application email/URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBCOMPANYNAME',
				'tokenName'       => __( 'Company name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBWEBSITE',
				'tokenName'       => __( 'Website', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBTAGLINE',
				'tokenName'       => __( 'Tagline', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBVIDEO',
				'tokenName'       => __( 'Video', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBTWITTER',
				'tokenName'       => __( 'Twitter username', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
			array(
				'tokenId'         => 'WPJMJOBLOGOURL',
				'tokenName'       => __( 'Logo URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'WPJMJOBAPPLICATION',
			),
		);

		$form_fields = $this->get_job_application_form_fields();
		if ( ! empty( $form_fields ) ) {
			foreach ( $form_fields as $key => $field ) {
				$fields[] = array(
					'tokenId'         => 'WPJMAPPLICATIONFIELD_' . $key,
					'tokenName'       => $field['label'],
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMJOBAPPLICATIONID',
				);
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function wpjm_resume_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['code'];
		if ( 'WPJMSUBMITRESUME' === $trigger_meta ) {
			$fields = array(
				array(
					'tokenId'         => 'WPJMRESUMECATEGORIES',
					'tokenName'       => __( 'Resume categories', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJM_RESUME_ID',
					'tokenName'       => __( 'Resume ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJM_RESUME_URL',
					'tokenName'       => __( 'Resume URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMENAME',
					'tokenName'       => __( 'Candidate name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEEMAIL',
					'tokenName'       => __( 'Candidate email', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEPROTITLE',
					'tokenName'       => __( 'Professional title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMELOCATION',
					'tokenName'       => __( 'Location', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEPHOTO',
					'tokenName'       => __( 'Photo', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEVIDEO',
					'tokenName'       => __( 'Video', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMECONTENT',
					'tokenName'       => __( 'Resume content', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEURLS',
					'tokenName'       => __( 'URL(s)', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEEDUCATION',
					'tokenName'       => __( 'Education', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
				array(
					'tokenId'         => 'WPJMRESUMEEXPERIENCE',
					'tokenName'       => __( 'Experience', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => 'WPJMSUBMITRESUME',
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function wpjm_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPJMSUBMITJOB';

		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) || in_array( 'WPJMJOBAPPLICATION', $pieces ) ) {

				$entry = $this->get_entry_id( $pieces, $replace_args );

				if ( $pieces[2] === 'WPJMJOBTYPE' ) {
					$job_terms   = wpjm_get_the_job_types( $entry );
					$entry_terms = array();
					if ( ! empty( $job_terms ) ) {
						foreach ( $job_terms as $term ) {
							$entry_terms[] = esc_html( $term->name );
						}
					}
					$value = implode( ', ', $entry_terms );
				} elseif ( $pieces[2] === 'WPJMJOBOWNERNAME' ) {
					$job    = get_post( $entry );
					$author = get_user_by( 'ID', $job->post_author );
					if ( $author instanceof WP_User ) {
						$value = $author->user_login;
					}
				} elseif ( $pieces[2] === 'WPJMJOBOWNEREMAIL' ) {
					$job    = get_post( $entry );
					$author = get_user_by( 'ID', $job->post_author );
					if ( $author instanceof WP_User ) {
						$value = $author->user_email;
					}
				} elseif ( $pieces[2] === 'WPJMJOBOWNERFIRSTNAME' ) {
					$job    = get_post( $entry );
					$author = get_user_by( 'ID', $job->post_author );
					if ( $author instanceof WP_User ) {
						$value = $author->first_name;
					}
				} elseif ( $pieces[2] === 'WPJMJOBOWNERLASTNAME' ) {
					$job    = get_post( $entry );
					$author = get_user_by( 'ID', $job->post_author );
					if ( $author instanceof WP_User ) {
						$value = $author->last_name;
					}
				} elseif ( $pieces[2] === 'WPJMJOBTITLE' || 'WPJMJOBAPPLICATION' === $pieces[2] ) {
					$job   = get_post( $entry );
					$value = $job->post_title;
				} elseif ( $pieces[2] === 'WPJMJOBID' ) {
					$job   = get_post( $entry );
					$value = $job->ID;
				} elseif ( $pieces[2] === 'WPJMJOBLOCATION' ) {
					$job   = get_post( $entry );
					$value = get_the_job_location( $job );
				} elseif ( $pieces[2] === 'WPJMJOBDESCRIPTION' ) {
					$job   = get_post( $entry );
					$value = wpjm_get_the_job_description( $job );
				} elseif ( $pieces[2] === 'WPJMJOBAPPURL' ) {
					$job    = get_post( $entry );
					$method = get_the_job_application_method( $job );
					if ( ! empty( $method ) ) {
						if ( 'email' === $method->type ) {
							$value = $method->email;
						} elseif ( 'url' === $method->type ) {
							$value = $method->url;
						}
					}
				} elseif ( $pieces[2] === 'WPJMJOBCOMPANYNAME' ) {
					$job   = get_post( $entry );
					$value = get_the_company_name( $job );
				} elseif ( $pieces[2] === 'WPJMJOBWEBSITE' ) {
					$job   = get_post( $entry );
					$value = get_the_company_website( $job );
				} elseif ( $pieces[2] === 'WPJMJOBTAGLINE' ) {
					$job   = get_post( $entry );
					$value = get_the_company_tagline( $job );
				} elseif ( $pieces[2] === 'WPJMJOBVIDEO' ) {
					$job   = get_post( $entry );
					$value = get_the_company_video( $job );
				} elseif ( $pieces[2] === 'WPJMJOBTWITTER' ) {
					$job   = get_post( $entry );
					$value = get_the_company_twitter( $job );
				} elseif ( $pieces[2] === 'WPJMJOBLOGOURL' ) {
					$job   = get_post( $entry );
					$value = get_the_company_logo( $job );
				}
			}
		}
		$piece_resume = 'WPJMSUBMITRESUME';
		if ( $pieces ) {

			if ( in_array( $piece_resume, $pieces ) || in_array( 'WPJMJOBAPPLICATIONID', $pieces ) ) {
				$entry = $this->get_entry_id( $pieces, $replace_args );
				if ( 'WPJMRESUMENAME' === $pieces[2] || 'WPJMAPPLICATIONNAME' === $pieces[2] ) {
					$resume = get_post( $entry );
					$value  = $resume->post_title;
				} elseif ( 'WPJMRESUMEEMAIL' === $pieces[2] || 'WPJMAPPLICATIONEMAIL' === $pieces[2] ) {
					$resume          = get_post( $entry );
					$candidate_email = get_post_meta( $resume->ID, '_candidate_email', true );
					if ( empty( $candidate_email ) ) {
						$author = get_user_by( 'ID', $job->post_author );
						if ( $author instanceof WP_User ) {
							$candidate_email = $author->last_name;
						}
					}
					$value = $candidate_email;
				} elseif ( $pieces[2] === 'WPJMRESUMEPROTITLE' ) {
					// check if it has a resume id
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$value  = get_the_candidate_title( $resume );
				} elseif ( $pieces[2] === 'WPJMRESUMELOCATION' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$value  = get_the_candidate_location( $resume );
				} elseif ( $pieces[2] === 'WPJMRESUMEPHOTO' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$value  = get_the_candidate_photo( $resume );
				} elseif ( $pieces[2] === 'WPJMRESUMEVIDEO' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$value  = get_the_candidate_video( $resume );
				} elseif ( 'WPJMRESUMECONTENT' === $pieces[2] ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$value  = $resume->post_content;
				} elseif ( 'WPJMAPPLICATIONMESSAGE' === $pieces[2] ) {
					$resume = get_post( $entry );
					$value  = $resume->post_content;
				} elseif ( 'WPJM_RESUME_ID' === $pieces[2] ) {
					$value = $entry;
				} elseif ( 'WPJM_RESUME_URL' === $pieces[2] ) {
					$value = get_permalink( $entry );
				} elseif ( $pieces[2] === 'WPJMRESUMEURLS' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume = get_post( $entry );
					$links  = get_resume_links( $resume );
					$return = '<ul class="resume-links">';
					if ( ! empty( $links ) ) {
						foreach ( $links as $key => $link ) {
							$return .= '<li class="resume-link resume-link-www"><a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '">' . $link['name'] . '</a></li>';
						}
					}
					$return .= '</ul>';
					$value  = $return;
				} elseif ( $pieces[2] === 'WPJMRESUMEEDUCATION' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume    = get_post( $entry );
					$education = get_post_meta( $resume->ID, '_candidate_education', true );
					if ( ! empty( $education ) ) {
						$resume_education_str = '';
						foreach ( $education as $key => $item ) {
							// translators: Placeholder is location of education experience.
							$resume_education_str .= sprintf( __( 'Location: %s', 'wp-job-manager-resumes' ), $item['location'] ) . PHP_EOL;
							// translators: Placeholder is date of education experience.
							$resume_education_str .= sprintf( __( 'Date: %s', 'wp-job-manager-resumes' ), $item['date'] ) . PHP_EOL;
							// translators: Placeholder is qualifications/degrees of education experience.
							$resume_education_str .= sprintf( __( 'Qualification: %s', 'wp-job-manager-resumes' ), $item['qualification'] ) . PHP_EOL;
							// translators: Placeholder is notes for education experience.
							$resume_education_str .= sprintf( __( 'Notes: %s', 'wp-job-manager-resumes' ), $item['notes'] ) . PHP_EOL;
							$resume_education_str .= PHP_EOL;
						}

						$value = trim( $resume_education_str, PHP_EOL );
					}
				} elseif ( $pieces[2] === 'WPJMRESUMEEXPERIENCE' ) {
					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}
					$resume     = get_post( $entry );
					$experience = get_post_meta( $resume->ID, '_candidate_experience', true );
					if ( ! empty( $experience ) ) {
						$resume_experience_str = '';
						foreach ( $experience as $key => $item ) {
							// translators: Placeholder is employer name of experience.
							$resume_experience_str .= sprintf( __( 'Employer: %s', 'wp-job-manager-resumes' ), $item['employer'] ) . PHP_EOL;
							// translators: Placeholder is date of experience.
							$resume_experience_str .= sprintf( __( 'Date: %s', 'wp-job-manager-resumes' ), $item['date'] ) . PHP_EOL;
							// translators: Placeholder is job title of experience.
							$resume_experience_str .= sprintf( __( 'Job title: %s', 'wp-job-manager-resumes' ), $item['job_title'] ) . PHP_EOL;
							// translators: Placeholder is notes for experience.
							$resume_experience_str .= sprintf( __( 'Notes: %s', 'wp-job-manager-resumes' ), $item['notes'] ) . PHP_EOL;
							$resume_experience_str .= PHP_EOL;
						}

						$value = trim( $resume_experience_str, PHP_EOL );
					}
				} elseif ( $pieces[2] === 'WPJMAPPLICATIONCV' ) {

					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}

					$attachments_list = array();

					// Get the job application attachments.
					if ( ! function_exists( 'get_job_application_attachments' ) ) {
						return esc_html__( 'The addon WP Job Manager - Applications must be activated to use this token.', 'uncanny-automator' );
					}

					$attachments = get_job_application_attachments( $entry );

					if ( ! empty( $attachments ) ) {
						foreach ( $attachments as $attachment ) {
							$attachments_list[] = esc_url( $attachment );
						}
					}

					// Get the resume files.
					if ( ! function_exists( 'get_resume_files' ) ) {
						return esc_html__( 'The addon WP Job Manager - Resume Manager must be activated to use this token.', 'uncanny-automator' );
					}

					$attachments = get_resume_files( $entry );

					if ( ! empty( $attachments ) ) {
						foreach ( $attachments as $attachment ) {
							$attachments_list[] = esc_url( $attachment );
						}
					}

					// Separate the attachment urls by a comma.
					$value = implode( ', ', $attachments_list );

				} elseif ( $pieces[2] === 'WPJMRESUMECATEGORIES' ) {

					if ( $_resume_id = get_post_meta( $entry, '_resume_id', true ) ) {
						$entry = $_resume_id;
					}

					$resume_categories = Automator()->helpers->recipe->wp_job_manager->options->get_resume_categories( $entry );

					$value = implode( ', ', $resume_categories );
				}
			}
		}

		return $value;
	}

	/**
	 * Maybe Parse form fields
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string
	 */
	public function wpjm_token_form_fields( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		// Bail early if the pieces are empty or not an array or the WPJMJOBAPPLICATIONID is not in the pieces array
		if ( empty( $pieces ) || ! is_array( $pieces ) || ! in_array( 'WPJMJOBAPPLICATIONID', $pieces ) ) {
			return $value;
		}

		$token_key = $pieces[2];
		// Bail if token key does not start with WPJMAPPLICATIONFIELD_
		if ( strpos( $token_key, 'WPJMAPPLICATIONFIELD_' ) === false ) {
			return $value;
		}

		$index       = str_replace( 'WPJMAPPLICATIONFIELD_', '', $token_key );
		$form_fields = $this->get_job_application_form_fields();
		if ( empty( $form_fields ) || ! key_exists( $index, $form_fields ) ) {
			return $value;
		}

		// Field Configuration.
		$field = $form_fields[ $index ];

		// Entry ID.
		$entry_id = $this->get_entry_id( $pieces, $replace_args );
		if ( empty( $entry_id ) ) {
			return $value;
		}

		if ( $_resume_id = get_post_meta( $entry_id, '_resume_id', true ) ) {
			$entry_id = $_resume_id;
		}

		$raw   = get_post_meta( $entry_id, $field['label'], true );
		$value = is_array( $raw ) ? implode( ', ', $raw ) : $raw;

		return apply_filters( 'automator_wp_job_manager_application_parse_field_token_' . $index, $value, $field, $entry_id );
	}

	/**
	 * Get the entry ID
	 *
	 * @param $pieces
	 * @param $replace_args
	 *
	 * @return int
	 */
	public function get_entry_id( $pieces, $replace_args ) {

		$trigger_id     = $pieces[0];
		$trigger_meta   = $pieces[1];
		$field          = $pieces[2];
		$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

		global $wpdb;
		$entry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
				FROM {$wpdb->prefix}uap_trigger_log_meta
				WHERE meta_key = %s
				AND automator_trigger_log_id = %d
				AND automator_trigger_id = %d
				LIMIT 0,1",
				$trigger_meta,
				$trigger_log_id,
				$trigger_id
			)
		);

		return maybe_unserialize( $entry );
	}

	/**
	 * Get all the job application form fields
	 *
	 * @return array
	 */
	public function get_job_application_form_fields() {

		$fields = array();

		if ( function_exists( 'get_job_application_form_fields' ) ) {
			$all_fields = get_job_application_form_fields();
			// REVIEW - this will be problematic as the field keys are not consistent
			$defaults = apply_filters(
				'automator_wp_job_manager_application_fields_defaults',
				array(
					'full-name', // WPJMAPPLICATIONNAME
					'email-address', // WPJMAPPLICATIONEMAIL
					'message', // WPJMAPPLICATIONMESSAGE
					'online-resume',
					'upload-cv',  //WPJMAPPLICATIONCV
				)
			);
			foreach ( $all_fields as $key => $field ) {
				// Don't add the default fields or file fields
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
