<?php

namespace Uncanny_Automator;

// Check if the integration exists
$integration_exists = true;

?>

<div class="uap">
	<div class="uap-integration" id="uap-integration">

		<?php

		if ( $integration_exists ) {
			// Banner
			include Utilities::automator_get_view( 'admin-integrations/single/banner.php' );

			// Content
			include Utilities::automator_get_view( 'admin-integrations/single/content.php' );

		} else {
			// Not found
			esc_attr_e( "Sorry, this integration doesn't exist", 'uncanny-automator' );
		}

		?>

	</div>
</div>
