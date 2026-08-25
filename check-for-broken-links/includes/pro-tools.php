<?php
/**
 * Pro Tools preview page helpers: the four upcoming Pro tools with the same
 * sample datasets the brokenlinkchecker.io dashboard previews use. All demo
 * data is rendered dimmed behind a COMING SOON badge — it must never read as
 * live results.
 *
 * @package WPCBL_Check_Broken_Links
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'wpcbl_get_pro_tools' ) ) {
	/**
	 * The four upcoming Pro tools, keyed and ordered like the dashboard.
	 *
	 * Each tool: title, tagline, demo (rows for the preview table/list) and
	 * a 'columns' list where the demo renders as a table.
	 *
	 * @since 3.0.2
	 *
	 * @return array<string, array{title: string, tagline: string, columns?: array, demo: array}>
	 */
	function wpcbl_get_pro_tools() {
		return array(
			'ai-fix'         => array(
				'title'   => __( 'Fix with AI', 'check-for-broken-links' ),
				'tagline' => __( 'AI finds the best replacement for every broken link and fixes it in your post with one click.', 'check-for-broken-links' ),
				'columns' => array(
					__( 'Broken URL', 'check-for-broken-links' ),
					__( 'Status', 'check-for-broken-links' ),
					__( 'Suggested fix', 'check-for-broken-links' ),
					__( 'Confidence', 'check-for-broken-links' ),
				),
				'demo'    => array(
					array( '/blog/2019/seo-guide-old', '404', '/blog/seo-guide', '94%' ),
					array( '/docs/api/v1', '404', '/docs/api', '91%' ),
					array( '/products/legacy-widget', '410', '/products/widget-v2', '89%' ),
					array( 'twitter.com/oldhandle', '404', 'twitter.com/acmestore', '76%' ),
				),
			),
			'internal-links' => array(
				'title'   => __( 'Internal Link Optimizer', 'check-for-broken-links' ),
				'tagline' => __( 'Scans your content and suggests the internal links and anchors that lift rankings most.', 'check-for-broken-links' ),
				'columns' => array(
					__( 'Page', 'check-for-broken-links' ),
					__( 'Link to', 'check-for-broken-links' ),
					__( 'Suggested anchor', 'check-for-broken-links' ),
					__( 'Impact', 'check-for-broken-links' ),
				),
				'demo'    => array(
					array( '/blog/seo-checklist', '/features/monitoring', 'scheduled link monitoring', 'High' ),
					array( '/blog/wordpress-links', '/wordpress-plugin', 'our WordPress plugin', 'High' ),
					array( '/docs/getting-started', '/pricing', 'compare plans', 'Medium' ),
					array( '/blog/404-guide', '/features/csv-export', 'export broken links to CSV', 'Medium' ),
				),
			),
			'seo-audit'      => array(
				'title'   => __( 'SEO / AEO Audit', 'check-for-broken-links' ),
				'tagline' => __( 'Classic SEO checks plus AEO: how well AI assistants can read and cite your site.', 'check-for-broken-links' ),
				'columns' => array(
					__( 'Severity', 'check-for-broken-links' ),
					__( 'Finding', 'check-for-broken-links' ),
					__( 'Where', 'check-for-broken-links' ),
				),
				'demo'    => array(
					array( 'CRITICAL', __( '3 pages missing meta description', 'check-for-broken-links' ), '/products, /careers, /shipping' ),
					array( 'WARNING', __( '12 images missing alt text', 'check-for-broken-links' ), __( 'across 8 pages', 'check-for-broken-links' ) ),
					array( 'MISSING', __( 'llms.txt file for AI assistants', 'check-for-broken-links' ), '/' ),
					array( 'PASSED', __( 'HTTPS, sitemap.xml and robots.txt configured', 'check-for-broken-links' ), __( 'site-wide', 'check-for-broken-links' ) ),
				),
			),
			'white-label'    => array(
				'title'   => __( 'White Label', 'check-for-broken-links' ),
				'tagline' => __( 'Put your own logo, colors and domain on every report you share with clients. Business and Agency plans.', 'check-for-broken-links' ),
				'demo'    => array(
					array( __( 'Your logo', 'check-for-broken-links' ), __( 'On every shared report', 'check-for-broken-links' ) ),
					array( __( 'Accent color', 'check-for-broken-links' ), __( 'Match your brand palette', 'check-for-broken-links' ) ),
					array( __( 'Custom domain', 'check-for-broken-links' ), 'reports.youragency.com' ),
					array( __( 'Hide our branding', 'check-for-broken-links' ), __( '100% your name on the report', 'check-for-broken-links' ) ),
				),
			),
		);
	}
}

if ( ! function_exists( 'wpcbl_pro_tools_cta' ) ) {
	/**
	 * The plan-dependent call to action for the Pro Tools page.
	 *
	 * Free: upgrade link. Paid: "included in your plan" and NO purchase link —
	 * a paying customer must never be asked to upgrade for something their
	 * plan already covers.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $has_pro Whether the site has an active paid plan.
	 *
	 * @return array{label: string, url: string|null}
	 */
	function wpcbl_pro_tools_cta( $has_pro ) {
		if ( $has_pro ) {
			return array(
				'label' => __( 'Included in your plan — these tools unlock here automatically at launch.', 'check-for-broken-links' ),
				'url'   => null,
			);
		}

		return array(
			'label' => __( 'Upgrade to Pro — get every tool on this page at launch, at no extra cost.', 'check-for-broken-links' ),
			'url'   => admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ),
		);
	}
}
