<?php
/**
 * The "post-type" and "taxonomy" command groups.
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
 * How the site's content is structured: registered post types and taxonomies.
 */
final class Post_Type_Commands implements Site_Command_Group {

	private const TYPE_DEFAULTS     = array( 'name', 'label', 'description', 'hierarchical', 'public', 'capability_type' );
	private const TYPE_FIELDS       = array( 'name', 'label', 'description', 'hierarchical', 'public', 'capability_type', 'show_in_rest', 'rest_base', 'has_archive', 'supports', 'taxonomies' );
	private const TAXONOMY_DEFAULTS = array( 'name', 'label', 'description', 'object_type', 'show_tagcloud', 'hierarchical', 'public' );
	private const TAXONOMY_FIELDS   = array( 'name', 'label', 'description', 'object_type', 'show_tagcloud', 'hierarchical', 'public', 'show_in_rest', 'rest_base' );

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::reads( 'post-type list', 'List registered post types.' )
				->with_fields( self::TYPE_DEFAULTS, self::TYPE_FIELDS, 'Post types' )
				->handled_by( array( $this, 'list_post_types' ) ),
			Command_Definition::reads( 'post-type get', 'Show details about one post type.' )
				->with_argument( Command_Argument::required( 'post-type', 'Post type name, such as page.' ) )
				->with_record_fields( self::TYPE_FIELDS, self::TYPE_FIELDS )
				->handled_by( array( $this, 'get_post_type' ) ),
			Command_Definition::reads( 'taxonomy list', 'List registered taxonomies.' )
				->with_fields( self::TAXONOMY_DEFAULTS, self::TAXONOMY_FIELDS, 'Taxonomies' )
				->handled_by( array( $this, 'list_taxonomies' ) ),
			Command_Definition::reads( 'taxonomy get', 'Show details about one taxonomy.' )
				->with_argument( Command_Argument::required( 'taxonomy', 'Taxonomy name, such as category.' ) )
				->with_record_fields( self::TAXONOMY_FIELDS, self::TAXONOMY_FIELDS )
				->handled_by( array( $this, 'get_taxonomy' ) ),
		);
	}

	/**
	 * The "post-type list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_post_types( array $arguments, array $options ): Command_Output {
		$rows    = array();
		$skipped = 0;
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( $type instanceof \WP_Post_Type ) {
				$rows[] = $this->post_type( $type );
			} else {
				++$skipped;
			}
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "post-type get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the post type is not registered.
	 */
	public function get_post_type( array $arguments, array $options ): Command_Output {
		$type = get_post_type_object( $arguments['post-type'] );
		if ( ! $type instanceof \WP_Post_Type ) {
			throw new Command_Error( sprintf( "Post type %s doesn't exist.", $arguments['post-type'] ) );
		}

		return Command_Output::record( $this->post_type( $type ) );
	}

	/**
	 * The "taxonomy list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_taxonomies( array $arguments, array $options ): Command_Output {
		$rows    = array();
		$skipped = 0;
		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			if ( $taxonomy instanceof \WP_Taxonomy ) {
				$rows[] = $this->taxonomy( $taxonomy );
			} else {
				++$skipped;
			}
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "taxonomy get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the taxonomy is not registered.
	 */
	public function get_taxonomy( array $arguments, array $options ): Command_Output {
		$taxonomy = get_taxonomy( $arguments['taxonomy'] );
		if ( ! $taxonomy instanceof \WP_Taxonomy ) {
			throw new Command_Error( sprintf( "Taxonomy %s doesn't exist.", $arguments['taxonomy'] ) );
		}

		return Command_Output::record( $this->taxonomy( $taxonomy ) );
	}

	/**
	 * One post type as a row. A yes-or-no property that holds something else
	 * is printed as unknown (empty, or null in JSON), never as true.
	 *
	 * @param \WP_Post_Type $type Post type.
	 * @return array<string, scalar|null>
	 */
	private function post_type( \WP_Post_Type $type ): array {
		$supports = get_all_post_type_supports( $type->name );

		return array(
			'name'            => $type->name,
			'label'           => Values::text( $type->label ),
			'description'     => Values::text( $type->description ),
			'hierarchical'    => Values::flag( $type->hierarchical ),
			'public'          => Values::flag( $type->public ),
			'capability_type' => is_array( $type->capability_type ) ? implode( ', ', array_filter( $type->capability_type, 'is_scalar' ) ) : Values::text( $type->capability_type ),
			'show_in_rest'    => Values::flag( $type->show_in_rest ),
			'rest_base'       => is_string( $type->rest_base ) ? $type->rest_base : '',
			'has_archive'     => is_bool( $type->has_archive ) ? $type->has_archive : Values::text( $type->has_archive ),
			'supports'        => implode( ', ', array_keys( is_array( $supports ) ? $supports : array() ) ),
			'taxonomies'      => implode( ', ', get_object_taxonomies( $type->name ) ),
		);
	}

	/**
	 * One taxonomy as a row.
	 *
	 * @param \WP_Taxonomy $taxonomy Taxonomy.
	 * @return array<string, scalar|null>
	 */
	private function taxonomy( \WP_Taxonomy $taxonomy ): array {
		$object_type = is_array( $taxonomy->object_type ) ? $taxonomy->object_type : array();

		return array(
			'name'          => $taxonomy->name,
			'label'         => Values::text( $taxonomy->label ),
			'description'   => Values::text( $taxonomy->description ),
			'object_type'   => implode( ', ', array_filter( $object_type, 'is_scalar' ) ),
			'show_tagcloud' => Values::flag( $taxonomy->show_tagcloud ),
			'hierarchical'  => Values::flag( $taxonomy->hierarchical ),
			'public'        => Values::flag( $taxonomy->public ),
			'show_in_rest'  => Values::flag( $taxonomy->show_in_rest ),
			'rest_base'     => is_string( $taxonomy->rest_base ) ? $taxonomy->rest_base : '',
		);
	}
}
