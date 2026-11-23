<?php
namespace Uncanny_Automator;

/**
 * Admin Logs
 *
 * @since 4.5
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>
<style>
#wpcontent { padding-left: 0 !important; }
.uo-recipe_page_uncanny-automator-admin-logs #wpfooter,
.uo-recipe_page_uncanny-automator-admin-logs .notice { display: none!important;}
.uo-recipe_page_uncanny-automator-admin-logs #wpbody-content {
	float: none!important;
	padding-bottom: 0!important;
}
/* The settings frame is a sticky box one screen tall, made for panels that scroll inside it. The Logs viewer takes its own height (the list alone grows with its rows), so the page has to scroll instead, or every row below the first screen is out of reach. */
.uo-recipe_page_uncanny-automator-admin-logs .uap-settings {
	position: static;
	height: auto;
}
/* The page is never shorter than the admin menu, so a viewer shorter than the menu (loading, or a short list) leaves room below it, which takes the viewer's white instead of the admin grey. */
body.uo-recipe_page_uncanny-automator-admin-logs {
	background: #fff;
}
</style>

<uap-d-site-auth
	site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
	site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
>

	<div id="uap-settings" class="uap uap-settings" >

		<?php do_action( 'automator_tools_header_after' ); ?>

		<div id="uap-settings-content" class="uap-settings-content">

			<?php /* The Logs viewer: recipe runs, triggers, actions, failed actions, app events and AI activity, each routed from the hash (#/logs/recipe, #/logs/trigger, #/logs/action, #/logs/failed-actions, #/logs/app-events, #/logs/ai-activity). */ ?>
			<uap-f-page-logs-viewer<?php echo $show_ai_activity ? ' show-ai-activity' : ''; ?><?php echo $show_app_events ? ' show-app-events' : ''; ?>></uap-f-page-logs-viewer>

		</div>

	</div>

	<?php /* Single page-level dialog host. The viewer writes a run's hash; this element listens for it and opens. */ ?>
	<uap-f-recipe-log></uap-f-recipe-log>

</uap-d-site-auth>

<?php if ( '' !== $legacy_tab_hash ) { ?>
<script>
	<?php /* A link to one of the old Logs tabs (?tab=trigger) opens the viewer's tab, unless it already names one. */ ?>
	if ( ! window.location.hash ) {
		window.location.replace( '#/logs/<?php echo esc_js( $legacy_tab_hash ); ?>' );
	}
</script>
<?php } ?>
