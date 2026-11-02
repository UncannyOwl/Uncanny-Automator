<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Notices;

use InvalidArgumentException;
use Throwable;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;

/**
 * The one place recipe notices are held and asked.
 *
 * Notices are added here (Free, from `Application_Bootstrap`) or through the
 * `automator_recipe_notices` filter (Pro, add-ons). `for_recipe()` asks each,
 * in the order added and then the filter's order, and answers the recipe
 * wire's `notices` list. A notice that throws is left out and logged, and so
 * is a filter callback that throws, so one broken notice or add-on never takes
 * a recipe read down with it.
 *
 * The recipe-wire sibling of `Presentation\Admin\Notices\Notice_Center`,
 * without the admin machinery: no screens, capabilities, dismissals or skins.
 *
 * @since 8.0.0
 */
final class Recipe_Notice_Center {

	/**
	 * Filter that receives the notices other plugins add: Recipe_Notice[] in, Recipe_Notice[] out.
	 */
	public const FILTER = 'automator_recipe_notices';

	/**
	 * What a notice code may be: lower-case snake_case, as violation codes are.
	 */
	private const CODE_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

	/**
	 * The notices added here, by code, in the order added.
	 *
	 * @var array<string, Recipe_Notice>
	 */
	private array $notices = array();

	/**
	 * Add a notice.
	 *
	 * Each notice is one class, so adding the class that already holds the code
	 * again changes nothing: the plugin's startup can run twice in one process.
	 * A notice of another class under a taken code is refused.
	 *
	 * @param Recipe_Notice $notice The notice.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException For a malformed code, or one another notice class already holds.
	 */
	public function add( Recipe_Notice $notice ): void {
		$code = $notice->code();

		if ( 1 !== preg_match( self::CODE_PATTERN, $code ) ) {
			throw new InvalidArgumentException( sprintf( 'Recipe notice code "%s" must be lower-case snake_case.', esc_html( $code ) ) );
		}

		if ( isset( $this->notices[ $code ] ) ) {
			if ( get_class( $this->notices[ $code ] ) === get_class( $notice ) ) {
				return;
			}

			throw new InvalidArgumentException( sprintf( 'Recipe notice code "%s" is already taken.', esc_html( $code ) ) );
		}

		$this->notices[ $code ] = $notice;
	}

	/**
	 * The notices that apply to the recipe right now, as the wire's `notices` list.
	 *
	 * Each entry is `code`, then the message's `type`, `heading` (when set) and
	 * `message`. Null when none applies: the wire then leaves the key out and
	 * never carries an empty list.
	 *
	 * @param int $recipe_id The recipe being read.
	 *
	 * @return list<array<string, string>>|null
	 */
	public function for_recipe( int $recipe_id ): ?array {
		if ( $recipe_id < 1 ) {
			return null;
		}

		$notices = array();

		foreach ( $this->all_notices() as $code => $notice ) {
			try {
				$message = $notice->message( $recipe_id );
			} catch ( Throwable $e ) {
				$this->log_failure( $code, $e );
				continue;
			}

			if ( null !== $message ) {
				$notices[] = array_merge( array( Wire_Key::CODE => $code ), $message->to_wire() );
			}
		}

		return array() === $notices ? null : $notices;
	}

	/**
	 * The notices added here, then those added through the filter: by code, first code wins.
	 *
	 * A filter entry that is not a notice, has a malformed code, or throws for its code is left out.
	 * A filter callback that throws is logged and ends the filter for this read: the notices added
	 * here still answer, the filter's do not, since WordPress cannot resume the callbacks after it.
	 * WordPress's filter stack is put back first, so the failure leaves no filter looking active.
	 *
	 * @return array<string, Recipe_Notice>
	 */
	private function all_notices(): array {
		$notices = $this->notices;

		try {
			$added = Dispatcher::filter_unwinding_on_throw( self::FILTER, array() );
		} catch ( Throwable $e ) {
			$this->log_failure( self::FILTER, $e );

			return $notices;
		}

		foreach ( is_array( $added ) ? $added : array() as $notice ) {
			if ( ! $notice instanceof Recipe_Notice ) {
				continue;
			}

			try {
				$code = $notice->code();
			} catch ( Throwable $e ) {
				$this->log_failure( get_class( $notice ), $e );
				continue;
			}

			if ( 1 === preg_match( self::CODE_PATTERN, $code ) && ! isset( $notices[ $code ] ) ) {
				$notices[ $code ] = $notice;
			}
		}

		return $notices;
	}

	/**
	 * Log a notice that failed, so the recipe read goes on without it. Logged
	 * whether or not debug logging is on: the failure is otherwise silent.
	 *
	 * @param string    $code The notice's code (its class when the code itself failed, the filter's name when the filter did).
	 * @param Throwable $e    What it threw.
	 *
	 * @return void
	 */
	private function log_failure( string $code, Throwable $e ): void {
		automator_log( $e->getMessage(), 'Recipe notice "' . $code . '" failed', true, 'recipe-notices' );
	}
}
