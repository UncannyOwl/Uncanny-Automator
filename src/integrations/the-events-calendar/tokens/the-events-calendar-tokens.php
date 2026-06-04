<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class The_Events_Calendar_Tokens
 *
 * Single source of truth for token definitions and hydration across the
 * The Events Calendar / Event Tickets integration. Both free and pro
 * triggers consume this class via the helper's `tokens()` accessor.
 *
 * Token IDs are sacred — they match values stored in recipe DB and the
 * EC-tokens-manifest.json contract. Do NOT rename existing IDs.
 *
 * Hydration in this class is fully self-contained.
 *
 * @package Uncanny_Automator
 */
class The_Events_Calendar_Tokens {

	/**
	 * @var The_Events_Calendar_Helpers
	 */
	private $helpers;

	/**
	 * @param The_Events_Calendar_Helpers $helpers
	 */
	public function __construct( The_Events_Calendar_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	// =========================================================================
	// Event tokens (shared by every trigger)
	// =========================================================================

	/**
	 * Standard event token set in trigger format.
	 *
	 * @param string $option_code The trigger meta — IDs are namespaced under it.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function event_tokens( $option_code ) {
		return array(
			array(
				'tokenId'   => $option_code,
				'tokenName' => esc_html_x( 'Event title', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $option_code . '_ID',
				'tokenName' => esc_html_x( 'Event ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $option_code . '_URL',
				'tokenName' => esc_html_x( 'Event URL', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => $option_code . '_THUMB_ID',
				'tokenName' => esc_html_x( 'Event featured image ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $option_code . '_THUMB_URL',
				'tokenName' => esc_html_x( 'Event featured image URL', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate the standard event token set from a `tribe_events` post ID.
	 *
	 * @param int|string $event_id
	 * @param string     $option_code
	 *
	 * @return array<string,string>
	 */
	public function hydrate_event_tokens( $event_id, $option_code ) {

		$empty = array(
			$option_code                => '',
			$option_code . '_ID'        => '',
			$option_code . '_URL'       => '',
			$option_code . '_THUMB_ID'  => '',
			$option_code . '_THUMB_URL' => '',
		);

		$event = $event_id ? get_post( absint( $event_id ) ) : null;

		if ( ! $event instanceof \WP_Post ) {
			return $empty;
		}

		return array(
			$option_code                => (string) $event->post_title,
			$option_code . '_ID'        => (string) $event->ID,
			$option_code . '_URL'       => (string) get_permalink( $event->ID ),
			$option_code . '_THUMB_ID'  => (string) get_post_thumbnail_id( $event->ID ),
			$option_code . '_THUMB_URL' => (string) get_the_post_thumbnail_url( $event->ID ),
		);
	}

	// =========================================================================
	// Attendee (holder) tokens — basic name + email always present
	// =========================================================================

	/**
	 * Basic holder/attendee token set. Token IDs intentionally match the
	 * legacy IDs (`holder_name`, `holder_email`) for recipe DB parity.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function attendee_tokens() {
		return array(
			array(
				'tokenId'   => 'holder_name',
				'tokenName' => esc_html_x( 'Attendee name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'holder_email',
				'tokenName' => esc_html_x( 'Attendee email', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);
	}

	/**
	 * Hydrate holder tokens from an attendee ID. Resolves the first
	 * matching attendee record via Tribe's `tribe_tickets_get_attendees`.
	 *
	 * @param int $attendee_id
	 *
	 * @return array<string,string>
	 */
	public function hydrate_attendee_tokens( $attendee_id ) {

		$tokens = array(
			'holder_name'  => '',
			'holder_email' => '',
		);

		$attendee_id = absint( $attendee_id );

		if ( 0 === $attendee_id || ! function_exists( 'tribe_tickets_get_attendees' ) ) {
			return $tokens;
		}

		$details = tribe_tickets_get_attendees( $attendee_id );

		if ( empty( $details ) || ! is_array( $details ) ) {
			return $tokens;
		}

		$first = reset( $details );

		if ( ! is_array( $first ) ) {
			return $tokens;
		}

		$tokens['holder_name']  = (string) ( $first['holder_name'] ?? '' );
		$tokens['holder_email'] = (string) ( $first['holder_email'] ?? '' );

		return $tokens;
	}


	// =========================================================================
	// WooCommerce billing tokens (REGISTEREDWITHWC trigger)
	// =========================================================================

	/**
	 * WooCommerce ticket order tokens. Mirrors the legacy
	 * `ET_ANON_TOKENS::ec_wc_possible_tokens()` token IDs.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function wc_billing_tokens() {
		return array(
			array(
				'tokenId'   => 'TICKET_NAME',
				'tokenName' => esc_html_x( 'Ticket name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_STATUS',
				'tokenName' => esc_html_x( 'Order status', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_FIRST_NAME',
				'tokenName' => esc_html_x( 'Billing first name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_LAST_NAME',
				'tokenName' => esc_html_x( 'Billing last name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_COMPANY',
				'tokenName' => esc_html_x( 'Billing company', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_COUNTRY',
				'tokenName' => esc_html_x( 'Billing country', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_ADDRESS_1',
				'tokenName' => esc_html_x( 'Billing address line 1', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_ADDRESS_2',
				'tokenName' => esc_html_x( 'Billing address line 2', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_CITY',
				'tokenName' => esc_html_x( 'Billing city', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_STATE',
				'tokenName' => esc_html_x( 'Billing state', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_POSTCODE',
				'tokenName' => esc_html_x( 'Billing postcode', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_PHONE',
				'tokenName' => esc_html_x( 'Billing phone', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_EMAIL',
				'tokenName' => esc_html_x( 'Billing email', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);
	}

	/**
	 * Hydrate WC billing tokens directly from a WooCommerce order. Calls
	 * `wc_get_order()` itself — no filter pipeline, no legacy parser.
	 *
	 * @param int|string $order_id
	 * @param array      $attendee_data Optional attendee_data from the
	 *                                  Event Tickets create-attendee hook,
	 *                                  used to resolve TICKET_NAME.
	 *
	 * @return array<string,string>
	 */
	public function hydrate_wc_billing_tokens( $order_id, $attendee_data = array() ) {

		$tokens = array_fill_keys( array_column( $this->wc_billing_tokens(), 'tokenId' ), '' );

		$order = ( $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( absint( $order_id ) ) : null;

		if ( $order instanceof \WC_Order ) {
			$tokens['ORDER_ID']             = (string) $order->get_id();
			$tokens['ORDER_STATUS']         = (string) $order->get_status();
			$tokens['ORDER_PAYMENT_METHOD'] = (string) $order->get_payment_method_title();
			$tokens['ORDER_TOTAL']          = (string) $order->get_total();
			$tokens['BILLING_FIRST_NAME']   = (string) $order->get_billing_first_name();
			$tokens['BILLING_LAST_NAME']    = (string) $order->get_billing_last_name();
			$tokens['BILLING_COMPANY']      = (string) $order->get_billing_company();
			$tokens['BILLING_COUNTRY']      = (string) $order->get_billing_country();
			$tokens['BILLING_ADDRESS_1']    = (string) $order->get_billing_address_1();
			$tokens['BILLING_ADDRESS_2']    = (string) $order->get_billing_address_2();
			$tokens['BILLING_CITY']         = (string) $order->get_billing_city();
			$tokens['BILLING_STATE']        = (string) $order->get_billing_state();
			$tokens['BILLING_POSTCODE']     = (string) $order->get_billing_postcode();
			$tokens['BILLING_PHONE']        = (string) $order->get_billing_phone();
			$tokens['BILLING_EMAIL']        = (string) $order->get_billing_email();
		}

		// Ticket name comes from the attendee_data. Resolve via Tribe's
		// provider API when available (correctly handles tickets whose
		// post_title is empty / generated by the provider), then fall
		// back to the post title.
		$ticket_id = absint( $attendee_data['ticket_id'] ?? 0 );
		$event_id  = absint( $attendee_data['post_id'] ?? 0 );

		if ( $ticket_id > 0 ) {

			$ticket_name = '';

			if ( $event_id > 0 && class_exists( '\Tribe__Tickets__Tickets' ) ) {
				try {
					$provider_class = (string) \Tribe__Tickets__Tickets::get_event_ticket_provider( $ticket_id );
					if ( $provider_class && class_exists( $provider_class ) ) {
						$provider = new $provider_class();
						$ticket   = $provider->get_ticket( $event_id, $ticket_id );
						if ( is_object( $ticket ) && ! empty( $ticket->name ) ) {
							$ticket_name = (string) $ticket->name;
						}
					}
				} catch ( \Throwable $e ) {
					unset( $e );
				}
			}

			if ( '' === $ticket_name ) {
				$ticket_post = get_post( $ticket_id );
				if ( $ticket_post instanceof \WP_Post ) {
					$ticket_name = (string) $ticket_post->post_title;
				}
			}

			$tokens['TICKET_NAME'] = $ticket_name;
		}

		return $tokens;
	}

	// =========================================================================
	// Organizer tokens (Wave 1A.ii will fill in body)
	// =========================================================================

	/**
	 * Organizer token set in trigger format. Stub — Wave 1A.ii fills in.
	 *
	 * @param string $prefix The trigger meta — IDs are namespaced under it.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function organizer_tokens( $prefix ) {
		return array(
			array(
				'tokenId'   => $prefix,
				'tokenName' => esc_html_x( 'Organizer name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_ID',
				'tokenName' => esc_html_x( 'Organizer ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $prefix . '_URL',
				'tokenName' => esc_html_x( 'Organizer URL', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => $prefix . '_PHONE',
				'tokenName' => esc_html_x( 'Organizer phone', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => $prefix . '_EMAIL',
				'tokenName' => esc_html_x( 'Organizer email', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => $prefix . '_WEBSITE',
				'tokenName' => esc_html_x( 'Organizer website', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate organizer tokens from a `tribe_organizer` post ID.
	 *
	 * @param int|string $organizer_id
	 * @param string     $prefix
	 *
	 * @return array<string,string>
	 */
	public function hydrate_organizer_tokens( $organizer_id, $prefix ) {

		$empty = array(
			$prefix              => '',
			$prefix . '_ID'      => '',
			$prefix . '_URL'     => '',
			$prefix . '_PHONE'   => '',
			$prefix . '_EMAIL'   => '',
			$prefix . '_WEBSITE' => '',
		);

		$organizer = $organizer_id ? get_post( absint( $organizer_id ) ) : null;

		if ( ! $organizer instanceof \WP_Post ) {
			return $empty;
		}

		return array(
			$prefix              => (string) $organizer->post_title,
			$prefix . '_ID'      => (string) $organizer->ID,
			$prefix . '_URL'     => (string) get_permalink( $organizer->ID ),
			$prefix . '_PHONE'   => (string) get_post_meta( $organizer->ID, '_OrganizerPhone', true ),
			$prefix . '_EMAIL'   => (string) get_post_meta( $organizer->ID, '_OrganizerEmail', true ),
			$prefix . '_WEBSITE' => (string) get_post_meta( $organizer->ID, '_OrganizerWebsite', true ),
		);
	}

	// =========================================================================
	// Venue tokens (Wave 1A.ii will fill in body)
	// =========================================================================

	/**
	 * Venue token set in trigger format. Stub — Wave 1A.ii fills in.
	 *
	 * @param string $prefix The trigger meta — IDs are namespaced under it.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function venue_tokens( $prefix ) {
		return array(
			array(
				'tokenId'   => $prefix,
				'tokenName' => esc_html_x( 'Venue name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_ID',
				'tokenName' => esc_html_x( 'Venue ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $prefix . '_URL',
				'tokenName' => esc_html_x( 'Venue URL', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => $prefix . '_ADDRESS',
				'tokenName' => esc_html_x( 'Venue address', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_CITY',
				'tokenName' => esc_html_x( 'Venue city', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_STATE',
				'tokenName' => esc_html_x( 'Venue state / province', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_ZIP',
				'tokenName' => esc_html_x( 'Venue ZIP', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_COUNTRY',
				'tokenName' => esc_html_x( 'Venue country', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_PHONE',
				'tokenName' => esc_html_x( 'Venue phone', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => $prefix . '_WEBSITE',
				'tokenName' => esc_html_x( 'Venue website', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate venue tokens from a `tribe_venue` post ID.
	 *
	 * TEC writes the state/province value to multiple meta keys
	 * (`_VenueStateProvince` is the canonical combined slot, with
	 * `_VenueState` and `_VenueProvince` carrying the per-locale values).
	 * We read the combined key first, then fall back.
	 *
	 * @param int|string $venue_id
	 * @param string     $prefix
	 *
	 * @return array<string,string>
	 */
	public function hydrate_venue_tokens( $venue_id, $prefix ) {

		$empty = array(
			$prefix              => '',
			$prefix . '_ID'      => '',
			$prefix . '_URL'     => '',
			$prefix . '_ADDRESS' => '',
			$prefix . '_CITY'    => '',
			$prefix . '_STATE'   => '',
			$prefix . '_ZIP'     => '',
			$prefix . '_COUNTRY' => '',
			$prefix . '_PHONE'   => '',
			$prefix . '_WEBSITE' => '',
		);

		$venue = $venue_id ? get_post( absint( $venue_id ) ) : null;

		if ( ! $venue instanceof \WP_Post ) {
			return $empty;
		}

		$state = (string) get_post_meta( $venue->ID, '_VenueStateProvince', true );
		if ( '' === $state ) {
			$state = (string) get_post_meta( $venue->ID, '_VenueState', true );
		}
		if ( '' === $state ) {
			$state = (string) get_post_meta( $venue->ID, '_VenueProvince', true );
		}

		return array(
			$prefix              => (string) $venue->post_title,
			$prefix . '_ID'      => (string) $venue->ID,
			$prefix . '_URL'     => (string) get_permalink( $venue->ID ),
			$prefix . '_ADDRESS' => (string) get_post_meta( $venue->ID, '_VenueAddress', true ),
			$prefix . '_CITY'    => (string) get_post_meta( $venue->ID, '_VenueCity', true ),
			$prefix . '_STATE'   => $state,
			$prefix . '_ZIP'     => (string) get_post_meta( $venue->ID, '_VenueZip', true ),
			$prefix . '_COUNTRY' => (string) get_post_meta( $venue->ID, '_VenueCountry', true ),
			$prefix . '_PHONE'   => (string) get_post_meta( $venue->ID, '_VenuePhone', true ),
			$prefix . '_WEBSITE' => (string) get_post_meta( $venue->ID, '_VenueURL', true ),
		);
	}

	// =========================================================================
	// Ticket tokens (Wave 2 / 1B will fill in body)
	// =========================================================================

	/**
	 * Ticket token set in trigger format. Stub — Wave 2 (1B) fills in.
	 *
	 * @param string $prefix The trigger meta — IDs are namespaced under it.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function ticket_tokens( $prefix ) {
		return array(
			array(
				'tokenId'   => $prefix,
				'tokenName' => esc_html_x( 'Ticket name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_ID',
				'tokenName' => esc_html_x( 'Ticket ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $prefix . '_PRICE',
				'tokenName' => esc_html_x( 'Ticket price', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_CAPACITY',
				'tokenName' => esc_html_x( 'Ticket capacity', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $prefix . '_STOCK',
				'tokenName' => esc_html_x( 'Ticket stock', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate ticket tokens from a ticket post ID.
	 *
	 * Uses the provider-aware `get_event_ticket_provider()` to resolve a
	 * `Tribe__Tickets__Ticket_Object` when possible (so we pick up the
	 * provider-formatted price and live stock count). Falls back to post
	 * title + `_tribe_ticket_capacity` meta when ET is unavailable.
	 *
	 * @param int|string $ticket_id
	 * @param string     $prefix
	 *
	 * @return array<string,string>
	 */
	public function hydrate_ticket_tokens( $ticket_id, $prefix ) {

		$empty = array(
			$prefix               => '',
			$prefix . '_ID'       => '',
			$prefix . '_PRICE'    => '',
			$prefix . '_CAPACITY' => '',
			$prefix . '_STOCK'    => '',
		);

		$ticket_id = absint( $ticket_id );

		if ( 0 === $ticket_id ) {
			return $empty;
		}

		$ticket_post = get_post( $ticket_id );

		if ( ! $ticket_post instanceof \WP_Post ) {
			return $empty;
		}

		$name     = (string) $ticket_post->post_title;
		$price    = '';
		$capacity = '';
		$stock    = '';

		if ( class_exists( '\Tribe__Tickets__Tickets' ) ) {

			try {
				$provider_class = (string) \Tribe__Tickets__Tickets::get_event_ticket_provider( $ticket_id );

				if ( $provider_class && class_exists( $provider_class ) ) {
					$event = function_exists( 'tribe_events_get_ticket_event' ) ? tribe_events_get_ticket_event( $ticket_id ) : null;
					$event_id = $event instanceof \WP_Post ? $event->ID : 0;

					if ( $event_id > 0 ) {
						$provider = new $provider_class();
						$ticket   = $provider->get_ticket( $event_id, $ticket_id );

						if ( is_object( $ticket ) ) {
							if ( ! empty( $ticket->name ) ) {
								$name = (string) $ticket->name;
							}
							if ( isset( $ticket->price ) ) {
								$price = (string) $ticket->price;
							}
							if ( is_callable( array( $ticket, 'stock' ) ) ) {
								$stock = (string) $ticket->stock();
							}
						}
					}
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( '' === $capacity ) {
			if ( function_exists( 'tribe_tickets_get_capacity' ) ) {
				$capacity = (string) tribe_tickets_get_capacity( $ticket_id );
			} else {
				$capacity = (string) get_post_meta( $ticket_id, '_tribe_ticket_capacity', true );
			}
		}

		// Event Tickets stores unlimited capacity as -1; surface it as a
		// human-readable label rather than a raw sentinel in the token.
		if ( '-1' === $capacity ) {
			$capacity = esc_html_x( 'Unlimited', 'The Events Calendar', 'uncanny-automator' );
		}

		return array(
			$prefix               => $name,
			$prefix . '_ID'       => (string) $ticket_id,
			$prefix . '_PRICE'    => $price,
			$prefix . '_CAPACITY' => $capacity,
			$prefix . '_STOCK'    => $stock,
		);
	}

	// =========================================================================
	// Series tokens (Wave 2 / 1C will fill in body)
	// =========================================================================

	/**
	 * Series token set in trigger format. Stub — Wave 2 (1C) fills in.
	 *
	 * @param string $prefix The trigger meta — IDs are namespaced under it.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function series_tokens( $prefix ) {
		return array(
			array(
				'tokenId'   => $prefix,
				'tokenName' => esc_html_x( 'Series title', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $prefix . '_ID',
				'tokenName' => esc_html_x( 'Series ID', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => $prefix . '_URL',
				'tokenName' => esc_html_x( 'Series URL', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => $prefix . '_EVENT_COUNT',
				'tokenName' => esc_html_x( 'Series event count', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate series tokens from a `tribe_event_series` post ID.
	 *
	 * The event count is sourced from the ECP CT1 `tec_series_relationships`
	 * table when present, falling back to `0` if the Series add-on is not
	 * installed. All lookups are guarded so non-ECP sites get an empty
	 * token map rather than a fatal.
	 *
	 * @param int|string $series_id
	 * @param string     $prefix
	 *
	 * @return array<string,string>
	 */
	public function hydrate_series_tokens( $series_id, $prefix ) {

		$empty = array(
			$prefix                  => '',
			$prefix . '_ID'          => '',
			$prefix . '_URL'         => '',
			$prefix . '_EVENT_COUNT' => '',
		);

		if ( ! post_type_exists( 'tribe_event_series' ) ) {
			return $empty;
		}

		$series = $series_id ? get_post( absint( $series_id ) ) : null;

		if ( ! $series instanceof \WP_Post ) {
			return $empty;
		}

		$event_count = 0;

		global $wpdb;
		$table = $wpdb->prefix . 'tec_series_relationships';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists === $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$event_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE series_post_id = %d", $series->ID ) );
		}

		return array(
			$prefix                  => (string) $series->post_title,
			$prefix . '_ID'          => (string) $series->ID,
			$prefix . '_URL'         => (string) get_permalink( $series->ID ),
			$prefix . '_EVENT_COUNT' => (string) $event_count,
		);
	}

	// =========================================================================
	// Dynamic per-ticket attendee meta tokens (Event Tickets Plus IAC fields)
	// =========================================================================

	/**
	 * Per-ticket attendee meta token definitions. Built dynamically from
	 * `\Tribe__Tickets_Plus__Meta::get_attendee_meta_fields()` per ticket
	 * for the recipe-selected event.
	 *
	 * Token IDs follow the legacy patterns:
	 *   - `{ticket_id}|tribe-tickets-plus-iac-name`
	 *   - `{ticket_id}|tribe-tickets-plus-iac-email`
	 *   - `{ticket_id}|{field_slug}`
	 *
	 * Returns the basic holder tokens only when:
	 *   - No event selected (or "Any event" sentinel)
	 *   - Event Tickets Plus is not installed
	 *   - The selected event has no tickets
	 *
	 * Lives in the free tokens class (guarded on ET+) so both free and pro
	 * attendee triggers — e.g. the free "An attendee is checked in" — can
	 * expose the additional registration fields. Degrades to basic on
	 * non-ET+ sites.
	 *
	 * @param int|string $event_id            Recipe-selected event ID. `-1` returns basic only.
	 * @param int|string $selected_ticket_id  Optional ticket pin from a child field.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function dynamic_attendee_meta_tokens( $event_id, $selected_ticket_id = '' ) {

		$basic = $this->attendee_tokens();

		if ( empty( $event_id ) || '-1' === (string) $event_id ) {
			return $basic;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! method_exists( '\Tribe__Tickets__Tickets', 'get_all_event_tickets' ) ) {
			return $basic;
		}

		if ( ! class_exists( '\Tribe__Tickets_Plus__Meta' ) || ! method_exists( '\Tribe__Tickets_Plus__Meta', 'get_attendee_meta_fields' ) ) {
			return $basic;
		}

		$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( absint( $event_id ) );

		if ( empty( $tickets ) ) {
			return $basic;
		}

		$tokens     = $basic;
		$pin_ticket = ! empty( $selected_ticket_id ) && '-1' !== (string) $selected_ticket_id
			? absint( $selected_ticket_id )
			: 0;

		foreach ( $tickets as $ticket ) {

			$ticket_id = (int) $ticket->ID;

			if ( 0 !== $pin_ticket && $pin_ticket !== $ticket_id ) {
				continue;
			}

			$ticket_name = ! empty( $ticket->name ) ? $ticket->name : sprintf( 'ID: %d', $ticket_id );

			$tokens[] = array(
				'tokenId'   => $ticket_id . '|tribe-tickets-plus-iac-name',
				'tokenName' => $ticket_name . ' — ' . esc_html_x( 'Attendee name', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
			$tokens[] = array(
				'tokenId'   => $ticket_id . '|tribe-tickets-plus-iac-email',
				'tokenName' => $ticket_name . ' — ' . esc_html_x( 'Attendee email', 'The Events Calendar', 'uncanny-automator' ),
				'tokenType' => 'email',
			);

			$meta = \Tribe__Tickets_Plus__Meta::get_attendee_meta_fields( $ticket_id );

			if ( empty( $meta ) ) {
				continue;
			}

			foreach ( $meta as $field ) {

				$type = 'text';
				if ( isset( $field['type'] ) ) {
					if ( 'email' === $field['type'] ) {
						$type = 'email';
					} elseif ( 'number' === $field['type'] ) {
						$type = 'int';
					}
				}

				$tokens[] = array(
					'tokenId'   => $ticket_id . '|' . ( $field['slug'] ?? '' ),
					'tokenName' => $ticket_name . ' — ' . ( $field['label'] ?? '' ),
					'tokenType' => $type,
				);
			}
		}

		return $tokens;
	}

	/**
	 * Hydrate dynamic per-ticket attendee meta from an attendee post ID.
	 *
	 * Reads the Plus attendee meta map directly from post meta and returns
	 * a complete key=>value map. Always populates the basic `holder_*` keys.
	 *
	 * @param int $attendee_id
	 *
	 * @return array<string,string>
	 */
	public function hydrate_dynamic_attendee_meta_tokens( $attendee_id ) {

		$tokens      = $this->hydrate_attendee_tokens( $attendee_id );
		$attendee_id = absint( $attendee_id );

		if ( 0 === $attendee_id ) {
			return $tokens;
		}

		if ( ! class_exists( '\Tribe__Tickets_Plus__Meta' ) ) {
			return $tokens;
		}

		$attendee_meta = get_post_meta( $attendee_id, \Tribe__Tickets_Plus__Meta::META_KEY, true );

		if ( empty( $attendee_meta ) || ! is_array( $attendee_meta ) ) {
			return $tokens;
		}

		$ticket_id = EC_Attendee_Resolver::ticket_id_from_attendee( $attendee_id );

		if ( 0 === $ticket_id ) {
			return $tokens;
		}

		$prefix = $ticket_id . '|';

		// Holder name/email are also exposed under the legacy IAC keys.
		$tokens[ $prefix . 'tribe-tickets-plus-iac-name' ]  = $tokens['holder_name'];
		$tokens[ $prefix . 'tribe-tickets-plus-iac-email' ] = $tokens['holder_email'];

		// Direct slug → value, plus checkbox-style `slug_HASH` collapse to
		// match the legacy `parse_ec_tokens` regex behavior.
		$checkbox_groups = array();

		foreach ( $attendee_meta as $key => $value ) {

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
			}

			$tokens[ $prefix . $key ] = (string) $value;

			if ( preg_match( '/^([\w-]+)_[a-f0-9]+$/', $key, $matches ) ) {
				$checkbox_groups[ $matches[1] ][] = (string) $value;
			}
		}

		foreach ( $checkbox_groups as $base_slug => $values ) {
			$tokens[ $prefix . $base_slug ] = implode( ', ', array_filter( $values ) );
		}

		return $tokens;
	}
}
