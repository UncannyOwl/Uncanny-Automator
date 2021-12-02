<?php

namespace Uncanny_Automator;

?>

<div class="uap-integrations-collections" id="uap-integrations-all">

	<?php

	/**
	 * Create collection data
	 */
	$collection = Automator_Load::$core_class_inits['Admin_Menu']->get_all_integrations_collection();

	// Load template
	require Utilities::automator_get_view( 'admin-integrations/archive/collection.php' );

	?>

</div>
