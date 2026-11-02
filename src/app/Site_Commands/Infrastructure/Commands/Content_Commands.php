<?php
/**
 * The "post", "term" and "comment count" commands.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Posts, terms and comment counts. Post meta, user data and comment content
 * are deliberately not readable here.
 */
final class Content_Commands implements Site_Command_Group {

	private const POST_DEFAULTS  = array( 'ID', 'post_title', 'post_name', 'post_date', 'post_status' );
	private const POST_FIELDS    = array( 'ID', 'post_title', 'post_name', 'post_type', 'post_status', 'post_date', 'post_modified', 'post_author', 'post_parent', 'menu_order', 'comment_status', 'comment_count', 'url' );
	private const POST_GET       = array( 'ID', 'post_title', 'post_name', 'post_type', 'post_status', 'post_date', 'post_modified', 'post_author', 'post_parent', 'menu_order', 'comment_status', 'comment_count', 'url', 'post_excerpt', 'post_content' );
	private const TERM_DEFAULTS  = array( 'term_id', 'term_taxonomy_id', 'name', 'slug', 'description', 'parent', 'count' );
	private const TERM_FIELDS    = array( 'term_id', 'term_taxonomy_id', 'name', 'slug', 'description', 'parent', 'count', 'taxonomy', 'url' );
	private const PAGE_SIZE      = 100;
	private const MAX_PAGE_SIZE  = 1000;
	private const COMMENT_COUNTS = array( 'approved', 'moderated', 'spam', 'trash', 'post-trashed', 'all', 'total_comments' );

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		$taxonomy = Command_Argument::required( 'taxonomy', 'Taxonomy name, such as category.' );

		return array(
			Command_Definition::reads( 'post list', 'List posts, newest first. Shows up to 100 unless --posts_per_page is set (at most 1000); use --paged for more. Stats give the total and counts by status.' )
				->with_option( Command_Option::value( 'post_type', 'Post type, or a comma-separated list, or "any". Default: post.' ) )
				->with_option( Command_Option::value( 'post_status', 'Registered post status, such as publish or draft, or a comma-separated list, or "any". Default: any.' ) )
				->with_option( Command_Option::value( 's', 'Search text.' ) )
				->with_option( Command_Option::number( 'posts_per_page', 'Posts per page. Default: 100.', 1, self::MAX_PAGE_SIZE ) )
				->with_option( Command_Option::number( 'paged', 'Page number, starting at 1.', 1, 100000 ) )
				->with_fields( self::POST_DEFAULTS, self::POST_FIELDS, 'Posts' )
				->handled_by( array( $this, 'list_posts' ) ),
			Command_Definition::reads( 'post get', 'Show one post, including its content.' )
				->with_argument( Command_Argument::required( 'id', 'Post ID.' ) )
				->with_record_fields( self::POST_GET, self::POST_GET )
				->handled_by( array( $this, 'get_post' ) ),
			Command_Definition::reads( 'term list', 'List the terms of a taxonomy, including empty ones. Shows up to 100 unless --number is set (at most 1000); use --offset for more. --format=count counts every term, ignoring --number and --offset.' )
				->with_argument( $taxonomy )
				->with_option( Command_Option::number( 'number', 'Terms per page. Default: 100.', 1, self::MAX_PAGE_SIZE ) )
				->with_option( Command_Option::number( 'offset', 'Terms to skip, starting at 0.', 0, 100000000 ) )
				->with_fields( self::TERM_DEFAULTS, self::TERM_FIELDS, 'Terms' )
				->handled_by( array( $this, 'list_terms' ) ),
			Command_Definition::reads( 'term get', 'Show one term.' )
				->with_argument( $taxonomy )
				->with_argument( Command_Argument::required( 'term', 'Term ID, or slug with --by=slug.' ) )
				->with_option( Command_Option::choice( 'by', 'How to find the term. Default: id.', array( 'id', 'slug' ) ) )
				->with_record_fields( self::TERM_FIELDS, self::TERM_FIELDS )
				->handled_by( array( $this, 'get_term' ) ),
			Command_Definition::reads( 'comment count', 'Count comments by status, for the site or one post.' )
				->with_argument( Command_Argument::optional( 'post-id', 'Post ID.' ) )
				->handled_by( array( $this, 'count_comments' ) ),
		);
	}

	/**
	 * The "post list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When an option value is out of range.
	 */
	public function list_posts( array $arguments, array $options ): Command_Output {
		// As in WP-CLI, types and statuses may be comma-separated lists.
		$types = array_map( 'trim', explode( ',', (string) ( $options['post_type'] ?? 'post' ) ) );
		foreach ( $types as $type ) {
			if ( 'any' !== $type && ! post_type_exists( $type ) ) {
				throw new Command_Error( sprintf( "Post type %s doesn't exist.", $type ) );
			}
		}
		$type = in_array( 'any', $types, true ) ? 'any' : ( 1 === count( $types ) ? $types[0] : $types );
		// WP_Query quietly ignores an unknown status; refuse it instead.
		$statuses = array_map( 'trim', explode( ',', (string) ( $options['post_status'] ?? 'any' ) ) );
		foreach ( $statuses as $status ) {
			if ( 'any' !== $status && ! in_array( $status, get_post_stati(), true ) ) {
				throw new Command_Error( sprintf( 'Unknown post status: %s. Use a registered status such as publish, draft or private, or "any".', $status ) );
			}
		}

		$per_page = (int) ( $options['posts_per_page'] ?? self::PAGE_SIZE );
		$page     = (int) ( $options['paged'] ?? 1 );
		$query    = new \WP_Query(
			array(
				'post_type'           => $type,
				'post_status'         => in_array( 'any', $statuses, true ) ? 'any' : $statuses,
				's'                   => (string) ( $options['s'] ?? '' ),
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
				// The total of every page, so a count never needs paging.
				'no_found_rows'       => false,
			)
		);

		$rows    = array();
		$skipped = 0;
		foreach ( (array) $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$rows[] = $this->post( $post );
			} else {
				++$skipped;
			}
		}

		$total = max( (int) $query->found_posts, count( $rows ) );
		if ( array() === $rows && $page > 1 ) {
			// WordPress counts nothing for a page past the end; count the first page instead.
			$first = new \WP_Query(
				array_merge(
					$query->query,
					array(
						'paged' => 1,
						'posts_per_page' => 1,
						'fields' => 'ids',
					)
				)
			);
			$total = (int) $first->found_posts;
		}
		$output = Values::with_skipped( Command_Output::rows( $rows )->with_total( $total ), $skipped );
		$pages  = max( 1, (int) ceil( $total / $per_page ) );
		if ( $page > $pages ) {
			$output = $output->with_stats( sprintf( 'Page %d is past the last page, %d.', $page, $pages ) );
		} elseif ( $page < $pages ) {
			$output = $output->with_stats( sprintf( 'Page %d of %d. Next page: --paged=%d.', $page, $pages, $page + 1 ) );
		} elseif ( $pages > 1 ) {
			$output = $output->with_stats( sprintf( 'Page %d of %d, the last.', $page, $pages ) );
		}

		return is_string( $type ) && 'any' !== $type ? $this->with_status_counts( $output, $type ) : $output;
	}

	/**
	 * Add how many posts of the type each status holds, across the site and
	 * regardless of the filters, as the recipe list does.
	 *
	 * @param Command_Output $output Output.
	 * @param string         $type   Post type.
	 * @return Command_Output
	 */
	private function with_status_counts( Command_Output $output, string $type ): Command_Output {
		// WordPress counts every registered status, zeros included; a count
		// that is missing or not a number is unreadable, never taken as zero.
		$counts = wp_count_posts( $type );
		if ( ! is_object( $counts ) ) {
			return $output->with_warning( 'Counts by status are unavailable: WordPress\'s post counts are unreadable.' );
		}
		$counts = get_object_vars( $counts );
		$parts  = array();
		foreach ( get_post_stati() as $status ) {
			$count = Values::whole_number( $counts[ $status ] ?? null );
			if ( null === $count ) {
				return $output->with_warning( sprintf( 'Counts by status are unavailable: the "%s" count is unreadable.', $status ) );
			}
			if ( $count > 0 ) {
				$parts[] = $count . ' ' . $status;
			}
		}

		return $output->with_stats( sprintf( 'All "%s" posts on the site by status: %s.', $type, array() === $parts ? 'none' : implode( ', ', $parts ) ) );
	}

	/**
	 * The "post get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the post does not exist.
	 */
	public function get_post( array $arguments, array $options ): Command_Output {
		// get_post( 0 ) returns the current global post, so IDs start at 1.
		$post = $this->positive_id( $arguments['id'] ) ? get_post( (int) $arguments['id'] ) : null;
		if ( ! $post instanceof \WP_Post ) {
			throw new Command_Error( sprintf( 'Could not find the post with ID %s.', $arguments['id'] ) );
		}

		return Command_Output::record(
			$this->post( $post ) + array(
				'post_excerpt' => (string) $post->post_excerpt,
				'post_content' => (string) $post->post_content,
			)
		);
	}

	/**
	 * The "term list" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function list_terms( array $arguments, array $options ): Command_Output {
		$taxonomy = $this->taxonomy( $arguments['taxonomy'] );
		$query    = array(
			'taxonomy'               => $taxonomy,
			'hide_empty'             => false,
			// No hierarchy processing is needed when empty terms are included.
			// Otherwise WP_Term_Query loads the entire taxonomy before paging.
			'hierarchical'           => false,
			'update_term_meta_cache' => false,
			'suppress_filter'        => true,
		);
		// --field takes precedence over --format=count, as in WP-CLI.
		if ( 'count' === ( $options['format'] ?? null ) && ! isset( $options['field'] ) ) {
			return Command_Output::rows( array() )->with_total( $this->term_count( $query ) );
		}
		$number = (int) ( $options['number'] ?? self::PAGE_SIZE );
		$offset = (int) ( $options['offset'] ?? 0 );
		$terms  = get_terms(
			$query + array(
				'number' => $number,
				'offset' => $offset,
			)
		);
		// A WP_Error's message can carry server details, so it is not repeated.
		if ( ! is_array( $terms ) ) {
			throw new Command_Error( 'The terms could not be read.' );
		}
		if ( count( $terms ) > $number ) {
			throw new Command_Error( 'The terms query exceeded the page limit. Another plugin may have changed the query.' );
		}

		$rows    = array();
		$skipped = 0;
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$rows[] = $this->term( $term );
			} else {
				++$skipped;
			}
		}

		// A short first page is already the whole list. Keeping that path also
		// preserves the warning for plugins that return unreadable entries.
		$total  = 0 === $offset && count( $terms ) < $number ? count( $rows ) : max( count( $rows ), $this->term_count( $query ) );
		$output = Values::with_skipped( Command_Output::rows( $rows )->with_total( $total ), $skipped );
		if ( $offset >= $total && $offset > 0 ) {
			return $output->with_stats( sprintf( 'Offset %d is past the last term; %d terms exist.', $offset, $total ) );
		}
		if ( $offset > 0 || count( $rows ) < $total ) {
			$next = $offset + $number;
			return $output->with_stats( 'Results are partial. ' . ( $next < $total ? sprintf( 'Next page: --offset=%d.', $next ) : 'This is the last page.' ) );
		}

		return $output;
	}

	/**
	 * Count in the database without creating a term object for every row.
	 *
	 * @param array $query The same taxonomy and visibility as the page query.
	 * @return int
	 *
	 * @throws Command_Error When WordPress or a plugin returns an unreadable count.
	 */
	private function term_count( array $query ): int {
		$total = Values::whole_number( get_terms( $query + array( 'fields' => 'count' ) ) );
		if ( null === $total ) {
			throw new Command_Error( 'The terms could not be read.' );
		}

		return $total;
	}

	/**
	 * The "term get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the term does not exist.
	 */
	public function get_term( array $arguments, array $options ): Command_Output {
		$taxonomy = $this->taxonomy( $arguments['taxonomy'] );
		$by       = (string) ( $options['by'] ?? 'id' );
		$term     = 'slug' === $by
			? get_term_by( 'slug', $arguments['term'], $taxonomy )
			: ( $this->positive_id( $arguments['term'] ) ? get_term( (int) $arguments['term'], $taxonomy ) : null );
		if ( ! $term instanceof \WP_Term ) {
			throw new Command_Error( sprintf( 'Term doesn\'t exist: %s', $arguments['term'] ) );
		}

		return Command_Output::record( $this->term( $term ) );
	}

	/**
	 * The "comment count" command, in WP-CLI's layout.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the post ID is not a number or the post does not exist.
	 */
	public function count_comments( array $arguments, array $options ): Command_Output {
		$post_id = 0;
		if ( isset( $arguments['post-id'] ) ) {
			if ( ! $this->positive_id( $arguments['post-id'] ) ) {
				throw new Command_Error( sprintf( 'Invalid post ID: %s', $arguments['post-id'] ) );
			}
			$post_id = (int) $arguments['post-id'];
			// Zero counts for a post that does not exist would read as "no comments".
			if ( ! get_post( $post_id ) instanceof \WP_Post ) {
				throw new Command_Error( sprintf( 'Could not find the post with ID %d.', $post_id ) );
			}
		}

		// Only WordPress's own count fields, so a filter cannot add other data.
		$counts = wp_count_comments( $post_id );
		$counts = is_object( $counts ) ? get_object_vars( $counts ) : ( is_array( $counts ) ? $counts : array() );
		$width  = max( array_map( 'strlen', self::COMMENT_COUNTS ) ) + 3;
		$lines  = array();
		foreach ( self::COMMENT_COUNTS as $status ) {
			$count = Values::text( $counts[ $status ] ?? '' );
			if ( ! Values::is_whole_number( $count ) ) {
				throw new Command_Error( 'The comment counts are unreadable.' );
			}
			$lines[] = str_pad( $status . ':', $width ) . $count;
		}

		return Command_Output::text( implode( "\n", $lines ) );
	}

	/**
	 * One post as a row.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, scalar>
	 */
	private function post( \WP_Post $post ): array {
		return array(
			'ID'             => (int) $post->ID,
			'post_title'     => (string) $post->post_title,
			'post_name'      => (string) $post->post_name,
			'post_type'      => (string) $post->post_type,
			'post_status'    => (string) $post->post_status,
			'post_date'      => (string) $post->post_date,
			'post_modified'  => (string) $post->post_modified,
			'post_author'    => (int) $post->post_author,
			'post_parent'    => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'comment_status' => (string) $post->comment_status,
			'comment_count'  => (int) $post->comment_count,
			'url'            => (string) get_permalink( $post ),
		);
	}

	/**
	 * One term as a row.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<string, scalar>
	 */
	private function term( \WP_Term $term ): array {
		return array(
			'term_id'          => (int) $term->term_id,
			'term_taxonomy_id' => (int) $term->term_taxonomy_id,
			'name'             => (string) $term->name,
			'slug'             => (string) $term->slug,
			'description'      => (string) $term->description,
			'parent'           => (int) $term->parent,
			'count'            => (int) $term->count,
			'taxonomy'         => (string) $term->taxonomy,
			'url'              => Values::text( get_term_link( $term ) ),
		);
	}

	/**
	 * A registered taxonomy name, or a WP-CLI style error.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 *
	 * @throws Command_Error When it is not registered.
	 */
	private function taxonomy( string $taxonomy ): string {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new Command_Error( sprintf( "Taxonomy %s doesn't exist.", $taxonomy ) );
		}

		return $taxonomy;
	}

	/**
	 * Whether the text is an ID of 1 or more.
	 *
	 * @param string $value Text.
	 * @return bool
	 */
	private function positive_id( string $value ): bool {
		return Values::is_whole_number( $value ) && (int) $value > 0;
	}
}
