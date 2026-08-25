<?php
/**
 * Opens the redesigned admin layout: wrap, sidebar, topbar, main content.
 *
 * Contract - set before including:
 *   $wpcbl_page_title     (string, required) topbar title.
 *   $wpcbl_topbar_meta    (string HTML, optional) inline meta next to the title.
 *   $wpcbl_topbar_actions (string HTML, optional) buttons/links on the right.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="cbl-admin-wrap">
	<?php require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/sidebar.php'; ?>
	<div class="cbl-main">
		<?php require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/ttswp-banner.php'; ?>
		<div class="cbl-topbar">
			<div class="cbl-topbar-left">
				<span class="cbl-topbar-title"><?php echo esc_html( isset( $wpcbl_page_title ) ? $wpcbl_page_title : '' ); ?></span>
				<?php if ( ! empty( $wpcbl_topbar_meta ) ) : ?>
					<div class="cbl-topbar-meta"><?php
						echo wp_kses( $wpcbl_topbar_meta, array(
							'span'   => array( 'class' => true, 'id' => true ),
							'strong' => array( 'class' => true ),
							'a'      => array( 'href' => true, 'class' => true, 'target' => true, 'rel' => true ),
						) );
					?></div>
				<?php endif; ?>
			</div>
			<div class="cbl-topbar-actions">
				<?php
				if ( ! empty( $wpcbl_topbar_actions ) ) {
					echo wp_kses( $wpcbl_topbar_actions, array(
						'button' => array( 'type' => true, 'form' => true, 'class' => true, 'id' => true, 'title' => true, 'disabled' => true ),
						'input'  => array( 'type' => true, 'id' => true, 'class' => true, 'name' => true, 'value' => true, 'form' => true ),
						'a'      => array( 'href' => true, 'class' => true, 'id' => true, 'target' => true, 'rel' => true, 'style' => true ),
						'span'   => array( 'class' => true, 'id' => true ),
					) );
				}
				?>
			</div>
		</div>
		<div class="cbl-main-content">
