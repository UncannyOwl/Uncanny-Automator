<?php

namespace Uncanny_Automator\Integrations\Charitable;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Charitable_Helpers
 *
 * @package Uncanny_Automator
 */
class Charitable_Helpers extends Abstract_Helpers {

	/**
	 * Charitable_Helpers constructor.
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
				'text'  => esc_html_x( 'Any campaign', 'Charitable', 'uncanny-automator' ),
				'value' => -1,
			);

			foreach ( $campaigns as $campaign ) {
				$options[] = array(
					'text'  => esc_html( $campaign->post_title ),
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
			'label'       => esc_html_x( 'Campaign', 'Charitable', 'uncanny-automator' ),
			'required'    => true,
			'remote_data' => $this->remote_data_load_config( 'campaigns' ),
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
		$amount_condition['label'] = esc_html_x( 'Condition', 'Charitable', 'uncanny-automator' );
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
				'label'       => esc_attr_x( 'Amount', 'Charitable', 'uncanny-automator' ),
				'placeholder' => sprintf(
					/* translators: 1: Default amount */
					esc_attr_x( 'Example: %d', 'Charitable', 'uncanny-automator' ),
					$default_amount
				),
				'default'     => $default_amount,
				'min_number'  => 1,
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
				'tokenName' => esc_html_x( 'Campaign title', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_LINK',
				'tokenName' => esc_html_x( 'Campaign link', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'CAMPAIGN_ID',
				'tokenName' => esc_html_x( 'Campaign ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_TAGS',
				'tokenName' => esc_html_x( 'Campaign tags', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CATEGORIES',
				'tokenName' => esc_html_x( 'Campaign categories', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_THUMB_URL',
				'tokenName' => esc_html_x( 'Featured image URL', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'CAMPAIGN_THUMB_ID',
				'tokenName' => esc_html_x( 'Featured image ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_END_DATE',
				'tokenName' => esc_html_x( 'Campaign end date', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_MIN_DONATION_AMOUNT',
				'tokenName' => esc_html_x( 'Campaign minimum donation amount', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_GOAL',
				'tokenName' => esc_html_x( 'Campaign goal', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_ID',
				'tokenName' => esc_html_x( 'Campaign creator ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_NAME',
				'tokenName' => esc_html_x( 'Campaign creator name', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_CREATOR_EMAIL',
				'tokenName' => esc_html_x( 'Campaign creator email', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'email',
			),

			// Donation tokens.
			array(
				'tokenId'   => 'DONATION_AMOUNT_DONATED',
				'tokenName' => esc_html_x( 'Amount donated', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_ID',
				'tokenName' => esc_html_x( 'Donation ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DONATION_TITLE',
				'tokenName' => esc_html_x( 'Donation title', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_STATUS',
				'tokenName' => esc_html_x( 'Donation status', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ID',
				'tokenName' => esc_html_x( 'Donation donor ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_NAME',
				'tokenName' => esc_html_x( 'Donor name', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_EMAIL',
				'tokenName' => esc_html_x( 'Donor email', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS',
				'tokenName' => esc_html_x( 'Donor formatted address', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS_LINE_1',
				'tokenName' => esc_html_x( 'Donor address line 1', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_ADDRESS_LINE_2',
				'tokenName' => esc_html_x( 'Donor address line 2', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_CITY',
				'tokenName' => esc_html_x( 'Donor city', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_STATE',
				'tokenName' => esc_html_x( 'Donor state', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_POSTCODE',
				'tokenName' => esc_html_x( 'Donor postcode', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_COUNTRY',
				'tokenName' => esc_html_x( 'Donor country', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_COUNTRY_CODE',
				'tokenName' => esc_html_x( 'Donor country code', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_DONOR_PHONE',
				'tokenName' => esc_html_x( 'Donor phone number', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_KEY',
				'tokenName' => esc_html_x( 'Donation key', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONATION_TRANSACTION_ID',
				'tokenName' => esc_html_x( 'Gateway transaction ID', 'Charitable', 'uncanny-automator' ),
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
	 * Get Donation Status Options (for dropdowns).
	 *
	 * @return array
	 */
	public function get_donation_status_options() {
		return array(
			array(
				'text'  => esc_html_x( 'Any status', 'Charitable', 'uncanny-automator' ),
				'value' => '-1',
			),
			array(
				'text'  => esc_html_x( 'Paid', 'Charitable', 'uncanny-automator' ),
				'value' => 'charitable-completed',
			),
			array(
				'text'  => esc_html_x( 'Pending', 'Charitable', 'uncanny-automator' ),
				'value' => 'charitable-pending',
			),
			array(
				'text'  => esc_html_x( 'Failed', 'Charitable', 'uncanny-automator' ),
				'value' => 'charitable-failed',
			),
			array(
				'text'  => esc_html_x( 'Cancelled', 'Charitable', 'uncanny-automator' ),
				'value' => 'charitable-cancelled',
			),
			array(
				'text'  => esc_html_x( 'Refunded', 'Charitable', 'uncanny-automator' ),
				'value' => 'charitable-refunded',
			),
		);
	}

	/**
	 * Active payment gateway options for trigger dropdowns. Mirrors
	 * Charitable_Gateways::get_gateway_choices() (id => label) with an
	 * "Any gateway" sentinel prepended for trigger filtering.
	 *
	 * @return array
	 */
	public function get_active_gateway_options() {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any gateway', 'Charitable', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		if ( ! function_exists( 'charitable_get_helper' ) ) {
			return $options;
		}

		$gateways_helper = charitable_get_helper( 'gateways' );
		if ( ! $gateways_helper || ! method_exists( $gateways_helper, 'get_gateway_choices' ) ) {
			return $options;
		}

		foreach ( (array) $gateways_helper->get_gateway_choices() as $id => $label ) {
			$options[] = array(
				'text'  => (string) $label,
				'value' => (string) $id,
			);
		}

		return $options;
	}

	/**
	 * Donation status select field (without "Any" — for actions).
	 *
	 * @return array
	 */
	public function get_donation_status_options_no_any() {
		return array_values(
			array_filter(
				$this->get_donation_status_options(),
				function ( $opt ) {
					return '-1' !== (string) $opt['value'];
				}
			)
		);
	}

	/**
	 * Get Donor Tokens Configuration.
	 *
	 * @return array
	 */
	public function get_donor_tokens_config() {
		return array(
			array(
				'tokenId'   => 'DONOR_ID',
				'tokenName' => esc_html_x( 'Donor ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DONOR_FIRST_NAME',
				'tokenName' => esc_html_x( 'Donor first name', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONOR_LAST_NAME',
				'tokenName' => esc_html_x( 'Donor last name', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DONOR_EMAIL',
				'tokenName' => esc_html_x( 'Donor email', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'DONOR_USER_ID',
				'tokenName' => esc_html_x( 'Linked WP user ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate Donor Tokens.
	 *
	 * @param int $donor_id
	 *
	 * @return array
	 */
	public function hydrate_donor_tokens( $donor_id ) {

		$defaults = wp_list_pluck( $this->get_donor_tokens_config(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$donor_id = (int) $donor_id;
		if ( empty( $donor_id ) || ! class_exists( 'Charitable_Donor' ) ) {
			return $tokens;
		}

		$donor = new \Charitable_Donor( $donor_id );

		// Free Charitable_Donor exposes fields via __get() only.
		$user = $donor->get_user();

		$tokens['DONOR_ID']         = $donor_id;
		$tokens['DONOR_FIRST_NAME'] = $donor->first_name;
		$tokens['DONOR_LAST_NAME']  = $donor->last_name;
		$tokens['DONOR_EMAIL']      = $donor->email ?? '';
		$tokens['DONOR_USER_ID']    = ( $user && isset( $user->ID ) ) ? (int) $user->ID : 0;

		return $tokens;
	}

	/**
	 * Get Campaign Tokens Configuration.
	 *
	 * @return array
	 */
	public function get_campaign_tokens_config() {
		return array(
			array(
				'tokenId'   => 'CAMPAIGN_ID',
				'tokenName' => esc_html_x( 'Campaign ID', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_TITLE',
				'tokenName' => esc_html_x( 'Campaign title', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_LINK',
				'tokenName' => esc_html_x( 'Campaign link', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'CAMPAIGN_GOAL',
				'tokenName' => esc_html_x( 'Campaign goal', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_AMOUNT_DONATED',
				'tokenName' => esc_html_x( 'Amount donated', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_DONOR_COUNT',
				'tokenName' => esc_html_x( 'Donor count', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_DONATION_COUNT',
				'tokenName' => esc_html_x( 'Donation count', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CAMPAIGN_END_DATE',
				'tokenName' => esc_html_x( 'Campaign end date', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CAMPAIGN_PERCENT_DONATED',
				'tokenName' => esc_html_x( 'Percent donated', 'Charitable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate Campaign Tokens.
	 *
	 * @param int $campaign_id
	 *
	 * @return array
	 */
	public function hydrate_campaign_tokens( $campaign_id ) {

		$defaults = wp_list_pluck( $this->get_campaign_tokens_config(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$campaign = $this->get_campaign( $campaign_id );
		if ( ! $campaign ) {
			return $tokens;
		}

		$tokens['CAMPAIGN_ID']              = $campaign->get_campaign_id();
		$tokens['CAMPAIGN_TITLE']           = $campaign->post_title;
		$tokens['CAMPAIGN_LINK']            = get_permalink( $campaign->get_campaign_id() );
		$tokens['CAMPAIGN_GOAL']            = $campaign->get_goal() ? charitable_format_money( $campaign->get_goal() ) : '';
		$tokens['CAMPAIGN_AMOUNT_DONATED']  = charitable_format_money( $campaign->get_donated_amount() );
		$tokens['CAMPAIGN_DONOR_COUNT']     = $campaign->get_donor_count();
		$tokens['CAMPAIGN_DONATION_COUNT']  = $campaign->get_donation_count();
		$tokens['CAMPAIGN_END_DATE']        = $campaign->get_end_date();
		$tokens['CAMPAIGN_PERCENT_DONATED'] = $campaign->get_percent_donated();

		return $tokens;
	}

	/**
	 * Donor select options — pulled directly from the charitable_donors table so
	 * the helper works without Charitable Pro (the donors table ships in free).
	 *
	 * @return array
	 */
	public function get_donor_options() {

		static $options = null;
		if ( null !== $options ) {
			return $options;
		}

		$options = array();
		global $wpdb;
		$table = $wpdb->prefix . 'charitable_donors';

		// Cap to keep dropdowns usable on large sites; filterable for outliers.
		$limit = (int) apply_filters( 'automator_charitable_donor_select_limit', 500 );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT donor_id, first_name, last_name, email FROM {$table} ORDER BY first_name ASC, last_name ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $rows as $row ) {
			$name = trim( $row->first_name . ' ' . $row->last_name );
			if ( '' === $name ) {
				$name = $row->email;
			}
			$options[] = array(
				'text'  => sprintf( '%s (%s) — #%d', $name, $row->email, (int) $row->donor_id ),
				'value' => (int) $row->donor_id,
			);
		}

		return $options;
	}

	/**
	 * Prefix select options. Mirrors Charitable Pro's list with a sensible fallback
	 * so the create/update donor actions still produce a useful dropdown when Pro
	 * isn't installed.
	 *
	 * @return array
	 */
	public function get_donor_prefix_options() {

		if ( class_exists( 'Charitable_Donors' ) ) {
			$list = ( new \Charitable_Donors() )->get_prefixes();
		} else {
			$list = array(
				'mr'   => esc_html_x( 'Mr.', 'Charitable', 'uncanny-automator' ),
				'mrs'  => esc_html_x( 'Mrs.', 'Charitable', 'uncanny-automator' ),
				'miss' => esc_html_x( 'Miss', 'Charitable', 'uncanny-automator' ),
				'dr'   => esc_html_x( 'Dr.', 'Charitable', 'uncanny-automator' ),
			);
		}

		return $this->assoc_to_select_options( $list );
	}

	/**
	 * Suffix select options.
	 *
	 * @return array
	 */
	public function get_donor_suffix_options() {

		if ( class_exists( 'Charitable_Donors' ) ) {
			$list = ( new \Charitable_Donors() )->get_suffixes();
		} else {
			$list = array(
				'jr'  => esc_html_x( 'Jr.', 'Charitable', 'uncanny-automator' ),
				'sr'  => esc_html_x( 'Sr.', 'Charitable', 'uncanny-automator' ),
				'ii'  => esc_html_x( 'II', 'Charitable', 'uncanny-automator' ),
				'iii' => esc_html_x( 'III', 'Charitable', 'uncanny-automator' ),
				'iv'  => esc_html_x( 'IV', 'Charitable', 'uncanny-automator' ),
			);
		}

		return $this->assoc_to_select_options( $list );
	}

	/**
	 * Primary language select options. Pulls from Charitable Pro when available;
	 * otherwise falls back to the locales WordPress has installed plus en_US.
	 *
	 * @return array
	 */
	public function get_donor_language_options() {

		if ( function_exists( 'charitable_donor_get_languages' ) ) {
			$list = charitable_donor_get_languages();
			// Drop Charitable's empty placeholder; Automator's select handles "optional".
			unset( $list[''] );
		} else {
			$installed = function_exists( 'get_available_languages' ) ? get_available_languages() : array();
			$list      = array( 'en_US' => 'English (United States)' );
			foreach ( $installed as $locale ) {
				$list[ $locale ] = $locale;
			}
		}

		return $this->assoc_to_select_options( $list );
	}

	/**
	 * Existing donor tag options for the Add donor tag action multi-select.
	 *
	 * Stored in wp_charitable_donor_terms (Charitable Pro). Returns an empty array
	 * when the table isn't present — the multi-select still works via custom values.
	 *
	 * @return array
	 */
	public function get_donor_tag_options() {

		static $options = null;
		if ( null !== $options ) {
			return $options;
		}

		$options = array();

		if ( ! function_exists( 'charitable_get_table' ) || ! charitable_get_table( 'donor_terms' ) ) {
			return $options;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'charitable_donor_terms';
		$rows  = $wpdb->get_results( "SELECT donor_term_name FROM {$table} ORDER BY donor_term_name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( (array) $rows as $row ) {
			$options[] = array(
				'text'  => $row->donor_term_name,
				'value' => $row->donor_term_name,
			);
		}

		return $options;
	}

	/**
	 * Convert an associative key=>label array into Automator's select options shape.
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	private function assoc_to_select_options( $list ) {
		$options = array();
		foreach ( (array) $list as $value => $text ) {
			$options[] = array(
				'text'  => $text,
				'value' => $value,
			);
		}
		return $options;
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

	// ============================================================
	// Remote_Data segments — wrap existing get_*_options() methods.
	// ============================================================

	/**
	 * Remote_Data segment: campaigns.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_campaigns( $request ): array {
		return $this->remote_data_success( $this->get_campaign_options() );
	}

	/**
	 * Remote_Data segment: campaigns (no "Any" sentinel — for action consumers).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_campaigns_strict( $request ): array {
		$options = array_values(
			array_filter(
				$this->get_campaign_options(),
				static function ( $opt ) {
					return -1 !== (int) $opt['value'];
				}
			)
		);
		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data segment: donors_strict.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_donors_strict( $request ): array {
		return $this->remote_data_success( $this->get_donor_options() );
	}

	/**
	 * Remote_Data segment: donor_prefixes_strict.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_donor_prefixes_strict( $request ): array {
		return $this->remote_data_success( $this->get_donor_prefix_options() );
	}

	/**
	 * Remote_Data segment: donor_suffixes_strict.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_donor_suffixes_strict( $request ): array {
		return $this->remote_data_success( $this->get_donor_suffix_options() );
	}

	/**
	 * Remote_Data segment: donor_languages_strict.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_donor_languages_strict( $request ): array {
		return $this->remote_data_success( $this->get_donor_language_options() );
	}

	/**
	 * Remote_Data segment: donor_tags_strict.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_donor_tags_strict( $request ): array {
		return $this->remote_data_success( $this->get_donor_tag_options() );
	}

	/**
	 * Remote_Data segment: active_gateways.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_active_gateways( $request ): array {
		return $this->remote_data_success( $this->get_active_gateway_options() );
	}
}

if ( ! class_exists( 'Uncanny_Automator\\Integrations\\Charitable\\CHARITABLE_HELPERS' ) ) {
	class_alias(
		Charitable_Helpers::class,
		'Uncanny_Automator\\Integrations\\Charitable\\CHARITABLE_HELPERS'
	);
}
