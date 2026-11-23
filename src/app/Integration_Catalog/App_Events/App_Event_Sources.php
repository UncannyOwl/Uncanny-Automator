<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\App_Events;

/**
 * The apps that send this site events through the Automator platform, as
 * their integrations registered them on this request.
 *
 * An app's integration registers its source when it loads (its event
 * fanout's `register()`), so the list holds the apps whose integrations
 * loaded: every app on a full load (Automator REST, the platform's webhook,
 * the recipe editor), and on a targeted load the apps a published recipe
 * uses. Reached through `automator_app_event_sources()`.
 *
 * @since 8.1.0
 */
final class App_Event_Sources {

	/**
	 * Per platform slug.
	 *
	 * @var array<string, App_Event_Source>
	 */
	private $sources = array();

	/**
	 * Add an app. A second source for the same slug replaces the first.
	 *
	 * @since 8.1.0
	 *
	 * @param App_Event_Source $source The app.
	 *
	 * @return void
	 */
	public function register( App_Event_Source $source ): void {
		$this->sources[ $source->get_platform_slug() ] = $source;
	}

	/**
	 * The app the platform names with this slug.
	 *
	 * @since 8.1.0
	 *
	 * @param string $slug The platform's slug.
	 *
	 * @return App_Event_Source|null
	 */
	public function find( string $slug ): ?App_Event_Source {
		return $this->sources[ $slug ] ?? null;
	}

	/**
	 * The app whose triggers belong to this integration.
	 *
	 * @since 8.1.0
	 *
	 * @param string $code The integration code.
	 *
	 * @return App_Event_Source|null
	 */
	public function find_by_code( string $code ): ?App_Event_Source {
		foreach ( $this->sources as $source ) {
			if ( $code === $source->get_integration_code() ) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * Every registered app.
	 *
	 * @since 8.1.0
	 *
	 * @return App_Event_Source[]
	 */
	public function all(): array {
		return array_values( $this->sources );
	}
}
