<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Notices;

use InvalidArgumentException;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Severity;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;

/**
 * What a recipe notice says: how serious it is, the message, and an optional heading.
 *
 * The severity is a `Notice_Severity` value, sent as the wire `type`. The
 * message may use the limited Markdown violation messages use (`**bold**`,
 * `*italic*`, `[links](https://...)`), never HTML. The constructor refuses
 * whatever the builder's strict notice parse would refuse, so a bad notice
 * fails here and not in the builder.
 *
 * @since 8.0.0
 */
final class Recipe_Notice_Message {

	/**
	 * How serious the notice is: a `Notice_Severity` value.
	 *
	 * @var string
	 */
	private string $severity;

	/**
	 * The localized message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * The localized heading shown above the message, or null for none.
	 *
	 * @var string|null
	 */
	private ?string $heading;

	/**
	 * Validate and hold the notice's text.
	 *
	 * @param string      $severity A `Notice_Severity` value.
	 * @param string      $message  Localized message.
	 * @param string|null $heading  Localized heading, or null for none.
	 *
	 * @throws InvalidArgumentException For an unknown severity, a blank message, or a blank heading.
	 */
	public function __construct( string $severity, string $message, ?string $heading = null ) {
		if ( ! Notice_Severity::is_valid( $severity ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown recipe notice severity "%s".', esc_html( $severity ) ) );
		}

		if ( '' === trim( $message ) ) {
			throw new InvalidArgumentException( 'A recipe notice message must not be blank.' );
		}

		if ( null !== $heading && '' === trim( $heading ) ) {
			throw new InvalidArgumentException( 'A recipe notice heading must be null or not blank.' );
		}

		$this->severity = $severity;
		$this->message  = $message;
		$this->heading  = $heading;
	}

	/**
	 * The notice's wire fields after its code: `type`, `heading` when there is one, then `message`.
	 *
	 * @return array<string, string>
	 */
	public function to_wire(): array {
		$wire = array( Wire_Key::TYPE => $this->severity );

		if ( null !== $this->heading ) {
			$wire[ Wire_Key::HEADING ] = $this->heading;
		}

		$wire[ Wire_Key::MESSAGE ] = $this->message;

		return $wire;
	}
}
