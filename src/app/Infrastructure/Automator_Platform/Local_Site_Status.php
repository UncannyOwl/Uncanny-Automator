<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Whether the platform registered this site as a LOCAL site (L247) — the one
 * owner of that answer.
 *
 * The platform decides it from the site's address alone — `localhost`, a name
 * under `.localhost`, `.local`, `.test`, `.internal`, a single label, a
 * private, loopback or link-local address — and says so in the registration it
 * answers (`200` with `data.local: true`, platform answers contract 4.8). The
 * plugin never decides it: {@see Platform_Site_Registration} records what each
 * accepted registration said, and a registration answered without the flag
 * clears it.
 *
 * Nothing is ever delivered to a local site, so while it is local: webhook
 * triggers stay off and `register-site-for-webhooks` is never sent (the
 * shared `/v3` caller refuses it before the request), owed trigger
 * subscriptions are settled as not applicable, the move question is never
 * asked (a copy of a local site is a new local site), the staging-copy
 * and sharing controls are hidden, and app actions are never sent as
 * background jobs (no completion could reach the site). The one sentence a customer reads for all
 * of it is {@see Platform_Failure_Message::local_site()}.
 *
 * Stored as the Site-Name the site was registered as local under, autoloaded:
 * the shared caller and the settings screens read it on requests that already
 * read the drift markers.
 *
 * @since 8.0.0
 */
final class Local_Site_Status {

	/**
	 * The Site-Name a local registration was accepted under; absent when the
	 * site is not local.
	 */
	public const OPTION = 'automator_platform_site_local';

	/**
	 * @return bool Whether the platform's last accepted registration said this site is local.
	 */
	public function is_local(): bool {

		return '' !== (string) automator_get_option( self::OPTION, '' );
	}

	/**
	 * Record what an accepted registration said.
	 *
	 * @param bool   $local     Whether the answer carried `data.local: true`.
	 * @param string $site_name The Site-Name the site registered under.
	 *
	 * @return void
	 */
	public function record( bool $local, string $site_name ): void {

		if ( $local && '' !== $site_name ) {
			automator_update_option( self::OPTION, $site_name, true );

			return;
		}

		automator_delete_option( self::OPTION );
	}
}
