<?php
namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php // Mirrors the Pro orphan-recovery caption/note styling (orphan-recovery.css) so this Free tool matches it without depending on Pro. ?>
<style>
	.uap-orphan-recovery__caption {
		margin: 0 0 18px;
		font-size: 12px;
		color: #646970;
	}
	.uap-orphan-recovery__note {
		margin: 24px 0 0;
		padding-top: 16px;
		border-top: 1px solid #dcdcde;
		color: #646970;
		font-size: 12px;
		line-height: 1.6;
	}
</style>
<div class="uap-settings-panel">
	<div class="uap-settings-panel-top">
		<div class="uap-settings-panel-content">

			<p>
				<?php echo esc_html__( 'Find App actions that failed (timeout, rate-limit, 5xx, bad vendor response) and re-fire them. This replays the original API request, exactly like the per-action Resend button.', 'uncanny-automator' ); ?>
			</p>

			<?php // Mount point — the uap-resend-failed-actions Lit component (Task 7) renders the controls, counts, progress, and test candidate. ?>
			<uap-resend-failed-actions></uap-resend-failed-actions>

			<uo-alert type="error" class="uap-orphan-recovery__caption uap-spacing-top" heading="<?php echo esc_attr__( 'Keep this tab open while finding or resending.', 'uncanny-automator' ); ?>"></uo-alert>

			<p class="uap-orphan-recovery__note">
				<?php
				echo esc_html__(
					'This is a best-effort tool, not a guarantee. Resend replays the API request that was originally sent for a failed App action — it does not re-run the recipe or re-resolve tokens. Some actions can’t be resent: the original request may not have been logged, or the integration may have been disconnected or its credentials changed since the action ran. A resend fires now, against the current connection — later than the action originally ran — and re-firing can repeat the action’s effect on the connected app (for example, a second email or contact). Review the list and try a single resend before resending in bulk.',
					'uncanny-automator'
				);
				?>
			</p>

		</div>
	</div>
</div>
