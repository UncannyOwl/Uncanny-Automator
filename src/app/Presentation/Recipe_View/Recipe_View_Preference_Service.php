<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Recipe_View;

use InvalidArgumentException;
use Uncanny_Automator\App\Bridge\User_Option_Bridge;
use Uncanny_Automator\App\Bridge\WP_User_Option_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Query_Service;
use WP_Post;

/**
 * Reads and stores how people view a recipe in the builder and the log.
 *
 * Everything here is the person's, kept as user options so each site of a
 * network keeps its own: the view they pick, whether they dismissed the
 * diagram introduction, and their default view, what a recipe opens as until
 * they pick. The default is the list for someone who had already created a
 * recipe and the diagram for someone who had not. Whichever comes first
 * settles it, their first recipe created or their first read, and it never
 * changes after that, so someone new stays on the diagram once their recipes
 * exist.
 *
 * @since 8.0.0
 */
final class Recipe_View_Preference_Service {

	/**
	 * User option holding the view the person last picked. Absent until they pick.
	 *
	 * @var string
	 */
	public const VIEW_USER_OPTION = 'automator_recipe_view';

	/**
	 * User option holding '1' once the person dismissed the diagram introduction, '0' when it was shown again.
	 *
	 * @var string
	 */
	public const DIAGRAM_INTRO_USER_OPTION = 'automator_recipe_diagram_intro_dismissed';

	/**
	 * User option holding the person's settled default view. Absent until settled.
	 *
	 * @var string
	 */
	public const DEFAULT_VIEW_USER_OPTION = 'automator_recipe_default_view';

	/**
	 * The shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Each person's own options.
	 *
	 * @var User_Option_Bridge
	 */
	private $users;

	/**
	 * The recipe queries, for whether a person has created a recipe.
	 *
	 * @var Recipe_Query_Service
	 */
	private $recipes;

	/**
	 * Build with the WordPress defaults, or the given doubles.
	 *
	 * @param User_Option_Bridge|null   $users   Each person's own options.
	 * @param Recipe_Query_Service|null $recipes The recipe queries.
	 */
	private function __construct( ?User_Option_Bridge $users = null, ?Recipe_Query_Service $recipes = null ) {

		$this->users   = $users ?? new WP_User_Option_Bridge();
		$this->recipes = $recipes ?? new Recipe_Query_Service();
	}

	/**
	 * The shared instance.
	 *
	 * @since 8.0.0
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
	 * An instance over the given dependencies, for tests.
	 *
	 * @since 8.0.0
	 *
	 * @param User_Option_Bridge   $users   Each person's own options.
	 * @param Recipe_Query_Service $recipes The recipe queries.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( User_Option_Bridge $users, Recipe_Query_Service $recipes ): self {

		return new self( $users, $recipes );
	}

	/**
	 * A person's preferences, settling their default view when it has not
	 * been yet. Someone who never picked has no view; a stored value outside
	 * the two views reads the same way.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person.
	 *
	 * @return Recipe_View_Preferences
	 */
	public function get( int $user_id ): Recipe_View_Preferences {

		$view = $this->users->get( $user_id, self::VIEW_USER_OPTION );

		return new Recipe_View_Preferences(
			Recipe_View::is_view( $view ) ? new Recipe_View( $view ) : null,
			$this->default_view( $user_id ),
			'1' === (string) $this->users->get( $user_id, self::DIAGRAM_INTRO_USER_OPTION )
		);
	}

	/**
	 * Store a change and answer the whole record as now stored. A preference
	 * passed as null keeps what is stored. Everything is checked before
	 * anything is written.
	 *
	 * @since 8.0.0
	 *
	 * @param int         $user_id                 The person.
	 * @param string|null $view                    `diagram` or `list`, or null to keep it.
	 * @param bool|null   $diagram_intro_dismissed The introduction state, or null to keep it.
	 *
	 * @return Recipe_View_Preferences
	 *
	 * @throws InvalidArgumentException When both are null, or the view is not one of the two; nothing is stored.
	 */
	public function update( int $user_id, ?string $view, ?bool $diagram_intro_dismissed ): Recipe_View_Preferences {

		if ( null === $view && null === $diagram_intro_dismissed ) {
			throw new InvalidArgumentException( 'Send a view, the diagram introduction state, or both.' );
		}

		$picked = null === $view ? null : new Recipe_View( $view );

		if ( null !== $picked ) {
			$this->users->update( $user_id, self::VIEW_USER_OPTION, $picked->value() );
		}

		if ( null !== $diagram_intro_dismissed ) {
			$this->users->update( $user_id, self::DIAGRAM_INTRO_USER_OPTION, $diagram_intro_dismissed ? '1' : '0' );
		}

		return $this->get( $user_id );
	}

	/**
	 * The person's default view, settled now when it has not been yet.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person.
	 *
	 * @return Recipe_View
	 */
	public function default_view( int $user_id ): Recipe_View {

		return $this->stored_default_view( $user_id ) ?? $this->settle_default_view( $user_id, 0 );
	}

	/**
	 * The person's default view without settling it: the stored one, or what
	 * it would be settled to now. For readers that are not the person, such as
	 * the usage report, which must not settle anyone.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person.
	 *
	 * @return Recipe_View
	 */
	public function default_view_without_settling( int $user_id ): Recipe_View {

		return $this->stored_default_view( $user_id ) ?? $this->default_view_by_rule( $user_id, 0 );
	}

	/**
	 * Settle the author's default view when they create a recipe and have
	 * none settled yet. The new recipe itself never counts.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe just created.
	 * @param int $author_id Its author; 0 for a recipe a background process created, which settles nobody.
	 *
	 * @return void
	 */
	public function note_recipe_created( int $recipe_id, int $author_id ): void {

		if ( $author_id <= 0 || null !== $this->stored_default_view( $author_id ) ) {
			return;
		}

		$this->settle_default_view( $author_id, $recipe_id );
	}

	/**
	 * `save_post_{recipe post type}` listener. Only a new recipe settles its
	 * author's default; an update of one never does.
	 *
	 * @since 8.0.0
	 *
	 * @param int     $post_id The recipe.
	 * @param WP_Post $post    The recipe post.
	 * @param bool    $update  Whether an existing recipe was updated.
	 *
	 * @return void
	 */
	public static function on_recipe_saved( $post_id, $post, $update ): void {

		if ( $update ) {
			return;
		}

		self::instance()->note_recipe_created( (int) $post_id, (int) $post->post_author );
	}

	/**
	 * The person's settled default view, or null before it is settled.
	 *
	 * @param int $user_id The person.
	 *
	 * @return Recipe_View|null
	 */
	private function stored_default_view( int $user_id ): ?Recipe_View {

		$stored = $this->users->get( $user_id, self::DEFAULT_VIEW_USER_OPTION );

		return Recipe_View::is_view( $stored ) ? new Recipe_View( $stored ) : null;
	}

	/**
	 * The default the rule gives now: the list when the person has created a
	 * recipe other than the one just created, the diagram when they have not.
	 *
	 * @param int $user_id       The person.
	 * @param int $new_recipe_id The recipe just created; 0 when none was.
	 *
	 * @return Recipe_View
	 */
	private function default_view_by_rule( int $user_id, int $new_recipe_id ): Recipe_View {

		return new Recipe_View( $this->recipes->user_has_recipes( $user_id, $new_recipe_id ) ? Recipe_View::LIST : Recipe_View::DIAGRAM );
	}

	/**
	 * Settle and store the person's default view.
	 *
	 * @param int $user_id       The person.
	 * @param int $new_recipe_id The recipe just created; 0 when none was.
	 *
	 * @return Recipe_View
	 */
	private function settle_default_view( int $user_id, int $new_recipe_id ): Recipe_View {

		$default = $this->default_view_by_rule( $user_id, $new_recipe_id );

		$this->users->update( $user_id, self::DEFAULT_VIEW_USER_OPTION, $default->value() );

		return $default;
	}
}
