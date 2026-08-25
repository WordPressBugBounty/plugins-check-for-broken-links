<?php
/**
 * Plans & upgrades page. Live catalog and current-subscription shape come
 * from brokenlinkchecker.io (single source of truth: the SaaS config); a
 * bundled fallback keeps the page rendering when the API is unreachable.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_hero_is_pro = function_exists( 'wpcbl_has_pro' ) && wpcbl_has_pro();
$wpcbl_page_title  = $wpcbl_hero_is_pro
	? __( 'Plans & upgrades', 'check-for-broken-links' )
	: __( 'Upgrade to Pro', 'check-for-broken-links' );

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';

$wpcbl_fallback_ai_tiers = array(
	'20'   => array(
		'monthly' => 5,
		'yearly'  => 50,
	),
	'50'   => array(
		'monthly' => 9.95,
		'yearly'  => 99,
	),
	'200'  => array(
		'monthly' => 45,
		'yearly'  => 450,
	),
	'500'  => array(
		'monthly' => 79,
		'yearly'  => 790,
	),
	'1000' => array(
		'monthly' => 99,
		'yearly'  => 990,
	),
);
$wpcbl_fallback_plans    = array(
	'personal' => array(
		'name'          => __( 'Pro Personal', 'check-for-broken-links' ),
		'tagline'       => __( 'For your website', 'check-for-broken-links' ),
		'yearly'        => 49,
		'monthly'       => 5.95,
		'sites'         => 1,
		'scans'         => 200,
		'rank_keywords' => 50,
		'ai_fixes'      => 200,
		'seo_audits'    => 5,
		'ai_credits'    => 0,
		'keyword_tiers' => array(
			'100' => array(
				'monthly' => 3,
				'yearly'  => 30,
			),
			'200' => array(
				'monthly' => 5,
				'yearly'  => 50,
			),
			'500' => array(
				'monthly' => 10,
				'yearly'  => 100,
			),
		),
		'daily'         => array(
			'monthly' => 9.95,
			'yearly'  => 99,
		),
	),
	'business' => array(
		'name'          => __( 'Pro Business', 'check-for-broken-links' ),
		'tagline'       => __( 'For growing businesses', 'check-for-broken-links' ),
		'yearly'        => 99,
		'monthly'       => 11.95,
		'sites'         => 5,
		'scans'         => null,
		'rank_keywords' => 250,
		'ai_fixes'      => 1000,
		'seo_audits'    => 10,
		'ai_credits'    => 10,
		'keyword_tiers' => array(
			'500'  => array(
				'monthly' => 10,
				'yearly'  => 100,
			),
			'1000' => array(
				'monthly' => 18,
				'yearly'  => 180,
			),
		),
		'daily'         => array(
			'monthly' => 19.95,
			'yearly'  => 199,
		),
	),
	'agency'   => array(
		'name'          => __( 'Pro Agency', 'check-for-broken-links' ),
		'tagline'       => __( 'For agencies and freelancers', 'check-for-broken-links' ),
		'yearly'        => 299,
		'monthly'       => 34.95,
		'sites'         => 250,
		'scans'         => null,
		'rank_keywords' => 1000,
		'ai_fixes'      => 5000,
		'seo_audits'    => 25,
		'ai_credits'    => 10,
		'keyword_tiers' => array(
			'2000' => array(
				'monthly' => 25,
				'yearly'  => 250,
			),
			'5000' => array(
				'monthly' => 69,
				'yearly'  => 690,
			),
		),
		'daily'         => array(
			'monthly' => 49.95,
			'yearly'  => 499,
		),
	),
);

$wpcbl_upgrade_connect = function_exists( 'wpcbl_connect' ) ? wpcbl_connect() : null;
$wpcbl_catalog         = $wpcbl_upgrade_connect ? $wpcbl_upgrade_connect->fetch_plans() : null;
$wpcbl_api_plans       = isset( $wpcbl_catalog['plans'] ) && is_array( $wpcbl_catalog['plans'] ) ? $wpcbl_catalog['plans'] : array();
$wpcbl_ai_tiers        = ! empty( $wpcbl_catalog['ai_tiers'] ) && is_array( $wpcbl_catalog['ai_tiers'] ) ? $wpcbl_catalog['ai_tiers'] : $wpcbl_fallback_ai_tiers;
$wpcbl_plans           = array();
foreach ( $wpcbl_fallback_plans as $wpcbl_slug => $wpcbl_fallback ) {
	$wpcbl_remote            = isset( $wpcbl_api_plans[ $wpcbl_slug ] ) && is_array( $wpcbl_api_plans[ $wpcbl_slug ] ) ? $wpcbl_api_plans[ $wpcbl_slug ] : array();
	$wpcbl_plan_data         = array_merge( $wpcbl_fallback, array_intersect_key( $wpcbl_remote, $wpcbl_fallback ) );
	$wpcbl_plan_data['slug'] = $wpcbl_slug;
	$wpcbl_plans[]           = $wpcbl_plan_data;
}

$wpcbl_is_connected_pro = $wpcbl_hero_is_pro && $wpcbl_upgrade_connect && $wpcbl_upgrade_connect->is_connected();
$wpcbl_current_plan     = function_exists( 'wpcbl_connect_plan' ) ? (string) wpcbl_connect_plan() : '';
$wpcbl_shape            = $wpcbl_is_connected_pro ? ( $wpcbl_upgrade_connect->fetch_billing_shape() ?: array() ) : array();
$wpcbl_shape_interval   = isset( $wpcbl_shape['interval'] ) && 'monthly' === $wpcbl_shape['interval'] ? 'monthly' : 'yearly';
$wpcbl_shape_keywords   = isset( $wpcbl_shape['keywords'] ) ? (string) absint( $wpcbl_shape['keywords'] ) : '0';
$wpcbl_shape_daily      = ! empty( $wpcbl_shape['daily'] ) ? '1' : '0';
$wpcbl_shape_ai         = isset( $wpcbl_shape['ai_credits'] ) ? (string) absint( $wpcbl_shape['ai_credits'] ) : '0';
?>

<div class="cbl-upgrade-page">

<div class="cbl-plansx-hero">
	<div>
		<?php if ( $wpcbl_is_connected_pro ) : ?>
			<h2><?php esc_html_e( 'Your plan, upgrades and add-ons', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Change your plan or billing period, or add daily ranking updates. Changes are prorated, no need to visit the dashboard.', 'check-for-broken-links' ); ?></p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Set it once, forget it, we alert you when something breaks', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Pro runs automatic scheduled scans and emails you the moment broken links are found. A silent background monitor for your site.', 'check-for-broken-links' ); ?></p>
		<?php endif; ?>
	</div>
	<div class="cbl-plan-toggle" id="wpcbl-plan-toggle" data-wpcbl-initial-interval="<?php echo esc_attr( $wpcbl_is_connected_pro ? $wpcbl_shape_interval : 'yearly' ); ?>">
		<button type="button" class="cbl-plan-toggle-btn is-active" data-wpcbl-interval="yearly">
			<?php esc_html_e( 'Billed yearly', 'check-for-broken-links' ); ?>
			<span class="cbl-plan-toggle-save"><?php esc_html_e( 'Save 30%', 'check-for-broken-links' ); ?></span>
		</button>
		<button type="button" class="cbl-plan-toggle-btn" data-wpcbl-interval="monthly"><?php esc_html_e( 'Billed monthly', 'check-for-broken-links' ); ?></button>
	</div>
</div>

<p class="cbl-plan-status" id="wpcbl-plan-status" role="status"></p>

<div class="cbl-plan-grid">
	<?php foreach ( $wpcbl_plans as $wpcbl_plan ) : ?>
		<?php
		$wpcbl_slug       = $wpcbl_plan['slug'];
		$wpcbl_is_current = $wpcbl_is_connected_pro && $wpcbl_slug === $wpcbl_current_plan;
		$wpcbl_featured   = ! $wpcbl_is_current && 'business' === $wpcbl_slug;
		$wpcbl_pre_kw     = $wpcbl_is_current && isset( $wpcbl_plan['keyword_tiers'][ $wpcbl_shape_keywords ] ) ? $wpcbl_shape_keywords : '0';
		$wpcbl_pre_daily  = $wpcbl_is_current ? $wpcbl_shape_daily : '0';
		$wpcbl_pre_ai     = $wpcbl_is_current && isset( $wpcbl_ai_tiers[ $wpcbl_shape_ai ] ) && (int) $wpcbl_shape_ai > (int) $wpcbl_plan['ai_credits'] ? $wpcbl_shape_ai : '0';
		$wpcbl_features   = array(
			sprintf( /* translators: %s: number of sites. */ _n( '%s monitored website', '%s monitored websites', (int) $wpcbl_plan['sites'], 'check-for-broken-links' ), number_format_i18n( (int) $wpcbl_plan['sites'] ) ),
			null !== $wpcbl_plan['scans'] ? sprintf( /* translators: %s: number of scans. */ esc_html__( '%s on-demand scans / month', 'check-for-broken-links' ), number_format_i18n( (int) $wpcbl_plan['scans'] ) ) : __( 'Unlimited on-demand scans', 'check-for-broken-links' ),
			sprintf( /* translators: %s: number of AI fixes. */ esc_html__( '%s AI link fixes / month', 'check-for-broken-links' ), number_format_i18n( (int) $wpcbl_plan['ai_fixes'] ) ),
			sprintf( /* translators: %s: number of audits. */ esc_html__( '%s SEO / AEO audits / month', 'check-for-broken-links' ), number_format_i18n( (int) $wpcbl_plan['seo_audits'] ) ),
			__( 'Email alerts + SEO report page', 'check-for-broken-links' ),
			__( 'Pro WordPress plugin', 'check-for-broken-links' ),
		);
		$wpcbl_current_label = __( 'current plan', 'check-for-broken-links' );
		?>
		<div class="cbl-card cbl-plan-card<?php echo $wpcbl_featured ? ' cbl-plan-featured' : ''; ?><?php echo $wpcbl_is_current ? ' cbl-plan-current' : ''; ?>"
			data-wpcbl-plan-card="<?php echo esc_attr( $wpcbl_slug ); ?>"
			<?php if ( $wpcbl_is_current ) : ?>
				data-wpcbl-current="1"
				data-wpcbl-initial-kw="<?php echo esc_attr( $wpcbl_pre_kw ); ?>"
				data-wpcbl-initial-daily="<?php echo esc_attr( $wpcbl_pre_daily ); ?>"
				data-wpcbl-initial-ai="<?php echo esc_attr( $wpcbl_pre_ai ); ?>"
			<?php endif; ?>>
			<?php if ( $wpcbl_is_current ) : ?>
				<span class="cbl-plan-flag cbl-plan-flag-current"><?php esc_html_e( 'Current plan', 'check-for-broken-links' ); ?></span>
			<?php elseif ( $wpcbl_featured ) : ?>
				<span class="cbl-plan-flag"><?php esc_html_e( 'Most popular', 'check-for-broken-links' ); ?></span>
			<?php endif; ?>
			<p class="cbl-plan-name"><?php echo esc_html( $wpcbl_plan['name'] ); ?></p>
			<p class="cbl-plan-tagline"><?php echo esc_html( $wpcbl_plan['tagline'] ); ?></p>
			<p class="cbl-plan-price">
				<span class="wpcbl-plan-amount" data-yearly="<?php echo esc_attr( '$' . $wpcbl_plan['yearly'] ); ?>" data-monthly="<?php echo esc_attr( '$' . $wpcbl_plan['monthly'] ); ?>"><?php echo esc_html( '$' . $wpcbl_plan['yearly'] ); ?></span><span class="cbl-plan-period wpcbl-plan-period" data-yearly="<?php echo esc_attr( ' ' . __( '/year', 'check-for-broken-links' ) ); ?>" data-monthly="<?php echo esc_attr( ' ' . __( '/month', 'check-for-broken-links' ) ); ?>"><?php echo esc_html( ' ' . __( '/year', 'check-for-broken-links' ) ); ?></span>
			</p>
			<p class="cbl-plan-permonth"
				data-yearly="<?php echo esc_attr( sprintf( /* translators: %s: price. */ __( 'about $%s a month', 'check-for-broken-links' ), number_format_i18n( $wpcbl_plan['yearly'] / 12, 2 ) ) ); ?>"
				data-monthly="<?php echo esc_attr( __( 'billed month to month', 'check-for-broken-links' ) ); ?>"><?php printf( /* translators: %s: price. */ esc_html__( 'about $%s a month', 'check-for-broken-links' ), esc_html( number_format_i18n( $wpcbl_plan['yearly'] / 12, 2 ) ) ); ?></p>

			<?php
			// Selects are real form fields in checkout mode and read by JS in
			// change mode; either way the markup is identical.
			$wpcbl_form_open  = ! $wpcbl_is_connected_pro;
			?>
			<?php if ( $wpcbl_form_open ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbl-plan-cta-form">
					<input type="hidden" name="action" value="wpcbl_checkout_start" />
					<input type="hidden" name="plan" value="<?php echo esc_attr( $wpcbl_slug ); ?>" />
					<input type="hidden" name="interval" value="yearly" class="wpcbl-interval-input" />
					<?php wp_nonce_field( 'wpcbl_checkout_start' ); ?>
			<?php endif; ?>

			<div class="cbl-plan-selects">
				<div>
					<label class="cbl-plan-select-label" for="wpcbl-kw-<?php echo esc_attr( $wpcbl_slug ); ?>"><?php esc_html_e( 'Rank-tracked keywords', 'check-for-broken-links' ); ?></label>
					<select id="wpcbl-kw-<?php echo esc_attr( $wpcbl_slug ); ?>" name="keywords" class="cbl-plan-select wpcbl-sel-kw">
						<option value="0" <?php selected( '0', $wpcbl_pre_kw ); ?>><?php echo esc_html( number_format_i18n( (int) $wpcbl_plan['rank_keywords'] ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_kw ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
						<?php foreach ( $wpcbl_plan['keyword_tiers'] as $wpcbl_tier_key => $wpcbl_tier ) : ?>
							<?php $wpcbl_tier_is_current = $wpcbl_is_current && (string) $wpcbl_tier_key === $wpcbl_pre_kw; ?>
							<option value="<?php echo esc_attr( (string) $wpcbl_tier_key ); ?>" <?php selected( (string) $wpcbl_tier_key, $wpcbl_pre_kw ); ?>
								data-label-yearly="<?php echo esc_attr( number_format_i18n( (int) $wpcbl_tier_key ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_tier_is_current ? $wpcbl_current_label : '+$' . $wpcbl_tier['yearly'] . '/yr' ) ); ?>"
								data-label-monthly="<?php echo esc_attr( number_format_i18n( (int) $wpcbl_tier_key ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_tier_is_current ? $wpcbl_current_label : '+$' . $wpcbl_tier['monthly'] . '/mo' ) ); ?>"><?php echo esc_html( number_format_i18n( (int) $wpcbl_tier_key ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_tier_is_current ? $wpcbl_current_label : '+$' . $wpcbl_tier['yearly'] . '/yr' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="cbl-plan-select-label" for="wpcbl-daily-<?php echo esc_attr( $wpcbl_slug ); ?>"><?php esc_html_e( 'Ranking updates', 'check-for-broken-links' ); ?></label>
					<select id="wpcbl-daily-<?php echo esc_attr( $wpcbl_slug ); ?>" name="daily" class="cbl-plan-select wpcbl-sel-daily">
						<option value="0" <?php selected( '0', $wpcbl_pre_daily ); ?>><?php echo esc_html( __( 'Weekly updates', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_daily ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
						<option value="1" <?php selected( '1', $wpcbl_pre_daily ); ?>
							data-label-yearly="<?php echo esc_attr( __( 'Daily updates', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '1' === $wpcbl_pre_daily ? $wpcbl_current_label : '+$' . $wpcbl_plan['daily']['yearly'] . '/yr' ) ); ?>"
							data-label-monthly="<?php echo esc_attr( __( 'Daily updates', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '1' === $wpcbl_pre_daily ? $wpcbl_current_label : '+$' . $wpcbl_plan['daily']['monthly'] . '/mo' ) ); ?>"><?php echo esc_html( __( 'Daily updates', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '1' === $wpcbl_pre_daily ? $wpcbl_current_label : '+$' . $wpcbl_plan['daily']['yearly'] . '/yr' ) ); ?></option>
					</select>
				</div>
				<div>
					<label class="cbl-plan-select-label" for="wpcbl-ai-<?php echo esc_attr( $wpcbl_slug ); ?>"><?php esc_html_e( 'AI Visibility credits', 'check-for-broken-links' ); ?></label>
					<select id="wpcbl-ai-<?php echo esc_attr( $wpcbl_slug ); ?>" name="ai" class="cbl-plan-select wpcbl-sel-ai">
						<option value="0" <?php selected( '0', $wpcbl_pre_ai ); ?>><?php echo esc_html( ( (int) $wpcbl_plan['ai_credits'] > 0 ? number_format_i18n( (int) $wpcbl_plan['ai_credits'] ) . ' ' . __( 'credits', 'check-for-broken-links' ) : __( 'No credits', 'check-for-broken-links' ) ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_ai ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
						<?php foreach ( $wpcbl_ai_tiers as $wpcbl_ai_key => $wpcbl_ai_tier ) : ?>
							<?php
							if ( (int) $wpcbl_ai_key <= (int) $wpcbl_plan['ai_credits'] ) {
								continue;
							}
							$wpcbl_ai_is_current = $wpcbl_is_current && (string) $wpcbl_ai_key === $wpcbl_pre_ai;
							?>
							<option value="<?php echo esc_attr( (string) $wpcbl_ai_key ); ?>" <?php selected( (string) $wpcbl_ai_key, $wpcbl_pre_ai ); ?>
								data-label-yearly="<?php echo esc_attr( $wpcbl_ai_key . ' ' . __( 'credits', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_ai_is_current ? $wpcbl_current_label : '+$' . $wpcbl_ai_tier['yearly'] . '/yr' ) ); ?>"
								data-label-monthly="<?php echo esc_attr( $wpcbl_ai_key . ' ' . __( 'credits', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_ai_is_current ? $wpcbl_current_label : '+$' . $wpcbl_ai_tier['monthly'] . '/mo' ) ); ?>"><?php echo esc_html( $wpcbl_ai_key . ' ' . __( 'credits', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_ai_is_current ? $wpcbl_current_label : '+$' . $wpcbl_ai_tier['yearly'] . '/yr' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="cbl-plan-feats">
				<?php foreach ( $wpcbl_features as $wpcbl_feature ) : ?>
					<div class="cbl-plan-feat">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>
						<span><?php echo esc_html( $wpcbl_feature ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $wpcbl_form_open ) : ?>
					<button type="submit" class="cbl-btn cbl-plan-cta <?php echo $wpcbl_featured ? 'cbl-plan-cta-featured' : 'cbl-plan-cta-outline'; ?>">
						<?php
						/* translators: %s: plan name. */
						printf( esc_html__( 'Choose %s', 'check-for-broken-links' ), esc_html( $wpcbl_plan['name'] ) );
						?>
					</button>
				</form>
			<?php elseif ( $wpcbl_is_current ) : ?>
				<button type="button"
						class="cbl-btn cbl-plan-cta cbl-plan-cta-outline wpcbl-change-plan"
						id="wpcbl-update-current"
						disabled
						data-wpcbl-plan="<?php echo esc_attr( $wpcbl_slug ); ?>"
						data-wpcbl-plan-name="<?php echo esc_attr( $wpcbl_plan['name'] ); ?>">
					<?php esc_html_e( 'Update subscription', 'check-for-broken-links' ); ?>
				</button>
			<?php else : ?>
				<button type="button"
						class="cbl-btn cbl-plan-cta <?php echo $wpcbl_featured ? 'cbl-plan-cta-featured' : 'cbl-plan-cta-outline'; ?> wpcbl-change-plan"
						data-wpcbl-plan="<?php echo esc_attr( $wpcbl_slug ); ?>"
						data-wpcbl-plan-name="<?php echo esc_attr( $wpcbl_plan['name'] ); ?>">
					<?php
					/* translators: %s: plan name. */
					printf( esc_html__( 'Switch to %s', 'check-for-broken-links' ), esc_html( $wpcbl_plan['name'] ) );
					?>
				</button>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<p class="cbl-plan-smallprint">
	<?php esc_html_e( 'Prices in USD. Billed yearly or monthly, cancel anytime. All Pro features and priority support in every plan. Keyword add-on packs and AI Visibility credits are available on the pricing page.', 'check-for-broken-links' ); ?>
	<a href="https://brokenlinkchecker.io/pricing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See full pricing details', 'check-for-broken-links' ); ?></a>
</p>

<?php if ( ! $wpcbl_is_connected_pro ) : ?>
	<?php // Subscribers who land here (plan bought on another site or on the web) must not re-enter checkout — connecting activates the plan they already own. ?>
	<div class="cbl-plan-already">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<?php esc_html_e( 'Already purchased a plan?', 'check-for-broken-links' ); ?>
			<button type="submit" class="cbl-link-button"><?php esc_html_e( 'Connect this site to your account', 'check-for-broken-links' ); ?></button>
			<?php esc_html_e( '— Pro activates instantly, no license key needed.', 'check-for-broken-links' ); ?>
		</form>
	</div>
<?php endif; ?>

<div class="cbl-card cbl-support-strip">
	<span class="cbl-support-item">
		<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
		<?php esc_html_e( 'Priority support', 'check-for-broken-links' ); ?>
	</span>
	<span class="cbl-support-item">
		<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>
		<?php esc_html_e( 'All future Pro features', 'check-for-broken-links' ); ?>
	</span>
	<span class="cbl-support-item">
		<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
		<?php esc_html_e( 'Everything in Free, always', 'check-for-broken-links' ); ?>
	</span>
</div>

<?php
$wpcbl_upgrade_faq = array(
	array(
		'question' => __( 'What happens if my license expires?', 'check-for-broken-links' ),
		'answer'   => __( 'The plugin keeps working with the free features. Scans, reports and history stay on your account, and Pro features unlock again the moment you renew.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Can I upgrade my plan later?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes, any time from this page or the dashboard. Upgrades are prorated to your card right away; downgrades are credited to your next invoice.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Do you offer refunds?', 'check-for-broken-links' ),
		'answer'   => __( 'Refunds apply when the plugin has a defect. Report the issue via our support with enough detail for us to reproduce it, and give us a fair chance to fix it. If we can\'t resolve a confirmed defect within 14 days of a complete report, you get a full refund. Renewals can be cancelled anytime before the renewal date.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Does Pro work with my theme and page builder?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes. Scanning works on the rendered pages of your site, so it is independent of your theme or builder — including Elementor, Gutenberg and WooCommerce.', 'check-for-broken-links' ),
	),
);
?>

<div class="cbl-card">
	<h2 class="cbl-upgrade-faq-heading"><?php esc_html_e( 'Frequently asked questions', 'check-for-broken-links' ); ?></h2>
	<div class="cbl-faq-list">
		<?php foreach ( $wpcbl_upgrade_faq as $wpcbl_faq_item ) : ?>
			<details class="cbl-faq-item">
				<summary><?php echo esc_html( $wpcbl_faq_item['question'] ); ?></summary>
				<div class="cbl-faq-answer">
					<p><?php echo esc_html( $wpcbl_faq_item['answer'] ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</div>

</div><!-- .cbl-upgrade-page -->

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
