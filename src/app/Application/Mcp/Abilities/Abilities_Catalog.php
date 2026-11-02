<?php
/**
 * Catalogue behind the Automator abilities settings page.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp\Abilities;

use Uncanny_Automator\App\Application\Sub_Tooling\Action_Executor;
use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Discovery_Service;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Feed_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Settings;

/**
 * Integrations that can run on this site, their actions, and the switch state
 * of each, assembled for the settings page and its AJAX handlers.
 *
 * Reads action lists from the integration discovery cache. It builds a
 * missing entry only when asked to, which callers do from an admin-ajax
 * request whose action name makes the recipe manifest load every recipe
 * part; a page render never builds, so a partially loaded action set is
 * never cached.
 */
final class Abilities_Catalog {

	/**
	 * Site-owner switches.
	 *
	 * @var Action_Availability_Settings
	 */
	private Action_Availability_Settings $settings;

	/**
	 * Registered and active integrations.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private Integration_Registry_Bridge $integrations;

	/**
	 * Discovery cache; resolved lazily.
	 *
	 * @var Integration_Discovery_Service|null
	 */
	private ?Integration_Discovery_Service $discovery;

	/**
	 * Memoised classification for the request.
	 *
	 * @var array{available:array,unavailable:array}|null
	 */
	private ?array $groups = null;

	/**
	 * @param Action_Availability_Settings|null  $settings     Switches; defaults to the stored option.
	 * @param Integration_Registry_Bridge|null   $integrations Registry bridge; defaults to the live registry.
	 * @param Integration_Discovery_Service|null $discovery    Discovery service; defaults to the shared instance.
	 */
	public function __construct(
		?Action_Availability_Settings $settings = null,
		?Integration_Registry_Bridge $integrations = null,
		?Integration_Discovery_Service $discovery = null
	) {
		$this->settings     = $settings ?? new Action_Availability_Settings();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->discovery    = $discovery;
	}

	/**
	 * Integrations split into those that can run here and those that cannot.
	 *
	 * Available rows carry `entry` from the discovery cache, or null when the
	 * cache has nothing for them yet. Nothing is built here. Integrations known
	 * to have no actions are left out; there is nothing to switch.
	 *
	 * @return array{available:array<int,array<string,mixed>>,unavailable:array<int,array<string,mixed>>}
	 */
	public function get_integrations(): array {
		if ( null !== $this->groups ) {
			return $this->groups;
		}

		$all          = $this->integrations->get_all_integrations();
		$active       = $this->integrations->get_active_integrations();
		$available    = array();
		$blocked      = array();
		$plugin_files = array();

		foreach ( array_unique( array_merge( array_keys( $all ), array_keys( $active ) ) ) as $code ) {
			$code = (string) $code;
			$data = array_merge( (array) ( $all[ $code ] ?? array() ), (array) ( $active[ $code ] ?? array() ) );
			$row  = array(
				'code' => $code,
				'name' => (string) ( $data['name'] ?? $code ),
				'icon' => (string) ( $data['icon_svg'] ?? '' ),
			);

			$is_active             = isset( $active[ $code ] );
			$plugin_files[ $code ] = (string) ( $data['plugin_file_path'] ?? '' );

			if ( $is_active && $this->integrations->is_app_connected( $code ) ) {
				$row['enabled'] = ! $this->settings->is_integration_disabled( $code );
				$row['entry']   = $this->get_entry( $code, false );

				// Nothing to switch: an integration known to have no actions is not listed.
				// Unknown counts stay listed until the page script learns them.
				if ( null !== $row['entry'] && empty( $row['entry']['actions'] ) ) {
					continue;
				}

				$available[] = $row;
				continue;
			}

			$row['reason']     = $is_active ? 'not_connected' : 'plugin_inactive';
			$row['tier_label'] = '';
			$blocked[]         = $row;
		}

		// The local registry only knows an app once it is connected, so the
		// integration feed supplies everything the site has not set up yet.
		$feed = $this->feed_entries();
		$seen = array_fill_keys( array_merge( array_keys( $all ), array_keys( $active ) ), true );

		foreach ( $blocked as &$row ) {
			$entry               = $feed[ $row['code'] ] ?? array();
			$row['tier_label']   = $entry['tier_label'] ?? '';
			$row['icon']         = '' !== $row['icon'] ? $row['icon'] : (string) ( $entry['icon'] ?? '' );
			$row['requirements'] = $this->requirements_for(
				$row['reason'],
				$row['tier_label'],
				! empty( $plugin_files[ $row['code'] ] ) ? $plugin_files[ $row['code'] ] : (string) ( $entry['plugin_file'] ?? '' )
			);
		}
		unset( $row );

		foreach ( $feed as $code => $entry ) {
			if ( isset( $seen[ $code ] ) ) {
				continue;
			}

			$blocked[] = array(
				'code'         => $code,
				'name'         => $entry['name'],
				'icon'         => $entry['icon'],
				'reason'       => $entry['reason'],
				'tier_label'   => $entry['tier_label'],
				'requirements' => $this->requirements_for( $entry['reason'], $entry['tier_label'], $entry['plugin_file'] ),
			);
		}

		usort( $available, array( $this, 'compare_names' ) );
		usort( $blocked, array( $this, 'compare_names' ) );

		$this->groups = array(
			'available'   => $available,
			'unavailable' => $blocked,
		);

		return $this->groups;
	}

	/**
	 * One integration's actions with their switch state.
	 *
	 * @param string $code  Integration code.
	 * @param bool   $build Build and cache the entry when the cache has none. Only from a load-all request.
	 * @return array{code:string,enabled:bool,actions:array<int,array<string,mixed>>}|null Null when unknown or uncached.
	 */
	public function get_entry( string $code, bool $build ): ?array {
		$data = $this->discovery()->get_cached_integration( $code );

		if ( null === $data && $build ) {
			$data = $this->discovery()->discover_integration_by_code( $code );
		}

		if ( ! is_array( $data ) ) {
			return null;
		}

		$banned  = Action_Executor::get_banned_actions();
		$actions = array();

		foreach ( (array) ( $data['integration_actions'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || ! empty( $item['is_deprecated'] ) ) {
				continue;
			}

			$action_code = (string) ( $item['code'] ?? '' );

			if ( '' === $action_code ) {
				continue;
			}

			$locked    = isset( $banned[ $action_code ] );
			$actions[] = array(
				'code'     => $action_code,
				'sentence' => $this->sentence_of( $item, $action_code ),
				'enabled'  => ! $locked && ! $this->settings->is_action_disabled( $action_code ),
				'locked'   => $locked,
				'reason'   => $locked ? (string) $banned[ $action_code ] : '',
			);
		}

		usort(
			$actions,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['sentence'], $b['sentence'] );
			}
		);

		return array(
			'code'    => $code,
			'enabled' => ! $this->settings->is_integration_disabled( $code ),
			'actions' => $actions,
		);
	}

	/**
	 * Entries for several available integrations, keyed by code.
	 *
	 * Codes that are not available on this site are ignored.
	 *
	 * @param string[] $codes Integration codes.
	 * @param bool     $build Build and cache missing entries. Only from a load-all request.
	 * @return array<string,array<string,mixed>>
	 */
	public function get_entries( array $codes, bool $build ): array {
		$available = $this->available_by_code();
		$entries   = array();

		foreach ( $codes as $code ) {
			$code = (string) $code;

			if ( ! isset( $available[ $code ] ) ) {
				continue;
			}

			$entry = $this->get_entry( $code, $build );

			if ( null !== $entry ) {
				$entries[ $code ] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Whether an integration has a switch on this site.
	 *
	 * @param string $code Integration code.
	 * @return bool
	 */
	public function can_toggle_integration( string $code ): bool {
		return isset( $this->available_by_code()[ $code ] );
	}

	/**
	 * Whether an action has a switch on this site.
	 *
	 * The integration must be available and the action must be one of its
	 * non-banned actions. Builds the entry when needed, so call this from a
	 * load-all request only.
	 *
	 * @param string $code        Action code.
	 * @param string $integration Integration code.
	 * @return bool
	 */
	public function can_toggle_action( string $code, string $integration ): bool {
		if ( '' === $code || ! $this->can_toggle_integration( $integration ) ) {
			return false;
		}

		$entry = $this->get_entry( $integration, true );

		foreach ( (array) ( $entry['actions'] ?? array() ) as $action ) {
			if ( $action['code'] === $code ) {
				return ! $action['locked'];
			}
		}

		return false;
	}

	/**
	 * Switch a whole integration on or off for third-party AI apps.
	 *
	 * @param string $code    Integration code.
	 * @param bool   $enabled True to allow.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_integration_enabled( string $code, bool $enabled ): bool {
		$saved        = $this->settings->set_integration_disabled( $code, ! $enabled );
		$this->groups = null;

		return $saved;
	}

	/**
	 * Switch one action on or off for third-party AI apps.
	 *
	 * @param string $code    Action code.
	 * @param bool   $enabled True to allow.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_action_enabled( string $code, bool $enabled ): bool {
		$saved        = $this->settings->set_action_disabled( $code, ! $enabled );
		$this->groups = null;

		return $saved;
	}

	/**
	 * Integrations known to the feed, keyed by code, reduced to what the page shows.
	 *
	 * Reads the feed's raw cached JSON, never its processed list: the processed
	 * path runs discovery for registered integrations the feed lacks, which on a
	 * plain request would cache an incomplete action set. A cold cache fetches
	 * once and is then cached for an hour, as the Integrations page does.
	 *
	 * @return array<string,array{name:string,icon:string,reason:string,tier_label:string,plugin_file:string}>
	 */
	private function feed_entries(): array {
		try {
			$raw = Integration_Feed_Service::get_instance()->fetch_raw();
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return array();
		}

		$entries = array();

		foreach ( (array) $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$code = strtoupper( trim( (string) ( $item['integration_id'] ?? '' ) ) );

			if ( '' === $code ) {
				continue;
			}

			$type = (string) ( $item['integration_type'] ?? '' );
			$tier = (string) ( $item['integration_tier'] ?? '' );

			$entries[ $code ] = array(
				'name'        => (string) ( $item['integration_name'] ?? $code ),
				'icon'        => (string) ( $item['integration_icon'] ?? '' ),
				'reason'      => 'app' === $type ? 'not_connected' : ( 'built-in' === $type ? 'requires_plan' : 'plugin_inactive' ),
				'tier_label'  => 'pro-basic' === $tier ? 'Pro' : ( 'pro-plus' === $tier ? 'Plus' : ( 'pro-elite' === $tier ? 'Elite' : '' ) ),
				'plugin_file' => (string) ( $item['plugin_details']['plugin_file'] ?? '' ),
			);
		}

		return $entries;
	}

	/**
	 * Keep plan prerequisites alongside setup requirements. A plugin absent
	 * from the active registry is not evidence that it is uninstalled.
	 *
	 * @param string $reason      Registry setup classification.
	 * @param string $tier        Required paid plan, if any.
	 * @param string $plugin_file Known plugin basename or absolute file path.
	 * @return string[] Requirements the settings page can explain from known data.
	 */
	private function requirements_for( string $reason, string $tier, string $plugin_file ): array {
		if ( 'plugin_inactive' === $reason ) {
			$reason = 'setup_required';
			if ( '' !== $plugin_file ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugin_file = plugin_basename( $plugin_file );
				$installed   = get_plugins();
				if ( ! isset( $installed[ $plugin_file ] ) ) {
					$reason = 'plugin_not_installed';
				} elseif ( ! is_plugin_active( $plugin_file ) ) {
					$reason = 'plugin_inactive';
				}
			}
		}

		$requirements = array( $reason );
		if ( '' !== $tier && 'requires_plan' !== $reason ) {
			$requirements[] = 'requires_plan';
		}

		return $requirements;
	}

	/**
	 * Available rows keyed by integration code.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function available_by_code(): array {
		$rows = array();

		foreach ( $this->get_integrations()['available'] as $row ) {
			$rows[ $row['code'] ] = $row;
		}

		return $rows;
	}

	/**
	 * A readable sentence for an action row.
	 *
	 * Prefers the short label the recipe builder shows in its picker, then
	 * the dynamic sentence with token braces removed, then the code.
	 *
	 * @param array  $item        Discovered action item.
	 * @param string $action_code Action code.
	 * @return string
	 */
	private function sentence_of( array $item, string $action_code ): string {
		$sentence = $item['sentence'] ?? array();
		$short    = is_array( $sentence ) ? (string) ( $sentence['short'] ?? '' ) : (string) $sentence;
		$dynamic  = is_array( $sentence ) ? (string) ( $sentence['dynamic'] ?? '' ) : '';

		// Token braces and :CODE suffixes are builder markup, not something to show a site owner.
		$short   = $this->strip_token_markup( $short );
		$dynamic = $this->strip_token_markup( $dynamic );

		if ( '' !== $short ) {
			return $short;
		}

		return '' !== $dynamic ? $dynamic : $action_code;
	}

	/**
	 * Remove {{ }} braces and :CODE suffixes from a builder sentence, and decode entities.
	 *
	 * @param string $sentence Sentence with token markup.
	 * @return string Plain sentence, trimmed, with collapsed spaces.
	 */
	private function strip_token_markup( string $sentence ): string {
		$plain = (string) preg_replace( '/\{\{|\}\}|:[A-Z0-9_]+/', '', $sentence );
		$plain = (string) preg_replace( '/\s+/', ' ', $plain );

		// Builder sentences arrive escaped, sometimes twice. The page inserts them as
		// text, so decode here until nothing changes (bounded).
		for ( $pass = 0; $pass < 3; $pass++ ) {
			$decoded = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			if ( $decoded === $plain ) {
				break;
			}

			$plain = $decoded;
		}

		return trim( $plain );
	}

	/**
	 * Sort rows by name, case-insensitively.
	 *
	 * @param array $a Row.
	 * @param array $b Row.
	 * @return int
	 */
	private function compare_names( array $a, array $b ): int {
		return strcasecmp( $a['name'], $b['name'] );
	}

	/**
	 * Discovery service, resolved on first use.
	 *
	 * @return Integration_Discovery_Service
	 */
	private function discovery(): Integration_Discovery_Service {
		if ( null === $this->discovery ) {
			$this->discovery = Integration_Discovery_Service::get_instance();
		}

		return $this->discovery;
	}
}
