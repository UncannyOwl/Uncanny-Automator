<?php
/**
 * The "comment" commands that change the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Moderate comments as WP-CLI does: approve, unapprove, mark as spam, and
 * take out of spam. Every change can be undone with another of these
 * commands; nothing is deleted or moved to the trash here, though Akismet
 * deletes spam older than 15 days. WordPress's own functions make the
 * change, so its hooks run as on the Comments screen.
 */
final class Comment_Manage_Commands implements Site_Command_Group {

	/** Most comments one command changes. */
	private const MAX_COMMENTS = 100;

	/** Time to change one comment, whose hooks run then, and answer. */
	private const COMMENT_SECONDS = 2.0;

	private const FIELDS = array( 'id', 'post', 'author', 'from', 'to', 'status', 'detail' );

	/** Comment types the Comments screen moderates; order notes and editor notes are left alone. */
	private const TYPES = array( '', 'comment', 'pingback', 'trackback', 'review' );

	/** Longest author name shown. */
	private const AUTHOR_LENGTH = 40;

	/**
	 * What each command does: the target status, the WordPress statuses it
	 * applies to, the statuses that already are its result, and how a result
	 * reads.
	 *
	 * @var array<string, array{to: string, from: string[], done: string[], already: string, verb: string}>
	 */
	private const ACTIONS = array(
		'approve'   => array(
			'to'      => 'approved',
			'from'    => array( 'unapproved' ),
			'done'    => array( 'approved' ),
			'already' => 'It is already approved.',
			'verb'    => 'approved',
		),
		'unapprove' => array(
			'to'      => 'unapproved',
			'from'    => array( 'approved' ),
			'done'    => array( 'unapproved' ),
			'already' => 'It is already unapproved.',
			'verb'    => 'unapproved',
		),
		'spam'      => array(
			'to'      => 'spam',
			'from'    => array( 'approved', 'unapproved' ),
			'done'    => array( 'spam' ),
			'already' => 'It is already marked as spam.',
			'verb'    => 'marked as spam',
		),
		'unspam'    => array(
			'to'      => 'not spam',
			'from'    => array( 'spam' ),
			'done'    => array( 'approved', 'unapproved' ),
			'already' => 'It is not in spam.',
			'verb'    => 'taken out of spam',
		),
	);

	/**
	 * Starts the time budget of each command.
	 *
	 * @var callable(): Time_Budget
	 */
	private $budget;

	/**
	 * Use the current request's time budget unless another is supplied.
	 *
	 * @param callable(): Time_Budget|null $budget Starts a command's time budget.
	 */
	public function __construct( ?callable $budget = null ) {
		$this->budget = $budget ?? array( Request_Time_Budget::class, 'start' );
	}

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		$summaries = array(
			'approve'   => 'Approve comments, which shows them on the site.',
			'unapprove' => 'Unapprove comments, which hides them until they are approved again.',
			'spam'      => 'Mark comments as spam, which hides them. Run "comment unspam" to undo it. Nothing is deleted now, but Akismet, when active, deletes spam older than 15 days.',
			'unspam'    => 'Take comments out of spam; each returns to the status WordPress recorded before, or to unapproved.',
		);
		$commands  = array();
		foreach ( $summaries as $action => $summary ) {
			$commands[] = Command_Definition::changes( 'comment ' . $action, $summary . ' Name comment IDs, or use --post with --status for one post\'s comments, up to ' . self::MAX_COMMENTS . ' at a time.' )
				->with_argument( Command_Argument::optional( 'ids', 'Comment IDs, separated by commas, such as 12,15. Omit them with --post.' ) )
				->with_option( Command_Option::number( 'post', 'Change the comments on this post, by its ID.', 1, PHP_INT_MAX ) )
				->with_option( Command_Option::choice( 'status', 'With --post: which of its comments to change.', self::ACTIONS[ $action ]['from'] ) )
				->with_fields( self::FIELDS, self::FIELDS, 'Comments' )
				->handled_by(
					function ( array $arguments, array $options ) use ( $action ): Command_Output {
						return $this->moderate( $action, $arguments, $options );
					}
				);
		}

		return $commands;
	}

	/**
	 * Change comments to the status a command sets.
	 *
	 * @param string                     $action    approve, unapprove, spam or unspam.
	 * @param array<string, string>      $arguments The comment IDs, unless --post.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the comments cannot be chosen or changed.
	 */
	private function moderate( string $action, array $arguments, array $options ): Command_Output {
		$ids  = $arguments['ids'] ?? null;
		$post = isset( $options['post'] ) ? (int) $options['post'] : null;
		if ( ( null === $ids ) === ( null === $post ) ) {
			throw new Command_Error( sprintf( 'Name comment IDs, or use --post with --status, but not both. Usage: comment %s [<ids>] [--post=<id> --status=<status>]. Nothing was changed.', $action ) );
		}
		if ( null !== $post && ! isset( $options['status'] ) ) {
			throw new Command_Error( sprintf( 'Say which of the post\'s comments to change: --status=%s. Nothing was changed.', implode( '|', self::ACTIONS[ $action ]['from'] ) ) );
		}
		if ( null === $post && isset( $options['status'] ) ) {
			throw new Command_Error( 'The option --status chooses comments of one post; use it with --post. Nothing was changed.' );
		}
		Site_Changes::require_capability( 'moderate_comments', 'moderate comments' );

		list( $chosen, $more ) = null === $ids ? $this->post_comments( $post, (string) $options['status'] ) : array( $this->parse_ids( $ids ), false );

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $action, $chosen, $more, $post ): Command_Output {
				$session->budget()->require( self::COMMENT_SECONDS, 'Changing comments', 'Comments' );
				$rows = array();
				foreach ( $chosen as $id ) {
					$rows[] = $this->change( $session->budget(), $action, $id );
				}
				if ( array() === $rows ) {
					return Command_Output::text( sprintf( 'Post %d has no comments with that status. Nothing was changed.', (int) $post ) );
				}

				$warnings = array();
				if ( $more ) {
					$warnings[] = sprintf( 'More comments on post %d match; up to %d are changed at a time. Run the command again for the rest.', (int) $post, self::MAX_COMMENTS );
				}
				if ( 'approve' === $action && in_array( 'changed', array_column( $rows, 'status' ), true ) ) {
					$warnings[] = 'Approved comments are now visible on the site.';
				}

				return Packages::output( $rows, false, $warnings, 'changed', null === $post );
			}
		);
	}

	/**
	 * Change one comment, or say why not.
	 *
	 * @param Time_Budget $budget Time left.
	 * @param string      $action approve, unapprove, spam or unspam.
	 * @param int         $id     Comment ID.
	 * @return array<string, string>
	 */
	private function change( Time_Budget $budget, string $action, int $id ): array {
		$comment = get_comment( $id );
		$row     = array(
			'id'     => (string) $id,
			'post'   => '',
			'author' => '',
			'from'   => '',
			'to'     => self::ACTIONS[ $action ]['to'],
			'status' => 'skipped',
			'detail' => '',
		);
		if ( ! $comment instanceof \WP_Comment ) {
			$row['detail'] = 'No comment has this ID.';
			return $row;
		}
		if ( ! current_user_can( 'edit_comment', $id ) ) {
			$row['detail'] = 'The WordPress user this connection uses may not edit this comment.';
			return $row;
		}
		$row['post']   = (string) $comment->comment_post_ID;
		$row['author'] = self::author( $comment );
		$row['from']   = self::status( $comment );
		if ( ! in_array( (string) $comment->comment_type, self::TYPES, true ) ) {
			$type          = trim( str_replace( array( '_', '-' ), ' ', Site_Changes::text( $comment->comment_type ) ) );
			$row['detail'] = sprintf( 'It is %s %s, not a comment the Comments screen moderates, so it was left alone.', 1 === preg_match( '/^[aeiou]/i', $type ) ? 'an' : 'a', $type );
			return $row;
		}
		if ( 'post-trashed' === (string) $comment->comment_approved ) {
			$row['detail'] = 'Its post is in the trash; it comes back when the post is restored.';
			return $row;
		}
		if ( 'trash' === $row['from'] ) {
			$row['detail'] = 'It is in the trash. Restore it from Comments in WordPress first.';
			return $row;
		}
		if ( in_array( $row['from'], self::ACTIONS[ $action ]['done'], true ) ) {
			$row['status'] = 'unchanged';
			$row['detail'] = self::ACTIONS[ $action ]['already'];
			return $row;
		}
		if ( ! in_array( $row['from'], self::ACTIONS[ $action ]['from'], true ) ) {
			$row['detail'] = sprintf( 'It is %s, so it was not %s.', $row['from'], self::ACTIONS[ $action ]['verb'] )
				. ( 'spam' === $row['from'] ? sprintf( ' Run "comment unspam %d" first.', $id ) : '' );
			return $row;
		}
		if ( ! $budget->allows( self::COMMENT_SECONDS ) ) {
			$row['status'] = 'not started';
			$row['detail'] = $budget->shortfall( self::COMMENT_SECONDS, 'This change' ) . ' ' . $budget->next_step( self::COMMENT_SECONDS );
			return $row;
		}

		try {
			$done = $this->apply( $action, $id );
		} catch ( \Throwable $throwable ) {
			$row['status'] = 'failed';
			$row['detail'] = sprintf( 'Code on this site stopped the change with %s. Check the comment\'s status before trying again.', get_class( $throwable ) );
			return $row;
		}
		clean_comment_cache( $id );
		$now = get_comment( $id );
		if ( true !== $done || ! $now instanceof \WP_Comment || ! in_array( self::status( $now ), self::reached( $action ), true ) ) {
			$state         = $now instanceof \WP_Comment ? self::status( $now ) : 'unknown';
			$row['status'] = 'failed';
			$row['detail'] = ( is_wp_error( $done ) ? Site_Changes::text( $done->get_error_message() ) . ' ' : 'WordPress did not change the comment\'s status; a plugin may have stopped it. ' ) . sprintf( 'It is %s now.', $state );
			return $row;
		}
		$row['status'] = 'changed';
		$row['to']     = self::status( $now );

		return $row;
	}

	/**
	 * Make the change with WordPress's own function for it.
	 *
	 * @param string $action approve, unapprove, spam or unspam.
	 * @param int    $id     Comment ID.
	 * @return bool|\WP_Error
	 */
	private function apply( string $action, int $id ) {
		switch ( $action ) {
			case 'approve':
				return wp_set_comment_status( $id, 'approve', true );
			case 'unapprove':
				return wp_set_comment_status( $id, 'hold', true );
			case 'spam':
				return (bool) wp_spam_comment( $id );
			default:
				return (bool) wp_unspam_comment( $id );
		}
	}

	/**
	 * Statuses a comment may have after the change.
	 *
	 * @param string $action approve, unapprove, spam or unspam.
	 * @return string[]
	 */
	private static function reached( string $action ): array {
		$reached = array(
			'approve'   => array( 'approved' ),
			'unapprove' => array( 'unapproved' ),
			'spam'      => array( 'spam' ),
			// It returns to the status it had before it was marked as spam.
			'unspam'    => array( 'approved', 'unapproved' ),
		);

		return $reached[ $action ];
	}

	/**
	 * A comment's status, as people know it.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return string approved, unapproved, spam or trash.
	 */
	private static function status( \WP_Comment $comment ): string {
		$names = array(
			'1'            => 'approved',
			'0'            => 'unapproved',
			'spam'         => 'spam',
			'trash'        => 'trash',
			'post-trashed' => 'trash with its post',
		);

		return $names[ (string) $comment->comment_approved ] ?? (string) $comment->comment_approved;
	}

	/**
	 * A comment's author name, shortened when long.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return string
	 */
	private static function author( \WP_Comment $comment ): string {
		$author = Site_Changes::text( $comment->comment_author );

		return mb_strlen( $author ) > self::AUTHOR_LENGTH ? rtrim( mb_substr( $author, 0, self::AUTHOR_LENGTH - 1 ) ) . '…' : $author;
	}

	/**
	 * Comment IDs as written: whole numbers separated by commas.
	 *
	 * @param string $ids IDs.
	 * @return int[]
	 *
	 * @throws Command_Error When they are not, or too many.
	 */
	private function parse_ids( string $ids ): array {
		if ( 1 !== preg_match( '/^[1-9][0-9]{0,18}(,[1-9][0-9]{0,18})*$/', $ids ) ) {
			throw new Command_Error( sprintf( '"%s" is not a list of comment IDs. Separate the IDs with commas and no spaces, such as 12,15. Nothing was changed.', $ids ) );
		}
		$list = array_values( array_unique( array_map( 'intval', explode( ',', $ids ) ) ) );
		if ( count( $list ) > self::MAX_COMMENTS ) {
			throw new Command_Error( sprintf( 'Up to %d comments can be changed at a time; %d were named. Nothing was changed.', self::MAX_COMMENTS, count( $list ) ) );
		}

		return $list;
	}

	/**
	 * The first comments on a post with a status, oldest first.
	 *
	 * @param int    $post   Post ID.
	 * @param string $status approved, unapproved or spam.
	 * @return array{0: int[], 1: bool} Comment IDs, and whether more match.
	 *
	 * @throws Command_Error When the post does not exist.
	 */
	private function post_comments( int $post, string $status ): array {
		if ( ! get_post( $post ) instanceof \WP_Post ) {
			throw new Command_Error( sprintf( 'No post has the ID %d. Nothing was changed.', $post ) );
		}
		$query = array(
			'approved'   => 'approve',
			'unapproved' => 'hold',
			'spam'       => 'spam',
		);
		$ids   = array_map(
			'intval',
			(array) get_comments(
				array(
					'post_id'  => $post,
					'status'   => $query[ $status ],
					// The types the Comments screen moderates; "comment" includes those without a type.
					'type__in' => array( 'comment', 'pingback', 'trackback', 'review' ),
					'fields'   => 'ids',
					'number'   => self::MAX_COMMENTS + 1,
					'orderby'  => 'comment_ID',
					'order'    => 'ASC',
				)
			)
		);

		return array( array_slice( $ids, 0, self::MAX_COMMENTS ), count( $ids ) > self::MAX_COMMENTS );
	}

	/**
	 * A fresh time budget for one command.
	 *
	 * @return Time_Budget
	 */
	private function budget(): Time_Budget {
		return call_user_func( $this->budget );
	}
}
