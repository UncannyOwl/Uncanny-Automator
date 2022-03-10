<?php

namespace Uncanny_Automator;

?>

<div class="uap-integrations-search" id="uap-integrations-search">

	<div class="uap-field">
		<uo-icon id="search" class="uap-field-icon"></uo-icon>
		<input type="text" class="uap-field-text uap-field--has-icon" id="uap-integrations-search-field" placeholder="<?php esc_attr_e( 'Search for integrations', 'uncanny-automator' ); ?>">
	</div>

	<ul class="uap-integrations-search-sections" id="uap-integrations-search-sections">
		<li class="uap-integrations-search-sections-collections" data-destionation="uap-integrations-collections">
			<a href="#uap-integrations-collections"><?php esc_html_e( 'Collections', 'uncanny-automator' ); ?></a>
		</li>

		<li class="uap-integrations-search-sections-all" data-destionation="uap-integrations-all">
			<a href="#uap-integrations-all"><?php esc_html_e( 'All integrations', 'uncanny-automator' ); ?></a>
		</li>
	</ul>

</div>
