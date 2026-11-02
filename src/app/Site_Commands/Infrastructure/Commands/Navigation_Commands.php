<?php
/**
 * The "menu", "sidebar" and "widget" commands.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Navigation menus, menu locations, sidebars and the widgets placed in them.
 * Widget settings are not shown; they can hold arbitrary data.
 */
final class Navigation_Commands implements Site_Command_Group {

	private const MENU_FIELDS = array( 'term_id', 'name', 'slug', 'locations', 'count' );
	private const ITEM_FIELDS = array( 'db_id', 'type', 'title', 'link', 'position', 'object', 'object_id', 'parent', 'target' );

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::reads( 'menu list', 'List navigation menus and the locations they fill.' )
				->with_fields( self::MENU_FIELDS, self::MENU_FIELDS, 'Menus' )
				->handled_by( array( $this, 'list_menus' ) ),
			Command_Definition::reads( 'menu item list', 'List the items in one menu.' )
				->with_argument( Command_Argument::required( 'menu', 'Menu ID, slug or name.' ) )
				->with_fields( array( 'db_id', 'type', 'title', 'link', 'position' ), self::ITEM_FIELDS, 'Menu items' )
				->handled_by( array( $this, 'list_items' ) ),
			Command_Definition::reads( 'menu location list', 'List the menu locations the theme registers.' )
				->with_fields( array( 'location', 'description' ), array( 'location', 'description' ), 'Menu locations' )
				->handled_by( array( $this, 'list_locations' ) ),
			Command_Definition::reads( 'sidebar list', 'List registered sidebars (widget areas).' )
				->with_fields( array( 'name', 'id', 'description' ), array( 'name', 'id', 'description' ), 'Sidebars' )
				->handled_by( array( $this, 'list_sidebars' ) ),
			Command_Definition::reads( 'widget list', 'List the widgets placed in one sidebar.' )
				->with_argument( Command_Argument::required( 'sidebar-id', 'Sidebar ID, from sidebar list.' ) )
				->with_fields( array( 'name', 'id', 'position' ), array( 'name', 'id', 'position' ), 'Widgets' )
				->handled_by( array( $this, 'list_widgets' ) ),
		);
	}

	/**
	 * The "menu list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the menus cannot be read.
	 */
	public function list_menus( array $arguments, array $options ): Command_Output {
		$locations = get_nav_menu_locations();
		$menus     = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			throw new Command_Error( 'The menus could not be read.' );
		}
		$rows    = array();
		$skipped = 0;
		foreach ( $menus as $menu ) {
			if ( ! $menu instanceof \WP_Term ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'term_id'   => (int) $menu->term_id,
				'name'      => (string) $menu->name,
				'slug'      => (string) $menu->slug,
				'locations' => implode(
					', ',
					array_keys(
						array_filter(
							(array) $locations,
							static function ( $id ) use ( $menu ): bool {
								return (int) $id === (int) $menu->term_id;
							}
						)
					)
				),
				'count'     => (int) $menu->count,
			);
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "menu item list" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the menu does not exist.
	 */
	public function list_items( array $arguments, array $options ): Command_Output {
		$menu = wp_get_nav_menu_object( Values::is_whole_number( $arguments['menu'] ) ? (int) $arguments['menu'] : $arguments['menu'] );
		if ( ! $menu instanceof \WP_Term ) {
			throw new Command_Error( sprintf( 'Invalid menu %s.', $arguments['menu'] ) );
		}

		$rows  = array();
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) );
		if ( ! is_array( $items ) ) {
			throw new Command_Error( sprintf( 'The items of menu %s could not be read.', $arguments['menu'] ) );
		}
		$skipped = 0;
		foreach ( $items as $item ) {
			$row = is_object( $item ) ? $this->item( $item ) : null;
			if ( null === $row ) {
				++$skipped;
				continue;
			}
			$rows[] = $row;
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * One menu item as a row, or null when any of its fields is not what
	 * WordPress stores, so a damaged item is never shown as an invented one.
	 *
	 * @param object $item Menu item.
	 * @return array<string, scalar>|null
	 */
	private function item( $item ): ?array {
		$numbers = array(
			'db_id'     => Values::whole_number( $item->db_id ?? null ),
			'position'  => Values::whole_number( $item->menu_order ?? 0 ),
			'object_id' => Values::whole_number( $item->object_id ?? 0 ),
			'parent'    => Values::whole_number( $item->menu_item_parent ?? 0 ),
		);
		$texts   = array(
			'type'   => $item->type ?? '',
			'title'  => $item->title ?? '',
			'link'   => $item->url ?? '',
			'object' => $item->object ?? '',
			'target' => $item->target ?? '',
		);
		if ( in_array( null, $numbers, true ) || 0 === $numbers['db_id'] || array() !== array_filter(
			$texts,
			static function ( $value ): bool {
				return ! is_scalar( $value );
			}
		) ) {
			return null;
		}

		return array(
			'db_id'     => $numbers['db_id'],
			'type'      => (string) $texts['type'],
			'title'     => (string) $texts['title'],
			'link'      => (string) $texts['link'],
			'position'  => $numbers['position'],
			'object'    => (string) $texts['object'],
			'object_id' => $numbers['object_id'],
			'parent'    => $numbers['parent'],
			'target'    => (string) $texts['target'],
		);
	}

	/**
	 * The "menu location list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_locations( array $arguments, array $options ): Command_Output {
		$rows = array();
		foreach ( get_registered_nav_menus() as $location => $description ) {
			$rows[] = array(
				'location'    => (string) $location,
				'description' => Values::text( $description ),
			);
		}

		return Command_Output::rows( $rows );
	}

	/**
	 * The "sidebar list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_sidebars( array $arguments, array $options ): Command_Output {
		global $wp_registered_sidebars;

		$rows    = array();
		$skipped = 0;
		foreach ( is_array( $wp_registered_sidebars ) ? $wp_registered_sidebars : array() as $id => $sidebar ) {
			if ( ! is_array( $sidebar ) ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'name'        => Values::text( $sidebar['name'] ?? '' ),
				'id'          => (string) $id,
				'description' => Values::text( $sidebar['description'] ?? '' ),
			);
		}

		// As in WP-CLI, the inactive widgets area is listed too.
		$rows[] = array(
			'name'        => 'Inactive Widgets',
			'id'          => 'wp_inactive_widgets',
			'description' => 'Drag widgets here to remove them from the sidebar but keep their settings.',
		);

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "widget list" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the sidebar is not registered, or the placements are unreadable.
	 */
	public function list_widgets( array $arguments, array $options ): Command_Output {
		global $wp_registered_sidebars;

		$sidebar = $arguments['sidebar-id'];
		if ( 'wp_inactive_widgets' !== $sidebar && ! isset( $wp_registered_sidebars[ $sidebar ] ) ) {
			throw new Command_Error( sprintf( 'Invalid sidebar: %s', $sidebar ) );
		}

		// A sidebar with no widgets has no entry; one that is not a list is unreadable.
		$placed = self::sidebars_widgets();
		if ( ! is_array( $placed ) || ( array_key_exists( $sidebar, $placed ) && ! is_array( $placed[ $sidebar ] ) ) ) {
			throw new Command_Error( 'WordPress\'s widget placements are unreadable, so the widgets cannot be listed.' );
		}
		$rows    = array();
		$skipped = 0;
		foreach ( $placed[ $sidebar ] ?? array() as $position => $widget_id ) {
			if ( ! is_string( $widget_id ) || '' === $widget_id || ! is_int( $position ) ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'name'     => (string) preg_replace( '/-[0-9]+$/', '', $widget_id ),
				'id'       => $widget_id,
				'position' => $position + 1,
			);
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The widget placements, exactly as wp_get_sidebars_widgets() returns them.
	 * Plugin Check forbids calling that private core function, so its steps are
	 * repeated here: the in-memory copy outside wp-admin, the option otherwise,
	 * no "array_version" entry, then the core filter.
	 *
	 * @return mixed The placements; anything but an array is unreadable.
	 */
	private static function sidebars_widgets() {
		global $_wp_sidebars_widgets;

		$placed = ( ! is_admin() && ! empty( $_wp_sidebars_widgets ) ) ? $_wp_sidebars_widgets : get_option( 'sidebars_widgets', array() );
		if ( is_array( $placed ) ) {
			unset( $placed['array_version'] );
		}

		/** This filter is documented in wp-includes/widgets.php */
		return apply_filters( 'sidebars_widgets', $placed ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter, applied as wp_get_sidebars_widgets() applies it.
	}
}
