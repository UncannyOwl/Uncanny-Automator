<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\Import_Recipe;

/**
 * How the last recipe import went, once, to the admin who imported: why it
 * failed, or which recipes a bulk import created, each linked to its
 * editor. {@see Import_Recipe} leaves it in a 30-second transient for that
 * user; it is forgotten once drawn.
 *
 * @since 8.0.0
 */
final class Recipe_Import_Notice extends Notice {

	/**
	 * @return string
	 */
	public function id(): string {

		return 'recipe-import';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * Shown once; its X closes it.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::for_this_view();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$error = get_transient( $this->key( Import_Recipe::ERROR_TRANSIENT ) );
		if ( is_string( $error ) && '' !== $error ) {
			return $this->failed( $error );
		}

		$recipe_ids = get_transient( $this->key( Import_Recipe::SUCCESS_TRANSIENT ) );
		if ( ! is_array( $recipe_ids ) || array() === $recipe_ids ) {
			return null;
		}

		$recipes = array();
		foreach ( $recipe_ids as $recipe_id ) {
			$recipes[ (string) get_edit_post_link( (int) $recipe_id, 'url' ) ] = get_the_title( (int) $recipe_id );
		}

		return $this->imported( $recipes );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$editor = admin_url( 'post.php?action=edit&post=' );

		return array(
			$this->failed( 'The file is not a valid recipe export.' ),
			$this->imported(
				array(
					$editor . '101' => 'Welcome new members',
					$editor . '102' => 'Tag course completions',
				)
			),
		);
	}

	/**
	 * @param string $error Why the import failed.
	 *
	 * @return Notice_Message
	 */
	private function failed( string $error ): Notice_Message {

		return Notice_Message::error( esc_html_x( 'Recipe import failed', 'Import Recipe', 'uncanny-automator' ) )
			->with_paragraph( esc_html( $error ) );
	}

	/**
	 * @param array<string, string> $recipes Each recipe's title, by its editor address.
	 *
	 * @return Notice_Message
	 */
	private function imported( array $recipes ): Notice_Message {

		$message = Notice_Message::success( esc_html_x( 'Recipes imported', 'Import Recipe', 'uncanny-automator' ) );
		foreach ( $recipes as $url => $title ) {
			$message = $message->with_item( sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $title ) ) );
		}

		return $message;
	}

	/**
	 * Drawn: forget the outcome, so it is shown once.
	 *
	 * @return void
	 */
	public function shown(): void {

		if ( false !== get_transient( $this->key( Import_Recipe::ERROR_TRANSIENT ) ) ) {
			delete_transient( $this->key( Import_Recipe::ERROR_TRANSIENT ) );
			return;
		}

		delete_transient( $this->key( Import_Recipe::SUCCESS_TRANSIENT ) );
	}

	/**
	 * @param string $prefix The transient's prefix.
	 *
	 * @return string The current user's transient.
	 */
	private function key( string $prefix ): string {

		return $prefix . get_current_user_id();
	}
}
