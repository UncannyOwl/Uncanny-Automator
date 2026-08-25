<?php
/**
 * Agent claim notice.
 *
 * Rendered site-wide by Agent_Claim_Notice::render(). Deliberately uses core
 * WordPress notice markup rather than the `uo-*` component library, because
 * those components require the `uap-admin` bundle, which must not be enqueued
 * on every wp-admin screen.
 *
 * Sites with a connected free account have an allocation waiting and are sent
 * to the claim form. Sites with no account are sent to the setup wizard, and
 * are not told their account has an update, because they have no account.
 *
 * @package Uncanny_Automator
 *
 * @var array  $variant       Audience: `connected` bool, message `key`, button `url`.
 * @var string $dismiss_url   Nonced link that permanently dismisses the notice.
 * @var string $ajax_action   The admin-ajax action that persists a dismissal.
 * @var string $ajax_url      The admin-ajax endpoint.
 * @var string $dismiss_nonce Nonce guarding the suppression request.
 * @var string $mode_forever  Suppression mode that never lapses.
 * @var string $mode_snooze   Suppression mode that lapses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = ! empty( $variant['connected'] );

?>

<div id="automator-agent-claim-notice" class="notice notice-info is-dismissible">

	<h3 class="automator-agent-claim-notice__title">
		<?php if ( $is_connected ) : ?>
			<?php esc_html_e( 'Account update: free Uncanny Agent usage added', 'uncanny-automator' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Uncanny Agent is included with Uncanny Automator Lite', 'uncanny-automator' ); ?>
		<?php endif; ?>
	</h3>

	<p class="automator-agent-claim-notice__body">
		<?php if ( $is_connected ) : ?>
			<?php esc_html_e( "Uncanny Automator now includes Uncanny Agent — a capable AI assistant that helps you manage your site. There's no payment required and no setup! Just click to activate and start chatting.", 'uncanny-automator' ); ?>
		<?php else : ?>
			<?php esc_html_e( "Uncanny Automator now includes Uncanny Agent — a capable AI assistant that helps you manage your site. There's no payment required and no setup! Just click to create your account and start chatting.", 'uncanny-automator' ); ?>
		<?php endif; ?>
	</p>

	<p class="automator-agent-claim-notice__actions">
		<a
			class="button button-primary automator-agent-claim-notice__cta"
			href="<?php echo esc_url( $variant['url'] ); ?>"
			<?php if ( $is_connected ) : ?>
				target="_blank" rel="noopener noreferrer"
			<?php endif; ?>
		>
			<?php if ( $is_connected ) : ?>
				<?php esc_html_e( 'Claim my free Agent usage', 'uncanny-automator' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Connect my free account', 'uncanny-automator' ); ?>
			<?php endif; ?>
		</a>

		<a
			class="button button-secondary automator-agent-claim-notice__dismiss"
			href="<?php echo esc_url( $dismiss_url ); ?>"
		>
			<?php esc_html_e( 'Dismiss forever', 'uncanny-automator' ); ?>
		</a>
	</p>

</div>

<style>
	/*
	 * Scoped to this notice. Colours mirror the Automator design tokens in
	 * src/assets/src/shared/_legacy/style.scss — hardcoded because the
	 * `uap-admin` bundle that defines them is deliberately not enqueued on
	 * every wp-admin screen.
	 *
	 * --uap-color-primary: #0790e8
	 * --uap-color-gray:    #9e9e9e
	 */
	#automator-agent-claim-notice {
		/* Room on the right for the core dismiss button. */
		padding: 12px 38px 12px 12px;
		border-left-color: #0790e8;
	}

	#automator-agent-claim-notice .automator-agent-claim-notice__title {
		margin: 0 0 4px;
		font-size: 14px;
		line-height: 1.4;
	}

	#automator-agent-claim-notice .automator-agent-claim-notice__body {
		margin: 0 0 10px;
		padding: 0;
	}

	#automator-agent-claim-notice .automator-agent-claim-notice__actions {
		margin: 0;
		padding: 0;
	}

	/* Brand primary. The Automator button keeps one colour across states. */
	#automator-agent-claim-notice .automator-agent-claim-notice__cta,
	#automator-agent-claim-notice .automator-agent-claim-notice__cta:hover,
	#automator-agent-claim-notice .automator-agent-claim-notice__cta:focus {
		margin-right: 6px;
		border-color: #0790e8;
		background: #0790e8;
		color: #fff;
		box-shadow: none;
		text-shadow: none;
	}

	#automator-agent-claim-notice .automator-agent-claim-notice__dismiss,
	#automator-agent-claim-notice .automator-agent-claim-notice__dismiss:hover,
	#automator-agent-claim-notice .automator-agent-claim-notice__dismiss:focus {
		border-color: #9e9e9e;
		background: #fff;
		color: #1d2327;
		box-shadow: none;
	}
</style>

<script>
	( function () {
		var notice = document.getElementById( 'automator-agent-claim-notice' );

		if ( ! notice ) {
			return;
		}

		var sent = {};

		// Returns false when it cannot send, so the caller can fall back to
		// the plain link rather than swallowing the click.
		function persist( mode ) {
			if ( sent[ mode ] ) {
				return true;
			}

			if ( ! window.fetch ) {
				return false;
			}

			sent[ mode ] = true;

			var payload = new FormData();
			payload.append( 'action', '<?php echo esc_js( $ajax_action ); ?>' );
			payload.append( 'nonce', '<?php echo esc_js( $dismiss_nonce ); ?>' );
			payload.append( 'variant', '<?php echo esc_js( $variant['key'] ); ?>' );
			payload.append( 'mode', mode );

			window.fetch( '<?php echo esc_url_raw( $ajax_url ); ?>', {
				method: 'POST',
				credentials: 'same-origin',
				body: payload
			} );

			return true;
		}

		notice.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest ) {
				return;
			}

			// "Dismiss forever" is a real link. Only take over the click when
			// the request can actually be sent; otherwise let it navigate.
			if ( event.target.closest( '.automator-agent-claim-notice__dismiss' ) ) {
				if ( persist( '<?php echo esc_js( $mode_forever ); ?>' ) && notice.parentNode ) {
					event.preventDefault();
					notice.parentNode.removeChild( notice );
				}

				return;
			}

			// The core dismiss button is injected after render, so the click
			// is delegated from the notice itself.
			if ( event.target.closest( '.notice-dismiss' ) ) {
				persist( '<?php echo esc_js( $mode_forever ); ?>' );

				return;
			}

<?php if ( $is_connected ) : ?>
			// The claim form opens in a new tab and nothing refreshes the
			// license cache when the user returns, so someone who just
			// claimed would keep being told to claim for up to 12 hours.
			// Snooze rather than dismiss: if they did claim, the refreshed
			// ledger hides this for good before the snooze lapses; if they
			// abandoned the form, the offer comes back. The link is left
			// alone so it still opens.
			if ( event.target.closest( '.automator-agent-claim-notice__cta' ) ) {
				persist( '<?php echo esc_js( $mode_snooze ); ?>' );
			}
<?php endif; ?>
			// The connect call to action is deliberately not wired: the setup
			// wizard refreshes the license cache itself, so there is no stale
			// state to hide, and abandoning the wizard must not bury the offer.
		} );
	}() );
</script>
