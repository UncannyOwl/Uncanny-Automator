<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

use Uncanny_Automator\App\Infrastructure\Database\Stores\Recipe_Schema_Store;

/**
 * Recipe schema wiring.
 *
 * Constructs Recipe_Schema_Lifecycle over Recipe_Schema_Store and registers its
 * invalidate-only hooks.
 *
 * Idempotent — safe to call register() multiple times in one PHP request.
 */
final class Recipe_Schema_Bootstrap {

	/**
	 * Guards against wiring the schema hooks more than once per request.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Wire the recipe-schema lifecycle. No-op if already registered in
	 * this PHP request.
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		$lifecycle = new Recipe_Schema_Lifecycle( Recipe_Schema_Store::instance() );

		$lifecycle->register_hooks();
	}
}
