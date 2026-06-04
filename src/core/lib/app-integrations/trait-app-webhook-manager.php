<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;

/**
 * Trait App_Webhook_Manager
 *
 * Opt-in trait for App_Webhooks concrete classes whose external API supports
 * adding, editing, and deleting per-resource webhook subscriptions (e.g.,
 * per-project webhooks for Asana, per-repository webhooks for GitHub).
 *
 * Composed alongside `extends App_Webhooks`, never as a standalone:
 *
 *   class Asana_Webhooks extends App_Webhooks {
 *       use App_Webhook_Manager;
 *       public function fetch_resources() { ... }
 *       public function format_resource_for_storage( $resource ) { ... }
 *       public function create_webhook( $id, $events ) { ... }
 *       public function update_webhook( $id, $events ) { ... }
 *       public function delete_webhook( $id ) { ... }
 *   }
 *
 * Storage: a single option keyed by `automator_{settings_id}_webhook_manager`,
 * containing an associative array of resource entries. Each entry conforms to:
 *   [
 *       'id'      => string,
 *       'name'    => string,
 *       'hook_id' => string|null,
 *       'events'  => string[],
 *       'meta'    => array,
 *   ]
 *
 * Concrete implementations of `format_resource_for_storage()` MUST cast `id`,
 * `name`, and `hook_id` to `string` (e.g., `(string) $api_resource['id']`) to
 * keep the canonical-shape promise across APIs that return integer IDs.
 *
 * @package Uncanny_Automator\App_Integrations
 *
 * @property \Uncanny_Automator\App_Integrations\App_Helpers $helpers
 * @method bool store_webhooks_enabled_status( bool $enabled )
 */
trait App_Webhook_Manager {

	/**
	 * Fetch the integration's webhook-eligible resources from its remote API.
	 *
	 * @param bool $force_refresh When true, concretes must bypass any
	 *                            helper-side cache and re-hit the remote API.
	 *                            Used by `refresh_webhook_manager_config()` so
	 *                            the manual / settings-page-TTL refresh path
	 *                            actually surfaces newly-created upstream resources.
	 *
	 * @return array Array of raw resources to be normalized into the canonical shape.
	 */
	abstract public function fetch_resources( $force_refresh = false );

	/**
	 * Normalize a raw resource into the canonical stored shape.
	 *
	 * @param array $resource A single raw resource as returned by `fetch_resources()`.
	 *
	 * @return array Canonical-shape entry with id, name, hook_id, events, and meta keys.
	 */
	abstract public function format_resource_for_storage( $resource );

	/**
	 * Required: delete one webhook subscription on the external API.
	 *
	 * @param string $resource_id The canonical resource ID (matches `id` in the canonical entry).
	 *
	 * @return void
	 */
	abstract public function delete_webhook( $resource_id );

	/**
	 * Get the option name where this integration's manager config is stored.
	 *
	 * @return string
	 */
	public function get_webhook_manager_option_name() {
		return 'automator_' . $this->helpers->get_settings_id() . '_webhook_manager';
	}

	/**
	 * Read the raw stored manager option. Single seam concrete classes can
	 * override to detect-and-canonicalize legacy stored shapes in flight,
	 * keeping shape-migration concerns inside the integration that owns them.
	 *
	 * @return mixed Whatever is stored under the manager option, or `array()` when unset.
	 */
	protected function read_webhook_manager_option() {
		return automator_get_option( $this->get_webhook_manager_option_name(), array() );
	}

	/**
	 * Get the manager config; lazily fetch and persist when no stored copy exists.
	 *
	 * @return array Associative array keyed by canonical resource ID.
	 */
	public function get_webhook_manager_config() {
		$stored = $this->read_webhook_manager_option();

		if ( ! empty( $stored ) ) {
			return $stored;
		}

		$resources = $this->fetch_resources();
		if ( empty( $resources ) ) {
			return array();
		}

		$config = array();
		foreach ( $resources as $resource ) {
			$entry                  = $this->format_resource_for_storage( $resource );
			$config[ $entry['id'] ] = $entry;
		}

		$this->store_webhook_manager_config( $config );

		return $config;
	}

	/**
	 * Persist the manager config under the manager option name.
	 *
	 * @param array $config Canonical-shape config keyed by resource ID.
	 *
	 * @return void
	 */
	public function store_webhook_manager_config( $config ) {
		automator_update_option( $this->get_webhook_manager_option_name(), $config );
	}

	/**
	 * Update a single resource entry in the manager config and refresh the
	 * webhooks-enabled gate based on whether any entry is now subscribed.
	 *
	 * @param string $resource_id Canonical resource ID.
	 * @param array  $entry       Canonical-shape entry for the resource.
	 *
	 * @return void
	 */
	public function update_resource_in_manager_config( $resource_id, $entry ) {
		$config                 = $this->get_webhook_manager_config();
		$config[ $resource_id ] = $entry;
		$this->store_webhook_manager_config( $config );
		$this->refresh_webhooks_enabled_status( $config );
	}

	/**
	 * Recompute and persist the webhooks-enabled flag from the manager config.
	 *
	 * Enabled when at least one entry has a non-empty `hook_id` (i.e., is
	 * actively subscribed at the remote service).
	 *
	 * @param array $config Canonical-shape manager config.
	 *
	 * @return void
	 */
	protected function refresh_webhooks_enabled_status( $config ) {
		$enabled = false;
		foreach ( $config as $entry ) {
			if ( ! empty( $entry['hook_id'] ) ) {
				$enabled = true;
				break;
			}
		}
		$this->store_webhooks_enabled_status( $enabled );
	}

	/**
	 * Re-fetch resources from the remote API and reconcile the stored manager
	 * config against the live list. Subscription state on existing entries is
	 * preserved (`hook_id`, `events`, `meta.secret`, `meta.connected_at`); newly
	 * discovered resources are seeded; resources that no longer exist remotely
	 * are dropped UNLESS they still carry an active `hook_id` (kept visible so
	 * the user can clean up the orphaned subscription).
	 *
	 * Stamps `mark_webhook_manager_refreshed()` on success so the settings page
	 * staleness gate can see fresh data.
	 *
	 * Intended to be called only from the settings-page render path or the
	 * matching "Refresh" REST handler — never during trigger/action processing.
	 *
	 * @return array The merged manager config keyed by resource ID.
	 */
	public function refresh_webhook_manager_config() {
		$stored    = $this->get_webhook_manager_config();
		$resources = $this->fetch_resources( true );
		$merged    = array();

		foreach ( $resources as $resource ) {
			$formatted = $this->format_resource_for_storage( $resource );
			$id        = $formatted['id'];

			if ( isset( $stored[ $id ] ) ) {
				$formatted['hook_id']              = $stored[ $id ]['hook_id'] ?? null;
				$formatted['events']               = $stored[ $id ]['events'] ?? array();
				$formatted['meta']['secret']       = $stored[ $id ]['meta']['secret'] ?? null;
				$formatted['meta']['connected_at'] = $stored[ $id ]['meta']['connected_at'] ?? null;
			}

			$merged[ $id ] = $formatted;
		}

		// Preserve orphans that still carry an active hook so the user can disconnect them.
		foreach ( $stored as $id => $entry ) {
			if ( ! isset( $merged[ $id ] ) && ! empty( $entry['hook_id'] ) ) {
				$merged[ $id ] = $entry;
			}
		}

		$this->store_webhook_manager_config( $merged );
		$this->mark_webhook_manager_refreshed();

		return $merged;
	}

	/**
	 * Get the option key used to track when the manager config was last refreshed.
	 *
	 * Marker storage uses the standard `App_Helpers_Option_Data` envelope
	 * (`{ data: [], timestamp: int }`) — the `data` slot is intentionally unused;
	 * we only need the timestamp + the trait's stale-vs-fresh flag.
	 *
	 * @return string
	 */
	public function get_webhook_manager_refreshed_marker_key() {
		return $this->helpers->get_option_key( 'webhook_manager_refreshed' );
	}

	/**
	 * Read the staleness marker for the manager config.
	 *
	 * @param int $ttl Maximum age (in seconds) before the marker reports stale.
	 *
	 * @return array{data: array, timestamp: int, refresh: bool}
	 */
	public function get_webhook_manager_marker( $ttl = DAY_IN_SECONDS ) {
		return $this->helpers->get_app_option( $this->get_webhook_manager_refreshed_marker_key(), $ttl );
	}

	/**
	 * Stamp the staleness marker with `time()`. Called from
	 * `refresh_webhook_manager_config()` after a successful re-fetch.
	 *
	 * @return void
	 */
	public function mark_webhook_manager_refreshed() {
		$this->helpers->save_app_option( $this->get_webhook_manager_refreshed_marker_key(), array() );
	}

	/**
	 * Best-effort delete every subscribed webhook tracked in the manager config.
	 *
	 * Intended for disconnect / uninstall flows. Failures are logged and the
	 * loop continues so a single bad resource doesn't block the rest.
	 *
	 * @return void
	 */
	public function delete_all_managed_webhooks() {
		$config = $this->get_webhook_manager_config();
		if ( empty( $config ) ) {
			return;
		}

		foreach ( $config as $entry ) {
			if ( empty( $entry['hook_id'] ) ) {
				continue;
			}
			try {
				$this->delete_webhook( $entry['id'] );
			} catch ( Exception $e ) {
				automator_log(
					'Failed to delete managed webhook for ' . $entry['name'] . ': ' . $e->getMessage(),
					'App_Webhook_Manager bulk delete',
					AUTOMATOR_DEBUG_MODE,
					$this->helpers->get_settings_id()
				);
			}
		}
	}
}
