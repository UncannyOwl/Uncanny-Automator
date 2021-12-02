<?php

namespace Uncanny_Automator;

?>

<div class="uap-integration-items">
	<div class="uap-integration-items-col">
		<!-- Doesn't have triggers 
		<div class="uap-integration-items-col-container">
			<div class="uap-integration-items__title">
				<?php //_e( 'Triggers', 'uncanny-automator' ); ?>
			</div>
			<div class="uap-integration-items-container">
				<div class="uap-integration-item--empty">
					<?php //_e( 'No triggers', 'uncanny-automator' ); ?>
				</div>
			</div>
		</div> -->
		<!-- Logged-in triggers -->
		<div class="uap-integration-items-col-container">
			<div class="uap-integration-items__title-container">
				<div class="uap-integration-items__title uap-integration-items__title--triggers">
					<?php esc_html_e( 'Triggers for logged-in users', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-integration-items__title-tooltip uap-icon uap-icon--question-circle" uapm-tooltip="Behaviors that can be tracked for logged-in users."></div>
			</div>

			<div class="uap-integration-items-container">

				<div class="uap-accordion">

					<div class="uap-accordion-item uap-integration-item">

						<div class="uap-integration-item-header">

							<div class="uap-accordion-item__toggle">
								<div class="uap-integration-item__sentence uap-integration-item__sentence--pro" pro-tag="Pro">
									<?php esc_html_e( 'A user views a product', 'uncanny-automator' ); ?>
								</div>
							</div>

							<div class="uap-integration-item__description"></div>

						</div>

						<div class="uap-accordion-item__content uap-integration-item-tokens">

							<div class="uap-integration-item-tokens__title-container">
								<div class="uap-integration-item-tokens__title">
									<?php esc_html_e( 'Tokens', 'uncanny-automator' ); ?>
								</div>
								<div class="uap-integration-item-tokens__title-tooltip uap-icon uap-icon--question-circle" uapm-tooltip="Data from triggers that can be used in a recipe's actions, making them much more powerful and customizable."></div>
							</div>
							<ul class="uap-integration-item-tokens__list">
								<li class="uap-integration-item-token">Number of times</li>
								<li class="uap-integration-item-token">Product title</li>
								<li class="uap-integration-item-token">Product ID</li>
								<li class="uap-integration-item-token">Product URL</li>
								<li class="uap-integration-item-token">Product featured image ID</li>
								<li class="uap-integration-item-token">Product featured image URL</li>
							</ul>

						</div>

					</div>

				</div>

			</div>
		</div>
		<!-- Everyone triggers 
		<div class="uap-integration-items-col-container">
			<div class="uap-integration-items__title-container">
				<div class="uap-integration-items__title uap-integration-items__title--triggers">
					<?php //_e( 'Triggers for everyone', 'uncanny-automator' ); ?>
				</div>
				<div class="uap-integration-items__title-tooltip uap-icon uap-icon--question-circle" uapm-tooltip="Behaviors that can be tracked for logged-in users."></div>
			</div>

			<div class="uap-integration-items-container">
				<div class="uap-integration-item">
					<div class="uap-integration-item__sentence-container">
						<div class="uap-integration-item__sentence uap-integration-item__sentence--pro" pro-tag="Pro">
							<?php //_e( 'A user views a product', 'uncanny-automator' ); ?>
						</div>
					</div>
					<div class="uap-integration-item__description">
						<?php //_e( 'Requires Tin Canny LearnDash Reporting', 'uncanny-automator' ); ?>
					</div>
				</div>
			</div>
		</div> -->

	</div>

	<div class="uap-integration-items-col">
		<div class="uap-integration-items__title">
			<?php esc_html_e( 'Actions', 'uncanny-automator' ); ?>
		</div>
		<div class="uap-integration-items-container">
			<div class="uap-integration-item">

					<div class="uap-integration-item__sentence uap-integration-item__sentence--pro" pro-tag="Pro">
						<?php esc_html_e( 'Generate and email a coupon code to the user', 'uncanny-automator' ); ?>
					</div>

			</div>
		</div>
	</div>
</div>
