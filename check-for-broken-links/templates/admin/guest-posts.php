<?php
/**
 * Premium Guest Posts page: pick a package, name the pages the posts link
 * to, pay on Stripe. Mirrors the Link Building page on brokenlinkchecker.io.
 *
 * Packages and prices come from the catalog brokenlinkchecker.io serves
 * from its own billing config, never from this file, so the price shown
 * here is the price charged. The page works without a connection: the
 * order button connects the site first and then continues to checkout.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'Premium Guest Posts', 'check-for-broken-links' );
$wpcbl_gp_conn    = wpcbl_connect();
$wpcbl_gp_is_on   = $wpcbl_gp_conn && $wpcbl_gp_conn->is_connected();
$wpcbl_gp_catalog = $wpcbl_gp_conn ? $wpcbl_gp_conn->fetch_guest_posts_catalog() : null;

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_gp_catalog ) : ?>

	<div class="cbl-card">
		<h2><?php esc_html_e( 'Could not load the guest post packages', 'check-for-broken-links' ); ?></h2>
		<p><?php esc_html_e( 'brokenlinkchecker.io did not answer. Wait two minutes and reload this page.', 'check-for-broken-links' ); ?></p>
		<a class="cbl-btn cbl-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-guest-posts' ) ); ?>"><?php esc_html_e( 'Reload', 'check-for-broken-links' ); ?></a>
	</div>

<?php else : ?>

	<?php
	$wpcbl_gp_default = $wpcbl_gp_catalog['packages'][0]['key'];
	foreach ( $wpcbl_gp_catalog['packages'] as $wpcbl_gp_package ) {
		if ( $wpcbl_gp_package['popular'] ) {
			$wpcbl_gp_default = $wpcbl_gp_package['key'];
			break;
		}
	}
	?>

	<form class="cbl-gp" id="wpcbl-gp" novalidate>

		<div class="cbl-gp-head">
			<h1 class="cbl-gp-title"><?php esc_html_e( 'Boost your rankings and get cited in AI answers', 'check-for-broken-links' ); ?></h1>
			<p class="cbl-gp-sub"><?php esc_html_e( 'Each post is a 1,000+ word article published on a DR 50+ site with 10k+ monthly Ahrefs traffic. It carries a dofollow in-body link to your page. Every placement is indexed and reported with its live URL.', 'check-for-broken-links' ); ?></p>
		</div>

		<div class="cbl-gp-flash" id="wpcbl-gp-flash" role="status" hidden></div>

		<div class="cbl-gp-packages" role="radiogroup" aria-label="<?php esc_attr_e( 'Package', 'check-for-broken-links' ); ?>">
			<?php foreach ( $wpcbl_gp_catalog['packages'] as $wpcbl_gp_package ) : ?>
				<?php
				$wpcbl_gp_picked   = $wpcbl_gp_package['key'] === $wpcbl_gp_default;
				$wpcbl_gp_per_post = (int) round( $wpcbl_gp_package['price'] / $wpcbl_gp_package['posts'] );
				?>
				<label class="cbl-gp-card<?php echo $wpcbl_gp_picked ? ' is-picked' : ''; ?>">
					<input type="radio" name="wpcbl_gp_package" class="screen-reader-text"
						value="<?php echo esc_attr( $wpcbl_gp_package['key'] ); ?>" <?php checked( $wpcbl_gp_picked ); ?>>
					<?php if ( $wpcbl_gp_package['popular'] ) : ?>
						<span class="cbl-gp-flag"><?php esc_html_e( 'MOST POPULAR', 'check-for-broken-links' ); ?></span>
					<?php endif; ?>
					<span class="cbl-gp-card-top">
						<span class="cbl-gp-card-id">
							<span class="cbl-gp-name"><?php echo esc_html( $wpcbl_gp_package['name'] ); ?></span>
							<span class="cbl-gp-note">
								<?php
								/* translators: 1: number of guest posts, 2: delivery time in days. */
								echo esc_html( sprintf( __( '%1$s posts · %2$s-day delivery', 'check-for-broken-links' ), number_format_i18n( $wpcbl_gp_package['posts'] ), number_format_i18n( $wpcbl_gp_package['delivery_days'] ) ) );
								?>
							</span>
						</span>
						<span class="cbl-gp-radio" aria-hidden="true"></span>
					</span>
					<span class="cbl-gp-price">
						<span class="cbl-gp-amount"><?php echo esc_html( '$' . number_format( $wpcbl_gp_package['price'] ) ); ?></span>
						<span class="cbl-gp-note">
							<?php
							/* translators: %s: price per guest post, e.g. $42. */
							echo esc_html( sprintf( __( '%s / post', 'check-for-broken-links' ), '$' . number_format( $wpcbl_gp_per_post ) ) );
							?>
						</span>
					</span>
					<span class="cbl-gp-features">
						<span class="cbl-gp-feature">
							<span class="dashicons dashicons-yes" aria-hidden="true"></span>
							<?php
							/* translators: %s: number of guest posts. */
							echo esc_html( sprintf( __( '%s Premium Guest Posts', 'check-for-broken-links' ), number_format_i18n( $wpcbl_gp_package['posts'] ) ) );
							?>
						</span>
						<?php foreach ( $wpcbl_gp_catalog['features'] as $wpcbl_gp_feature ) : ?>
							<span class="cbl-gp-feature"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo esc_html( $wpcbl_gp_feature ); ?></span>
						<?php endforeach; ?>
						<span class="cbl-gp-feature">
							<span class="dashicons dashicons-yes" aria-hidden="true"></span>
							<?php
							/* translators: %s: delivery time in days. */
							echo esc_html( sprintf( __( '%s-day delivery', 'check-for-broken-links' ), number_format_i18n( $wpcbl_gp_package['delivery_days'] ) ) );
							?>
						</span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>

		<?php if ( $wpcbl_gp_catalog['guarantees'] ) : ?>
			<div class="cbl-gp-guarantees">
				<span class="cbl-gp-guarantee-lead"><?php esc_html_e( 'Every order is covered', 'check-for-broken-links' ); ?></span>
				<?php foreach ( $wpcbl_gp_catalog['guarantees'] as $wpcbl_gp_guarantee ) : ?>
					<span class="cbl-gp-feature"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo esc_html( $wpcbl_gp_guarantee ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="cbl-gp-cols">

			<div class="cbl-gp-panel">
				<div class="cbl-gp-panel-head">
					<span class="cbl-gp-panel-title"><?php esc_html_e( 'Where should the posts link?', 'check-for-broken-links' ); ?></span>
					<span class="cbl-gp-note" id="wpcbl-gp-picked"></span>
				</div>

				<div class="cbl-gp-targets" id="wpcbl-gp-targets"></div>

				<button type="button" class="cbl-gp-add" id="wpcbl-gp-add"><?php esc_html_e( '+ Add another target page', 'check-for-broken-links' ); ?></button>
				<p class="cbl-gp-note" id="wpcbl-gp-spread" hidden><?php esc_html_e( 'Posts are spread evenly across the target pages.', 'check-for-broken-links' ); ?></p>

				<label class="cbl-gp-field cbl-gp-notes">
					<span class="cbl-gp-label">
						<?php esc_html_e( 'Order notes', 'check-for-broken-links' ); ?>
						<span class="cbl-gp-note"><?php esc_html_e( '(optional)', 'check-for-broken-links' ); ?></span>
					</span>
					<textarea id="wpcbl-gp-notes" rows="3" maxlength="2000"
						placeholder="<?php esc_attr_e( 'Niche preferences, sites to avoid, tone of voice', 'check-for-broken-links' ); ?>"></textarea>
				</label>
			</div>

			<div class="cbl-gp-panel cbl-gp-summary">
				<span class="cbl-gp-panel-title"><?php esc_html_e( 'Order summary', 'check-for-broken-links' ); ?></span>
				<div class="cbl-gp-lines">
					<div class="cbl-gp-line"><span><?php esc_html_e( 'Package', 'check-for-broken-links' ); ?></span><b id="wpcbl-gp-sum-package"></b></div>
					<div class="cbl-gp-line"><span><?php esc_html_e( 'Posts', 'check-for-broken-links' ); ?></span><b id="wpcbl-gp-sum-posts"></b></div>
					<div class="cbl-gp-line"><span><?php esc_html_e( 'Target pages', 'check-for-broken-links' ); ?></span><b id="wpcbl-gp-sum-targets"></b></div>
					<div class="cbl-gp-line"><span><?php esc_html_e( 'Delivery', 'check-for-broken-links' ); ?></span><b id="wpcbl-gp-sum-delivery"></b></div>
				</div>
				<div class="cbl-gp-total">
					<span><?php esc_html_e( 'Total', 'check-for-broken-links' ); ?></span>
					<span class="cbl-gp-total-amount" id="wpcbl-gp-sum-total"></span>
				</div>
				<button type="submit" class="cbl-btn cbl-btn-primary cbl-gp-cta" id="wpcbl-gp-submit" disabled></button>
				<span class="cbl-gp-hint" id="wpcbl-gp-hint"></span>
				<span class="cbl-gp-secure">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<?php esc_html_e( 'Payment runs on Stripe. Your card never touches this site.', 'check-for-broken-links' ); ?>
				</span>
			</div>

		</div>

	</form>

	<?php if ( $wpcbl_gp_is_on ) : ?>
		<div class="cbl-gp cbl-gp-orders" id="wpcbl-gp-orders" hidden>
			<div class="cbl-gp-panel">
				<div class="cbl-gp-panel-head">
					<span class="cbl-gp-panel-title"><?php esc_html_e( 'Your orders', 'check-for-broken-links' ); ?></span>
					<span class="cbl-gp-note"><?php esc_html_e( 'Every paid order on your account, with its live URLs as they land.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-gp-order-list" id="wpcbl-gp-order-list"></div>
			</div>
		</div>
	<?php endif; ?>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
