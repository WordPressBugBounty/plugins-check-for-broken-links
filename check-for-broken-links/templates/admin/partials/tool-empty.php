<?php
/**
 * Not-connected state shared by the five tool pages: headline, body,
 * connect action and a "Preview · after you connect" panel with sample
 * data for that tool.
 *
 * Expects $wpcbl_empty = array(
 *   'tool'     => 'rank' | 'audit' | 'links' | 'aiv' | 'uptime',
 *   'headline' => string, 'body' => string, 'example' => string,
 *   'cta'      => string, 'chips' => array<string>, 'preview_meta' => string,
 * ).
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Partials
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_empty_tool = isset( $wpcbl_empty['tool'] ) ? $wpcbl_empty['tool'] : 'rank';
// The previews name this site where a domain shows, never a stand-in.
$wpcbl_empty_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( '' === $wpcbl_empty_host ) {
	$wpcbl_empty_host = 'example.com';
}
?>
<div class="cbl-tool-empty cbl-tool-empty-<?php echo esc_attr( $wpcbl_empty_tool ); ?>">
	<div class="cbl-tool-empty-copy">
		<div class="cbl-tool-empty-top">
			<span class="cbl-tool-empty-icon" aria-hidden="true">
				<?php if ( 'rank' === $wpcbl_empty_tool ) : ?>
					<span class="cbl-tool-empty-bars"><i style="height:12px"></i><i style="height:17px"></i><i style="height:26px"></i><i style="height:34px"></i><i style="height:40px"></i></span>
				<?php elseif ( 'audit' === $wpcbl_empty_tool ) : ?>
					<svg viewBox="0 0 48 40" width="48" height="40"><circle cx="19" cy="19" r="15" fill="none" stroke="var(--cbl-purple-border)" stroke-width="4"></circle><circle cx="19" cy="19" r="15" fill="none" stroke="var(--cbl-purple)" stroke-width="4" stroke-linecap="round" stroke-dasharray="66 94" transform="rotate(-90 19 19)"></circle><path d="M32 32 L44 40" stroke="var(--cbl-purple)" stroke-width="4" stroke-linecap="round" opacity=".7"></path></svg>
				<?php elseif ( 'links' === $wpcbl_empty_tool ) : ?>
					<svg viewBox="0 0 52 40" width="52" height="40"><path d="M13 12 L39 12" stroke="var(--cbl-purple-border)" stroke-width="3" stroke-linecap="round"></path><path d="M13 12 L39 28" stroke="var(--cbl-purple)" stroke-width="3" stroke-linecap="round" stroke-dasharray="5 5"></path><path d="M13 28 L39 28" stroke="var(--cbl-purple-border)" stroke-width="3" stroke-linecap="round"></path><circle cx="13" cy="12" r="6" fill="var(--cbl-purple)"></circle><circle cx="39" cy="12" r="6" fill="var(--cbl-purple-border)"></circle><circle cx="13" cy="28" r="6" fill="var(--cbl-purple-border)"></circle><circle cx="39" cy="28" r="6" fill="var(--cbl-purple)" opacity=".7"></circle></svg>
				<?php elseif ( 'aiv' === $wpcbl_empty_tool ) : ?>
					<svg viewBox="0 0 52 40" width="52" height="40"><path d="M6 6h26a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4H16l-8 6v-6H6a2 2 0 0 1-2-2V8a4 4 0 0 1 2-2z" fill="var(--cbl-purple-bg)" stroke="var(--cbl-purple-border)" stroke-width="2" stroke-linejoin="round"></path><circle cx="14" cy="16" r="2.6" fill="var(--cbl-purple)" opacity=".7"></circle><circle cx="22" cy="16" r="2.6" fill="var(--cbl-purple)"></circle><circle cx="30" cy="16" r="2.6" fill="var(--cbl-purple-border)"></circle><path d="M41 4l1.4 4 4 1.4-4 1.4L41 15l-1.4-4.2-4-1.4 4-1.4L41 4z" fill="var(--cbl-purple)"></path></svg>
				<?php else : ?>
					<span class="cbl-tool-empty-bars is-uptime"><i></i><i></i><i></i><i class="is-down"></i><i></i><i></i></span>
				<?php endif; ?>
			</span>
			<span class="cbl-tool-empty-pill"><span class="cbl-tool-empty-pill-dot"></span><?php esc_html_e( 'Free plan included', 'check-for-broken-links' ); ?></span>
		</div>
		<h1 class="cbl-tool-empty-title"><?php echo esc_html( $wpcbl_empty['headline'] ); ?></h1>
		<p class="cbl-tool-empty-body"><?php echo esc_html( $wpcbl_empty['body'] ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<button type="submit" class="cbl-btn cbl-btn-primary cbl-tool-empty-cta"><?php echo esc_html( $wpcbl_empty['cta'] ); ?> <span aria-hidden="true">&rarr;</span></button>
		</form>
		<div class="cbl-tool-empty-assure">
			<?php foreach ( $wpcbl_empty['chips'] as $wpcbl_i => $wpcbl_chip ) : ?>
				<?php if ( $wpcbl_i > 0 ) : ?><span class="cbl-tool-empty-sep" aria-hidden="true">|</span><?php endif; ?>
				<span><?php echo esc_html( $wpcbl_chip ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="cbl-tool-empty-preview" aria-label="<?php esc_attr_e( 'Preview of the tool after you connect, with sample data', 'check-for-broken-links' ); ?>">
		<div class="cbl-tool-empty-preview-head">
			<span><?php esc_html_e( 'Preview · after you connect', 'check-for-broken-links' ); ?></span>
			<span><?php echo esc_html( $wpcbl_empty['preview_meta'] ); ?></span>
		</div>

		<?php if ( 'rank' === $wpcbl_empty_tool ) : ?>
			<div class="cbl-tep-card">
				<div class="cbl-tep-row">
					<div class="cbl-tep-main"><div class="cbl-tep-name">wordpress broken link checker</div><div class="cbl-tep-sub">Google &middot; United States</div></div>
					<div class="cbl-tep-nums"><span class="cbl-tep-was">14</span><span class="cbl-tep-now">8</span><span class="cbl-tep-delta is-up">&#9650; 6</span></div>
				</div>
				<svg viewBox="0 0 300 60" class="cbl-tep-spark" aria-hidden="true"><polyline points="4,46 52,44 100,49 148,38 196,30 244,18 296,12" fill="none" stroke="var(--cbl-purple)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline><circle cx="296" cy="12" r="4" fill="var(--cbl-purple)"></circle></svg>
			</div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">broken link checker plugin</span><span class="cbl-tep-nums"><span class="cbl-tep-pos">21</span><span class="cbl-tep-delta is-up">&#9650; 3</span></span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">seo audit wordpress</span><span class="cbl-tep-nums"><span class="cbl-tep-pos">34</span><span class="cbl-tep-delta is-down">&#9660; 2</span></span></div>

		<?php elseif ( 'audit' === $wpcbl_empty_tool ) : ?>
			<div class="cbl-tep-card cbl-tep-score">
				<svg viewBox="0 0 72 72" width="72" height="72" aria-hidden="true"><circle cx="36" cy="36" r="29" fill="none" stroke="var(--cbl-purple-bg)" stroke-width="10"></circle><circle cx="36" cy="36" r="29" fill="none" stroke="var(--cbl-purple)" stroke-width="10" stroke-linecap="round" stroke-dasharray="128 182" transform="rotate(-90 36 36)"></circle></svg>
				<div><div class="cbl-tep-big">70 <span class="cbl-tep-sub">/ 100 <?php esc_html_e( 'audit score', 'check-for-broken-links' ); ?></span></div><div class="cbl-tep-sub"><?php esc_html_e( '14 issues found, each with a fix', 'check-for-broken-links' ); ?></div></div>
			</div>
			<div class="cbl-tep-line"><span class="cbl-tep-name"><?php esc_html_e( 'Missing meta descriptions', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-warn">12 <?php esc_html_e( 'pages', 'check-for-broken-links' ); ?></span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name"><?php esc_html_e( 'No FAQ schema on product pages', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-purple">AEO</span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name"><?php esc_html_e( 'Headings in the right order', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-ok"><?php esc_html_e( 'Passed', 'check-for-broken-links' ); ?></span></div>

		<?php elseif ( 'links' === $wpcbl_empty_tool ) : ?>
			<div class="cbl-tep-card">
				<div class="cbl-tep-row"><span class="cbl-tep-label"><?php esc_html_e( 'Suggested link', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-purple"><?php esc_html_e( 'High value', 'check-for-broken-links' ); ?></span></div>
				<div class="cbl-tep-stack">
					<div class="cbl-tep-name">How to choose running shoes</div>
					<div class="cbl-tep-sub"><span aria-hidden="true">&darr;</span> <?php esc_html_e( 'anchor:', 'check-for-broken-links' ); ?> <strong>marathon training</strong></div>
					<div class="cbl-tep-name">Marathon training plan</div>
				</div>
				<div class="cbl-tep-row"><span class="cbl-tep-apply"><svg viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M8 0.5l1.5 4.3 4.3 1.5-4.3 1.5L8 12.1 6.5 7.8 2.2 6.3l4.3-1.5L8 0.5zM13 10.6l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2z"></path></svg><?php esc_html_e( 'Apply with AI', 'check-for-broken-links' ); ?></span><span class="cbl-tep-sub"><?php esc_html_e( 'inserts the link in place', 'check-for-broken-links' ); ?></span></div>
			</div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">Best trail shoes 2026</span><span class="cbl-tep-chip is-warn">3 <?php esc_html_e( 'gaps', 'check-for-broken-links' ); ?></span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">Recovery after long runs</span><span class="cbl-tep-chip is-err"><?php esc_html_e( 'No content links', 'check-for-broken-links' ); ?></span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">Shoe sizing guide</span><span class="cbl-tep-chip is-ok"><?php esc_html_e( 'Well linked', 'check-for-broken-links' ); ?></span></div>

		<?php elseif ( 'aiv' === $wpcbl_empty_tool ) : ?>
			<div class="cbl-tep-card">
				<div class="cbl-tep-label"><?php esc_html_e( 'Prompt tracked', 'check-for-broken-links' ); ?></div>
				<div class="cbl-tep-name">&ldquo;what is the best broken link checker for WordPress&rdquo;</div>
				<div class="cbl-tep-stack cbl-tep-engines">
					<div class="cbl-tep-row"><span>ChatGPT</span><span class="cbl-tep-nums"><span class="cbl-tep-chip is-ok"><?php esc_html_e( 'Mentioned', 'check-for-broken-links' ); ?> &middot; #2</span><span class="cbl-tep-delta is-up">&#9650; 1</span></span></div>
					<div class="cbl-tep-row"><span>Perplexity</span><span class="cbl-tep-chip is-err"><?php esc_html_e( 'Not named', 'check-for-broken-links' ); ?></span></div>
					<div class="cbl-tep-row"><span>Google AI</span><span class="cbl-tep-chip is-ok"><?php esc_html_e( 'Mentioned', 'check-for-broken-links' ); ?> &middot; #4</span></div>
				</div>
			</div>
			<div class="cbl-tep-line cbl-tep-line-stack"><span class="cbl-tep-label"><?php esc_html_e( 'Named instead of you', 'check-for-broken-links' ); ?></span><span class="cbl-tep-tags"><span>competitor-a.com</span><span>competitor-b.io</span><span>competitor-c.com</span></span></div>
			<div class="cbl-tep-line"><span class="cbl-tep-name">&ldquo;wordpress seo audit plugin&rdquo;</span><span class="cbl-tep-chip"><?php esc_html_e( 'Queued', 'check-for-broken-links' ); ?></span></div>

		<?php else : ?>
			<div class="cbl-tep-card">
				<div class="cbl-tep-row">
					<div class="cbl-tep-main"><div class="cbl-tep-name"><?php echo esc_html( $wpcbl_empty_host ); ?></div><div class="cbl-tep-sub"><?php esc_html_e( 'Checked every 5 minutes', 'check-for-broken-links' ); ?></div></div>
					<div class="cbl-tep-nums"><span class="cbl-tep-now">99.94%</span><span class="cbl-tep-sub"><?php esc_html_e( 'uptime', 'check-for-broken-links' ); ?></span></div>
				</div>
				<div class="cbl-tep-uptime" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i class="is-down"></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
			</div>
			<div class="cbl-tep-line cbl-tep-line-stack">
				<span class="cbl-tep-row"><span class="cbl-tep-name"><span class="cbl-tep-dot is-err"></span><?php esc_html_e( 'Outage · 20 minutes', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-ok"><?php esc_html_e( 'Resolved', 'check-for-broken-links' ); ?></span></span>
				<span class="cbl-tep-sub"><?php esc_html_e( 'Down 3:04 AM', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span> <?php esc_html_e( 'Back 3:24 AM', 'check-for-broken-links' ); ?></span>
			</div>
			<div class="cbl-tep-line"><span class="cbl-tep-name"><span class="cbl-tep-dot is-warn"></span><?php esc_html_e( 'Slow response · 2.8s', 'check-for-broken-links' ); ?></span><span class="cbl-tep-chip is-warn"><?php esc_html_e( 'Warning', 'check-for-broken-links' ); ?></span></div>
		<?php endif; ?>

		<p class="cbl-tool-empty-example"><?php echo esc_html( $wpcbl_empty['example'] ); ?></p>
	</div>
</div>
