<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_HELPERS
 *
 * @package Uncanny_Automator
 */
class CHARITABLE_HELPERS {

	/**
	 * CHARITABLE_HELPERS constructor.
	 */
	public function __construct() {
	}

	/**
	 * Active Charitable Campaign Posts Select Options.
	 *
	 * @return array
	 */
	public function get_campaign_options() {

		static $options = null;
		if ( null !== $options ) {
			return $options;
		}

		$options   = array();
		$campaigns = $this->get_active_campaign_posts();
		if ( ! empty( $campaigns ) ) {
			$options[] = array(
				'text'  => __( 'Any campaign', 'uncanny-automator' ),
				'value' => -1,
			);

			foreach ( $campaigns as $campaign ) {
				$options[] = array(
					'text'  => $campaign->post_title,
					'value' => $campaign->ID,
				);
			}
		}

		return $options;
	}

	/**
	 * Active Charitable Campaign Posts Select.
	 *
	 * @return array
	 */
	public function campaign_select() {
		return array(
			'input_type'  => 'select',
			'option_code' => 'CHARITABLE_CAMPAIGN',
			'label'       => __( 'Campaign', 'uncanny-automator' ),
			'required'    => true,
			'options'     => $this->get_campaign_options(),
		);
	}

	/**
	 * Recipe Donation Amount Conditions Select.
	 *
	 * @return array
	 */
	public function donation_amount_conditions_select() {
		// Equal to, not equal to, less than, greater than, greater or equal to, less or equal to.
		$amount_condition          = Automator()->helpers->recipe->field->less_or_greater_than();
		$amount_condition['label'] = __( 'Condition', 'uncanny-automator' );
		$conditions                = array();
		foreach ( $amount_condition['options'] as $value => $text ) {
			$conditions[] = array(
				'text'  => $text,
				'value' => $value,
			);
		}
		$amount_condition['options'] = $conditions;

		return $amount_condition;
	}

	/**
	 * Recipe Donation Amount Input.
	 *
	 * @return array
	 */
	public function donation_amount_input() {
		$default_amount = (int) apply_filters( 'automator_charitable_recipe_default_amount', 100 );
		return Automator()->helpers->recipe->field->int(
			array(
				'option_code' => 'DONATION_AMOUNT',
				'label'       => esc_attr__( 'Amount', 'uncanny-automator' ),
				'placeholder' => sprintf(
					/* translators: 1: Default amount */
					esc_attr__( 'Example: %d', 'uncanny-automator' ),
					$default_amount
				),
				'default'     => $default_amount,
				'min_number'  => 1, //REVIEW - possible to grab the min donation amount from the selected campaign?
			)
		);
	}

	/**
	 * Active Charitable Campaign Posts.
	 *
	 * @return array
	 */
	public function get_active_campaign_posts() {

		$campaigns = \Charitable_Campaigns::query(
			array(
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_campaign_end_date',
						'value'   => gmdate( 'Y-m-d H:i:s' ),
						'compare' => '>=',
						'type'    => 'datetime',
					),
					array(
						'key'     => '_campaign_end_date',
						'value'   => 0,
						'compare' => '=',
					),
				),
			)
		);

		return ! empty( $campaigns->posts ) ? $campaigns->posts : array();
	}

	/**
	 * Get Charitable Campaign Object.
	 *
	 * @param int $campaign_id - Campaign Post ID.
	 *
	 * @return mixed - Charitable_Campaign object or false.
	 */
	public function get_campaign( $campaign_id ) {

		$campaign_id = (int) $campaign_id;
		if ( empty( $campaign_id ) ) {
			return false;
		}
		$campaign = charitable_get_campaign( $campaign_id );

		return is_a( $campaign, 'Charitable_Campaign' ) ? $campaign : false;
	}

	/**
	 * Get Charitable Donation Object.
	 *
	 * @param int $donation_id - Donation Post ID.
	 *
	 * @return mixed - Charitable_Donation object or false.
	 */
	public function get_donation( $donation_id ) {

		$donation_id = (int) $donation_id;
		if ( empty( $donation_id ) ) {
			return false;
		}

		$donation = charitable_get_donation( $donation_id );

		return is_a( $donation, 'Charitable_Donation' ) ? $donation : false;
	}

	/**
	 * Get Charitable Campaign Object from Donation.
	 *
	 * @param mixed $donation - Maybe Charitable_Donation object or Donation Post ID.
	 *
	 * @return mixed - Charitable_Campaign object or false.
	 */
	public function get_donation_campaign( $donation ) {

		// Validate $donation.
		if ( is_int( $donation ) ) {
			$donation_id = $donation;
			$donation    = $this->get_donation( $donation_id );
			if ( ! $donation ) {
				return false;
			}
		}

		if ( ! is_a( $donation, 'Charitable_Donation' ) ) {
			return false;
		}

		// Get campaigns.
		$campaigns = $donation->get_campaign_donations();
		// Bail no campaigns.
		if ( empty( $campaigns ) ) {
			return false;
		}

		// TODO REVIEW - Handle Multiple Campaigns.
		$campaign_obj = reset( $campaigns );
		$campaign_id  = $campaign_obj->campaign_id;
		$campaign     = $this->get_campaign( $campaign_id );

		return $campaign ? $campaign : false;
	}

	/**
	 * Validates a Charitable Donation And Checks if it's in an Approved Status.
	 *
	 * @param int $donation_id - Donation Post ID.
	 *
	 * @return mixed - Charitable_Donation object or false.
	 */
	public function validate_approved_donation( $donation_id ) {

		$donation = $this->get_donation( $donation_id );
		if ( ! $donation ) {
			return false;
		}

		if ( ! charitable_is_approved_status( get_post_status( $donation_id ) ) ) {
			return false;
		}

		return $donation;
	}

	/**
	 * Get Donation Tokens Configuration.
	 *
	 * @return array
	 */
	public function get_donation_tokens_config() {

		return array(
			// Campaign tokens.
			array(
				'tokenId'   => 'CAMPAIGN_TITLE',
				'tokenName' => __( 'Campaign title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_LINK',
				'tokenName' => __( 'Campaign link', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'CAMPAIGN_ID',
				'tokenName' => __( 'Campaign ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_TAGS',
				'tokenName' => __( 'Campaign tags', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CATEGORIES',
				'tokenName' => __( 'Campaign categories', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_THUMB_URL',
				'tokenName' => __( 'Featured image URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'CAMPAIGN_THUMB_ID',
				'tokenName' => __( 'Featured image ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_END_DATE',
				'tokenName' => __( 'Campaign end date', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_MIN_DONATION_AMOUNT',
				'tokenName' => __( 'Campaign minimum donation amount', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_GOAL',
				'tokenName' => __( 'Campaign goal', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_ID',
				'tokenName' => __( 'Campaign creator ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_NAME',
				'tokenName' => __( 'Campaign creator name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_EMAIL',
				'tokenName' => __( 'Campaign creator email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),

			// Donation tokens.
			array(
				'tokenId'   => 'DONATION_AMOUNT_DONATED',
				'tokenName' => __( 'Amount donated', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			/* REVIEW - Not Sure what's being requested.
			array(
				'tokenId'   => 'DONATION_PAYMENT_ID',
				'tokenName' => __( 'Donation payment ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			*/
			array(
				'tokenId'   => 'DONATION_ID',
				'tokenName' => __( 'Donation ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DONATION_TITLE',
				'tokenName' => __( 'Donation title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_STATUS',
				'tokenName' => __( 'Donation status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ID',
				'tokenName' => __( 'Donation donor ID', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_NAME',
				'tokenName' => __( 'Donor name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_EMAIL',
				'tokenName' => __( 'Donor email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS',
				'tokenName' => __( 'Donor formatted address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS_LINE_1',
				'tokenName' => __( 'Donor address line 1', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS_LINE_2',
				'tokenName' => __( 'Donor address line 2', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_CITY',
				'tokenName' => __( 'Donor city', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_STATE',
				'tokenName' => __( 'Donor state', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_POSTCODE',
				'tokenName' => __( 'Donor postcode', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_COUNTRY',
				'tokenName' => __( 'Donor country', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_COUNTRY_CODE',
				'tokenName' => __( 'Donor country code', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_PHONE',
				'tokenName' => __( 'Donor phone number', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_PAYMENT_METHOD',
				'tokenName' => __( 'Payment method', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_KEY',
				'tokenName' => __( 'Donation key', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_TRANSACTION_ID',
				'tokenName' => __( 'Gateway transaction ID', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Populate Token Values.
	 *
	 * @param int $donation_id Donation ID.
	 *
	 * @return array
	 */
	public function hydrate_donation_tokens( $donation_id ) {

		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->get_donation_tokens_config(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		// Get Charitable_Donation object.
		$donation = $this->get_donation( $donation_id );
		// Bail invalid donation ID.
		if ( ! $donation ) {
			return $tokens;
		}

		//$tokens['DONATION_PAYMENT_ID']     = ''; // TODO REVIEW - Not sure what's requested.
		$tokens['DONATION_ID']             = $donation_id;
		$tokens['DONATION_TITLE']          = get_the_title( $donation_id );
		$tokens['DONATION_AMOUNT_DONATED'] = $donation->get_amount_formatted();
		$tokens['DONATION_STATUS']         = $donation->get_status_label();
		$tokens['DONATION_PAYMENT_METHOD'] = $donation->get_gateway_label();
		$tokens['DONATION_KEY']            = $donation->get_donation_key();
		$tokens['DONATION_TRANSACTION_ID'] = $donation->get_gateway_transaction_id();

		// Donor data.
		$donor_data = $donation->get_donor_data();
		if ( ! empty( $donor_data ) ) {
			$tokens['DONATION_DONOR_ID']             = $donation->get_donor_id();
			$tokens['DONATION_DONOR_NAME']           = $donor_data['first_name'] . ' ' . $donor_data['last_name'];
			$tokens['DONATION_DONOR_EMAIL']          = $donor_data['email'];
			$tokens['DONATION_DONOR_PHONE']          = $donor_data['phone'];
			$tokens['DONATION_DONOR_ADDRESS']        = $donation->get_donor_address();
			$tokens['DONATION_DONOR_ADDRESS_LINE_1'] = $donor_data['address'];
			$tokens['DONATION_DONOR_ADDRESS_LINE_2'] = $donor_data['address_2'];
			$tokens['DONATION_DONOR_CITY']           = $donor_data['city'];
			$tokens['DONATION_DONOR_STATE']          = $donor_data['state'];
			$tokens['DONATION_DONOR_POSTCODE']       = $donor_data['postcode'];
			$tokens['DONATION_DONOR_COUNTRY_CODE']   = $donor_data['country'];
			$tokens['DONATION_DONOR_COUNTRY']        = $this->get_full_country_name_from_code( $donor_data['country'] );
		}

		// Campaign data.
		$campaign = $this->get_donation_campaign( $donation );
		// Bail invalid campaign.
		if ( ! $campaign ) {
			return $tokens;
		}
		$campaign_id = $campaign->get_campaign_id();

		$tokens['CAMPAIGN_ID']                  = $campaign_id;
		$tokens['CAMPAIGN_TITLE']               = $campaign->post_title;
		$tokens['CAMPAIGN_LINK']                = get_permalink( $campaign_id );
		$tokens['CAMPAIGN_TAGS']                = charitable_get_campaign_taxonomy_terms_list( $campaign, 'tags' );
		$tokens['CAMPAIGN_CATEGORIES']          = charitable_get_campaign_taxonomy_terms_list( $campaign, 'campaign_category' );
		$tokens['CAMPAIGN_THUMB_URL']           = get_the_post_thumbnail_url( $campaign_id, 'full' );
		$tokens['CAMPAIGN_THUMB_ID']            = get_post_thumbnail_id( $campaign_id );
		$tokens['CAMPAIGN_END_DATE']            = $campaign->get_end_date();
		$tokens['CAMPAIGN_GOAL']                = charitable_format_money( $campaign->get_goal() );
		$tokens['CAMPAIGN_CREATOR_ID']          = $campaign->get_campaign_creator();
		$tokens['CAMPAIGN_CREATOR_NAME']        = $campaign->get_campaign_creator_name();
		$tokens['CAMPAIGN_CREATOR_EMAIL']       = $campaign->get_campaign_creator_email();
		$tokens['CAMPAIGN_MIN_DONATION_AMOUNT'] = charitable_format_money( charitable_get_minimum_donation_amount( $campaign_id ) );

		return $tokens;
	}

	/**
	 * Get Full Country Name.
	 *
	 * @param string $country_code - Country Code.
	 *
	 * @return string - Country Name if found else Country Code.
	 */
	public function get_full_country_name_from_code( $country_code ) {
		if ( empty( $country_code ) ) {
			return $country_code;
		}
		$countries = charitable_get_location_helper()->get_countries();
		if ( ! empty( $countries ) ) {
			if ( array_key_exists( $country_code, $countries ) ) {
				return $countries[ $country_code ];
			}
		}
		return $country_code;
	}

}
