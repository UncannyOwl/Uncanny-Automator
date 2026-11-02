<?php
/**
 * Abstract App Event Fanout — E.7 / E.8 base class.
 *
 * @package Uncanny_Automator\App_Integrations
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\App_Integrations;

/**
 * Per-integration event-fanout base class. Subclasses translate the
 * platform-normalized vendor_event envelope (Contract J) into per-trigger
 * hook shapes that existing trigger classes consume.
 *
 * Pipeline shape (E.6 → E.7 → E.8):
 *
 *   1. Platform delivers a vendor webhook to
 *      `automator/platform/v1/webhooks/events`.
 *   2. `Integrations_Webhook_Controller::ingest_vendor_event()` lands the row
 *      in the durable inbox by `payload_type=vendor_event` (Contract I); the
 *      drain then fires the canonical per-integration hook:
 *
 *         do_action("automator_{integration}_event_received", $data, $event_name);
 *
 *   3. The integration's `App_Event_Fanout` subclass listens on that hook
 *      (via `register()` below) and translates each enumerated event into
 *      a per-trigger action — usually a legacy 3-arg shape that
 *      `App_Trigger`-based triggers `add_action(...)` against (E.8).
 *
 * Why a base class:
 *   - Listener registration boilerplate is identical across integrations.
 *   - The depth-agnostic `events` lookup handles platform-side flattening
 *     passes uniformly, including the drain window where pre- and
 *     post-flatten payloads arrive interleaved.
 *   - Empty-payload short-circuit (cursor-only ping with no advanced
 *     entries) is identical too — and is deliberately distinguished from
 *     a missing `events` key, which is drift and gets logged.
 *
 * Subclass contract:
 *   - {@see get_integration_slug()} — lowercase platform slug (e.g. `dropbox`,
 *     `google-drive`). Used to derive the canonical hook name.
 *   - {@see dispatch_event()} — translate one event into the per-trigger
 *     shape the integration's triggers expect. Called once per enumerated
 *     event in the vendor_event payload.
 *
 * @since 8.0.0
 */
abstract class App_Event_Fanout {

	/**
	 * Lowercase platform slug — drives the listener hook name.
	 *
	 * @return string
	 */
	abstract protected function get_integration_slug(): string;

	/**
	 * Translate a single enumerated event into the per-trigger hook shape
	 * the integration's triggers consume. Implementations typically end
	 * with `do_action('automator_{integration}_webhook_received', ...)`
	 * or a more specific per-event-type hook.
	 *
	 * @param array<string, mixed> $event    One element of the platform's
	 *                                       events array.
	 * @param array<string, mixed> $envelope Full vendor_event data block —
	 *                                       useful when an event needs the
	 *                                       enclosing `external_account_id`
	 *                                       or `received_at` context.
	 *
	 * @return void
	 */
	abstract protected function dispatch_event( array $event, array $envelope ): void;

	/**
	 * Build the canonical per-integration event hook name.
	 *
	 * @return string
	 */
	final protected function event_action_name(): string {
		return sprintf( 'automator_%s_event_received', $this->get_integration_slug() );
	}

	/**
	 * Wire the listener. Idempotent — WP's add_action with the same
	 * callback + priority is silently deduplicated.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( $this->event_action_name(), array( $this, 'fanout' ), 10, 2 );
	}

	/**
	 * Iterate enumerated events from the platform-normalized envelope and
	 * dispatch one per-trigger action per entry. Skips silently when no
	 * events are present (cursor-only pings, malformed deliveries).
	 *
	 * Finds the events array however many `data` wrappers the envelope
	 * stacked. A real Dropbox fan-out currently arrives at
	 * `$data['data']['events']` (delivery envelope → Contract-J envelope,
	 * which carries `events` directly); `$data['events']` (flat) and the
	 * pre-flatten `$data['data']['data']['events']` are both still handled,
	 * the latter because queued deliveries outlive the deploy that changed
	 * the shape. It descends `data` keys rather than assuming a depth, so
	 * adding or removing an envelope layer never silently breaks fanout.
	 *
	 * A missing `events` key at every depth is treated as drift and logged
	 * (forced), NOT folded into the empty-ping short-circuit.
	 *
	 * @param mixed  $data       Platform vendor_event data block. Typed as
	 *                           mixed because do_action emitters may pass
	 *                           non-array payloads — defensive cast inside.
	 * @param string $event_name Vendor-supplied event name. Forwarded
	 *                           verbatim to `dispatch_event` via $envelope
	 *                           when relevant.
	 *
	 * @return void
	 */
	final public function fanout( $data, $event_name = '' ): void {

		unset( $event_name );

		if ( ! is_array( $data ) ) {
			return;
		}

		// Events sit under a VARIABLE number of `data` wrappers, one per
		// envelope layer the platform stacked. Current Dropbox deliveries
		// arrive at `$data['data']['events']`: the delivery envelope
		// (`{event_type, connection_id, event_id, data}`) wraps the Contract-J
		// envelope (`{integration, external_account_id, received_at, events}`).
		//
		// That used to be one level deeper — the Contract-J envelope wrapped
		// `{events}` in a redundant `data` key, which the platform has since
		// flattened. Keep descending rather than pinning the depth: outbox
		// rows committed before that deploy still carry the old shape until
		// they drain, and so can inbox rows this site retained. Tolerance here
		// is what lets the two sides deploy independently.
		$events = null;
		$cursor = $data;
		for ( $depth = 0; $depth < 4 && is_array( $cursor ); $depth++ ) {
			if ( isset( $cursor['events'] ) && is_array( $cursor['events'] ) ) {
				$events = $cursor['events'];
				break;
			}
			$cursor = isset( $cursor['data'] ) && is_array( $cursor['data'] ) ? $cursor['data'] : null;
		}

		// No `events` key at ANY depth is envelope drift, NOT an empty
		// delivery — and it is the exact signature of the silent no-op that
		// let a live Dropbox webhook be accepted, stored and drained without
		// firing a trigger. Distinguish it from the legitimate cursor-only
		// ping (`events` present but empty) and say so, so the next shape
		// change surfaces as a log line rather than a missing automation.
		if ( null === $events ) {
			// FORCED (third arg true) on purpose. Utilities::log() otherwise
			// bails unless debug mode is on, and production runs with it off —
			// which would leave the exact silent no-op this branch exists to
			// expose still silent. Drift means the envelope contract is
			// genuinely broken, so it is rare by definition and the write
			// volume is negligible; a missed automation is not.
			automator_log(
				sprintf(
					'No `events` key at any depth for %s — envelope drift. Top-level keys: %s',
					$this->get_integration_slug(),
					implode( ', ', array_keys( $data ) )
				),
				'App_Event_Fanout',
				true
			);
			return;
		}

		if ( array() === $events ) {
			return;
		}

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$this->dispatch_event( $event, $data );
		}
	}
}
