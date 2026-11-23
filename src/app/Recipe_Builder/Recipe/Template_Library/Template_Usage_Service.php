<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Bridge\Automator_Usage_Report_Bridge;
use Uncanny_Automator\App\Bridge\Usage_Report_Bridge;

/**
 * Records template library searches, filter picks and imports for the usage report.
 *
 * Search and import keep the 7.x keys. Every event's value is a plain
 * string, which Usage_Reports::get_template_library_info() groups by value.
 *
 * @since 8.1.0
 */
final class Template_Usage_Service {

	/**
	 * Usage-report key for a library search.
	 *
	 * @var string
	 */
	const SEARCH_EVENT = 'template-library-search';

	/**
	 * Usage-report key for a picked category, recorded by its name.
	 *
	 * @var string
	 */
	const CATEGORY_EVENT = 'template-library-category';

	/**
	 * Usage-report key for a picked integration, recorded by its name.
	 *
	 * @var string
	 */
	const INTEGRATION_EVENT = 'template-library-integration';

	/**
	 * Usage-report key for a template import.
	 *
	 * @var string
	 */
	const IMPORT_EVENT = 'template-library-import';

	/**
	 * Shortest search term worth recording, in characters.
	 *
	 * @var int
	 */
	const MIN_SEARCH_LENGTH = 3;

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Usage-report recorder.
	 *
	 * @var Usage_Report_Bridge
	 */
	private $reports;

	/**
	 * Lazy-default constructor injection.
	 *
	 * @param Usage_Report_Bridge|null $reports Usage-report recorder.
	 */
	public function __construct( ?Usage_Report_Bridge $reports = null ) {
		$this->reports = $reports ?? new Automator_Usage_Report_Bridge();
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Record one library event the page sends: a search term, or the name
	 * of a picked category or integration. Ignores an unknown type.
	 *
	 * @param string $type  `search`, `category` or `integration`.
	 * @param string $value The term as typed, or the name as shown.
	 * @return void
	 */
	public function record( string $type, string $value ): void {
		switch ( $type ) {
			case 'search':
				$this->record_search( $value );
				break;
			case 'category':
				$this->record_name( self::CATEGORY_EVENT, $value );
				break;
			case 'integration':
				$this->record_name( self::INTEGRATION_EVENT, $value );
				break;
		}
	}

	/**
	 * Record a search term, trimmed and lowercased. Ignores a term shorter
	 * than three characters or with no letter in it (any language).
	 *
	 * WordPress polyfills mb_strlen() but not mb_strtolower(), so a host
	 * without mbstring lowercases with strtolower() (ASCII letters only).
	 *
	 * @param string $term The term as typed.
	 * @return void
	 */
	private function record_search( string $term ): void {
		$term = trim( $term );
		$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term );

		if ( mb_strlen( $term ) < self::MIN_SEARCH_LENGTH || 1 !== preg_match( '/\p{L}/u', $term ) ) {
			return;
		}

		$this->reports->record_event( self::SEARCH_EVENT, $term );
	}

	/**
	 * Record a picked category's or integration's name, trimmed. Ignores an empty name.
	 *
	 * @param string $event The usage-report key.
	 * @param string $name  The name as shown.
	 * @return void
	 */
	private function record_name( string $event, string $name ): void {
		$name = trim( $name );

		if ( '' === $name ) {
			return;
		}

		$this->reports->record_event( $event, $name );
	}

	/**
	 * Record an imported template's title.
	 *
	 * @param string $title The template title.
	 * @return void
	 */
	public function record_import( string $title ): void {
		$this->reports->record_event( self::IMPORT_EVENT, $title );
	}
}
