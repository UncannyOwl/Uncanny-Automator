<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

use Uncanny_Automator\App\Bridge\User_Option_Bridge;
use Uncanny_Automator\App\Bridge\WP_User_Option_Bridge;

/**
 * Where stored dismissals live: one map per user (a user option) and one for
 * the site (an option), each `notice id => {variant, until}`. A dismissal
 * holds while its variant matches the notice's and its time has not passed;
 * `until` 0 is forever.
 *
 * @since 8.0.0
 */
final class Notice_Dismissals {

	/**
	 * The site's map.
	 */
	public const SITE_OPTION = 'automator_notice_dismissals';

	/**
	 * Each user's map, a user option.
	 */
	public const USER_OPTION = 'automator_notice_dismissals';

	/**
	 * @var User_Option_Bridge
	 */
	private $users;

	/**
	 * @param User_Option_Bridge|null $users Each user's own options.
	 */
	public function __construct( ?User_Option_Bridge $users = null ) {

		$this->users = $users ?? new WP_User_Option_Bridge();
	}

	/**
	 * Whether the notice is put away for this user now.
	 *
	 * @param string           $id        The notice's id.
	 * @param Notice_Dismissal $dismissal How it may be put away now.
	 * @param int              $user_id   The user.
	 * @param int              $now       Unix time.
	 *
	 * @return bool
	 */
	public function is_dismissed( string $id, Notice_Dismissal $dismissal, int $user_id, int $now ): bool {

		if ( ! $dismissal->is_stored() ) {
			return false;
		}

		$record = $this->map( $dismissal->scope(), $user_id )[ $id ] ?? null;
		if ( ! is_array( $record ) || $dismissal->variant() !== (string) ( $record['variant'] ?? '' ) ) {
			return false;
		}

		$until = (int) ( $record['until'] ?? 0 );

		return 0 === $until || $until > $now;
	}

	/**
	 * Store a dismissal.
	 *
	 * @param string           $id        The notice's id.
	 * @param Notice_Dismissal $dismissal How it may be put away; must be stored.
	 * @param int              $user_id   The user who dismissed it.
	 * @param int              $now       Unix time.
	 *
	 * @return void
	 */
	public function dismiss( string $id, Notice_Dismissal $dismissal, int $user_id, int $now ): void {

		if ( ! $dismissal->is_stored() ) {
			return;
		}

		$map        = $this->map( $dismissal->scope(), $user_id );
		$map[ $id ] = array(
			'variant' => $dismissal->variant(),
			'until'   => 0 === $dismissal->seconds() ? 0 : $now + $dismissal->seconds(),
		);

		if ( Notice_Dismissal::SCOPE_SITE === $dismissal->scope() ) {
			// Autoloaded: the notice center reads it on every admin page.
			automator_update_option( self::SITE_OPTION, $map );
			return;
		}

		$this->users->update( $user_id, self::USER_OPTION, $map );
	}

	/**
	 * @param string $scope   Notice_Dismissal::SCOPE_USER or SCOPE_SITE.
	 * @param int    $user_id The user.
	 *
	 * @return array<string, mixed>
	 */
	private function map( string $scope, int $user_id ): array {

		$map = Notice_Dismissal::SCOPE_SITE === $scope
			? automator_get_option( self::SITE_OPTION, array() )
			: $this->users->get( $user_id, self::USER_OPTION );

		return is_array( $map ) ? $map : array();
	}
}
