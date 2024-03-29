<?php

namespace Uncanny_Automator;

use FluentCrm\App\Models\CustomContactField;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\Tag;

/**
 * Class Fcrm_Tokens
 *
 * @package Uncanny_Automator
 */
class Fcrm_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FCRM';

	/**
	 * Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_fcrm_fcrmlist_tokens', array( $this, 'fcrm_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_fcrm_fcrmtag_tokens', array( $this, 'fcrm_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'fcrm_token' ), 20, 6 );
		// Separate status tokens for now.
		add_filter( 'automator_maybe_parse_token', array( $this, 'fcrm_status_tokens' ), 36, 6 );
	}

	/**
	 * Fluent CRM possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function fcrm_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_meta = $args['meta'];

		$trigger_code = $args['triggers_meta']['code'];

		// Allow Pro to add its trigger code.
		$contact_id_token_enabled_triggers = $this->get_contact_id_token_enabled_triggers( $tokens, $args );

		// Add 'Contact ID' to a set of triggers using trigger code.
		if ( in_array( $trigger_code, $contact_id_token_enabled_triggers, true ) ) {
			$tokens[] = array(
				'tokenId'         => 'contact_id',
				'tokenName'       => esc_html__( 'Contact ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			);
		}

		// All subscriber fields.
		foreach ( Subscriber::mappables() as $key => $label ) {
			$tokens[] = array(
				'tokenId'         => $key,
				'tokenName'       => $label,
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);

			// Add 'Company Name' token.
			if ( 'company_id' === $key ) {
				$tokens[] = array(
					'tokenId'         => 'company_name',
					'tokenName'       => esc_html__( 'Primary Company Name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				);
			}
		}

		// All custom subscriber fields
		foreach ( ( new CustomContactField() )->getGlobalFields()['fields'] as $field ) {
			$tokens[] = array(
				'tokenId'         => $field['slug'],
				'tokenName'       => $field['label'],
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
		}

		return $tokens;

	}

	/**
	 * Proccesses Fluent CRM tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function fcrm_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {

			if ( ! isset( $pieces[2] ) ) {
				return $value;
			}

			$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

			$trigger_id   = $pieces[0];
			$trigger_meta = $pieces[2];

			$contact_related_tokens = array(
				'FCRMLIST',
				'ANONFCRMUSERSTATUSUPDATED',
				'FCRMTAG',
			);

			if ( in_array( $pieces[1], $contact_related_tokens, true )
				&& 'contact_id' === $pieces[2] ) {
				// Get the subscriber ID.
				return $this->get_subscriber_id_from_log( $trigger_id, $trigger_log_id );
			}

			if (
				( 'FCRMUSERLIST' === $pieces['1'] && 'FCRMLIST' === $trigger_meta ) ||
				( 'FCRMUSERTAG' === $pieces['1'] && 'FCRMTAG' === $trigger_meta ) ||
				( 'ANONFCRMUSERLIST' === $pieces['1'] && 'FCRMLIST' === $trigger_meta ) ||
				( 'ANONFCRMUSERTAG' === $pieces['1'] && 'FCRMTAG' === $trigger_meta )
			) {

				// value is the list or lists(if any list was selected) that the subscriber was added too
				global $wpdb;

				// Get a serialized array of list_ids OR tag_ids added to subscriber
				$entry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
							FROM {$wpdb->prefix}uap_trigger_log_meta
							WHERE meta_key = %s
							AND automator_trigger_log_id = %d
							AND automator_trigger_id = %d
							LIMIT 0, 1",
						$trigger_meta,
						// String. The trigger meta.
						$trigger_log_id,
						// Integer. The trigger log id.
						$trigger_id
						// Integer. The trigger id.
					)
				);

				if ( $entry ) {

					if ( 'FCRMLIST' === $trigger_meta ) {
						// ids added to subscriber during trigger
						$list_ids = maybe_unserialize( $entry );

						if ( is_array( $list_ids ) ) {
							$list_names = array();

							// All lists available in Fluent CRM
							$lists = Lists::orderBy( 'title', 'DESC' )->get();

							// List selected in the trigger ( 0 === any list )
							$trigger_list = $trigger_data[0]['meta']['FCRMLIST'];

							if ( ! empty( $lists ) ) {
								foreach ( $lists as $list ) {
									if ( 0 === absint( $trigger_list ) && in_array( $list->id, $list_ids, true ) ) {
										// Any list was selected
										$list_names[] = esc_html( $list->title );
									} elseif ( (int) $list->id === (int) $trigger_list ) {
										// a specific list selected
										$list_names[] = esc_html( $list->title );
									}
								}
							}

							return implode( ', ', $list_names );
						}//end if
					}//end if

					if ( 'FCRMTAG' === $trigger_meta ) {

						// ids added to subscriber during trigger
						$tag_ids = maybe_unserialize( $entry );

						if ( is_array( $tag_ids ) ) {
							$tag_names = array();

							// All tags available in Fluent CRM
							$tags = Tag::orderBy( 'title', 'DESC' )->get();

							// Tag selected in the trigger ( 0 === any tag )
							$trigger_tag = $trigger_data[0]['meta']['FCRMTAG'];

							if ( ! empty( $tags ) ) {
								foreach ( $tags as $tag ) {
									if ( 0 === absint( $trigger_tag ) && in_array( $tag->id, $tag_ids, true ) ) {
										// Any tag was selected
										$tag_names[] = esc_html( $tag->title );
									} elseif ( (int) $tag->id === (int) $trigger_tag ) {
										// a specific tag selected
										$tag_names[] = esc_html( $tag->title );
									}
								}
							}

							return implode( ', ', $tag_names );
						}//end if
					}//end if
				}//end if

				return '';
			}//end if

			if ( 'FCRMLIST' === $pieces['1'] || 'FCRMTAG' === $pieces['1'] ) {

				global $wpdb;

				// value is the contact information of the subscriber

				// Get the subscriber ID
				$entry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = 'subscriber_id'
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d
						LIMIT 0, 1",
						$trigger_log_id,
						$trigger_id
					)
				);

				if ( absint( $entry ) ) {
					$subscriber    = Subscriber::where( 'id', absint( $entry ) )->first();
					$contact_field = $pieces['2'];

					// Handle Primary Company Name token
					if ( 'company_name' === $contact_field ) {
						return $this->get_primary_company_name( $subscriber );
					}

					if ( isset( $subscriber->$contact_field ) ) {
						// its a standard field
						return $subscriber->$contact_field;
					} else {
						$custom_field_values = $subscriber->custom_fields();
						if ( isset( $custom_field_values[ $contact_field ] ) ) {
							if ( is_array( $custom_field_values[ $contact_field ] ) ) {
								return implode( ',', $custom_field_values[ $contact_field ] );
							}

							return $custom_field_values[ $contact_field ];
						}
					}
				}

				return '';
			}//end if
		}//end if

		return $value;
	}

	/**
	 * Get Primary Company Name
	 *
	 * @param \Subscriber $subscriber
	 *
	 * @return string
	 */
	public function get_primary_company_name( $subscriber ) {

		if ( ! $subscriber instanceof Subscriber ) {
			return '-';
		}

		// Ensure that the subscriber has a company ID.
		if ( ! isset( $subscriber->company_id ) || empty( $subscriber->company_id ) ) {
			return '-';
		}

		// Get the company.
		if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
			return '-';
		}
		$company = \FluentCrm\App\Models\Company::find( $subscriber->company_id );
		if ( ! $company instanceof \FluentCrm\App\Models\Company ) {
			return '-';
		}

		// Get the company name.
		if ( isset( $company->name ) && ! empty( $company->name ) ) {
			return $company->name;
		}

		return '-';
	}

	/**
	 * Parses the tokens.
	 *
	 * @param string $value         The value.
	 * @param array  $pieces        The pieces.
	 * @param int    $recipe_id     The recipe id.
	 * @param array  $trigger_data  The trigger data.
	 * @param int    $user_id       The user id.
	 * @param string $replace_args  The replace args
	 *
	 * @return string The token value.
	 */
	public function fcrm_status_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		// Bail out if Fluent CRM Api is not found.
		if ( ! function_exists( '\FluentCrmApi' ) ) {
			return $value;
		}

		if ( ! isset( $pieces[2] ) ) {
			return $value;
		}

		if ( false !== strpos( $pieces[2], 'FLUENTCRM_STATUS_FIELD_' ) ) {

			$property = str_replace( 'FLUENTCRM_STATUS_FIELD_', '', $pieces[2] );

			$trigger_id = $replace_args['trigger_id'];

			$trigger_log_id = $replace_args['trigger_log_id'];

			// Get the trigger run number.
			$run_number = Automator()->get->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );

			// Get the trigger meta value inserted from the trigger.
			$contact_email = Automator()->get->maybe_get_meta_value_from_trigger_log(
				'FCRMUSERUPDATEDSTATUS',
				$trigger_id,
				$trigger_log_id,
				$run_number,
				$user_id
			);

			// Get FluentCRM Contacts API instance.
			$contact_api = \FluentCrmApi( 'contacts' );

			// Query the contact by email address.
			$contact = $contact_api->getContactByUserRef( $contact_email );

			$token_value = '';

			if ( isset( $contact->$property ) ) {
				$token_value = $contact->$property;
			} else {
				// Try custom field.
				$contact_id = 0;
				if ( isset( $contact->id ) ) {
					$contact_id = $contact->id;
				}

				$token_value = $this->get_custom_field_value( $property, $contact_id );
			}

			return $token_value;

		}

		return $value;

	}

	/**
	 * Returns the custom field value.
	 *
	 * @param  mixed $key The custom field key.
	 * @param  mixed $subscriber_id The subscriber id.
	 *
	 * @return string The custom field value. Separated by comma if multiple.
	 */
	protected function get_custom_field_value( $key = '', $subscriber_id = 0 ) {

		$value = '';

		if ( empty( $key ) ) {
			return $value;
		}

		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `value`
                FROM {$wpdb->prefix}fc_subscriber_meta
                WHERE subscriber_id = %d AND `key` = %s",
				$subscriber_id,
				$key
			)
		);

		if ( is_serialized( $value ) ) {
			$value = maybe_unserialize( $value );
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
		}

		return $value;

	}

	/**
	 * Retrieve the subscriber ID from the log.
	 *
	 * @param int $trigger_id
	 * @param int $trigger_log_id
	 *
	 * @return int The subscriber ID.
	 */
	protected function get_subscriber_id_from_log( $trigger_id, $trigger_log_id ) {

		global $wpdb;

		$subscriber_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
				FROM {$wpdb->prefix}uap_trigger_log_meta
				WHERE meta_key = 'subscriber_id'
				AND automator_trigger_log_id = %d
				AND automator_trigger_id = %d
				LIMIT 0, 1",
				$trigger_log_id,
				$trigger_id
			)
		);

		return absint( $subscriber_id );

	}

	public function get_contact_id_token_enabled_triggers( $tokens, $args ) {

		return apply_filters(
			'automator_fluent_crm_token_contact_id',
			array(
				'ANONFCRMUSERLIST',
				'ANONFCRMUSERTAG',
			),
			$tokens,
			$args,
			$this
		);

	}

}
