<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

use InvalidArgumentException;

/**
 * What a notice says right now: its severity, a heading, paragraphs, an
 * optional list whose rows may carry their own action, the buttons, and
 * whether U-bot presents it. A value; every `with_*()` returns a new one.
 *
 * Every text is escaped where it is translated (`esc_html_x()`): the heading
 * is escaped text; paragraphs and list rows are escaped HTML the notice
 * built (a `<code>` address, a link). The skins still pass them through a
 * small allow-list, and decode the heading before an attribute escapes it.
 *
 * @since 8.0.0
 */
final class Notice_Message {

	/**
	 * @var string
	 */
	private $severity;

	/**
	 * @var string
	 */
	private $heading;

	/**
	 * @var string[]
	 */
	private $paragraphs = array();

	/**
	 * @var array<int, array{html: string, action: Notice_Action|null}>
	 */
	private $items = array();

	/**
	 * @var Notice_Action[]
	 */
	private $actions = array();

	/**
	 * @var bool
	 */
	private $mascot = false;

	/**
	 * @var string
	 */
	private $state = '';

	/**
	 * @var array{label: string, text: string}|null
	 */
	private $report = null;

	/**
	 * @var int|null
	 */
	private $priority = null;

	/**
	 * @param string $severity A Notice_Severity constant.
	 * @param string $heading  Escaped text.
	 */
	private function __construct( string $severity, string $heading ) {

		$this->severity = $severity;
		$this->heading  = $heading;
	}

	/**
	 * @param string $severity A Notice_Severity constant.
	 * @param string $heading  Escaped text.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException For an unknown severity or an empty heading.
	 */
	public static function create( string $severity, string $heading ): self {

		if ( ! Notice_Severity::is_valid( $severity ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown notice severity "%s".', $severity ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer message, never printed.
		}

		if ( '' === trim( $heading ) ) {
			throw new InvalidArgumentException( 'A notice needs a heading.' );
		}

		return new self( $severity, $heading );
	}

	/**
	 * @param string $heading Escaped text.
	 *
	 * @return self
	 */
	public static function error( string $heading ): self {

		return self::create( Notice_Severity::ERROR, $heading );
	}

	/**
	 * @param string $heading Escaped text.
	 *
	 * @return self
	 */
	public static function warning( string $heading ): self {

		return self::create( Notice_Severity::WARNING, $heading );
	}

	/**
	 * @param string $heading Escaped text.
	 *
	 * @return self
	 */
	public static function success( string $heading ): self {

		return self::create( Notice_Severity::SUCCESS, $heading );
	}

	/**
	 * @param string $heading Escaped text.
	 *
	 * @return self
	 */
	public static function info( string $heading ): self {

		return self::create( Notice_Severity::INFO, $heading );
	}

	/**
	 * @param string $html Escaped HTML.
	 *
	 * @return self
	 */
	public function with_paragraph( string $html ): self {

		$message               = clone $this;
		$message->paragraphs[] = $html;

		return $message;
	}

	/**
	 * A list row, with an optional action of its own (Remove, for one).
	 *
	 * @param string             $html   Escaped HTML.
	 * @param Notice_Action|null $action The row's action.
	 *
	 * @return self
	 */
	public function with_item( string $html, ?Notice_Action $action = null ): self {

		$message          = clone $this;
		$message->items[] = array(
			'html'   => $html,
			'action' => $action,
		);

		return $message;
	}

	/**
	 * @param Notice_Action $action A button.
	 *
	 * @return self
	 */
	public function with_action( Notice_Action $action ): self {

		$message            = clone $this;
		$message->actions[] = $action;

		return $message;
	}

	/**
	 * U-bot presents it: for information and education, never for a problem.
	 *
	 * @return self
	 */
	public function with_mascot(): self {

		$message         = clone $this;
		$message->mascot = true;

		return $message;
	}

	/**
	 * A plain-text report the admin can copy and send to support, folded
	 * behind a label.
	 *
	 * @param string $label Escaped text: what opens it.
	 * @param string $text  Plain text, escaped by the skin.
	 *
	 * @return self
	 */
	public function with_report( string $label, string $text ): self {

		$message         = clone $this;
		$message->report = array(
			'label' => $label,
			'text'  => $text,
		);

		return $message;
	}

	/**
	 * @return array{label: string, text: string}|null
	 */
	public function report(): ?array {

		return $this->report;
	}

	/**
	 * Name the state the notice is about, printed as `data-state` for styling
	 * and tests (`refused`, `move-pending`).
	 *
	 * @param string $state Lower case letters, digits, `-` and `_`.
	 *
	 * @return self
	 */
	public function with_state( string $state ): self {

		$message        = clone $this;
		$message->state = $state;

		return $message;
	}

	/**
	 * @return string The state it is about; '' when not named.
	 */
	public function state(): string {

		return $this->state;
	}

	/**
	 * The same message with a priority of its own, for a notice whose
	 * variants are not all as urgent: it wins over the notice's priority().
	 *
	 * @param int $priority Lower comes first.
	 *
	 * @return self
	 */
	public function with_priority( int $priority ): self {

		$message           = clone $this;
		$message->priority = $priority;

		return $message;
	}

	/**
	 * @return int|null Its own priority; null when the notice's decides.
	 */
	public function priority(): ?int {

		return $this->priority;
	}

	/**
	 * @return string A Notice_Severity constant.
	 */
	public function severity(): string {

		return $this->severity;
	}

	/**
	 * @return string Escaped text.
	 */
	public function heading(): string {

		return $this->heading;
	}

	/**
	 * @return string[] Escaped HTML, one per paragraph.
	 */
	public function paragraphs(): array {

		return $this->paragraphs;
	}

	/**
	 * @return array<int, array{html: string, action: Notice_Action|null}>
	 */
	public function items(): array {

		return $this->items;
	}

	/**
	 * @return Notice_Action[]
	 */
	public function actions(): array {

		return $this->actions;
	}

	/**
	 * @return bool
	 */
	public function has_mascot(): bool {

		return $this->mascot;
	}
}
