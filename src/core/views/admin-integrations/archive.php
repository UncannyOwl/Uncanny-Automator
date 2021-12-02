<?php

/**
 * Variables:
 * $all_recipes_url         URL of the "All recipes" page
 * $user_has_automator_pro  User has Uncanny Automator Pro
 * $integrations            List of integrations. Key is the integration numeric ID
 * $collections             List of collections. Key is the taxonomy ID.
 */

namespace Uncanny_Automator;

?>

<div class="wrap uap" style="margin: 0;">
	<div id="uap-integrations" class="uap-integrations">

		<!-- Some plugins need an h1 to add their notices -->
		<h1 style="margin: 0; padding: 0"></h1>

		<?php

		// Banner "Connect your plugins together"
		require Utilities::automator_get_view( 'admin-integrations/archive/banner.php' );

		// Search bar
		require Utilities::automator_get_view( 'admin-integrations/archive/search.php' );

		// Collections
		require Utilities::automator_get_view( 'admin-integrations/archive/collections.php' );

		// All integrations
		require Utilities::automator_get_view( 'admin-integrations/archive/all-integrations.php' );

		?>

	</div>
</div>
