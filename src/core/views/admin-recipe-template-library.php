<?php
/**
 * The wrapper template for displaying the Automator template library admin page.
 * The page is a single Lit web component; all layout, filtering, and dialog state live in the frontend.
 *
 * @package     Uncanny_Automator/views
 * @version     0.0.1
*/

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<uap-d-site-auth
	site-api-url="<?php echo esc_attr( rest_url() . AUTOMATOR_REST_API_END_POINT ); ?>"
	site-api-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
>
	<uap-f-recipe-template class="uap-wp-admin-pinned-app"></uap-f-recipe-template>
</uap-d-site-auth>
