<?php
/**
 * What one site command is, accepts and does.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain;

use Uncanny_Automator\App\Site_Commands\Domain\Output\Formats;

/**
 * Every command carries one honest label: it reads, or it changes the site.
 * Help, the synopsis, input checks and access checks all come from this one
 * record, and each command names its own handler, so a new command can never
 * run another command's code.
 *
 * Definitions are immutable; each with_*() returns a changed copy.
 */
final class Command_Definition {

	public const MAX_PATH_WORDS = 3;

	private string $path;
	private string $summary;
	private bool $read_only;

	/**
	 * Positional arguments in order.
	 *
	 * @var Command_Argument[]
	 */
	private array $arguments = array();

	/**
	 * Command-specific options by name.
	 *
	 * @var array<string, Command_Option>
	 */
	private array $options = array();

	/**
	 * Fields shown without --fields; empty for commands that print text.
	 *
	 * @var string[]
	 */
	private array $default_fields = array();

	/**
	 * Fields --fields and --field may choose from.
	 *
	 * @var string[]
	 */
	private array $fields = array();

	/**
	 * Whether the output is one item rather than a list.
	 *
	 * @var bool
	 */
	private bool $record = false;

	/**
	 * What a list holds, such as "Plugins", for its stats line.
	 *
	 * @var string
	 */
	private string $label = 'Items';

	/**
	 * Runs the command: function( array $arguments, array $options ): Output\Command_Output.
	 *
	 * @var callable|null
	 */
	private $handler = null;

	/**
	 * Whether the command reads its own command line, as file commands do,
	 * instead of declared arguments and options.
	 *
	 * @var bool
	 */
	private bool $takes_line = false;

	/**
	 * Builds the help text of a command that reads its own command line.
	 *
	 * @var callable|null
	 */
	private $help = null;

	/**
	 * Validate the path once, where the command is declared.
	 *
	 * @param string $path      Command path, such as "plugin list".
	 * @param string $summary   One-sentence description.
	 * @param bool   $read_only False when the command changes the site.
	 *
	 * @throws \InvalidArgumentException When the path is malformed.
	 */
	private function __construct( string $path, string $summary, bool $read_only ) {
		$words = explode( ' ', $path );
		if ( count( $words ) > self::MAX_PATH_WORDS || 1 !== preg_match( '/^[a-z][a-z0-9-]*( [a-z][a-z0-9-]*)*$/', $path ) ) {
			throw new \InvalidArgumentException( 'Invalid site command path: ' . $path );
		}

		$this->path      = $path;
		$this->summary   = $summary;
		$this->read_only = $read_only;
	}

	/**
	 * A command that only reads. Running it writes nothing to the site: not
	 * to the database, the object cache or files. WordPress's read functions
	 * fill caches as they go, so the runner isolates the handler (see
	 * Ports\Read_Isolation). It never contacts another service and never
	 * executes a data file, such as a translation or .maintenance file, to
	 * read it. See POLICY.md.
	 *
	 * @param string $path    Command path.
	 * @param string $summary One-sentence description.
	 * @return self
	 */
	public static function reads( string $path, string $summary ): self {
		return new self( $path, $summary, true );
	}

	/**
	 * A command that changes the site.
	 *
	 * @param string $path    Command path.
	 * @param string $summary One-sentence description.
	 * @return self
	 */
	public static function changes( string $path, string $summary ): self {
		return new self( $path, $summary, false );
	}

	/**
	 * A read-only command that reads its own command line, such as "grep -rn
	 * x wp-content", including short options and pipes to other such
	 * commands. Its handler receives the whole line as the "line" argument.
	 *
	 * @param string   $path    Command name.
	 * @param string   $summary One-sentence description.
	 * @param callable $help    Builds its help text: function(): string.
	 * @return self
	 */
	public static function reads_line( string $path, string $summary, callable $help ): self {
		$definition             = new self( $path, $summary, true );
		$definition->takes_line = true;
		$definition->help       = $help;

		return $definition;
	}

	/**
	 * Add the next positional argument.
	 *
	 * @param Command_Argument $argument Argument.
	 * @return self
	 *
	 * @throws \InvalidArgumentException When a required argument follows an optional one.
	 */
	public function with_argument( Command_Argument $argument ): self {
		$last = end( $this->arguments );
		if ( $last instanceof Command_Argument && ! $last->is_required() && $argument->is_required() ) {
			throw new \InvalidArgumentException( 'A required argument cannot follow an optional one: ' . $this->path );
		}

		$copy              = clone $this;
		$copy->arguments[] = $argument;

		return $copy;
	}

	/**
	 * Add a command-specific option.
	 *
	 * @param Command_Option $option Option.
	 * @return self
	 *
	 * @throws \InvalidArgumentException When the name is taken.
	 */
	public function with_option( Command_Option $option ): self {
		if ( isset( $this->options[ $option->name() ] ) || in_array( $option->name(), Formats::OPTIONS, true ) ) {
			throw new \InvalidArgumentException( 'Duplicate site command option: ' . $option->name() );
		}

		$copy                             = clone $this;
		$copy->options[ $option->name() ] = $option;

		return $copy;
	}

	/**
	 * Mark the output as rows of items that accept --format, --fields and --field.
	 *
	 * @param string[] $default_fields Fields shown by default, in order.
	 * @param string[] $fields         Every field a row carries.
	 * @param string   $label          What the list holds, such as "Plugins".
	 * @return self
	 *
	 * @throws \InvalidArgumentException When a default field is not a known field.
	 */
	public function with_fields( array $default_fields, array $fields, string $label = 'Items' ): self {
		if ( array() === $default_fields || array_diff( $default_fields, $fields ) ) {
			throw new \InvalidArgumentException( 'Default fields must be known fields: ' . $this->path );
		}

		$copy                 = clone $this;
		$copy->default_fields = array_values( $default_fields );
		$copy->fields         = array_values( $fields );
		$copy->label          = $label;

		return $copy;
	}

	/**
	 * Mark the output as one item that accepts --format, --fields and --field.
	 *
	 * @param string[] $default_fields Fields shown by default, in order.
	 * @param string[] $fields         Every field the item carries.
	 * @return self
	 */
	public function with_record_fields( array $default_fields, array $fields ): self {
		$copy         = $this->with_fields( $default_fields, $fields );
		$copy->record = true;

		return $copy;
	}

	/**
	 * Name the code that runs this command.
	 *
	 * @param callable $handler function( array $arguments, array $options ): Output\Command_Output.
	 * @return self
	 */
	public function handled_by( callable $handler ): self {
		$copy          = clone $this;
		$copy->handler = $handler;

		return $copy;
	}

	/**
	 * Command path, such as "plugin list".
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * First word of the path, such as "plugin".
	 *
	 * @return string
	 */
	public function group(): string {
		return explode( ' ', $this->path, 2 )[0];
	}

	/**
	 * One-sentence description.
	 *
	 * @return string
	 */
	public function summary(): string {
		return $this->summary;
	}

	/**
	 * Whether the command only reads.
	 *
	 * @return bool
	 */
	public function is_read_only(): bool {
		return $this->read_only;
	}

	/**
	 * Whether the command reads its own command line.
	 *
	 * @return bool
	 */
	public function takes_line(): bool {
		return $this->takes_line;
	}

	/**
	 * The help text of a command that reads its own command line, or null.
	 *
	 * @return string|null
	 */
	public function help(): ?string {
		return null === $this->help ? null : (string) call_user_func( $this->help );
	}

	/**
	 * Whether the command prints rows of items.
	 *
	 * @return bool
	 */
	public function lists_items(): bool {
		return array() !== $this->fields;
	}

	/**
	 * What a list holds, such as "Plugins".
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Positional arguments in order.
	 *
	 * @return Command_Argument[]
	 */
	public function arguments(): array {
		return $this->arguments;
	}

	/**
	 * Fields shown without --fields.
	 *
	 * @return string[]
	 */
	public function default_fields(): array {
		return $this->default_fields;
	}

	/**
	 * Every field a row carries.
	 *
	 * @return string[]
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * The handler, or null when the declaration is incomplete.
	 *
	 * @return callable|null
	 */
	public function handler(): ?callable {
		return $this->handler;
	}

	/**
	 * Command options, plus the shared output options for item lists.
	 *
	 * @return array<string, Command_Option>
	 */
	public function options(): array {
		if ( ! $this->lists_items() ) {
			return $this->options;
		}

		return $this->options + array(
			Formats::FIELDS_OPTION => Command_Option::value( Formats::FIELDS_OPTION, 'Comma-separated fields to show instead of the default fields.' ),
			Formats::FIELD_OPTION  => Command_Option::value( Formats::FIELD_OPTION, 'Print the value of one field for each item.' ),
			Formats::FORMAT_OPTION => Command_Option::choice( Formats::FORMAT_OPTION, 'Output format.', $this->record ? Formats::RECORD : Formats::ALL ),
		);
	}

	/**
	 * The usage line, generated from the declaration so it cannot drift.
	 *
	 * @return string
	 */
	public function synopsis(): string {
		$parts = array( $this->path );
		foreach ( $this->arguments as $argument ) {
			$parts[] = $argument->synopsis();
		}
		foreach ( $this->options() as $option ) {
			$parts[] = $option->synopsis();
		}

		return implode( ' ', $parts );
	}
}
