<?php
/**
 * Plugin Status Service
 *
 * @package Uncanny_Automator\App\Plan\Services\Plugin
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Services\Plugin;

/**
 * Each active Automator plugin with its installed version and the update
 * WordPress would offer, for the Dashboard Status card.
 *
 * Updates come from WordPress's own update_plugins site transient, read through
 * get_site_transient() so every filter applies. Pro uses that filter to hide its
 * own update while Automator has one pending, so this reports exactly what
 * WordPress would offer. An offer for the installed version or older is stale
 * and is not an update.
 */
final class Plugin_Status_Service {

	private Plugin_Service $plugins;

	/**
	 * Constructor.
	 */
	public function __construct( ?Plugin_Service $plugins = null ) {
		$this->plugins = $plugins ?? new Plugin_Service();
	}

	/**
	 * Automator first, then Pro when it is active.
	 *
	 * @return list<array{id:string,name:string,version:string,update:array{version:string,url:string}|null}>
	 */
	public function get_statuses(): array {
		$offers = $this->update_offers();
		$rows   = array( $this->row( 'automator', 'Uncanny Automator', plugin_basename( AUTOMATOR_BASE_FILE ), AUTOMATOR_PLUGIN_VERSION, $offers ) );

		if ( $this->plugins->is_pro_active() ) {
			$pro_file = $this->plugins->get_pro_plugin_file();
			$rows[]   = $this->row( 'automator_pro', 'Uncanny Automator Pro', $pro_file, $this->plugins->get_plugin_version( $pro_file ), $offers );
		}

		return $rows;
	}

	/**
	 * One plugin row.
	 *
	 * @param array<string,mixed> $offers Update offers keyed by plugin file.
	 *
	 * @return array{id:string,name:string,version:string,update:array{version:string,url:string}|null}
	 */
	private function row( string $id, string $name, string $file, string $version, array $offers ): array {
		$offer   = $offers[ $file ] ?? null;
		$offered = is_object( $offer ) && isset( $offer->new_version ) && is_string( $offer->new_version ) ? $offer->new_version : '';

		return array(
			'id'      => $id,
			'name'    => $name,
			'version' => $version,
			'update'  => '' !== $offered && version_compare( $offered, $version, '>' )
				? array(
					'version' => $offered,
					'url'     => self_admin_url( 'update-core.php' ),
				)
				: null,
		);
	}

	/**
	 * The plugin updates WordPress currently offers, keyed by plugin file.
	 *
	 * @return array<string,mixed>
	 */
	private function update_offers(): array {
		$transient = get_site_transient( 'update_plugins' );

		return is_object( $transient ) && isset( $transient->response ) && is_array( $transient->response ) ? $transient->response : array();
	}
}
