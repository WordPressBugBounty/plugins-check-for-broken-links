<?php
/**
 * Help page (redesigned).
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_faq = array(
	array(
		'question' => __( 'What does the Check for Broken Links plugin do?', 'check-for-broken-links' ),
		'answer'   => __( 'The plugin scans your website for broken URLs, dead links, and 404 errors across your content.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'How do I check for broken links on my site?', 'check-for-broken-links' ),
		'answer'   => __( 'Go to the Dashboard & Scan page and click Start Manual Scan. The plugin will scan supported content and display the results in a table.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Will fixing broken links improve SEO?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes. Removing broken links improves user experience and helps search engines crawl your website more efficiently.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'What content can the plugin scan?', 'check-for-broken-links' ),
		'answer'   => __( 'The plugin scans posts, pages, custom content, comments, and slider content.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Does the plugin fix broken links automatically?', 'check-for-broken-links' ),
		'answer'   => __( 'No. The plugin only detects broken links and shows where they appear. You can then manually update or remove them.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Is the plugin free?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes. The core plugin is free forever. Pro plans start at $29 per year for 1 site, $49 for 5 sites, and $159 for 250 sites.', 'check-for-broken-links' ),
	),
);

$wpcbl_page_title = __( 'Help', 'check-for-broken-links' );

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<div class="cbl-card">
	<h2><?php esc_html_e( 'Frequently Asked Questions', 'check-for-broken-links' ); ?></h2>

	<div class="cbl-faq-list">
		<?php foreach ( $wpcbl_faq as $wpcbl_faq_item ) : ?>
			<details class="cbl-faq-item">
				<summary><?php echo esc_html( $wpcbl_faq_item['question'] ); ?></summary>
				<div class="cbl-faq-answer">
					<p><?php echo wp_kses_post( nl2br( $wpcbl_faq_item['answer'] ) ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</div>

<div class="cbl-card">
	<h2><?php esc_html_e( 'Need more help?', 'check-for-broken-links' ); ?></h2>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: support URL, 2: forum URL. */
				__( 'Reach out to us at <a href="%1$s" target="_blank" rel="noopener noreferrer">brokenlinkchecker.io/support</a> or post in the <a href="%2$s" target="_blank" rel="noopener noreferrer">WordPress.org support forum</a>.', 'check-for-broken-links' ),
				'https://brokenlinkchecker.io/support/',
				'https://wordpress.org/support/plugin/check-for-broken-links/'
			),
			array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
		);
		?>
	</p>
</div>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
