<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="uap">

	<!-- Some plugins need an h1 to add their notices -->
	<h1 style="margin: 0; padding: 0;"></h1>

	<style>

	#wpcontent {
		padding-left: 0;
	}

	</style>

	<uap-d-site-auth
		site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
		site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	>
		<uap-page-addons></uap-page-addons>
	</uap-d-site-auth>

</div>
