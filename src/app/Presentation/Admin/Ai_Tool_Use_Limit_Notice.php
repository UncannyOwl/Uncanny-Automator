<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use DateTime;
use DateTimeZone;
use Throwable;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;

/**
 * "Your AI reached today's limit of N tool uses", on a Lite site (every paid
 * plan is unlimited), on the Dashboard and Automator's screens, while the AI
 * meter the Dashboard shows is at its limit. "Wait for the reset" (or the X)
 * hides it for that user until the reset: the meter's own reset time, else
 * the next 6 AM in Toronto.
 *
 * @since 8.0.0
 */
final class Ai_Tool_Use_Limit_Notice extends Notice {

	/**
	 * Filter that may supply the status in place of the meter:
	 * `array{used: int, limit?: int, resets_at?: int}` or null.
	 */
	public const FILTER_STATUS = 'automator_ai_tool_use_limit_status';

	public const DEFAULT_LIMIT = 150;

	public const RESET_TIMEZONE = 'America/Toronto';

	public const RESET_HOUR = 6;

	/**
	 * @var callable(): mixed
	 */
	private $meter;

	/**
	 * @var array{used: int, limit: int, resets_at: int}|null|false False until read.
	 */
	private $meter_status = false;

	/**
	 * @param callable|null $meter Returns the hosted AI meter (`unlimited`, `used`, `limit`, `resets_at`); the Dashboard's when not given.
	 */
	public function __construct( ?callable $meter = null ) {

		$this->meter = $meter ?? static function () {
			return Hosted_Ai_Dashboard::instance()->ai_tool_uses();
		};
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'ai-tool-use-limit';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * Until the reset, for the user who waits.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_user( max( 1, $this->reset_timestamp() - time() ) );
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( is_automator_pro_active() || ! $this->is_supported_screen( $screen ) ) {
			return null;
		}

		$status = $this->status();
		if ( null === $status || $status['limit'] <= 0 || $status['used'] < $status['limit'] ) {
			return null;
		}

		return $this->limit_reached( $status['limit'] );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->limit_reached( self::DEFAULT_LIMIT ) );
	}

	/**
	 * @param int $limit Today's tool uses.
	 *
	 * @return Notice_Message
	 */
	private function limit_reached( int $limit ): Notice_Message {

		$plans_url = add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-pro-upgrade',
			),
			admin_url( 'edit.php' )
		);

		return Notice_Message::warning( esc_html_x( "Your AI reached today's tool-use limit", 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph(
				sprintf(
					/* translators: %1$s: number of tool uses in today's limit. %2$s: the time the limit resets, in the site's timezone. */
					esc_html__( "Your AI reached today's limit of %1\$s tool uses on this site. It resets at %2\$s. Every paid plan has unlimited tool usage.", 'uncanny-automator' ),
					esc_html( number_format_i18n( $limit ) ),
					esc_html( $this->reset_time_label() )
				)
			)
			->with_action( Notice_Action::link( esc_html__( 'See plans', 'uncanny-automator' ), $plans_url )->primary() )
			->with_action( Notice_Action::dismiss( esc_html__( 'Wait for the reset', 'uncanny-automator' ) ) );
	}

	/**
	 * When today's limit resets, as a clock time in the site's timezone: the
	 * same moment the Dashboard's usage card counts down to.
	 *
	 * @return string
	 */
	private function reset_time_label(): string {
		return wp_date( (string) get_option( 'time_format' ), $this->reset_timestamp() );
	}

	/**
	 * Today's use: from the filter when something supplies it, else the meter.
	 *
	 * @return array{limit: int, used: int, resets_at: int}|null
	 */
	public function status(): ?array {

		$status = Dispatcher::filter( self::FILTER_STATUS, $this->read_meter() );

		if ( ! is_array( $status ) || ! isset( $status['used'] ) ) {
			return null;
		}

		return array(
			'limit'     => isset( $status['limit'] ) ? (int) $status['limit'] : self::DEFAULT_LIMIT,
			'used'      => (int) $status['used'],
			'resets_at' => isset( $status['resets_at'] ) ? (int) $status['resets_at'] : 0,
		);
	}

	/**
	 * When today's limit resets: the meter's time when it is still ahead,
	 * else the next 6 AM in Toronto.
	 *
	 * @return int Unix time.
	 */
	public function reset_timestamp(): int {

		$status = $this->status();
		if ( null !== $status && $status['resets_at'] > time() ) {
			return $status['resets_at'];
		}

		$reset = new DateTime( 'now', new DateTimeZone( self::RESET_TIMEZONE ) );
		$reset->setTime( self::RESET_HOUR, 0, 0 );
		if ( $reset->getTimestamp() <= time() ) {
			$reset->modify( '+1 day' );
		}

		return $reset->getTimestamp();
	}

	/**
	 * The Dashboard and Automator's recipe screens.
	 *
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return bool
	 */
	private function is_supported_screen( Notice_Screen $screen ): bool {

		$id = $screen->id();

		return 'dashboard' === $id
			|| 0 === strpos( $id, AUTOMATOR_POST_TYPE_RECIPE )
			|| 0 === strpos( $id, 'edit-' . AUTOMATOR_POST_TYPE_RECIPE );
	}

	/**
	 * The meter as a status, once per request; null for an unlimited plan or
	 * a meter that cannot be read.
	 *
	 * @return array{used: int, limit: int, resets_at: int}|null
	 */
	private function read_meter(): ?array {

		if ( false !== $this->meter_status ) {
			return $this->meter_status;
		}

		try {
			$meter = ( $this->meter )();
		} catch ( Throwable $error ) {
			$meter = null;
		}

		$this->meter_status = ! is_array( $meter ) || ! empty( $meter['unlimited'] )
			? null
			: array(
				'used'      => (int) $meter['used'],
				'limit'     => (int) $meter['limit'],
				'resets_at' => (int) strtotime( (string) $meter['resets_at'] ),
			);

		return $this->meter_status;
	}
}
