<?php

namespace Uncanny_Automator;

?>

<div class="uap-integrations-collections" id="uap-integrations-collections">

	<?php

	// Add the "Featured" section
	if ( isset( $collections[ 'featured' ] ) ) {
		// Get collection data
		$collection = $collections[ 'featured' ];

		// Load template
		require Utilities::automator_get_view( 'admin-integrations/archive/collection.php' );
	}

	// Add the "Installed" section
	if ( isset( $collections[ 'installed-integrations' ] ) ) {
		// Get collection data
		$collection = $collections[ 'installed-integrations' ];

		// Load template
		require Utilities::automator_get_view( 'admin-integrations/archive/collection.php' );
	}

	// Load all the other collections
	foreach ( $collections as $collection ) {
		// Don't show collections "Featured" and "Installed integrations" here
		// We're already showing them above
		if ( ! in_array( $collection->id, array( 'featured', 'installed-integrations' ) ) ) {
			// Load template
			require Utilities::automator_get_view( 'admin-integrations/archive/collection.php' );
		}
	}

	?>

</div>
