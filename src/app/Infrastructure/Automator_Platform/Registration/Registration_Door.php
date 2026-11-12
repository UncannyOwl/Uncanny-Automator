<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Query_Door;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;

/**
 * Which door the platform reaches this site through, for its registration
 * challenge and its callbacks: the REST API, or the site's own address with a
 * query variable ({@see Query_Door}). The register names both addresses of
 * one door ({@see Registration_Wire::challenge_url()},
 * {@see Registration_Wire::callback_url()}), so the callbacks travel the door
 * the challenge proved.
 *
 * The REST API is the default, and a site the platform reaches there is never
 * moved. The door changes only when an attempt ends unproven
 * ({@see after_unproven()}), or from the record of such an ending an earlier
 * version left (below):
 *
 *  - through the REST API, when the platform's last check of this site's
 *    address was answered there by something that is not the plugin (a REST
 *    lock's 401 or 403, a 404 page, a 200 page; {@see Challenge_Progress::answered_without_the_plugin()}):
 *    the next attempt gives the site's own address;
 *  - through the site's own address, for any reason: the next attempt goes
 *    back to the REST API, so a site whose firewall refuses both is never
 *    stuck on either, and one whose REST API was opened since is found there.
 *
 * Kept until the next such ending: every register uses it, a registration
 * that lands is made through it, and a registration made through one door
 * keeps the platform there.
 *
 * Plugin 8.0.x had the REST API alone: it recorded how an attempt ended
 * ({@see Registration_Outcomes::last_failure()}) and chose no door. So before
 * a new attempt the first rule above is read from that record instead of a
 * live answer ({@see after_recorded_unproven()}), and a site that updated
 * holding the platform's word that its REST API refuses it gives the site's
 * own address on its very next register. Never a site with a registration on
 * file, which 8.0.x made through the REST API: a re-register with its proof is
 * answered at once, with no challenge, and would hand the platform the other
 * door unproven. A record of the site's own address never counts, so the way
 * back to the REST API holds.
 *
 * Whether the plugin's REST API refuses anonymous callers is never guessed
 * locally: a lock can sit in a filter keyed on the route, the visitor's
 * address or a header, in the web server or in a firewall in front of it, so
 * only the platform's own check of the address is evidence.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()} and {@see Query_Door}; not part of the plugin's API.
 *
 * @since 8.0.4
 */
final class Registration_Door {

	/**
	 * The option holding the door of the next registration; absent for the
	 * REST API.
	 */
	public const OPTION = 'automator_platform_registration_door';

	/**
	 * The REST API: `/wp-json/automator/platform/v1/…`.
	 */
	public const REST = 'rest';

	/**
	 * The site's own address: `/?automator_platform=…` ({@see Query_Door}).
	 */
	public const QUERY = 'query';

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @param Site_Credentials_Store|null $store What the registration on file was made with.
	 */
	public function __construct( ?Site_Credentials_Store $store = null ) {

		$this->store = $store ?? new Site_Credentials_Store();
	}

	/**
	 * The door the next registration gives the platform.
	 *
	 * @return string {@see REST} or {@see QUERY}.
	 */
	public function current(): string {

		return self::QUERY === automator_get_option( self::OPTION, self::REST ) ? self::QUERY : self::REST;
	}

	/**
	 * Whether the next registration gives the platform the site's own address.
	 *
	 * @return bool
	 */
	public function uses_query_door(): bool {

		return self::QUERY === $this->current();
	}

	/**
	 * Whether the door at the site's own address answers: the next
	 * registration gives it to the platform, or the registration on file was
	 * made through it, so the platform still sends its callbacks there (and a
	 * move's check of the old address goes there) until a registration
	 * through the REST API lands. Shut everywhere else, so a site the platform
	 * reaches through the REST API has no second way in.
	 *
	 * @return bool
	 */
	public function is_query_door_open(): bool {

		return $this->uses_query_door() || Query_Door::is_door_address( (string) $this->store->registered_challenge_url() );
	}

	/**
	 * An attempt through the current door ended unproven: choose the next
	 * attempt's door (see the class docblock).
	 *
	 * @param Challenge_Progress|null $last_check What the platform last said of
	 *                                            its check of this site's address,
	 *                                            null when nothing said.
	 *
	 * @return bool Whether the next attempt uses the other door.
	 */
	public function after_unproven( ?Challenge_Progress $last_check ): bool {

		if ( $this->uses_query_door() ) {
			automator_delete_option( self::OPTION );
			return true;
		}

		if ( null === $last_check || ! $last_check->answered_without_the_plugin() ) {
			return false;
		}

		automator_update_option( self::OPTION, self::QUERY, false );

		return true;
	}

	/**
	 * A new attempt is about to begin: choose its door from how the last
	 * attempt's ending was recorded, when no door was chosen from it (see the
	 * class docblock). With no registration on file, a record that is
	 * unreachable, whose reason is an answer by something that is not the
	 * plugin ({@see Challenge_Progress::is_answer_without_the_plugin()}) and
	 * that names this site's REST challenge address
	 * ({@see Registration_Challenge_Controller::url()}) gives the next attempt
	 * the site's own address. Kept as {@see after_unproven()} keeps it: the
	 * door is open before the register that names it is sent, and a register
	 * the platform holds, whose refusal replaces the record, changes nothing.
	 *
	 * Such a record says no door was chosen since: this version writes one
	 * only as {@see after_unproven()} moves the door to the site's own
	 * address, the way back writes a record of that address, and a
	 * registration that lands deletes it. A registration on file is never
	 * moved by one: 8.0.x made every one through the REST API, and one made
	 * through the site's own address keeps that door open
	 * ({@see is_query_door_open()}).
	 *
	 * @since 8.0.4
	 *
	 * @param array<string, mixed>|null $failure How the last attempt failed, as
	 *                                           recorded; null when none did.
	 *
	 * @return bool Whether the next attempt uses the other door.
	 */
	public function after_recorded_unproven( ?array $failure ): bool {

		if ( null === $failure || $this->uses_query_door() || $this->store->has_registered() || ! self::is_rest_api_refusal( $failure ) ) {
			return false;
		}

		automator_update_option( self::OPTION, self::QUERY, false );

		return true;
	}

	/**
	 * Whether a record of how an attempt failed says it ended unproven at
	 * this site's REST API, answered there by something that is not the
	 * plugin.
	 *
	 * @since 8.0.4
	 *
	 * @param array<string, mixed> $failure The record.
	 *
	 * @return bool
	 */
	private static function is_rest_api_refusal( array $failure ): bool {

		$reason = $failure['reason'] ?? null;

		return Registration_Status::UNREACHABLE === ( $failure['state'] ?? null )
			&& is_string( $reason )
			&& Challenge_Progress::is_answer_without_the_plugin( $reason )
			&& Registration_Challenge_Controller::url() === ( $failure['challenge_url'] ?? null );
	}
}
