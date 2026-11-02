<?php
/**
 * Most Active Recipes Service
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Recipe\Services
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * The recipes that ran the most, for the Dashboard's Most active recipes card:
 * up to five, each with a plain-text title, the run count the Recipes list
 * shows, and its editor and logs links.
 */
final class Most_Active_Recipes_Service {

	/** The most recipes the card lists. */
	public const LIMIT = 5;

	private Execution_Log_Store $store;

	private Recipe_Url_Service $urls;

	/**
	 * Constructor.
	 *
	 * @param Execution_Log_Store|null $store Run log store.
	 * @param Recipe_Url_Service|null  $urls  Recipe admin URLs.
	 */
	public function __construct( ?Execution_Log_Store $store = null, ?Recipe_Url_Service $urls = null ) {
		$this->store = $store ?? Database::get_execution_log_store();
		$this->urls  = $urls ?? Recipe_Url_Service::instance();
	}

	/**
	 * The most active recipes, most runs first. Empty when no recipe has run.
	 *
	 * @return array<int,array{id:int,title:string,runs:int,links:array{edit:string,logs:string}}>
	 *
	 * @throws Store_Exception When the run counts cannot be read.
	 */
	public function get_most_active(): array {
		return array_map(
			function ( array $recipe ): array {
				return array(
					'id'    => $recipe['id'],
					'title' => html_entity_decode( wp_strip_all_tags( $recipe['title'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'runs'  => $recipe['runs'],
					'links' => array(
						'edit' => $this->urls->url_edit_recipe( $recipe['id'] ),
						'logs' => $this->urls->url_logs( $recipe['id'] ),
					),
				);
			},
			$this->store->get_most_active_recipes( self::LIMIT )
		);
	}
}
