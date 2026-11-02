<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * Automator's look, on Automator screens: every notice in one stack. The
 * most urgent is open as an Automator alert; each of the others is one row
 * that opens in its place (a `<details name>` group: one open at a time, no
 * script). Every row ends in the same two columns, a slot for the X (empty
 * when the notice cannot be put away), then "Show" at the edge, so they line
 * up. Notices past their kind's limit ({@see Notice_Limits}) wait behind one
 * "N more notices" row at the end, which opens them in place. Built from the
 * component library: `uas-alert`, `uas-button`, `uas-icon`. U-bot presents
 * information.
 *
 * The stack is a `div.notice` so WordPress moves it under the page title, as
 * it does every notice (it moves `div.notice` only).
 *
 * @since 8.0.0
 */
final class Automator_Notice_Skin implements Notice_Skin {

	/**
	 * Each severity's icon.
	 */
	private const ICONS = array(
		Notice_Severity::ERROR   => 'circle-xmark',
		Notice_Severity::WARNING => 'circle-exclamation',
		Notice_Severity::SUCCESS => 'circle-check',
		Notice_Severity::INFO    => 'circle-info',
	);

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

		$shown  = array();
		$folded = array();
		foreach ( $entries as $entry ) {
			if ( $entry->is_folded() ) {
				$folded[] = $entry;
				continue;
			}
			$shown[] = $entry;
		}

		$this->print_assets();
		?>
		<div class="notice automator-notices" role="region" aria-label="<?php esc_attr_e( 'Uncanny Automator notices', 'uncanny-automator' ); ?>">
			<?php
			foreach ( $shown as $index => $entry ) {
				$this->render_entry( $entry, 0 === $index );
			}
			$this->render_folded( $folded );
			?>
		</div>
		<?php

		return array_map(
			static function ( Notice_Entry $entry ): string {
				return $entry->id();
			},
			$shown
		);
	}

	/**
	 * The notices past their kind's limit, behind one row that opens them in
	 * place. They are not counted as drawn, so a one-shot notice among them
	 * stays owed. The row carries its label for every smaller count, so the
	 * script can relabel it as notices are dismissed.
	 *
	 * @param Notice_Entry[] $folded The folded notices, in order.
	 *
	 * @return void
	 */
	private function render_folded( array $folded ): void {

		if ( array() === $folded ) {
			return;
		}

		$total  = count( $folded );
		$labels = array();
		for ( $count = 1; $count <= $total; $count++ ) {
			/* translators: %d: how many more Uncanny Automator notices are folded behind this row */
			$labels[ $count ] = sprintf( _n( '%d more notice', '%d more notices', $count, 'uncanny-automator' ), $count );
		}
		?>
		<details class="automator-notices__more">
			<summary class="automator-notice__row automator-notices__more-row">
				<uas-icon code="plus" class="automator-notice__row-icon"></uas-icon>
				<span class="automator-notice__row-heading" data-automator-notices-labels="<?php echo esc_attr( (string) wp_json_encode( $labels ) ); ?>"><?php echo esc_html( $labels[ $total ] ); ?></span>
				<span class="automator-notice__row-x"></span>
				<span class="automator-notice__row-show"><span class="automator-notices__show"><?php esc_html_e( 'Show', 'uncanny-automator' ); ?></span><span class="automator-notices__hide"><?php esc_html_e( 'Hide', 'uncanny-automator' ); ?></span><uas-icon code="angle-down"></uas-icon></span>
			</summary>
			<div class="automator-notices__folded">
				<?php
				foreach ( $folded as $entry ) {
					$this->render_entry( $entry, false );
				}
				?>
			</div>
		</details>
		<?php
	}

	/**
	 * @param Notice_Entry $entry The notice.
	 * @param bool         $open  Whether it is the open one.
	 *
	 * @return void
	 */
	private function render_entry( Notice_Entry $entry, bool $open ): void {

		$message  = $entry->message();
		$severity = $message->severity();
		$mascot   = $message->has_mascot();
		?>
		<details class="automator-notice automator-notice--<?php echo esc_attr( $severity ); ?>" name="automator-notices" data-automator-notice="<?php echo esc_attr( $entry->id() ); ?>"<?php echo '' !== $message->state() ? ' data-state="' . esc_attr( $message->state() ) . '"' : ''; ?><?php echo $open ? ' open' : ''; ?>>
			<summary class="automator-notice__row">
				<uas-icon code="<?php echo esc_attr( self::ICONS[ $severity ] ); ?>" class="automator-notice__row-icon"></uas-icon>
				<span class="automator-notice__row-heading"><?php echo Notice_Html::text( $message->heading() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></span>
				<span class="automator-notice__row-x"><?php $this->render_dismiss( $entry, false ); ?></span>
				<span class="automator-notice__row-show"><?php esc_html_e( 'Show', 'uncanny-automator' ); ?><uas-icon code="angle-down"></uas-icon></span>
			</summary>
			<uas-alert type="<?php echo esc_attr( $mascot ? 'transparent' : $severity ); ?>" heading="<?php echo esc_attr( Notice_Html::plain( $message->heading() ) ); ?>" no-radius<?php echo $mascot ? ' custom-icon' : ''; ?>>
				<?php if ( $mascot ) : ?>
					<img slot="icon" src="<?php echo esc_url( plugins_url( 'src/assets/build/img/credits-left-hundred.svg', AUTOMATOR_BASE_FILE ) ); ?>" width="72" height="72" alt="" />
				<?php endif; ?>
				<?php $this->render_dismiss( $entry, true ); ?>
				<?php foreach ( $message->paragraphs() as $paragraph ) : ?>
					<p><?php echo Notice_Html::text( $paragraph ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></p>
				<?php endforeach; ?>
				<?php $this->render_items( $entry ); ?>
				<?php echo Notice_Html::report( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Notice_Html::report(). ?>
				<?php $this->render_actions( $entry, $message->actions() ); ?>
			</uas-alert>
		</details>
		<?php
	}

	/**
	 * The close button, for a notice that may be put away: in the open
	 * alert's corner, and at the end of its row when closed. Without script
	 * it is a link to the dismiss address.
	 *
	 * @param Notice_Entry $entry    The notice.
	 * @param bool         $in_alert Whether it goes in the open alert's corner.
	 *
	 * @return void
	 */
	private function render_dismiss( Notice_Entry $entry, bool $in_alert ): void {

		if ( ! $entry->dismissal()->is_dismissible() ) {
			return;
		}

		$url = $entry->dismiss_url();
		?>
		<uas-button<?php echo $in_alert ? ' slot="top-right-icon"' : ' class="automator-notice__row-dismiss"'; ?> color="transparent" size="sm" href="<?php echo esc_url( '' !== $url ? $url : '#' ); ?>" data-automator-notice-dismiss="<?php echo esc_attr( $url ); ?>" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'uncanny-automator' ); ?>">
			<uas-icon code="xmark"></uas-icon>
		</uas-button>
		<?php
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
		<ul class="automator-notice__items">
			<?php foreach ( $message->items() as $item ) : ?>
				<li>
					<span><?php echo Notice_Html::text( $item['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></span>
					<?php if ( null !== $item['action'] ) : ?>
						<?php $this->render_action( $item['action'], '', $entry->is_preview(), 'sm' ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * The buttons: in one row, or as choices beside what each does.
	 *
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
			<div class="automator-notice__choices">
				<?php foreach ( $actions as $action ) : ?>
					<div class="automator-notice__choice">
						<?php $this->render_action( $action, $entry->dismiss_url(), $entry->is_preview() ); ?>
						<span class="automator-notice__choice-text"><?php echo Notice_Html::text( $action->description() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
			return;
		}
		?>
		<div class="automator-notice__actions">
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
	 * @param string        $size        The button size.
	 *
	 * @return void
	 */
	private function render_action( Notice_Action $action, string $dismiss_url, bool $preview, string $size = 'md' ): void {

		$color = $action->is_primary() ? 'primary' : 'secondary';

		if ( Notice_Action::POST === $action->kind() && $preview ) {
			?>
			<uas-button type="button" color="<?php echo esc_attr( $color ); ?>" size="<?php echo esc_attr( $size ); ?>">
				<?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
			</uas-button>
			<?php
			return;
		}

		if ( Notice_Action::POST === $action->kind() ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-notice__form">
				<?php echo Notice_Html::hidden_fields( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each field escaped in hidden_fields(). ?>
				<uas-button type="submit" color="<?php echo esc_attr( $color ); ?>" size="<?php echo esc_attr( $size ); ?>">
					<?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
				</uas-button>
			</form>
			<?php
			return;
		}

		if ( Notice_Action::DISMISS === $action->kind() ) {
			?>
			<uas-button href="<?php echo esc_url( '' !== $dismiss_url ? $dismiss_url : '#' ); ?>" color="<?php echo esc_attr( $color ); ?>" size="<?php echo esc_attr( $size ); ?>" data-automator-notice-dismiss="<?php echo esc_attr( $dismiss_url ); ?>">
				<?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
			</uas-button>
			<?php
			return;
		}
		?>
		<uas-button href="<?php echo esc_url( $action->url() ); ?>"<?php echo $action->opens_new_tab() ? ' target="_blank"' : ''; ?> color="<?php echo esc_attr( $color ); ?>" size="<?php echo esc_attr( $size ); ?>"<?php echo $action->is_dismissing() && '' !== $dismiss_url ? ' data-automator-notice-dismiss-on-follow="' . esc_attr( $dismiss_url ) . '"' : ''; ?>>
			<?php echo Notice_Html::text( $action->label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses(). ?>
		</uas-button>
		<?php
	}

	/**
	 * The stack's styles and the dismiss script, once per page.
	 *
	 * @return void
	 */
	private function print_assets(): void {

		if ( $this->assets_printed ) {
			return;
		}
		$this->assets_printed = true;
		?>
		<style id="automator-notices-style">
			.automator-notices.notice { margin: 16px 0; padding: 0; background: #ffffff; border: 1px solid #e4e4e4; border-radius: 12px; box-shadow: none; overflow: hidden; }
			.automator-notice + .automator-notice { border-top: 1px solid #e4e4e4; }
			.automator-notice__row { list-style: none; display: flex; align-items: center; gap: 12px; min-height: 36px; padding: 0 16px; cursor: pointer; font-family: var(--uas--typography--font--family, inherit); font-size: 14px; color: #000000; }
			.automator-notice__row::-webkit-details-marker { display: none; }
			.automator-notice__row:hover { background: #fafafa; }
			.automator-notice__row:focus-visible { outline: 2px solid #0790e8; outline-offset: -2px; }
			.automator-notice[open] > .automator-notice__row { display: none; }
			.automator-notice--error .automator-notice__row-icon { color: #e51b1b; }
			.automator-notice--warning .automator-notice__row-icon { color: #ed6c02; }
			.automator-notice--success .automator-notice__row-icon { color: #1d9323; }
			.automator-notice--info .automator-notice__row-icon { color: #0288d1; }
			.automator-notice__row-heading { flex: 1 1 auto; min-width: 0; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.automator-notice__row-show { display: inline-flex; align-items: center; gap: 4px; color: #0478c3; font-weight: 500; }
			.automator-notice__row-x { flex: 0 0 40px; width: 40px; display: inline-flex; align-items: center; justify-content: center; }
			.automator-notice uas-alert p { margin: 0 0 6px; }
			.automator-notice__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
			.automator-notice__choices { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
			.automator-notice__choice { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
			.automator-notice__choice-text { max-width: 70ch; color: #3c434a; }
			.automator-notice__form { display: inline-flex; margin: 0; }
			.automator-notice__items { margin: 8px 0 0 18px; list-style: disc; }
			.automator-notice__items li { margin: 4px 0; }
			.automator-notice-report { margin-top: 8px; }
			.automator-notice-report textarea { box-sizing: border-box; width: 100%; margin-top: 6px; font-family: monospace; font-size: 12px; }
			.automator-notice__items .automator-notice__form { margin-left: 8px; vertical-align: middle; }
			.automator-notices__more, .automator-notices__folded > .automator-notice { border-top: 1px solid #e4e4e4; }
			.automator-notices__more-row .automator-notice__row-icon { color: #757575; }
			.automator-notices__more[open] > summary .automator-notices__show, .automator-notices__more:not([open]) > summary .automator-notices__hide { display: none; }
			.automator-notices__more[open] > summary uas-icon[code="angle-down"] { transform: rotate(180deg); }
			.automator-notices__more[open] > .automator-notices__more-row { background: #f6f7f7; }
			.automator-notices__more[open] > .automator-notices__more-row:hover { background: #f0f0f1; }
		</style>
		<script>
			document.addEventListener( 'click', function ( event ) {
				var follow = event.target.closest ? event.target.closest( '[data-automator-notice-dismiss-on-follow]' ) : null;
				if ( follow && window.fetch ) {
					window.fetch( follow.getAttribute( 'data-automator-notice-dismiss-on-follow' ), { credentials: 'same-origin', keepalive: true } );
				}
				var button = event.target.closest ? event.target.closest( '[data-automator-notice-dismiss]' ) : null;
				var item = button ? button.closest( '[data-automator-notice]' ) : null;
				if ( ! item ) {
					return;
				}
				event.preventDefault();
				var url = button.getAttribute( 'data-automator-notice-dismiss' );
				if ( url && window.fetch ) {
					window.fetch( url, { credentials: 'same-origin', keepalive: true } );
				}
				var stack = item.closest( '.automator-notices' );
				item.parentNode.removeChild( item );
				var more = stack.querySelector( '.automator-notices__more' );
				var left = more ? more.querySelectorAll( 'details[data-automator-notice]' ).length : 0;
				if ( more && ! left ) {
					more.parentNode.removeChild( more );
					more = null;
				} else if ( more ) {
					var label = more.querySelector( '[data-automator-notices-labels]' );
					try {
						label.textContent = JSON.parse( label.getAttribute( 'data-automator-notices-labels' ) )[ left ] || label.textContent;
					} catch ( error ) {}
				}
				var next = stack.querySelector( ':scope > details[data-automator-notice]' );
				if ( ! next && ! more ) {
					stack.parentNode.removeChild( stack );
				} else if ( ! next ) {
					more.open = true;
				} else if ( ! stack.querySelector( 'details[data-automator-notice][open]' ) ) {
					next.open = true;
				}
			} );
		</script>
		<?php
	}
}
