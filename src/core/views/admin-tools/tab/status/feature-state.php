<?php
/**
 * Feature visibility and the local observations behind it.
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$feature_state_sections = $report['feature_state'] ?? array();
$feature_state_help     = new \Uncanny_Automator\App\Feature_State\Presentation\Feature_State_Report_Help();
?>
<?php foreach ( $feature_state_sections as $feature_state_heading => $feature_state_rows ) : ?>
	<table class="automator_status_table widefat" style="table-layout: fixed;">
		<colgroup>
			<col style="width: 30%;">
			<col style="width: 26px;">
			<col style="width: 25%;">
			<col>
		</colgroup>
		<thead>
			<tr>
				<th colspan="4" data-export-label="<?php echo esc_attr( $feature_state_heading ); ?>">
					<h2><?php echo esc_html( $feature_state_heading ); ?></h2>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $feature_state_rows as $feature_state_label => $feature_state_value ) : ?>
				<?php $feature_state_explanation = $feature_state_help->explain( $feature_state_label, $feature_state_value ); ?>
				<tr>
					<td data-export-label="<?php echo esc_attr( $feature_state_label ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $feature_state_label ) ) ); ?></td>
					<td class="help"><span><?php echo 'Original key identifier: ' . esc_html( $feature_state_label ); ?></span></td>
					<td style="overflow-wrap: anywhere;">
						<code><?php echo esc_html( $feature_state_value ); ?></code>
					</td>
					<td class="feature-state-description"><?php echo esc_html( $feature_state_explanation ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endforeach; ?>
