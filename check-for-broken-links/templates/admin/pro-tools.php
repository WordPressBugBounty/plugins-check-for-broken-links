<?php
/**
 * Pro Tools preview page: the four upcoming Pro tools with clearly-labeled
 * demo data. Free plans get an upgrade CTA; paid plans see "included in
 * your plan" — never an upgrade button.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'Pro Tools', 'check-for-broken-links' );

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';

$wpcbl_pt_tools   = wpcbl_get_pro_tools();
$wpcbl_pt_has_pro = wpcbl_has_pro();
$wpcbl_pt_cta     = wpcbl_pro_tools_cta( $wpcbl_pt_has_pro );
?>

<div class="cbl-pro-tools-page">

	<div class="cbl-card cbl-pt-intro">
		<div class="cbl-pt-intro-text">
			<h2><?php esc_html_e( 'Five new tools are on the way', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Everything below is a preview with sample data — each tool fills with your site\'s real data the day it launches.', 'check-for-broken-links' ); ?></p>
		</div>
		<div class="cbl-pt-intro-cta">
			<?php if ( $wpcbl_pt_cta['url'] ) : ?>
				<a href="<?php echo esc_url( $wpcbl_pt_cta['url'] ); ?>" class="cbl-btn cbl-btn-primary"><?php echo esc_html( $wpcbl_pt_cta['label'] ); ?></a>
			<?php else : ?>
				<span class="cbl-pt-included"><?php echo esc_html( $wpcbl_pt_cta['label'] ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<?php foreach ( $wpcbl_pt_tools as $wpcbl_pt_key => $wpcbl_pt_tool ) : ?>
		<div class="cbl-card cbl-pt-card">
			<div class="cbl-pt-head">
				<div>
					<h3 class="cbl-pt-title"><?php echo esc_html( $wpcbl_pt_tool['title'] ); ?></h3>
					<p class="cbl-pt-tagline"><?php echo esc_html( $wpcbl_pt_tool['tagline'] ); ?></p>
				</div>
				<span class="cbl-pt-soon-badge"><?php esc_html_e( 'COMING SOON', 'check-for-broken-links' ); ?></span>
			</div>

			<div class="cbl-pt-demo" aria-hidden="true">
				<?php if ( 'white-label' === $wpcbl_pt_key ) : ?>
					<div class="cbl-pt-feature-grid">
						<?php foreach ( $wpcbl_pt_tool['demo'] as $wpcbl_pt_row ) : ?>
							<div class="cbl-pt-feature">
								<span class="cbl-pt-feature-name"><?php echo esc_html( $wpcbl_pt_row[0] ); ?></span>
								<span class="cbl-pt-feature-desc"><?php echo esc_html( $wpcbl_pt_row[1] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<table class="cbl-pt-table">
						<thead>
							<tr>
								<?php foreach ( $wpcbl_pt_tool['columns'] as $wpcbl_pt_col ) : ?>
									<th><?php echo esc_html( $wpcbl_pt_col ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wpcbl_pt_tool['demo'] as $wpcbl_pt_row ) : ?>
								<tr>
									<?php foreach ( $wpcbl_pt_row as $wpcbl_pt_cell ) : ?>
										<td><?php echo esc_html( $wpcbl_pt_cell ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<span class="cbl-pt-demo-label"><?php esc_html_e( 'Sample data', 'check-for-broken-links' ); ?></span>
			</div>
		</div>
	<?php endforeach; ?>

	<?php if ( $wpcbl_pt_cta['url'] ) : ?>
		<div class="cbl-card cbl-pt-footer-cta">
			<span><?php esc_html_e( 'One plan unlocks every tool on this page the day it ships.', 'check-for-broken-links' ); ?></span>
			<a href="<?php echo esc_url( $wpcbl_pt_cta['url'] ); ?>" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?></a>
		</div>
	<?php endif; ?>

</div>

<?php require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php'; ?>
