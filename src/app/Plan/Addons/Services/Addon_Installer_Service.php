<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Services;

use Exception;
use Uncanny_Automator\App\Infrastructure\Addons\Addon_Download_Client;
use Uncanny_Automator\App\Infrastructure\Addons\Addon_Download_Exception;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon_Action_Result;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;
use Uncanny_Automator\Services\Plugin\Manager;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Runs one plugin-manager action against an addon.
 *
 * Orchestration only: entitlement comes from Addon_Entitlement_Service, the
 * signed URL from Addon_Download_Client, and the actual install / update /
 * activate work from the core WordPress upgrader wrapper. What comes back is
 * an Addon_Action_Result — an outcome plus, on failure, what the user should
 * do next.
 */
final class Addon_Installer_Service {

	/**
	 * The actions the plugin manager runs.
	 *
	 * @var array<int,string>
	 */
	const ACTIONS = array( 'install', 'update', 'activate', 'download' );

	/**
	 * Nonce action the core upgrader wrapper verifies.
	 *
	 * @var string
	 */
	const UPGRADER_NONCE = 'Aut0Mat0RPlug1nM@nag5r';

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Addon catalog.
	 *
	 * @var Addon_Catalog_Service
	 */
	private Addon_Catalog_Service $catalog;

	/**
	 * Addon access gate.
	 *
	 * @var Addon_Entitlement_Service
	 */
	private Addon_Entitlement_Service $entitlement;

	/**
	 * Injected license reader, when the caller supplied one.
	 *
	 * @var License_Manager|null
	 */
	private ?License_Manager $license_manager;

	/**
	 * WordPress upgrader wrapper.
	 *
	 * @var Manager
	 */
	private Manager $upgrader;

	/**
	 * Signed-download client.
	 *
	 * @var Addon_Download_Client
	 */
	private Addon_Download_Client $download_client;

	/**
	 * Plugin install lookups.
	 *
	 * @var Plugin_Service
	 */
	private Plugin_Service $plugins;

	/**
	 * Construct with lazy-default dependencies.
	 *
	 * @param Addon_Catalog_Service|null     $catalog         Addon catalog.
	 * @param Addon_Entitlement_Service|null $entitlement     Access gate.
	 * @param License_Manager|null           $license_manager License reader.
	 * @param Manager|null                   $upgrader        Upgrader wrapper.
	 * @param Addon_Download_Client|null     $download_client Download client.
	 * @param Plugin_Service|null            $plugins         Plugin lookups.
	 */
	private function __construct(
		?Addon_Catalog_Service $catalog = null,
		?Addon_Entitlement_Service $entitlement = null,
		?License_Manager $license_manager = null,
		?Manager $upgrader = null,
		?Addon_Download_Client $download_client = null,
		?Plugin_Service $plugins = null
	) {
		$this->catalog         = $catalog ?? Addon_Catalog_Service::instance();
		$this->entitlement     = $entitlement ?? Addon_Entitlement_Service::instance();
		$this->license_manager = $license_manager;
		$this->upgrader        = $upgrader ?? new Manager();
		$this->download_client = $download_client ?? new Addon_Download_Client();
		$this->plugins         = $plugins ?? new Plugin_Service();
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build with explicit dependencies.
	 *
	 * @param Addon_Catalog_Service|null     $catalog         Addon catalog.
	 * @param Addon_Entitlement_Service|null $entitlement     Access gate.
	 * @param License_Manager|null           $license_manager License reader.
	 * @param Manager|null                   $upgrader        Upgrader wrapper.
	 * @param Addon_Download_Client|null     $download_client Download client.
	 * @param Plugin_Service|null            $plugins         Plugin lookups.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Addon_Catalog_Service $catalog = null,
		?Addon_Entitlement_Service $entitlement = null,
		?License_Manager $license_manager = null,
		?Manager $upgrader = null,
		?Addon_Download_Client $download_client = null,
		?Plugin_Service $plugins = null
	): self {
		return new self( $catalog, $entitlement, $license_manager, $upgrader, $download_client, $plugins );
	}

	/**
	 * Look up the addon an action targets.
	 *
	 * @param int $addon_id Product id.
	 *
	 * @return Addon|null
	 */
	public function find_addon( int $addon_id ): ?Addon {
		return $this->catalog->find( $addon_id );
	}

	/**
	 * Run one action against one addon.
	 *
	 * @param string $action   One of install|update|activate|download.
	 * @param int    $addon_id Product id.
	 *
	 * @return Addon_Action_Result
	 */
	public function execute( string $action, int $addon_id ): Addon_Action_Result {

		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return Addon_Action_Result::failure(
				esc_html_x( 'Invalid action requested', 'Addons', 'uncanny-automator' ),
				Addon_Action_Result::INTENT_RELOAD
			);
		}

		$addon = $this->find_addon( $addon_id );
		if ( null === $addon ) {
			return Addon_Action_Result::failure(
				esc_html_x( 'Invalid addon requested', 'Addons', 'uncanny-automator' ),
				Addon_Action_Result::INTENT_RELOAD
			);
		}

		// Activation needs no license: the package is already on disk.
		if ( 'activate' === $action ) {
			return $this->activate( $addon );
		}

		$license = $this->license_manager()->get_license_data();
		if ( null === $license || 'valid' !== ( $license['license'] ?? '' ) ) {
			return Addon_Action_Result::failure(
				esc_html_x( 'License invalid', 'Addons', 'uncanny-automator' ),
				Addon_Action_Result::INTENT_FIX_LICENSE
			);
		}

		$required_tier = $addon->required_tier();
		if ( ! $this->entitlement->has_access_to_tier( $required_tier ) ) {
			return Addon_Action_Result::failure(
				$this->upgrade_message( $required_tier ),
				Addon_Action_Result::INTENT_UPGRADE
			);
		}

		try {
			$url = $this->download_client->get_download_url( $addon, (string) ( $license['license_key'] ?? '' ) );
		} catch ( Addon_Download_Exception $e ) {
			return $this->download_failure( (int) $e->getCode() );
		}

		if ( 'download' === $action ) {
			return Addon_Action_Result::download( $url );
		}

		return $this->run_upgrader( $action, $url );
	}

	/**
	 * The license reader.
	 *
	 * Resolved per call rather than captured at construction: the shared
	 * instance outlives any single license read, and the license manager can
	 * be replaced (after an activation, or by a test seam) mid-request.
	 *
	 * @return License_Manager
	 */
	private function license_manager(): License_Manager {
		return $this->license_manager ?? automator_license_manager();
	}

	/**
	 * Activate an installed addon.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return Addon_Action_Result
	 */
	private function activate( Addon $addon ): Addon_Action_Result {

		$plugin_file = $this->plugins->get_addon_plugin_file(
			array(
				'key'  => $addon->constant_key(),
				'name' => $addon->name(),
			)
		);

		if ( '' === $plugin_file ) {
			return $this->activation_failure( $addon );
		}

		try {
			$this->upgrader->activate(
				array(
					'plugin' => $plugin_file,
					'name'   => $addon->name(),
				),
				wp_create_nonce( self::UPGRADER_NONCE )
			);
		} catch ( Exception $e ) {
			return $this->activation_failure( $addon );
		}

		return Addon_Action_Result::success( true );
	}

	/**
	 * Install or update an addon from its signed package.
	 *
	 * @param string $action Either install or update.
	 * @param string $url    Signed download URL.
	 *
	 * @return Addon_Action_Result
	 */
	private function run_upgrader( string $action, string $url ): Addon_Action_Result {

		try {
			$result = 'update' === $action
				? $this->upgrader->remote_update( $url, wp_create_nonce( self::UPGRADER_NONCE ) )
				: $this->upgrader->install( $url, wp_create_nonce( self::UPGRADER_NONCE ) );

			return Addon_Action_Result::success( (bool) ( $result['data']['activated'] ?? false ) );

		} catch ( Exception $e ) {

			// A well-formed URL is all the user needs — their browser fetches
			// it, not WordPress, so wp_http_validate_url()'s DNS and
			// private-host rules would reject usable links.
			if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
				return $this->download_failure( (int) $e->getCode() );
			}

			return Addon_Action_Result::failure(
				sprintf(
					// translators: %s is either "installing" or "updating".
					esc_html_x( 'There was an error %s the addon. Please download and upload directly on the plugins page.', 'Addons', 'uncanny-automator' ),
					'update' === $action
						? esc_html_x( 'updating', 'Addons', 'uncanny-automator' )
						: esc_html_x( 'installing', 'Addons', 'uncanny-automator' )
				),
				Addon_Action_Result::INTENT_DIRECT_DOWNLOAD,
				$url
			);
		}
	}

	/**
	 * The failure for an addon that could not be activated here.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return Addon_Action_Result
	 */
	private function activation_failure( Addon $addon ): Addon_Action_Result {
		return Addon_Action_Result::failure(
			esc_html_x( 'Activation failed. Please try to activate manually.', 'Addons', 'uncanny-automator' ),
			Addon_Action_Result::INTENT_PLUGINS_PAGE,
			$this->plugins->get_plugin_search_url( $addon->name() )
		);
	}

	/**
	 * Map a download failure code to the intent the user acts on.
	 *
	 * A request the site cannot make (400) or an unauthorized one (401) is
	 * usually a stale page; anything else means the store answered and
	 * refused, which the user resolves from their account.
	 *
	 * @param int $code Download failure code.
	 *
	 * @return Addon_Action_Result
	 */
	private function download_failure( int $code ): Addon_Action_Result {

		$message = esc_html_x( 'There was an error processing your request.', 'Addons', 'uncanny-automator' );

		if ( in_array( $code, array( 400, 401 ), true ) ) {
			return Addon_Action_Result::failure(
				$message . ' ' . esc_html_x( 'Please refresh the page and try again.', 'Addons', 'uncanny-automator' ),
				Addon_Action_Result::INTENT_RELOAD
			);
		}

		return Addon_Action_Result::failure(
			$message . ' ' . esc_html_x( 'Please visit your account page to download the addon manually.', 'Addons', 'uncanny-automator' ),
			Addon_Action_Result::INTENT_MY_ACCOUNT
		);
	}

	/**
	 * The upgrade prompt for a tier this site does not have.
	 *
	 * @param string $tier Required tier.
	 *
	 * @return string
	 */
	private function upgrade_message( string $tier ): string {
		return Addon::TIER_ELITE === $tier
			? esc_html_x( 'Please upgrade to the Elite plan to access this addon.', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'Please upgrade to the Plus plan to access this addon.', 'Addons', 'uncanny-automator' );
	}
}
