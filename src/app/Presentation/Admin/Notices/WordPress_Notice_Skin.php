<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * The WordPress look, for every screen that is not Automator's own (and for
 * one without Automator's components): a core notice with a heading, the
 * WordPress border for its severity (information in Automator blue), and
 * Automator-blue primary buttons. Only the most urgent notice is drawn; the
 * rest are counted, with a link to Automator, where the stack shows them.
 *
 * Core markup only: the component library is not loaded on these screens.
 *
 * @since 8.0.0
 */
final class WordPress_Notice_Skin implements Notice_Skin {

	/**
	 * Automator blue.
	 */
	private const BRAND_BLUE = '#0790e8';

	/**
	 * @var bool
	 */
	private $assets_printed = false;

	/**
	 * @param Notice_Entry[] $entries Most urgent first.
	 * @param Notice_Screen  $screen  The screen.
	 *
	 * @return string[]
	 */
	public function render( array $entries, Notice_Screen $screen ): array {

		$entries = array_values( $entries );
		$entry   = $entries[0];
		$message = $entry->message();
		$classes = 'notice notice-' . $message->severity() . ( $entry->dismissal()->is_dismissible() ? ' is-dismissible' : '' ) . ' automator-wp-notice';

		$this->print_assets();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-automator-notice="<?php echo esc_attr( $entry->id() ); ?>"<?php echo '' !== $message->state() ? ' data-state="' . esc_attr( $message->state() ) . '"' : ''; ?><?php echo '' !== $entry->dismiss_url() ? ' data-automator-notice-dismiss="' . esc_attr( $entry->dismiss_url() ) . '"' : ''; ?>>
			<h3 class="automator-wp-notice__title"><?php echo Notice_Html::text( $message->heading() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></h3>
			<?php foreach ( $message->paragraphs() as $paragraph ) : ?>
				<p class="automator-wp-notice__body"><?php echo Notice_Html::text( $paragraph ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></p>
			<?php endforeach; ?>
			<?php $this->render_items( $entry ); ?>
			<?php echo Notice_Html::report( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Notice_Html::report(). ?>
			<?php $this->render_actions( $entry, $message->actions() ); ?>
			<?php $this->render_more( count( $entries ) - 1 ); ?>
		</div>
		<?php

		return array( $entry->id() );
	}

	/**
	 * @param Notice_Entry $entry The notice.
	 *
	 * @return void
	 */
	private function render_items( Notice_Entry $entry ): void {

		$message = $entry->message();
		if ( array() === $message->items() ) {
			return;
		}
		?>
		<ul class="automator-wp-notice__items">
			<?php foreach ( $message->items() as $item ) : ?>
				<li>
					<?php echo Notice_Html::text( $item['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
					<?php if ( null !== $item['action'] ) : ?>
						<?php $this->render_action( $item['action'], '', $entry->is_preview() ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * @param Notice_Entry    $entry   The notice.
	 * @param Notice_Action[] $actions Its actions.
	 *
	 * @return void
	 */
	private function render_actions( Notice_Entry $entry, array $actions ): void {

		if ( array() === $actions ) {
			return;
		}

		if ( Notice_Html::are_choices( $actions ) ) {
			?>
			<div class="automator-wp-notice__choices">
				<?php foreach ( $actions as $action ) : ?>
					<div class="automator-wp-notice__choice">
						<?php $this->render_action( $action, $entry->dismiss_url(), $entry->is_preview() ); ?>
						<span class="description"><?php echo Notice_Html::text( $action->description() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
			return;
		}
		?>
		<div class="automator-wp-notice__actions">
			<?php
			foreach ( $actions as $action ) {
				$this->render_action( $action, $entry->dismiss_url(), $entry->is_preview() );
			}
			?>
		</div>
		<?php
	}

	/**
	 * One button: a link, a form, or the notice's own dismiss. A preview's
	 * form is the button alone: it posts nothing.
	 *
	 * @param Notice_Action $action      The action.
	 * @param string        $dismiss_url The notice's dismiss address; '' when not stored.
	 * @param bool          $preview     Whether the notice is a preview.
	 *
	 * @return void
	 */
	private function render_action( Notice_Action $action, string $dismiss_url, bool $preview ): void {

		$class = $action->is_primary() ? 'button button-primary automator-wp-notice__primary' : 'button button-secondary automator-wp-notice__secondary';

		if ( Notice_Action::POST === $action->kind() && $preview ) {
			?>
			<button type="button" class="<?php echo esc_attr( $class ); ?>"><?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></button>
			<?php
			return;
		}

		if ( Notice_Action::POST === $action->kind() ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-wp-notice__form">
				<?php echo Notice_Html::hidden_fields( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each field escaped in hidden_fields(). ?>
				<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></button>
			</form>
			<?php
			return;
		}

		if ( Notice_Action::DISMISS === $action->kind() ) {
			?>
			<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( '' !== $dismiss_url ? $dismiss_url : '#' ); ?>" data-automator-notice-dismiss="<?php echo esc_attr( $dismiss_url ); ?>"><?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></a>
			<?php
			return;
		}
		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $action->url() ); ?>"<?php echo $action->opens_new_tab() ? ' target="_blank" rel="noopener"' : ''; ?><?php echo $action->is_dismissing() && '' !== $dismiss_url ? ' data-automator-notice-dismiss-on-follow="' . esc_attr( $dismiss_url ) . '"' : ''; ?>>
			<?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
		</a>
		<?php
	}

	/**
	 * The count of the notices not drawn here, with the way to them.
	 *
	 * @param int $more How many.
	 *
	 * @return void
	 */
	private function render_more( int $more ): void {

		if ( $more < 1 ) {
			return;
		}

		$url = add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);
		?>
		<p class="automator-wp-notice__more">
			<?php
			/* translators: %d: how many more Uncanny Automator notices there are */
			echo esc_html( sprintf( _n( 'Uncanny Automator has %d more notice.', 'Uncanny Automator has %d more notices.', $more, 'uncanny-automator' ), $more ) );
			?>
			<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View them in Automator', 'uncanny-automator' ); ?></a>
		</p>
		<?php
	}

	/**
	 * The notice's styles and the dismiss script, once per page.
	 *
	 * @return void
	 */
	private function print_assets(): void {

		if ( $this->assets_printed ) {
			return;
		}
		$this->assets_printed = true;
		?>
		<style id="automator-wp-notice-style">
			.automator-wp-notice { padding: 12px 38px 12px 12px; }
			.automator-wp-notice.notice-info { border-left-color: <?php echo esc_html( self::BRAND_BLUE ); ?>; }
			.automator-wp-notice .automator-wp-notice__title { margin: 0 0 4px; font-size: 14px; line-height: 1.4; }
			.automator-wp-notice .automator-wp-notice__body { margin: 0 0 6px; padding: 0; }
			.automator-wp-notice .automator-wp-notice__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 10px 0 0; }
			.automator-wp-notice .automator-wp-notice__choices { display: flex; flex-direction: column; gap: 8px; margin: 10px 0 0; }
			.automator-wp-notice .automator-wp-notice__choice { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
			.automator-wp-notice .automator-wp-notice__form { display: inline-flex; margin: 0; }
			.automator-wp-notice .automator-wp-notice__items { margin: 4px 0 6px 18px; list-style: disc; }
			.automator-wp-notice .automator-notice-report { margin: 6px 0; }
			.automator-wp-notice .automator-notice-report textarea { box-sizing: border-box; width: 100%; margin-top: 6px; font-family: monospace; font-size: 12px; background: #f6f7f7; border: 1px solid #c3c4c7; }
			.automator-wp-notice .automator-wp-notice__more { margin: 10px 0 0; padding: 0; color: #50575e; }
			.automator-wp-notice .button.automator-wp-notice__primary,
			.automator-wp-notice .button.automator-wp-notice__primary:hover,
			.automator-wp-notice .button.automator-wp-notice__primary:focus { border-color: <?php echo esc_html( self::BRAND_BLUE ); ?>; background: <?php echo esc_html( self::BRAND_BLUE ); ?>; color: #ffffff; box-shadow: none; text-shadow: none; }
			.automator-wp-notice .button.automator-wp-notice__secondary,
			.automator-wp-notice .button.automator-wp-notice__secondary:hover,
			.automator-wp-notice .button.automator-wp-notice__secondary:focus { border-color: #9e9e9e; background: #ffffff; color: #1d2327; box-shadow: none; }
		</style>
		<script>
			document.addEventListener( 'click', function ( event ) {
				var notice = event.target.closest ? event.target.closest( '.automator-wp-notice' ) : null;
				if ( ! notice ) {
					return;
				}
				var link = event.target.closest( 'a[data-automator-notice-dismiss]' );
				var url = notice.getAttribute( 'data-automator-notice-dismiss' );
				if ( event.target.closest( '[data-automator-notice-dismiss-on-follow]' ) && url && window.fetch ) {
					window.fetch( url, { credentials: 'same-origin', keepalive: true } );
				}
				if ( link ) {
					event.preventDefault();
				}
				if ( ( link || event.target.closest( '.notice-dismiss' ) ) && url && window.fetch ) {
					window.fetch( url, { credentials: 'same-origin', keepalive: true } );
				}
				if ( link && notice.parentNode ) {
					notice.parentNode.removeChild( notice );
				}
			} );
		</script>
		<?php
	}
}
