<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\Automator_Review;

/**
 * A connected Lite site running low on app credits, by tier: 100 or fewer
 * left (information, U-bot), 25 or fewer (a warning), none (an error). On the
 * screens the credits notice always used. A dismissal hides that tier and
 * every tier above it for the site, in the options the site always used; a
 * dismissed tier gives way to the next one up.
 *
 * Reads the cached license only: a notice never calls the license API, so
 * with no cached license it says nothing.
 *
 * @since 8.0.0
 */
final class App_Credits_Notice extends Notice {

	/**
	 * A tier's dismissal: this prefix plus 0, 25 or 100, set to `hide-forever`.
	 */
	public const OPTION_PREFIX = '_uncanny_credits_notification_';

	/**
	 * Tiers, lowest first.
	 */
	private const TIERS = array( 0, 25, 100 );

	private const PRICING_URL = 'https://automatorplugin.com/pricing/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=%s&utm_campaign=get-unlimited-credits';

	/**
	 * @return string
	 */
	public function id(): string {

		return 'app-credits';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * For the site, for the tier showing now.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_site( 0, (string) $this->tier( $this->credits_left() ) );
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->is_credits_screen( $screen ) || ! $this->is_due() ) {
			return null;
		}

		$left = (int) $this->credits_left();

		return $this->tier_message( (int) $this->tier( $left ), $left, trim( (string) ( $this->license()['customer_name'] ?? '' ) ) );
	}

	/**
	 * One for each tier.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array(
			$this->tier_message( 100, 80, 'Alex' ),
			$this->tier_message( 25, 20, 'Alex' ),
			$this->tier_message( 0, 0, 'Alex' ),
		);
	}

	/**
	 * @param int    $tier The tier: 0, 25 or 100.
	 * @param int    $left Credits left.
	 * @param string $name The account's customer name.
	 *
	 * @return Notice_Message
	 */
	private function tier_message( int $tier, int $left, string $name ): Notice_Message {

		$learn_more = '<a target="_blank" href="https://automatorplugin.com/knowledge-base/what-are-credits/">' . esc_html__( 'Learn more', 'uncanny-automator' ) . '</a>';

		if ( 0 === $tier ) {
			return Notice_Message::error( esc_html_x( 'Critical issue: There are no credits left in your account!', 'Reviews banner', 'uncanny-automator' ) )
				->with_state( 'credits-0' )
				->with_priority( 30 )
				->with_paragraph( esc_html_x( 'Recipes with app integrations are not working because your Uncanny Automator account has run out of credits. Upgrade to Pro to get unlimited credits and continue using app integrations.', 'Reviews banner', 'uncanny-automator' ) )
				->with_action( Notice_Action::link( esc_html_x( 'Upgrade now', 'Reviews banner', 'uncanny-automator' ), sprintf( self::PRICING_URL, 'credit-notification-0-credits-left' ) )->primary() )
				->with_action( Notice_Action::dismiss( esc_html_x( 'Dismiss', 'Reviews banner', 'uncanny-automator' ) ) );
		}

		if ( 25 === $tier ) {
			return Notice_Message::warning(
				sprintf(
					/* translators: 1. Number of credits remaining */
					esc_html__( 'Warning! Only %d app credits left in your Uncanny Automator account!', 'uncanny-automator' ),
					$left
				)
			)
				->with_state( 'credits-25' )
				->with_paragraph( esc_html__( 'When you reach 0 credits, recipes with app integrations will stop working.', 'uncanny-automator' ) . ' ' . $learn_more )
				->with_action( Notice_Action::link( esc_html_x( 'Upgrade', 'Reviews banner', 'uncanny-automator' ), sprintf( self::PRICING_URL, 'credit-notification-25-credits-left' ) )->primary() )
				->with_action( Notice_Action::dismiss( esc_html_x( 'Dismiss', 'Reviews banner', 'uncanny-automator' ) ) );
		}

		return Notice_Message::info(
			sprintf(
				// translators: 1: Customer name, 2: Number of credits remaining
				esc_html__( 'Hey %1$s! You have %2$d app credits remaining for app integrations in Uncanny Automator!', 'uncanny-automator' ),
				esc_html( $name ),
				$left
			)
		)
			->with_state( 'credits-100' )
			->with_mascot()
			->with_paragraph( esc_html__( 'When credits run out, app integrations will stop working.', 'uncanny-automator' ) . ' ' . $learn_more )
			->with_action( Notice_Action::link( esc_html_x( 'Get unlimited credits', 'Reviews banner', 'uncanny-automator' ), sprintf( self::PRICING_URL, 'credit-notification' ) )->primary() )
			->with_action( Notice_Action::dismiss( esc_html_x( 'Dismiss', 'Reviews banner', 'uncanny-automator' ) ) );
	}

	/**
	 * Whether a tier is due on this site, whatever the screen: Lite, a cached
	 * license, and credits left in a tier the site has not dismissed. The
	 * review banner stays away while one is.
	 *
	 * @return bool
	 */
	public function is_due(): bool {

		return ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) && null !== $this->tier( $this->credits_left() );
	}

	/**
	 * Dismissed: hide this tier and every tier above it.
	 *
	 * @return void
	 */
	public function dismissed(): void {

		$tier = $this->tier( $this->credits_left() );
		if ( null === $tier ) {
			return;
		}

		foreach ( self::TIERS as $each ) {
			if ( $each >= $tier ) {
				automator_update_option( self::OPTION_PREFIX . $each, 'hide-forever', true );
			}
		}
	}

	/**
	 * The lowest tier the credits left fall in that the site has not
	 * dismissed; null when none.
	 *
	 * @param int|null $left Credits left.
	 *
	 * @return int|null
	 */
	private function tier( ?int $left ): ?int {

		if ( null === $left ) {
			return null;
		}

		foreach ( self::TIERS as $tier ) {
			if ( $left <= $tier && 'hide-forever' !== automator_get_option( self::OPTION_PREFIX . $tier ) ) {
				return $tier;
			}
		}

		return null;
	}

	/**
	 * The app credits left on the connected account; 250 (none used) when the
	 * account reports no usage yet; null with no cached connected license.
	 *
	 * @return int|null
	 */
	private function credits_left(): ?int {

		$license = $this->license();
		if ( array() === $license ) {
			return null;
		}

		$left = empty( $license['usage_limit'] ) || empty( $license['paid_usage_count'] )
			? 250
			: absint( (int) $license['usage_limit'] - (int) $license['paid_usage_count'] );

		return (int) Dispatcher::filter( 'automator_review_get_credits_remaining', $left, $this );
	}

	/**
	 * The cached license; empty when none is cached.
	 *
	 * @return array<string, mixed>
	 */
	private function license(): array {

		$license = get_transient( License_Manager::TRANSIENT_LICENSE );

		return is_array( $license ) ? $license : array();
	}

	/**
	 * Where the credits notice always showed: Automator's screens, the
	 * Dashboard, Plugins, Posts and Pages lists; never the old App
	 * integrations page.
	 *
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return bool
	 */
	private function is_credits_screen( Notice_Screen $screen ): bool {

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which screen is showing.

		return 'uncanny-automator-app-integrations' !== $page
			&& in_array( $screen->id(), Automator_Review::get_allowed_page_for_credits_notif(), true );
	}
}
