<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="uap">

	<!-- Some plugins need an h1 to add their notices -->
	<h1 style="margin: 0; padding: 0;"></h1>

	<uap-d-site-auth
		site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
		site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	>
		<uap-page-upgrade></uap-page-upgrade>
	</uap-d-site-auth>

</div>
