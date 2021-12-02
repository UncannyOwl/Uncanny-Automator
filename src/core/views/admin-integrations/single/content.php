<?php

namespace Uncanny_Automator;

?>

<div class="uap-integration-content" id="uap-integration-content">
	<div class="uap-integration-content__left">
		<?php

		// Content - Triggers and actions
		require Utilities::automator_get_view( 'admin-integrations/single/items.php' );

		// Content - Recipe inspiration
		require Utilities::automator_get_view( 'admin-integrations/single/recipes.php' );

		?>
	</div>
	<div class="uap-integration-content__right">
		<?php

		// Content - Sidebar
		require Utilities::automator_get_view( 'admin-integrations/single/sidebar.php' );

		?>
	</div>
</div>
