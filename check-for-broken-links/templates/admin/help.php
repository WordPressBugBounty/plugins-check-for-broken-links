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
		'answer'   => __( 'It scans your posts, pages and custom content for links that no longer work, then lists every one it finds with the page it sits on. Connect the site and the same dashboard also covers rankings, audits, internal links, AI visibility and uptime.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'How do I check for broken links on my site?', 'check-for-broken-links' ),
		'answer'   => __( 'Go to the Dashboard and click Start Manual Scan. The plugin scans supported content and sends you to Broken link scan for the results.', 'check-for-broken-links' ),
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
		'answer'   => __( 'Not on its own. It shows every broken link with the page it sits on, so you can edit, redirect or remove it. Pro adds Fix with AI, which suggests a replacement and applies it with one click.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Is the plugin free?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes. The core plugin is free forever, and a free brokenlinkchecker.io account unlocks every tool with free limits. Pro Personal is $49 a year, Pro Business $99 and Pro Agency $299.', 'check-for-broken-links' ),
	),
);

$wpcbl_page_title = __( 'Help', 'check-for-broken-links' );

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php
$wpcbl_help_conn      = wpcbl_connect();
$wpcbl_help_connected = $wpcbl_help_conn && $wpcbl_help_conn->is_connected();
?>
<div class="cbl-help">
	<div class="cbl-help-faq">
		<div class="cbl-help-faq-head">
			<h2><?php esc_html_e( 'Frequently asked questions', 'check-for-broken-links' ); ?></h2>
			<span><?php
				printf(
					/* translators: %d: number of answers. */
					esc_html( _n( '%d answer', '%d answers', count( $wpcbl_faq ), 'check-for-broken-links' ) ),
					count( $wpcbl_faq )
				);
			?></span>
		</div>
		<div class="cbl-faq-list cbl-help-faq-list">
			<?php foreach ( $wpcbl_faq as $wpcbl_i => $wpcbl_faq_item ) : ?>
				<details class="cbl-faq-item"<?php echo 0 === $wpcbl_i ? ' open' : ''; ?>>
					<summary><?php echo esc_html( $wpcbl_faq_item['question'] ); ?></summary>
					<div class="cbl-faq-answer">
						<p><?php echo wp_kses_post( nl2br( $wpcbl_faq_item['answer'] ) ); ?></p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>

	<aside class="cbl-help-rail">
		<div class="cbl-help-contact">
			<div class="cbl-help-contact-head">
				<span class="cbl-help-icon" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M1.8 4h12.4a1 1 0 0 1 1 1v6.4a1 1 0 0 1-1 1H1.8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"></path><path d="M1 4.5l7 5 7-5"></path></svg></span>
				<span class="cbl-help-contact-title"><?php esc_html_e( 'Still stuck?', 'check-for-broken-links' ); ?></span>
			</div>
			<p><?php esc_html_e( 'Send us the page URL and a short description. We usually reply within one working day.', 'check-for-broken-links' ); ?></p>
			<a href="https://brokenlinkchecker.io/contact" target="_blank" rel="noopener noreferrer" class="cbl-btn cbl-btn-primary cbl-help-contact-cta"><?php esc_html_e( 'Contact support', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>

		<div class="cbl-help-links">
			<div class="cbl-help-links-head"><?php esc_html_e( 'Other ways to get help', 'check-for-broken-links' ); ?></div>
			<a href="https://wordpress.org/support/plugin/check-for-broken-links/" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'WordPress.org support forum', 'check-for-broken-links' ); ?></span><span aria-hidden="true">&#8599;</span></a>
			<a href="https://brokenlinkchecker.io/wordpress-plugin" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Plugin guide on brokenlinkchecker.io', 'check-for-broken-links' ); ?></span><span aria-hidden="true">&#8599;</span></a>
			<a href="https://wordpress.org/support/plugin/check-for-broken-links/reviews/#new-post" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Leave a review', 'check-for-broken-links' ); ?></span><span class="cbl-help-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span></a>
		</div>

		<div class="cbl-help-meta">
			<div><?php
				printf(
					/* translators: %s: plugin version. */
					esc_html__( 'Plugin version %s', 'check-for-broken-links' ),
					esc_html( WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION )
				);
			?></div>
			<div><?php echo $wpcbl_help_connected ? esc_html__( 'Site connected to brokenlinkchecker.io', 'check-for-broken-links' ) : esc_html__( 'Site not connected yet', 'check-for-broken-links' ); ?></div>
		</div>
	</aside>
</div>
<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
