<?php
/**
 * Settings-page "Account" section: a lavender hero card that connects this
 * site to a brokenlinkchecker.io account. Mirrors the SEO Tip page's hero +
 * benefit-card visual language so it reads as distinct from the grey
 * settings boxes below.
 *
 * @package WPCBL_Check_Broken_Links
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpcbl_connect = wpcbl_connect();

if ( ! $wpcbl_connect ) {
	return;
}

$wpcbl_is_connected = $wpcbl_connect->is_connected();
$wpcbl_connection   = $wpcbl_connect->get_connection();
$wpcbl_entitlements = $wpcbl_connect->get_entitlements();
$wpcbl_action_url   = admin_url( 'admin-post.php' );
$wpcbl_plan_label   = ucfirst( ! empty( $wpcbl_connection['plan'] ) ? $wpcbl_connection['plan'] : 'free' );
?>
<div id="cbl-connect" class="<?php echo $wpcbl_is_connected ? 'cbl-connect-slim' : 'cbl-connect-hero'; ?>">

	<?php if ( $wpcbl_is_connected ) : ?>

		<div class="cbl-connect-bar">
			<div class="cbl-connect-bar-info">
				<span class="cbl-connect-bar-title">
					<?php esc_html_e( 'Your site is linked to brokenlinkchecker.io', 'check-for-broken-links' ); ?>
					<span class="cbl-connect-bar-status"><span class="cbl-connect-status-dot"></span><?php esc_html_e( 'Connected', 'check-for-broken-links' ); ?></span>
				</span>
				<span class="cbl-connect-bar-meta">
					<?php
					$wpcbl_used  = isset( $wpcbl_entitlements['quota']['used'] ) ? (int) $wpcbl_entitlements['quota']['used'] : null;
					$wpcbl_limit = isset( $wpcbl_entitlements['quota']['limit'] ) ? (int) $wpcbl_entitlements['quota']['limit'] : null;
					if ( null !== $wpcbl_used && null !== $wpcbl_limit ) {
						printf(
							/* translators: 1: account email, 2: plan name, 3: sites used, 4: site limit. */
							esc_html__( '%1$s · %2$s plan · %3$d of %4$d sites used', 'check-for-broken-links' ),
							esc_html( isset( $wpcbl_connection['email'] ) ? $wpcbl_connection['email'] : '' ),
							esc_html( $wpcbl_plan_label ),
							$wpcbl_used,
							$wpcbl_limit
						);
					} else {
						printf(
							/* translators: 1: account email, 2: plan name. */
							esc_html__( '%1$s · %2$s plan', 'check-for-broken-links' ),
							esc_html( isset( $wpcbl_connection['email'] ) ? $wpcbl_connection['email'] : '' ),
							esc_html( $wpcbl_plan_label )
						);
					}
					?>
				</span>
			</div>
			<div class="cbl-connect-bar-actions">
				<a href="https://brokenlinkchecker.io/dashboard" target="_blank" rel="noopener noreferrer" class="cbl-connect-bar-btn cbl-connect-bar-btn-solid"><?php esc_html_e( 'Manage in dashboard', 'check-for-broken-links' ); ?></a>
				<form method="post" action="<?php echo esc_url( $wpcbl_action_url ); ?>">
					<input type="hidden" name="action" value="wpcbl_refresh" />
					<?php wp_nonce_field( 'wpcbl_refresh' ); ?>
					<button type="submit" class="cbl-connect-bar-btn cbl-connect-bar-btn-ghost"><?php esc_html_e( 'Refresh connection', 'check-for-broken-links' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( $wpcbl_action_url ); ?>">
					<input type="hidden" name="action" value="wpcbl_disconnect" />
					<?php wp_nonce_field( 'wpcbl_disconnect' ); ?>
					<button type="submit" class="cbl-connect-bar-link"><?php esc_html_e( 'Disconnect', 'check-for-broken-links' ); ?></button>
				</form>
			</div>
		</div>

	<?php else : ?>

		<span class="cbl-connect-status cbl-connect-status-off">
			<span class="cbl-connect-status-dot"></span>
			<?php esc_html_e( 'Not connected', 'check-for-broken-links' ); ?>
		</span>

		<h2 class="cbl-connect-hero-title"><?php esc_html_e( 'Connect your site to unlock more', 'check-for-broken-links' ); ?></h2>
		<p class="cbl-connect-hero-sub"><?php esc_html_e( 'Link this site to a free brokenlinkchecker.io account. No license keys, ever.', 'check-for-broken-links' ); ?></p>

		<div class="cbl-connect-grid">

			<div class="cbl-card cbl-benefit-card">
				<div class="cbl-benefit-icon cbl-benefit-icon-green">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				</div>
				<h3 class="cbl-benefit-heading"><?php esc_html_e( 'Instant activation', 'check-for-broken-links' ); ?></h3>
				<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Connect once and every feature unlocks here automatically.', 'check-for-broken-links' ); ?></p>
			</div>

			<div class="cbl-card cbl-benefit-card">
				<div class="cbl-benefit-icon cbl-benefit-icon-purple">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
				</div>
				<h3 class="cbl-benefit-heading"><?php esc_html_e( 'All your sites in one place', 'check-for-broken-links' ); ?></h3>
				<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Manage every connected site from a single dashboard.', 'check-for-broken-links' ); ?></p>
			</div>

			<div class="cbl-card cbl-benefit-card">
				<div class="cbl-benefit-icon cbl-benefit-icon-orange">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
				</div>
				<h3 class="cbl-benefit-heading"><?php esc_html_e( 'Early access & member perks', 'check-for-broken-links' ); ?></h3>
				<p class="cbl-pro-feature-desc"><?php esc_html_e( 'New features and the occasional bonus.', 'check-for-broken-links' ); ?></p>
			</div>

			<div class="cbl-card cbl-benefit-card">
				<div class="cbl-benefit-icon cbl-benefit-icon-blue">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
				</div>
				<h3 class="cbl-benefit-heading"><?php esc_html_e( 'Scan history & health', 'check-for-broken-links' ); ?></h3>
				<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Your broken-link trends saved to your account over time.', 'check-for-broken-links' ); ?></p>
			</div>

		</div>

		<div class="cbl-connect-cta">
			<form method="post" action="<?php echo esc_url( $wpcbl_action_url ); ?>">
				<input type="hidden" name="action" value="wpcbl_connect_start" />
				<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
				<button type="submit" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?></button>
			</form>
			<p class="cbl-seo-tip-note"><?php esc_html_e( 'Optional but recommended. It is free and takes a few seconds. Required to unlock Pro features on this site.', 'check-for-broken-links' ); ?></p>
		</div>

	<?php endif; ?>

</div>
