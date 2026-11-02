<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Rejection_Observer;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * The wire context of a registration request: where it goes, who sends it and what
 * it says about this install: the register and completion URLs, the challenge and
 * callback routes, the headers, the license, and the address the site runs on.
 *
 * No options and no state beyond the injected license provider.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Registration_Wire {

	/**
	 * The license this site registers and signs with ({@see license()}); null
	 * reads the shared License_Manager.
	 *
	 * @var License_Provider_Interface|null
	 */
	private $license;

	/**
	 * @param License_Provider_Interface|null $license The license provider; null reads the shared License_Manager when it is needed.
	 */
	public function __construct( ?License_Provider_Interface $license = null ) {

		$this->license = $license;
	}

	/**
	 * The canonical Site-Name this install registers under — the value the
	 * drift marker is written from and compared against.
	 *
	 * The platform keys its site rows on a canonical host, so the marker has
	 * to be canonical too: lowercased, with the scheme, a leading `www.`, a
	 * `:port`, a `?query`/`#fragment` and the trailing slash all removed. A
	 * raw `home_url()` would otherwise report a move the moment the site
	 * gains `www.`, an explicit port, or a capitalized host — a false URL
	 * drift, and an alarming notice for something that never moved.
	 *
	 * A subdirectory install keeps its path (`example.com/wp`), and locale
	 * segments are deliberately left alone: the platform normalizes
	 * `migrate_from.site_name` server-side.
	 *
	 * @since 8.0.0
	 *
	 * @return string Canonical host (plus path, for a subdirectory install).
	 */
	public function current_site_name(): string {

		$name = strtolower( trim( (string) get_home_url() ) );
		$name = (string) preg_replace( '#^https?://#', '', $name );
		$name = (string) preg_replace( '#[?\#].*$#', '', $name );
		$name = (string) preg_replace( '#:\d+(?=/|$)#', '', $name );
		$name = (string) preg_replace( '#^www\.#', '', $name );

		return rtrim( $name, '/' );
	}

	/**
	 * Non-reversible marker of the License-Key we registered with. Stored,
	 * not the key, so the option table never carries a second copy of the
	 * key.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function license_fingerprint(): string {

		$key = $this->license_key();

		return '' === $key ? '' : substr( hash( 'sha256', $key ), 0, 8 );
	}

	/**
	 * Resolve the platform's `/app/v1/sites/register` endpoint URL.
	 * Filterable via `automator_platform_site_register_url` so staging
	 * + integration-test environments can re-target without redefining
	 * `AUTOMATOR_API_URL` globally.
	 *
	 * @return string Fully-qualified URL.
	 */
	public function register_url(): string {

		$base = defined( 'AUTOMATOR_API_URL' )
			? rtrim( (string) AUTOMATOR_API_URL, '/' )
			: 'https://api.automatorplugin.com';

		return (string) Dispatcher::filter(
			'automator_platform_site_register_url',
			$base . AUTOMATOR_PLATFORM_API_PATH . '/sites/register'
		);
	}

	/**
	 * The platform's C1 completion endpoint — where a proven first-registration
	 * attempt is redeemed for the winning signing secret.
	 *
	 * @return string Fully-qualified URL.
	 */
	public function complete_url(): string {

		$base = defined( 'AUTOMATOR_API_URL' )
			? rtrim( (string) AUTOMATOR_API_URL, '/' )
			: 'https://api.automatorplugin.com';

		return (string) Dispatcher::filter(
			'automator_platform_site_register_complete_url',
			$base . AUTOMATOR_PLATFORM_API_PATH . '/sites/register/complete'
		);
	}

	/**
	 * This install's dedicated C1 pre-registration endpoint — the URL the
	 * platform issues its challenge to. Same host as the callback (A2), so the
	 * challenge is only ever delivered to the license-validated site.
	 *
	 * @return string Fully-qualified URL, or '' outside WP (test sanity guard).
	 */
	public function challenge_url(): string {

		return function_exists( 'rest_url' )
			? (string) rest_url( Registration_Challenge_Controller::REST_NAMESPACE . Registration_Challenge_Controller::ROUTE )
			: '';
	}

	/**
	 * Plugin's REST endpoint that the platform fans webhook events back
	 * to (Integrations_Webhook_Controller, registered at
	 * `automator/platform/v1/webhooks/events`). Sent in the request body
	 * so the platform can persist it for later callback delivery.
	 *
	 * @return string Fully-qualified callback URL, or empty string when
	 *                `rest_url()` is unavailable (sanity guard for
	 *                non-WP test contexts).
	 */
	public function callback_url(): string {

		return function_exists( 'rest_url' )
			? (string) rest_url( AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE )
			: '';
	}

	/**
	 * Read the plugin version constant. Sent both as the `Item-Version`
	 * header (lazy-lift gate per Contract D + H) and as the
	 * `plugin_version` body field.
	 *
	 * @return string
	 */
	public function plugin_version(): string {

		return defined( 'AUTOMATOR_PLUGIN_VERSION' )
			? (string) AUTOMATOR_PLUGIN_VERSION
			: '';
	}

	/**
	 * Build the Contract A request-header set.
	 *
	 * `Site-Name` is deliberately NOT set here. It is the platform's
	 * license-tenancy key (LicenseAuthBase rejects a value not in the
	 * license's `site_names` with 401), and it is supplied on
	 * `http_request_args` by `Platform_Headers_Injector` (priority 9999),
	 * sourcing `preg_replace('#^https?://#','', get_home_url())`. That
	 * injector keeps a header the caller set, in any case, as the only
	 * spelling (two spellings go out as two lines the platform reads as one
	 * folded `a, b` value and rejects), so a `Site-Name` set here would be
	 * the one sent. With `get_site_url()`, which carries the scheme and any
	 * `/wp` core directory, that value diverges from the injector's on a
	 * "WordPress in its own directory" / Bedrock install
	 * (`example.com/wp` vs `example.com`) and fails license auth → silent
	 * registration loss. Passing an empty header set here matches
	 * Site_Heartbeat_Cron and Capability_Discovery, which already rely on
	 * the injector.
	 *
	 * `License-Key` and `Item-Name` are set here: the registration signs with
	 * the key it registers, and the injector, which fills only what is
	 * missing, then leaves them as they are.
	 *
	 * Reads `uap_automator_pro_license_key` directly — that option is
	 * the canonical license storage and predates the `automator_*`
	 * prefix convention; renaming it would orphan every existing
	 * customer site.
	 *
	 * @return array<string, string>
	 */
	public function headers(): array {

		return array(
			'License-Key'  => $this->license_key(),
			'Item-Name'    => $this->item_name(),
			'Item-Version' => $this->plugin_version(),
		);
	}

	/**
	 * The License-Key this site registers (and signs) with: the license the
	 * plugin verified active — Pro when the Pro plugin is loaded and its
	 * license activated, else the free license (handoff R3). One rule, owned
	 * by the license provider ({@see License_Manager::get_type()}), so the key
	 * the platform sees on a /v3 request is the key the site registered with,
	 * and a Pro key that is present but not activated is never sent.
	 *
	 * @return string '' when no license is active.
	 */
	private function license_key(): string {

		return $this->license()->get_key();
	}

	/**
	 * The license provider: injected, or the shared License_Manager, resolved
	 * on use so a test override of the shared one applies.
	 *
	 * @since 8.0.0
	 *
	 * @return License_Provider_Interface
	 */
	private function license(): License_Provider_Interface {

		return $this->license ?? automator_license_manager() ?? new License_Manager();
	}

	/**
	 * Whether the site has an active (status=valid) license — Pro or free, by
	 * the same rule as {@see license_key()}. A precondition gate so we never
	 * POST `/sites/register` with credentials the platform can't resolve.
	 *
	 * @return bool
	 */
	public function has_active_license(): bool {

		return '' !== $this->license()->get_type();
	}

	/**
	 * The Item-Name header, paired with {@see license_key()} by the same rule:
	 * the Pro item name only with the activated Pro license.
	 *
	 * @return string
	 */
	private function item_name(): string {

		return $this->license()->get_item_name();
	}

	/**
	 * Environment as WordPress sees it, behind a seam so tests never define
	 * the process-wide constant. Non-production sites never migrate.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function environment_type(): string {

		$default = function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production';

		return (string) Dispatcher::filter( 'automator_platform_environment_type', $default );
	}

	/**
	 * Whether the URL is this class's own register or completion route. Their
	 * answers ARE the re-verification (wire contract W4), so
	 * {@see Site_Credentials_Rejection_Observer} never reads one as a
	 * rejection of this install's credentials.
	 *
	 * @since 8.0.0
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	public function is_registration_route( string $url ): bool {

		return in_array( $url, array( $this->register_url(), $this->complete_url() ), true );
	}
}
