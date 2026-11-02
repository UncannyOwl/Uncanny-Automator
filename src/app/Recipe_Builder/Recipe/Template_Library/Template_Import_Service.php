<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Template_Library\Template_Library_Client;
use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\Import_Recipe;
use WP_Error;

/**
 * Imports a library template as a new local recipe.
 *
 * Orchestration only: the fetch goes through the client (always fresh —
 * never the preview cache), the import through the shared core
 * Import_Recipe entry (old/new format detection, imported-recipe warning
 * meta, friendly engine-error mapping), the legacy-parity
 * `automator_recipe_imported` action through the Dispatcher, and the
 * usage event into the same `usage_report_stats` option the weekly
 * report reads.
 */
final class Template_Import_Service {

	/**
	 * Usage-report event key (legacy parity).
	 *
	 * @var string
	 */
	const USAGE_EVENT = 'template-library-import';

	/**
	 * Usage-report stats option (mirrors Usage_Reports::STATS_OPTION_NAME).
	 *
	 * @var string
	 */
	const STATS_OPTION_NAME = 'usage_report_stats';

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Upstream client.
	 *
	 * @var Template_Library_Client
	 */
	private $client;

	/**
	 * Core import entry point.
	 *
	 * @var Import_Recipe
	 */
	private $importer;

	/**
	 * Lazy-default constructor injection (Rule 9c). The importer default
	 * reuses the boot-time core instance when present so admin hooks are
	 * not double-registered.
	 *
	 * @param Template_Library_Client|null $client   Upstream client.
	 * @param Import_Recipe|null           $importer Core import entry.
	 */
	public function __construct( ?Template_Library_Client $client = null, ?Import_Recipe $importer = null ) {
		$this->client = $client ?? Template_Library_Client::instance();

		if ( null === $importer ) {
			$importer = Automator_Load::get_core_class_instance( 'Import_Recipe' );
			$importer = is_a( $importer, Import_Recipe::class ) ? $importer : new Import_Recipe();
		}
		$this->importer = $importer;
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
	 * Import one template; returns exactly `{ redirect: <edit URL> }`.
	 *
	 * @param int $template_id Upstream template id.
	 * @return array{redirect: string}|WP_Error
	 */
	public function import( int $template_id ) {
		$export = $this->client->get_template_export( $template_id );
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$result = $this->importer->import_recipe_json( $export );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_recipe_id = (int) $result;

		Dispatcher::action( 'automator_recipe_imported', $new_recipe_id );

		$this->log_usage_event( $this->template_title( $export ) );

		$redirect = get_edit_post_link( $new_recipe_id, 'url' );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			return new WP_Error(
				'template_import_failed',
				esc_html_x( 'The recipe was imported but its edit URL could not be built.', 'Template library', 'uncanny-automator' )
			);
		}

		return array( 'redirect' => $redirect );
	}

	/**
	 * Recipe title from either export format (legacy `recipe->post`,
	 * portable-document `recipe.title`).
	 *
	 * @param object $export Decoded export JSON.
	 * @return string
	 */
	private function template_title( object $export ): string {
		if ( isset( $export->recipe->post->post_title ) ) {
			return (string) $export->recipe->post->post_title;
		}
		if ( isset( $export->recipe->title ) ) {
			return (string) $export->recipe->title;
		}

		return '';
	}

	/**
	 * Append the imported template's title to the usage-report stats
	 * option (same storage the weekly Usage_Reports cron transmits).
	 *
	 * @param string $title Recipe title.
	 * @return void
	 */
	private function log_usage_event( string $title ): void {
		$stats = automator_get_option( self::STATS_OPTION_NAME, array( 'events' => array() ) );
		if ( ! isset( $stats['events'][ self::USAGE_EVENT ] ) || ! is_array( $stats['events'][ self::USAGE_EVENT ] ) ) {
			$stats['events'][ self::USAGE_EVENT ] = array();
		}
		$stats['events'][ self::USAGE_EVENT ][] = $title;
		automator_update_option( self::STATS_OPTION_NAME, $stats );
	}
}
