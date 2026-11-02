<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * How many notices of each kind show at once in Automator's stack: urgent
 * (errors, and any notice given a priority under the default), warnings,
 * and information (information and success). Past its kind's limit, a
 * notice folds behind one "more notices" row.
 *
 * @since 8.0.0
 */
final class Notice_Limits {

	/**
	 * Errors, and notices Automator judged more urgent.
	 */
	public const URGENT = 'urgent';

	/**
	 * Warnings.
	 */
	public const WARNING = 'warning';

	/**
	 * Information and success.
	 */
	public const INFO = 'info';

	/**
	 * @var array<string, int>
	 */
	private $limits;

	/**
	 * @param int $urgent  Urgent notices at once.
	 * @param int $warning Warnings at once.
	 * @param int $info    Information and success notices at once.
	 */
	public function __construct( int $urgent = 2, int $warning = 1, int $info = 1 ) {

		$this->limits = array(
			self::URGENT  => max( 1, $urgent ),
			self::WARNING => max( 1, $warning ),
			self::INFO    => max( 1, $info ),
		);
	}

	/**
	 * The kind a notice counts as.
	 *
	 * @param string $severity A Notice_Severity value.
	 * @param int    $priority The notice's priority.
	 *
	 * @return string self::URGENT, self::WARNING or self::INFO.
	 */
	public function kind( string $severity, int $priority ): string {

		if ( Notice_Severity::ERROR === $severity || Notice::DEFAULT_PRIORITY > $priority ) {
			return self::URGENT;
		}

		return Notice_Severity::WARNING === $severity ? self::WARNING : self::INFO;
	}

	/**
	 * How many of a kind show at once.
	 *
	 * @param string $kind self::URGENT, self::WARNING or self::INFO.
	 *
	 * @return int
	 */
	public function limit( string $kind ): int {

		return $this->limits[ $kind ];
	}
}
