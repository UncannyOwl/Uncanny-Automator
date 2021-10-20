<div class="wrap uap">
	
	<div id="uap-integrations">

		<?php

		// Banner "Connect your plugins together"
		include Uncanny_Automator\Utilities::automator_get_view( 'admin-integrations/archive/banner.php' );

		// Search bar
		include Uncanny_Automator\Utilities::automator_get_view( 'admin-integrations/archive/search.php' );

		// Collections
		include Uncanny_Automator\Utilities::automator_get_view( 'admin-integrations/archive/collections.php' );

		// All integrations
		include Uncanny_Automator\Utilities::automator_get_view( 'admin-integrations/archive/all-integrations.php' );

		?>

	</div>

</div>