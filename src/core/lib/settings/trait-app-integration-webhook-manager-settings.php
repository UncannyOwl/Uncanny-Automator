<?php
namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait App_Integration_Webhook_Manager_Settings
 *
 * Opt-in trait for App_Integration_Settings classes whose webhook concrete
 * uses the App_Webhook_Manager trait — i.e., integrations that manage
 * per-resource webhook subscriptions through the settings UI.
 *
 * Composed alongside `extends App_Integration_Settings`:
 *
 *   class Asana_Settings extends App_Integration_Settings {
 *       use Premium_Integration_Webhook_Settings;
 *       use App_Integration_Webhook_Manager_Settings;
 *
 *       public function get_webhook_manager_resource_label() {
 *           return esc_html_x( 'Project', 'Asana', 'uncanny-automator' );
 *       }
 *
 *       public function get_webhook_manager_grouping() {
 *           return array( 'field' => 'workspace_id', 'label_field' => 'workspace_name' );
 *       }
 *   }
 *
 * Owns:
 *   - Manager UI rendering (output_webhook_manager + table + event switches)
 *   - REST handlers (handle_create_webhook, handle_update_webhook, handle_delete_webhook)
 *   - Disconnect cleanup wiring
 *
 * Required host contract (composed via App_Integration_Settings):
 *   - $helpers (provides get_event_options(): array of {value, text})
 *   - $webhooks (App_Webhook_Manager-composed App_Webhooks subclass)
 *   - get_id() (App_Integration_Settings)
 *   - get_array_data() (Premium_Integration_Rest_Processing, protected)
 *   - maybe_get_posted_row_id() (App_Integration_Settings_Setup)
 *   - output_panel_subtitle, output_subtle_panel_paragraph, output_settings_table
 *     (Premium_Integration_Templating_Helpers)
 *   - alert_html, get_success_alert, get_error_alert (Premium_Integration_Alerts)
 *   - $base_settings_object->register_option() (Settings_Options)
 *
 * @package Uncanny_Automator\Settings
 *
 * @property \Uncanny_Automator\App_Integrations\App_Webhooks $webhooks
 * @property \Uncanny_Automator\App_Integrations\App_Helpers $helpers
 */
trait App_Integration_Webhook_Manager_Settings {

	/**
	 * Cached event-data payload — populated on first call to get_event_data().
	 * `null` distinguishes "not yet cached" from "cached as empty array".
	 *
	 * @var array|null
	 */
	private $event_data_cache = null;

	/**
	 * Get the human-readable label for a single managed resource (e.g. "Project", "Repository").
	 *
	 * @return string
	 */
	abstract public function get_webhook_manager_resource_label();

	/**
	 * Get the plural form of the resource label (e.g. "Projects", "Repositories").
	 *
	 * The default appends `s` to the singular label, which is correct for the
	 * common case ("Project" → "Projects"). Concretes whose label doesn't
	 * pluralize that way override this — e.g. GitHub's "Repository" →
	 * "Repositories" — to keep the UI copy grammatical.
	 *
	 * @return string
	 */
	public function get_webhook_manager_resource_label_plural() {
		return $this->get_webhook_manager_resource_label() . 's';
	}

	/**
	 * Optional grouping config for the manager table. Concretes return
	 * `array( 'field' => 'workspace_id', 'label_field' => 'workspace_name' )`
	 * when their resources should be grouped under a header row, or null otherwise.
	 *
	 * @return array|null
	 */
	public function get_webhook_manager_grouping() {
		return null;
	}

	/**
	 * Output the intro copy that sits above the manager table.
	 *
	 * @return void
	 */
	public function output_webhook_manager_intro() {
		$this->output_panel_subtitle(
			sprintf(
				// translators: %s: resource label plural
				esc_html_x( '%s webhooks', 'Integration settings', 'uncanny-automator' ),
				esc_html( $this->get_webhook_manager_resource_label() )
			)
		);
		$this->output_subtle_panel_paragraph(
			sprintf(
				// translators: %s: resource label
				esc_html_x( 'To use triggers in your recipes, authorize webhooks for each %s with the events you want to listen for.', 'Integration settings', 'uncanny-automator' ),
				esc_html( strtolower( $this->get_webhook_manager_resource_label() ) )
			)
		);
	}

	/**
	 * Output the manager table — intro copy plus per-resource rows.
	 *
	 * @return void
	 */
	public function output_webhook_manager() {
		$this->output_webhook_manager_intro();
		$this->maybe_refresh_webhook_manager();

		$config = $this->webhooks->get_webhook_manager_config();
		if ( empty( $config ) ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => sprintf(
						// translators: %s: resource label plural
						esc_html_x( 'No %s found.', 'Integration settings', 'uncanny-automator' ),
						esc_html( strtolower( $this->get_webhook_manager_resource_label_plural() ) )
					),
					'class'   => 'uap-spacing-bottom',
				)
			);
			$this->output_webhook_manager_status();
			return;
		}

		$this->output_settings_table(
			$this->get_webhook_manager_columns(),
			$this->get_webhook_manager_rows( $config )
		);
		$this->output_webhook_manager_status();
	}

	/**
	 * Auto-refresh the manager config when the staleness marker is older than
	 * a day (or has never been stamped). Runs only from `output_webhook_manager()`,
	 * which is only invoked from the settings page render path — never from
	 * trigger/action processing or webhook receipt.
	 *
	 * Failures are logged and swallowed; the page continues to render the
	 * existing stored config so a transient API hiccup doesn't break the UI.
	 *
	 * @return void
	 */
	protected function maybe_refresh_webhook_manager() {
		$marker = $this->webhooks->get_webhook_manager_marker( DAY_IN_SECONDS );

		// Marker is fresh — nothing to do.
		if ( 0 !== $marker['timestamp'] && ! $marker['refresh'] ) {
			return;
		}

		try {
			$this->webhooks->refresh_webhook_manager_config();
		} catch ( Exception $e ) {
			automator_log(
				'Webhook manager auto-refresh failed: ' . $e->getMessage(),
				'App_Integration_Webhook_Manager_Settings auto-refresh',
				AUTOMATOR_DEBUG_MODE,
				$this->get_id()
			);
		}
	}

	/**
	 * Render the manual Refresh alert beneath the table.
	 *
	 * Built via `alert_html()` (and the action-button leaf inside it) so the
	 * call site stays array-driven — no inline PHP-in-HTML interpolation.
	 *
	 * @return void
	 */
	protected function output_webhook_manager_status() {
		$plural_label = strtolower( $this->get_webhook_manager_resource_label_plural() );

		$this->alert_html(
			array(
				'heading' => sprintf(
					// translators: %s: resource label plural (e.g. projects, repositories)
					esc_html_x( 'Need to update your %s list?', 'Integration settings', 'uncanny-automator' ),
					esc_html( $plural_label )
				),
				'content' => sprintf(
					// translators: %s: resource label plural
					esc_html_x( "If you've added or removed %s in your account, click the button below to pull the latest list.", 'Integration settings', 'uncanny-automator' ),
					esc_html( $plural_label )
				),
				'class'   => 'uap-spacing-bottom',
				'button'  => array(
					'action' => 'refresh_webhook_manager',
					'label'  => sprintf(
						// translators: %s: resource label plural
						esc_html_x( 'Refresh %s', 'Integration settings', 'uncanny-automator' ),
						esc_html( $plural_label )
					),
					'args'   => array(
						'color' => 'secondary',
						'icon'  => 'rotate',
					),
				),
			)
		);
	}

	/**
	 * Get the columns for the manager table.
	 *
	 * @return array
	 */
	public function get_webhook_manager_columns() {
		return array(
			array(
				'key'    => 'resource',
				'header' => $this->get_webhook_manager_resource_label(),
			),
			array(
				'key'    => 'status',
				'header' => esc_html_x( 'Status', 'Integration settings', 'uncanny-automator' ),
			),
			array(
				'key'    => 'action',
				'header' => esc_html_x( 'Action', 'Integration settings', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build the row payload for the manager table from the canonical config.
	 *
	 * @param array $config Canonical-shape manager config keyed by resource ID.
	 *
	 * @return array
	 */
	public function get_webhook_manager_rows( $config ) {
		$grouping    = $this->get_webhook_manager_grouping();
		$seen_groups = array();
		$rows        = array();

		foreach ( $config as $entry ) {
			$show_group_header = false;
			if ( ! empty( $grouping ) ) {
				$group_value = $entry['meta'][ $grouping['field'] ] ?? null;
				if ( null !== $group_value && ! in_array( $group_value, $seen_groups, true ) ) {
					$show_group_header = true;
					$seen_groups[]     = $group_value;
				}
			}
			$rows[] = $this->get_webhook_manager_row( $entry, $show_group_header );
		}

		return $rows;
	}

	/**
	 * Build a single row entry for the manager table.
	 *
	 * @param array $entry             Canonical-shape entry.
	 * @param bool  $show_group_header Whether to prepend a group header line to the resource cell.
	 *
	 * @return array
	 */
	public function get_webhook_manager_row( $entry, $show_group_header = false ) {
		$is_connected = ! empty( $entry['hook_id'] );

		$base_button = array(
			'name'               => 'automator_action',
			'row-submission'     => true,
			'size'               => 'small',
			'type'               => 'submit',
			'needs-confirmation' => true,
			'integration-id'     => $this->get_id(),
		);

		$actions = $is_connected
			? array( $this->build_edit_action( $base_button, $entry ), $this->build_delete_action( $base_button, $entry ) )
			: array( $this->build_connect_action( $base_button, $entry ) );

		$resource_text = $this->build_resource_text_column( $entry, $show_group_header );

		return array(
			'id'      => $entry['id'],
			'columns' => array(
				'resource' => array( 'options' => $resource_text ),
				'status'   => array(
					'options' => array(
						array(
							'type' => 'text',
							'data' => $is_connected
								? esc_html_x( 'Connected', 'Integration settings', 'uncanny-automator' )
								: esc_html_x( 'Not connected', 'Integration settings', 'uncanny-automator' ),
						),
					),
				),
				'action'   => array(
					'options' => $actions,
					'layout'  => 'horizontal',
				),
			),
		);
	}

	/**
	 * Build the resource-cell payload, optionally with a group header line.
	 *
	 * @param array $entry             Canonical-shape entry.
	 * @param bool  $show_group_header Whether to prepend a group header line.
	 *
	 * @return array
	 */
	protected function build_resource_text_column( $entry, $show_group_header ) {
		$grouping = $this->get_webhook_manager_grouping();

		if ( $show_group_header && ! empty( $grouping ) ) {
			$label = $entry['meta'][ $grouping['label_field'] ] ?? '';
			return array(
				array(
					'type' => 'text',
					'data' => '**' . $label . ':**',
				),
				array(
					'type' => 'text',
					'data' => $entry['name'],
				),
			);
		}

		return array(
			array(
				'type' => 'text',
				'data' => $entry['name'],
			),
		);
	}

	/**
	 * Build the connect (create-webhook) action button payload.
	 *
	 * @param array $base_button Shared button attributes.
	 * @param array $entry       Canonical-shape entry.
	 *
	 * @return array
	 */
	protected function build_connect_action( $base_button, $entry ) {
		$label = $this->get_webhook_manager_resource_label();
		return array(
			'type' => 'button',
			'data' => $base_button + array(
				'value'                     => 'create_webhook',
				'label'                     => esc_html_x( 'Connect', 'Integration settings', 'uncanny-automator' ),
				'icon'                      => array(
					'id'   => 'bolt',
					'size' => 'xsmall',
				),
				'confirmation-heading'      => esc_html_x( 'Connect Webhook', 'Integration settings', 'uncanny-automator' ),
				'confirmation-content'      => sprintf(
					// translators: %1$s: resource label, %2$s: resource name
					esc_html_x( 'Select the events from the %1$s %2$s you want to listen for:', 'Integration settings', 'uncanny-automator' ),
					esc_html( strtolower( $label ) ),
					esc_html( $entry['name'] )
				),
				'confirmation-fields'       => wp_json_encode( $this->get_event_switches() ),
				'confirmation-button-label' => esc_html_x( 'Connect webhook', 'Integration settings', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build the edit (update-webhook) action button payload.
	 *
	 * @param array $base_button Shared button attributes.
	 * @param array $entry       Canonical-shape entry.
	 *
	 * @return array
	 */
	protected function build_edit_action( $base_button, $entry ) {
		$label = $this->get_webhook_manager_resource_label();
		return array(
			'type' => 'button',
			'data' => $base_button + array(
				'value'                     => 'update_webhook',
				'label'                     => esc_html_x( 'Edit', 'Integration settings', 'uncanny-automator' ),
				'icon'                      => array(
					'id'   => 'pencil',
					'size' => 'xsmall',
				),
				'confirmation-heading'      => esc_html_x( 'Edit webhook events', 'Integration settings', 'uncanny-automator' ),
				'confirmation-content'      => sprintf(
					// translators: %1$s: resource label, %2$s: resource name
					esc_html_x( 'Select the events from the %1$s %2$s you want to listen for:', 'Integration settings', 'uncanny-automator' ),
					esc_html( strtolower( $label ) ),
					esc_html( $entry['name'] )
				),
				'confirmation-fields'       => wp_json_encode( $this->get_event_switches( $entry['events'] ) ),
				'confirmation-button-label' => esc_html_x( 'Edit webhook', 'Integration settings', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build the delete (remove-webhook) action button payload.
	 *
	 * @param array $base_button Shared button attributes.
	 * @param array $entry       Canonical-shape entry.
	 *
	 * @return array
	 */
	protected function build_delete_action( $base_button, $entry ) {
		$label = $this->get_webhook_manager_resource_label();
		return array(
			'type' => 'button',
			'data' => $base_button + array(
				'value'                     => 'delete_webhook',
				'label'                     => esc_html_x( 'Remove', 'Integration settings', 'uncanny-automator' ),
				'icon'                      => array(
					'id'   => 'trash',
					'size' => 'xsmall',
				),
				'color'                     => 'danger',
				'confirmation-heading'      => esc_html_x( 'Remove Webhook', 'Integration settings', 'uncanny-automator' ),
				'confirmation-content'      => sprintf(
					// translators: %1$s: resource label, %2$s: resource name
					esc_html_x( 'Are you sure you want to remove the webhook for the %1$s %2$s?', 'Integration settings', 'uncanny-automator' ),
					esc_html( strtolower( $label ) ),
					esc_html( $entry['name'] )
				),
				'confirmation-button-label' => esc_html_x( 'Remove webhook', 'Integration settings', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build the event-switch component tree shown in the create/edit confirmation dialog.
	 *
	 * @param array|null $current_events Currently selected events for the resource being edited.
	 *
	 * @return array
	 */
	protected function get_event_switches( $current_events = null ) {
		$event_data       = $this->get_event_data();
		$event_options    = $event_data['options'];
		$all_event_values = $event_data['values'];

		$current_events = $current_events ?? array();
		$has_all_events = empty( $current_events ) || empty( array_diff( $all_event_values, $current_events ) );

		$all_events_switch = array(
			'type'               => 'switch',
			'name'               => 'all-events',
			'checked'            => $has_all_events,
			'label-on'           => esc_html_x( 'All events', 'Integration settings', 'uncanny-automator' ),
			'label-off'          => esc_html_x( 'All events', 'Integration settings', 'uncanny-automator' ),
			'label-placement'    => 'right',
			'data-state-control' => 'all-events',
		);

		$individual_switches = array();
		foreach ( $event_options as $event ) {
			$individual_switches[] = array(
				'type'            => 'switch',
				'name'            => 'events[' . $event['value'] . ']',
				'checked'         => in_array( $event['value'], $current_events, true ),
				'value'           => $event['value'],
				'label-on'        => $event['text'],
				'label-off'       => $event['text'],
				'label-placement' => 'right',
			);
		}

		return array_merge(
			array( $all_events_switch ),
			array(
				array(
					'type'       => 'section',
					'id'         => $this->get_id() . '-events-section',
					'state'      => 'all-events',
					'show-when'  => '0',
					'components' => $individual_switches,
					'class'      => 'uap-bordered-section',
				),
			)
		);
	}

	/**
	 * Get the event options + values pulled from the helpers, cached for the lifetime of this instance.
	 *
	 * @return array{options: array, values: array}
	 */
	protected function get_event_data() {
		if ( null !== $this->event_data_cache ) {
			return $this->event_data_cache;
		}

		$event_options          = $this->helpers->get_event_options();
		$this->event_data_cache = array(
			'options' => $event_options,
			'values'  => wp_list_pluck( $event_options, 'value' ),
		);
		return $this->event_data_cache;
	}

	/**
	 * REST handler — create a webhook subscription for the posted resource.
	 *
	 * @param array $response Current response payload.
	 * @param array $data     Posted data from the REST settings table component.
	 *
	 * @return array
	 */
	public function handle_create_webhook( $response, $data ) {
		// TODO: confirm posted key shape (`row-id` vs `row_id`) when wiring real Asana/GitHub settings (Tasks 9/17).
		$resource_id = $this->maybe_get_posted_row_id( $data );
		$events      = $this->get_webhook_data_events( $data );

		try {
			$resource = $this->webhooks->create_webhook( $resource_id, $events );
		} catch ( Exception $e ) {
			$response['alert'] = $this->get_error_alert( $e->getMessage() );
			return $response;
		}

		$response['alert'] = $this->get_success_alert(
			sprintf(
				// translators: %s: resource name
				esc_html_x( 'Webhook created successfully for %s.', 'Integration settings', 'uncanny-automator' ),
				esc_html( $resource['name'] )
			)
		);

		return $this->refresh_manager_response( $response );
	}

	/**
	 * REST handler — update a webhook subscription's events for the posted resource.
	 *
	 * @param array $response Current response payload.
	 * @param array $data     Posted data from the REST settings table component.
	 *
	 * @return array
	 */
	public function handle_update_webhook( $response, $data ) {
		// TODO: confirm posted key shape (`row-id` vs `row_id`) when wiring real Asana/GitHub settings (Tasks 9/17).
		$resource_id = $this->maybe_get_posted_row_id( $data );
		$events      = $this->get_webhook_data_events( $data );

		try {
			$resource = $this->webhooks->update_webhook( $resource_id, $events );
		} catch ( Exception $e ) {
			$response['alert'] = $this->get_error_alert( $e->getMessage() );
			return $response;
		}

		$response['alert'] = $this->get_success_alert(
			sprintf(
				// translators: %s: resource name
				esc_html_x( 'Webhook updated successfully for %s.', 'Integration settings', 'uncanny-automator' ),
				esc_html( $resource['name'] )
			)
		);

		return $this->refresh_manager_response( $response );
	}

	/**
	 * REST handler — delete a webhook subscription for the posted resource.
	 *
	 * @param array $response Current response payload.
	 * @param array $data     Posted data from the REST settings table component.
	 *
	 * @return array
	 */
	public function handle_delete_webhook( $response, $data ) {
		// TODO: confirm posted key shape (`row-id` vs `row_id`) when wiring real Asana/GitHub settings (Tasks 9/17).
		$resource_id = $this->maybe_get_posted_row_id( $data );

		try {
			$resource = $this->webhooks->delete_webhook( $resource_id );
		} catch ( Exception $e ) {
			$response['alert'] = $this->get_error_alert( $e->getMessage() );
			return $response;
		}

		$response['alert'] = $this->get_success_alert(
			sprintf(
				// translators: %s: resource name
				esc_html_x( 'Webhook deleted successfully for %s.', 'Integration settings', 'uncanny-automator' ),
				esc_html( $resource['name'] )
			)
		);

		return $this->refresh_manager_response( $response );
	}

	/**
	 * Handle the manual "Refresh resources" button click — force a re-fetch
	 * regardless of the staleness-marker TTL. Routed here automatically by
	 * `Premium_Integration_Rest_Processing::process_rest_request()` based on
	 * the button's `value` of `refresh_webhook_manager`.
	 *
	 * @param array $response The REST response under construction.
	 * @param array $data     The posted data from the settings table component.
	 *
	 * @return array
	 */
	public function handle_refresh_webhook_manager( $response, $data ) {
		try {
			$this->webhooks->refresh_webhook_manager_config();
		} catch ( Exception $e ) {
			$response['alert'] = $this->get_error_alert( $e->getMessage() );
			return $response;
		}

		$response['alert'] = $this->get_success_alert(
			sprintf(
				// translators: %s: resource label plural (e.g. projects, repositories)
				esc_html_x( 'Refreshed %s from the connected account.', 'Integration settings', 'uncanny-automator' ),
				esc_html( strtolower( $this->get_webhook_manager_resource_label_plural() ) )
			)
		);

		// In-place table update via `data-update` event — the form component picks up
		// `$response['data']` and the list component re-renders without a page reload.
		// The server-rendered "Last refreshed: X ago" status alert remains stale until
		// the next natural page load; that's an accepted trade-off for the smoother UX.
		return $this->refresh_manager_response( $response );
	}

	/**
	 * Resolve the selected event names from posted data — `all-events` short-circuits to every
	 * defined event, otherwise the `events[name => "1"|"0"]` map is filtered to keys with truthy values.
	 *
	 * Accepts both the nested REST shape ($data['events'] as an associative array) and the flat
	 * form-encoded shape ($data['events[name]'] keys), since the settings table component has
	 * been observed to emit either depending on payload codec.
	 *
	 * @param array $data Posted data from the REST settings table component.
	 *
	 * @return array
	 */
	protected function get_webhook_data_events( $data ) {
		$all_events = $data['all-events'] ?? false;
		if ( $all_events ) {
			return $this->get_event_data()['values'];
		}

		$events = isset( $data['events'] ) && is_array( $data['events'] )
			? $data['events']
			: $this->get_array_data( 'events', $data );

		return array_keys( array_filter( $events ) );
	}

	/**
	 * Refresh the manager rows on the response so the table re-renders with the latest config.
	 *
	 * @param array $response Current response payload.
	 *
	 * @return array
	 */
	protected function refresh_manager_response( $response ) {
		$response['data'] = $this->get_webhook_manager_rows(
			$this->webhooks->get_webhook_manager_config()
		);
		return $response;
	}

	/**
	 * Wire the disconnect cleanup filter — concretes opt in by calling this from register_hooks().
	 *
	 * @return void
	 */
	public function register_webhook_manager_disconnect_cleanup() {
		add_filter(
			'automator_before_disconnect_' . $this->get_id(),
			array( $this, 'before_disconnect_webhook_manager_cleanup' ),
			10,
			3
		);
	}

	/**
	 * Disconnect-cleanup hook — best-effort delete every managed webhook and queue the
	 * manager option for deletion via the base settings object's option registry.
	 *
	 * @param array  $response             Current response payload.
	 * @param array  $data                 Posted data.
	 * @param object $base_settings_object The base settings object whose register_option() to call.
	 *
	 * @return array
	 */
	public function before_disconnect_webhook_manager_cleanup( $response, $data, $base_settings_object ) {
		try {
			$this->webhooks->delete_all_managed_webhooks();
		} catch ( Exception $e ) {
			automator_log(
				'Error during disconnect cleanup: ' . $e->getMessage(),
				'App_Integration_Webhook_Manager_Settings disconnect cleanup',
				AUTOMATOR_DEBUG_MODE,
				$this->get_id()
			);
		}

		$base_settings_object->register_option( $this->webhooks->get_webhook_manager_option_name() );

		return $response;
	}
}
