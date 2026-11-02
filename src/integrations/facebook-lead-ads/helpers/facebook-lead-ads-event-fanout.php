<?php
/**
 * Facebook Lead Ads Event Fanout
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App_Integrations\App_Event_Fanout;

/**
 * Fires the Lead Ads trigger for each lead the platform delivers signed
 * (signed delivery design §7.3). An event whose lead, page or form id is not
 * a Graph id (digits) is skipped and logged.
 *
 * The platform fetches each lead from Graph once and delivers it, fields
 * included, through the site's signed inbox. Per event:
 *
 *     [
 *         'event_type'   => 'leadgen',
 *         'leadgen_id'   => '…', 'page_id' => '…', 'form_id' => '…', 'created_time' => 1759831200,
 *         'change'       => [ …Meta's change value… ],
 *         'lead'         => [ 'id', 'created_time', 'field_data' => [ [ 'name', 'values' ] ], … ],
 *     ]
 *
 * When the platform could not fetch the lead, `lead` is null and
 * `lead_error` says why (`{type, code, subcode}`): `lead_access_denied`, the
 * page will not release the lead to the app (its Leads Access Manager, say);
 * `token_rejected`, Graph refused the platform's stored token for the page.
 * The trigger still fires, with `verified_lead` null: it has no verified
 * fields, so the trigger fetches the lead with this site's own token. A
 * rejected token also owes the page's adoption, so this site's token is
 * offered to the platform ({@see Facebook_Lead_Ads_Page_Adoption::token_rejected()}).
 *
 * The trigger's hook gets the change value where the legacy forward puts it
 * (`data.data`), and the lead under `verified_lead` with `source`
 * `platform_signed`, both at the top level of the argument. The legacy REST
 * route passes its POST only under `data`, so nothing a request sends can
 * reach the top level: field values are trusted from this path alone (D5).
 *
 * The change value is passed as a form POST presents it, so the same lead
 * gives the same tokens whichever way it arrived: every value a string, a
 * boolean `1` or `0`, a null left out.
 *
 * Each lead is claimed first ({@see Facebook_Lead_Ads_Lead_Dedup}); a lead
 * the legacy forward already ran is not run again.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
final class Facebook_Lead_Ads_Event_Fanout extends App_Event_Fanout {

	/**
	 * The claim both paths share.
	 *
	 * @var Facebook_Lead_Ads_Lead_Dedup
	 */
	private $dedup;

	/**
	 * Adopts a page again when its stored token was rejected.
	 *
	 * @var Facebook_Lead_Ads_Page_Adoption
	 */
	private $adoption;

	/**
	 * Constructor.
	 *
	 * @param Facebook_Lead_Ads_Lead_Dedup    $dedup    The claim both paths share.
	 * @param Facebook_Lead_Ads_Page_Adoption $adoption Adopts a page again when its stored token was rejected.
	 */
	public function __construct( Facebook_Lead_Ads_Lead_Dedup $dedup, Facebook_Lead_Ads_Page_Adoption $adoption ) {
		$this->dedup    = $dedup;
		$this->adoption = $adoption;
	}

	/**
	 * Get integration slug.
	 *
	 * @return string
	 */
	protected function get_integration_slug(): string {
		return 'facebook_lead_ads';
	}

	/**
	 * Fire the trigger for one signed lead.
	 *
	 * @param array<string, mixed> $event    One delivered event.
	 * @param array<string, mixed> $envelope The delivery (unused here).
	 *
	 * @return void
	 */
	protected function dispatch_event( array $event, array $envelope ): void {

		unset( $envelope );

		if ( 'leadgen' !== self::text( $event, 'event_type' ) ) {
			return;
		}

		if ( ! $this->has_graph_ids( $event ) ) {
			automator_log(
				'A signed Facebook lead event whose ids are not Graph ids was skipped: ' . wp_json_encode( array_intersect_key( $event, array_flip( array( 'event_id', 'leadgen_id', 'page_id', 'form_id' ) ) ) ),
				'Facebook_Lead_Ads_Event_Fanout',
				true
			);
			return;
		}

		$leadgen_id = self::text( $event, 'leadgen_id' );
		$page_id    = self::text( $event, 'page_id' );

		if ( isset( $event['lead_error'] ) && is_array( $event['lead_error'] ) && 'token_rejected' === self::text( $event['lead_error'], 'type' ) ) {
			$this->adoption->token_rejected( $page_id );
		}

		if ( ! $this->dedup->claim( $leadgen_id ) ) {
			return;
		}

		$change = isset( $event['change'] ) && is_array( $event['change'] ) ? $event['change'] : array();
		$lead   = isset( $event['lead'] ) && is_array( $event['lead'] ) ? $event['lead'] : null;

		do_action(
			'automator_facebook_lead_ads_rest_api_handle_request_after',
			array(
				'data'          => array(
					'data' => self::as_posted(
						$change + array(
							'page_id'      => $page_id,
							'form_id'      => self::text( $event, 'form_id' ),
							'leadgen_id'   => $leadgen_id,
							'created_time' => self::text( $event, 'created_time' ),
						)
					),
				),
				'verified_lead' => $lead,
				'source'        => 'platform_signed',
			)
		);
	}

	/**
	 * Whether the event's lead, page and form ids are Graph ids. The lead id
	 * reaches get_lead( int, int ) when the lead's fields are not verified.
	 *
	 * @param array<string, mixed> $event One delivered event.
	 *
	 * @return bool
	 */
	private function has_graph_ids( array $event ) {
		return Facebook_Lead_Ads_App_Helpers::is_graph_id( $event['leadgen_id'] ?? null )
			&& Facebook_Lead_Ads_App_Helpers::is_graph_id( $event['page_id'] ?? null )
			&& Facebook_Lead_Ads_App_Helpers::is_graph_id( $event['form_id'] ?? null );
	}

	/**
	 * Values as a form POST presents them: strings, booleans as `1` / `0`,
	 * nulls left out — what the legacy forward's REST request reads.
	 *
	 * @param array<string|int, mixed> $values Decoded JSON values.
	 *
	 * @return array<string|int, mixed>
	 */
	private static function as_posted( array $values ) {

		$posted = array();

		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$posted[ $key ] = self::as_posted( $value );
			} elseif ( is_bool( $value ) ) {
				$posted[ $key ] = $value ? '1' : '0';
			} elseif ( is_scalar( $value ) ) {
				$posted[ $key ] = (string) $value;
			}
		}

		return $posted;
	}

	/**
	 * @param array<string, mixed> $source A decoded object.
	 * @param string               $key    The field.
	 *
	 * @return string The field as a string, '' when absent or not scalar.
	 */
	private static function text( array $source, $key ) {
		return isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? trim( (string) $source[ $key ] ) : '';
	}
}
