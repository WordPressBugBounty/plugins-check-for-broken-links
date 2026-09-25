<?php
/**
 * Plans & upgrades page. Live catalog and current-subscription shape come
 * from brokenlinkchecker.io (single source of truth: the SaaS config); a
 * bundled fallback keeps the page rendering when the API is unreachable.
 *
 * Three states: not connected, connected on the Free plan, and connected
 * on a paid plan. A paying site never sees an upgrade prompt for what its
 * plan already covers: its card is marked current and only the add-ons and
 * the tiers above it are offered as upgrades.
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

// Fallback prices mirror brokenlinkchecker.io config/plans.php, config/rank.php
// and config/ai_visibility.php. The live /api/v1/plans catalog overrides them.
$wpcbl_fallback_ai_tiers = array(
	'20'   => array(
		'monthly' => 5,
		'yearly'  => 50,
	),
	'50'   => array(
		'monthly' => 11,
		'yearly'  => 110,
	),
	'200'  => array(
		'monthly' => 39,
		'yearly'  => 390,
	),
	'500'  => array(
		'monthly' => 89,
		'yearly'  => 890,
	),
	'1000' => array(
		'monthly' => 159,
		'yearly'  => 1590,
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
		// Daily updates are priced by keyword count: '0' is the plan's
		// included count, the other keys are the keyword tiers.
		'daily_by_tier' => array(
			'0'   => array(
				'monthly' => 9.95,
				'yearly'  => 99,
			),
			'100' => array(
				'monthly' => 12.95,
				'yearly'  => 129,
			),
			'200' => array(
				'monthly' => 17.95,
				'yearly'  => 179,
			),
			'500' => array(
				'monthly' => 29.95,
				'yearly'  => 299,
			),
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
		'daily_by_tier' => array(
			'0'    => array(
				'monthly' => 19.95,
				'yearly'  => 199,
			),
			'500'  => array(
				'monthly' => 29.95,
				'yearly'  => 299,
			),
			'1000' => array(
				'monthly' => 49.95,
				'yearly'  => 499,
			),
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
		'daily_by_tier' => array(
			'0'    => array(
				'monthly' => 49.95,
				'yearly'  => 499,
			),
			'2000' => array(
				'monthly' => 89.95,
				'yearly'  => 899,
			),
			'5000' => array(
				'monthly' => 199.95,
				'yearly'  => 1999,
			),
		),
	),
);

// Comparison rows the catalog API does not carry. Sources on
// brokenlinkchecker.io: crawler.limits (Free pages and scans), plans.*.scan_pages,
// rank.manual_refreshes, uptime.limits.*.interval and retention.plans.
$wpcbl_compare_static = array(
	'free'     => array(
		'sites'      => 1,
		'scan_pages' => 500,
		'scans'      => 50,
		'frequency'  => __( 'Monthly', 'check-for-broken-links' ),
		'keywords'   => 10,
		'refreshes'  => 1,
		'audits'     => 1,
		'uptime'     => __( '5 min', 'check-for-broken-links' ),
		'retention'  => __( '1 mo', 'check-for-broken-links' ),
	),
	'personal' => array(
		'scan_pages' => 1000,
		'frequency'  => __( 'Daily+', 'check-for-broken-links' ),
		'refreshes'  => 3,
		'uptime'     => __( '60 sec', 'check-for-broken-links' ),
		'retention'  => __( '6 mo', 'check-for-broken-links' ),
	),
	'business' => array(
		'scan_pages' => 2500,
		'frequency'  => __( 'Hourly+', 'check-for-broken-links' ),
		'refreshes'  => 10,
		'uptime'     => __( '30 sec', 'check-for-broken-links' ),
		'retention'  => __( '2 yr', 'check-for-broken-links' ),
	),
	'agency'   => array(
		'scan_pages' => 5000,
		'frequency'  => __( 'Hourly+', 'check-for-broken-links' ),
		'refreshes'  => 20,
		'uptime'     => __( '30 sec', 'check-for-broken-links' ),
		'retention'  => __( '3 yr', 'check-for-broken-links' ),
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
	if ( ! is_array( $wpcbl_plan_data['keyword_tiers'] ) ) {
		$wpcbl_plan_data['keyword_tiers'] = array();
	}
	if ( ! is_array( $wpcbl_plan_data['daily_by_tier'] ) || ! isset( $wpcbl_plan_data['daily_by_tier']['0'] ) ) {
		$wpcbl_plan_data['daily_by_tier'] = array( '0' => $wpcbl_plan_data['daily'] );
	}
	$wpcbl_plans[ $wpcbl_slug ] = $wpcbl_plan_data;
}

$wpcbl_is_connected     = $wpcbl_upgrade_connect && $wpcbl_upgrade_connect->is_connected();
$wpcbl_is_connected_pro = $wpcbl_hero_is_pro && $wpcbl_is_connected;
$wpcbl_current_plan     = function_exists( 'wpcbl_connect_plan' ) ? (string) wpcbl_connect_plan() : '';
$wpcbl_plan_order       = array_keys( $wpcbl_plans );
$wpcbl_current_rank     = $wpcbl_is_connected_pro ? array_search( $wpcbl_current_plan, $wpcbl_plan_order, true ) : false;
$wpcbl_shape            = $wpcbl_is_connected_pro ? ( $wpcbl_upgrade_connect->fetch_billing_shape() ?: array() ) : array();
$wpcbl_shape_interval   = isset( $wpcbl_shape['interval'] ) && 'monthly' === $wpcbl_shape['interval'] ? 'monthly' : 'yearly';
$wpcbl_shape_keywords   = isset( $wpcbl_shape['keywords'] ) ? (string) absint( $wpcbl_shape['keywords'] ) : '0';
$wpcbl_shape_daily      = ! empty( $wpcbl_shape['daily'] ) ? '1' : '0';
$wpcbl_shape_ai         = isset( $wpcbl_shape['ai_credits'] ) ? (string) absint( $wpcbl_shape['ai_credits'] ) : '0';
$wpcbl_initial_interval = $wpcbl_is_connected_pro ? $wpcbl_shape_interval : 'yearly';

// Header chip: what this site is on right now.
if ( $wpcbl_is_connected_pro ) {
	$wpcbl_chip = sprintf(
		/* translators: %s: plan name, for example Pro Business. */
		__( 'You\'re on %s · site connected', 'check-for-broken-links' ),
		isset( $wpcbl_plans[ $wpcbl_current_plan ] ) ? $wpcbl_plans[ $wpcbl_current_plan ]['name'] : __( 'Pro', 'check-for-broken-links' )
	);
} elseif ( $wpcbl_is_connected ) {
	$wpcbl_chip = __( 'You\'re on Free · site connected · manual scans', 'check-for-broken-links' );
} else {
	$wpcbl_chip = __( 'Free plugin · site not connected · manual scans', 'check-for-broken-links' );
}

/**
 * A USD amount the way the page prints it: whole dollars stay whole,
 * cents always show two digits. Mirrors the JS formatter.
 *
 * @param float $amount Amount in dollars.
 *
 * @return string
 */
$wpcbl_money = static function ( $amount ) {
	$amount = round( (float) $amount, 2 );

	return floor( $amount ) === $amount ? number_format( $amount ) : number_format( $amount, 2 );
};

$wpcbl_current_label = __( 'current plan', 'check-for-broken-links' );
$wpcbl_billed_tpl    = /* translators: %s: the full yearly price. */ __( 'billed yearly at $%s', 'check-for-broken-links' );
?>

<div class="cbl-up">

<header class="cbl-up-head">
	<div class="cbl-up-intro">
		<span class="cbl-up-chip"><span class="cbl-up-dot<?php echo $wpcbl_is_connected ? ' is-on' : ''; ?>" aria-hidden="true"></span><?php echo esc_html( $wpcbl_chip ); ?></span>
		<?php if ( $wpcbl_is_connected_pro ) : ?>
			<h2 class="cbl-up-title"><?php esc_html_e( 'Your plan, upgrades and add-ons', 'check-for-broken-links' ); ?></h2>
			<p class="cbl-up-lead"><?php esc_html_e( 'Change your plan, billing period or add-ons right here. Upgrades charge only the prorated difference.', 'check-for-broken-links' ); ?></p>
		<?php else : ?>
			<h2 class="cbl-up-title"><?php esc_html_e( 'Track rankings, audit SEO and catch broken links automatically.', 'check-for-broken-links' ); ?></h2>
			<p class="cbl-up-lead"><?php esc_html_e( 'A Pro plan tracks your keyword positions, runs SEO and AEO audits and scans your site on a schedule. You get an email and a report when something needs attention.', 'check-for-broken-links' ); ?></p>
		<?php endif; ?>
	</div>
	<div class="cbl-up-toggle" id="wpcbl-plan-toggle" role="group" aria-label="<?php esc_attr_e( 'Billing period', 'check-for-broken-links' ); ?>" data-wpcbl-initial-interval="<?php echo esc_attr( $wpcbl_initial_interval ); ?>">
		<button type="button" class="cbl-up-toggle-btn is-active" data-wpcbl-interval="yearly" aria-pressed="true">
			<?php esc_html_e( 'Billed yearly', 'check-for-broken-links' ); ?>
			<span class="cbl-up-save"><?php esc_html_e( 'Save 30%', 'check-for-broken-links' ); ?></span>
		</button>
		<button type="button" class="cbl-up-toggle-btn" data-wpcbl-interval="monthly" aria-pressed="false"><?php esc_html_e( 'Billed monthly', 'check-for-broken-links' ); ?></button>
	</div>
</header>

<p class="cbl-up-status" id="wpcbl-plan-status" role="status"></p>

<div class="cbl-up-grid">
	<?php foreach ( $wpcbl_plans as $wpcbl_slug => $wpcbl_plan ) : ?>
		<?php
		$wpcbl_rank       = array_search( $wpcbl_slug, $wpcbl_plan_order, true );
		$wpcbl_is_current = $wpcbl_is_connected_pro && $wpcbl_slug === $wpcbl_current_plan;
		$wpcbl_is_lower   = false !== $wpcbl_current_rank && $wpcbl_rank < $wpcbl_current_rank;
		$wpcbl_featured   = ! $wpcbl_is_current && ! $wpcbl_is_lower && 'business' === $wpcbl_slug;
		$wpcbl_pre_kw     = $wpcbl_is_current && isset( $wpcbl_plan['keyword_tiers'][ $wpcbl_shape_keywords ] ) ? $wpcbl_shape_keywords : '0';
		$wpcbl_pre_daily  = $wpcbl_is_current ? $wpcbl_shape_daily : '0';
		$wpcbl_pre_ai     = $wpcbl_is_current && isset( $wpcbl_ai_tiers[ $wpcbl_shape_ai ] ) && (int) $wpcbl_shape_ai > (int) $wpcbl_plan['ai_credits'] ? $wpcbl_shape_ai : '0';

		// Opening totals for the preselected add-ons (only the current card
		// has any), so the headline matches what the subscription costs.
		$wpcbl_total_yearly  = (float) $wpcbl_plan['yearly'];
		$wpcbl_total_monthly = (float) $wpcbl_plan['monthly'];
		if ( '0' !== $wpcbl_pre_kw ) {
			$wpcbl_total_yearly  += (float) $wpcbl_plan['keyword_tiers'][ $wpcbl_pre_kw ]['yearly'];
			$wpcbl_total_monthly += (float) $wpcbl_plan['keyword_tiers'][ $wpcbl_pre_kw ]['monthly'];
		}
		$wpcbl_daily_price = isset( $wpcbl_plan['daily_by_tier'][ $wpcbl_pre_kw ] ) ? $wpcbl_plan['daily_by_tier'][ $wpcbl_pre_kw ] : $wpcbl_plan['daily_by_tier']['0'];
		if ( '1' === $wpcbl_pre_daily ) {
			$wpcbl_total_yearly  += (float) $wpcbl_daily_price['yearly'];
			$wpcbl_total_monthly += (float) $wpcbl_daily_price['monthly'];
		}
		if ( '0' !== $wpcbl_pre_ai ) {
			$wpcbl_total_yearly  += (float) $wpcbl_ai_tiers[ $wpcbl_pre_ai ]['yearly'];
			$wpcbl_total_monthly += (float) $wpcbl_ai_tiers[ $wpcbl_pre_ai ]['monthly'];
		}
		$wpcbl_is_monthly = 'monthly' === $wpcbl_initial_interval;
		$wpcbl_headline   = '$' . number_format( $wpcbl_is_monthly ? $wpcbl_total_monthly : $wpcbl_total_yearly / 12, 2 );
		$wpcbl_billed     = $wpcbl_is_monthly ? __( 'billed month to month', 'check-for-broken-links' ) : sprintf( $wpcbl_billed_tpl, $wpcbl_money( $wpcbl_total_yearly ) );
		$wpcbl_addons_on  = ( '0' !== $wpcbl_pre_kw ? 1 : 0 ) + ( '1' === $wpcbl_pre_daily ? 1 : 0 ) + ( '0' !== $wpcbl_pre_ai ? 1 : 0 );

		$wpcbl_kw_count = number_format_i18n( (int) ( '0' !== $wpcbl_pre_kw ? $wpcbl_pre_kw : $wpcbl_plan['rank_keywords'] ) );
		$wpcbl_strong   = static function ( $value, $class = '' ) {
			return '<strong' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . esc_html( $value ) . '</strong>';
		};
		$wpcbl_features = array(
			sprintf( /* translators: %s: number of keywords. */ esc_html__( '%s rank-tracked keywords', 'check-for-broken-links' ), $wpcbl_strong( $wpcbl_kw_count, 'wpcbl-kw-count' ) ),
			sprintf( /* translators: %s: number of sites. */ esc_html( _n( '%s monitored website', '%s monitored websites', (int) $wpcbl_plan['sites'], 'check-for-broken-links' ) ), $wpcbl_strong( number_format_i18n( (int) $wpcbl_plan['sites'] ) ) ),
			null !== $wpcbl_plan['scans']
				? sprintf( /* translators: %s: number of scans. */ esc_html__( '%s on-demand scans / month', 'check-for-broken-links' ), $wpcbl_strong( number_format_i18n( (int) $wpcbl_plan['scans'] ) ) )
				: sprintf( /* translators: %s: the word Unlimited. */ esc_html__( '%s on-demand scans', 'check-for-broken-links' ), $wpcbl_strong( __( 'Unlimited', 'check-for-broken-links' ) ) ),
			sprintf( /* translators: %s: number of AI fixes. */ esc_html__( '%s AI link fixes / month', 'check-for-broken-links' ), $wpcbl_strong( number_format_i18n( (int) $wpcbl_plan['ai_fixes'] ) ) ),
			sprintf( /* translators: %s: number of audits. */ esc_html__( '%s SEO / AEO audits / month', 'check-for-broken-links' ), $wpcbl_strong( number_format_i18n( (int) $wpcbl_plan['seo_audits'] ) ) ),
			esc_html__( 'Email alerts + SEO report page', 'check-for-broken-links' ),
			esc_html__( 'Pro WordPress plugin included', 'check-for-broken-links' ),
		);
		$wpcbl_card_class = 'cbl-up-card';
		$wpcbl_card_class .= $wpcbl_featured ? ' is-featured' : '';
		$wpcbl_card_class .= $wpcbl_is_current ? ' is-current' : '';
		$wpcbl_card_class .= $wpcbl_is_lower ? ' is-lower' : '';
		?>
		<article class="<?php echo esc_attr( $wpcbl_card_class ); ?>"
			data-wpcbl-plan-card="<?php echo esc_attr( $wpcbl_slug ); ?>"
			data-wpcbl-base-yearly="<?php echo esc_attr( (string) $wpcbl_plan['yearly'] ); ?>"
			data-wpcbl-base-monthly="<?php echo esc_attr( (string) $wpcbl_plan['monthly'] ); ?>"
			<?php if ( $wpcbl_is_current ) : ?>
				data-wpcbl-current="1"
				data-wpcbl-initial-kw="<?php echo esc_attr( $wpcbl_pre_kw ); ?>"
				data-wpcbl-initial-daily="<?php echo esc_attr( $wpcbl_pre_daily ); ?>"
				data-wpcbl-initial-ai="<?php echo esc_attr( $wpcbl_pre_ai ); ?>"
			<?php endif; ?>>
			<?php if ( $wpcbl_is_current ) : ?>
				<span class="cbl-up-flag is-current"><?php esc_html_e( 'Current plan', 'check-for-broken-links' ); ?></span>
			<?php elseif ( $wpcbl_featured ) : ?>
				<span class="cbl-up-flag"><?php esc_html_e( 'Most popular', 'check-for-broken-links' ); ?></span>
			<?php endif; ?>

			<div>
				<h3 class="cbl-up-name"><?php echo esc_html( $wpcbl_plan['name'] ); ?></h3>
				<p class="cbl-up-tag"><?php echo esc_html( $wpcbl_plan['tagline'] ); ?></p>
			</div>

			<div>
				<p class="cbl-up-price">
					<span class="cbl-up-amount wpcbl-plan-amount"><?php echo esc_html( $wpcbl_headline ); ?></span>
					<span class="cbl-up-per"><?php esc_html_e( '/month', 'check-for-broken-links' ); ?></span>
				</p>
				<p class="cbl-up-billed wpcbl-plan-billed"
					data-tpl-yearly="<?php echo esc_attr( $wpcbl_billed_tpl ); ?>"
					data-text-monthly="<?php esc_attr_e( 'billed month to month', 'check-for-broken-links' ); ?>"><?php echo esc_html( $wpcbl_billed ); ?></p>
			</div>

			<?php $wpcbl_form_open = ! $wpcbl_is_connected_pro; ?>
			<?php if ( $wpcbl_form_open ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cbl-up-form">
					<input type="hidden" name="action" value="wpcbl_checkout_start" />
					<input type="hidden" name="plan" value="<?php echo esc_attr( $wpcbl_slug ); ?>" />
					<input type="hidden" name="interval" value="<?php echo esc_attr( $wpcbl_initial_interval ); ?>" class="wpcbl-interval-input" />
					<?php wp_nonce_field( 'wpcbl_checkout_start' ); ?>
					<button type="submit" class="cbl-up-cta<?php echo $wpcbl_featured ? ' is-primary' : ''; ?>">
						<?php
						/* translators: %s: plan name. */
						printf( esc_html__( 'Choose %s', 'check-for-broken-links' ), esc_html( $wpcbl_plan['name'] ) );
						?>
					</button>
			<?php else : ?>
				<div class="cbl-up-form">
					<?php if ( $wpcbl_is_current ) : ?>
						<button type="button"
								class="cbl-up-cta wpcbl-change-plan"
								id="wpcbl-update-current"
								disabled
								data-wpcbl-plan="<?php echo esc_attr( $wpcbl_slug ); ?>"
								data-wpcbl-plan-name="<?php echo esc_attr( $wpcbl_plan['name'] ); ?>">
							<?php esc_html_e( 'Update subscription', 'check-for-broken-links' ); ?>
						</button>
						<p class="cbl-up-cta-note"><?php esc_html_e( 'Change an add-on or the billing period to update.', 'check-for-broken-links' ); ?></p>
					<?php else : ?>
						<button type="button"
								class="cbl-up-cta wpcbl-change-plan<?php echo $wpcbl_featured ? ' is-primary' : ''; ?><?php echo $wpcbl_is_lower ? ' is-quiet' : ''; ?>"
								data-wpcbl-plan="<?php echo esc_attr( $wpcbl_slug ); ?>"
								data-wpcbl-plan-name="<?php echo esc_attr( $wpcbl_plan['name'] ); ?>">
							<?php
							/* translators: %s: plan name. */
							printf( esc_html__( 'Switch to %s', 'check-for-broken-links' ), esc_html( $wpcbl_plan['name'] ) );
							?>
						</button>
					<?php endif; ?>
			<?php endif; ?>

					<ul class="cbl-up-feats">
						<?php foreach ( $wpcbl_features as $wpcbl_feature ) : ?>
							<li>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg>
								<span><?php echo wp_kses( $wpcbl_feature, array( 'strong' => array( 'class' => true ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>

					<details class="cbl-up-addons"<?php echo $wpcbl_addons_on ? ' open' : ''; ?>>
						<summary>
							<?php esc_html_e( 'Add-ons', 'check-for-broken-links' ); ?>
							<span class="cbl-up-addons-hint wpcbl-addons-hint"
								data-text-none="<?php esc_attr_e( 'Customize', 'check-for-broken-links' ); ?>"
								data-tpl-some="<?php /* translators: %s: number of add-ons picked. */ esc_attr_e( '%s added · edit', 'check-for-broken-links' ); ?>"><?php
								/* translators: %s: number of add-ons picked. */
								echo esc_html( $wpcbl_addons_on ? sprintf( __( '%s added · edit', 'check-for-broken-links' ), number_format_i18n( $wpcbl_addons_on ) ) : __( 'Customize', 'check-for-broken-links' ) );
								?></span>
							<span class="cbl-up-addons-hide"><?php esc_html_e( 'Hide', 'check-for-broken-links' ); ?></span>
						</summary>
						<div class="cbl-up-selects">
							<label class="cbl-up-field" for="wpcbl-kw-<?php echo esc_attr( $wpcbl_slug ); ?>">
								<span><?php esc_html_e( 'Rank-tracked keywords', 'check-for-broken-links' ); ?></span>
								<select id="wpcbl-kw-<?php echo esc_attr( $wpcbl_slug ); ?>" name="keywords" class="cbl-up-select wpcbl-sel-kw">
									<option value="0" <?php selected( '0', $wpcbl_pre_kw ); ?>
										data-yearly="0" data-monthly="0"
										data-count="<?php echo esc_attr( number_format_i18n( (int) $wpcbl_plan['rank_keywords'] ) ); ?>"><?php echo esc_html( number_format_i18n( (int) $wpcbl_plan['rank_keywords'] ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_kw ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
									<?php foreach ( $wpcbl_plan['keyword_tiers'] as $wpcbl_tier_key => $wpcbl_tier ) : ?>
										<?php
										$wpcbl_tier_is_current = $wpcbl_is_current && (string) $wpcbl_tier_key === $wpcbl_pre_kw;
										$wpcbl_tier_name       = number_format_i18n( (int) $wpcbl_tier_key ) . ' ' . __( 'keywords', 'check-for-broken-links' ) . ' · ';
										$wpcbl_tier_yearly     = $wpcbl_tier_name . ( $wpcbl_tier_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_tier['yearly'] ) . '/yr' );
										$wpcbl_tier_monthly    = $wpcbl_tier_name . ( $wpcbl_tier_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_tier['monthly'] ) . '/mo' );
										?>
										<option value="<?php echo esc_attr( (string) $wpcbl_tier_key ); ?>" <?php selected( (string) $wpcbl_tier_key, $wpcbl_pre_kw ); ?>
											data-yearly="<?php echo esc_attr( (string) $wpcbl_tier['yearly'] ); ?>"
											data-monthly="<?php echo esc_attr( (string) $wpcbl_tier['monthly'] ); ?>"
											data-count="<?php echo esc_attr( number_format_i18n( (int) $wpcbl_tier_key ) ); ?>"
											data-label-yearly="<?php echo esc_attr( $wpcbl_tier_yearly ); ?>"
											data-label-monthly="<?php echo esc_attr( $wpcbl_tier_monthly ); ?>"><?php echo esc_html( $wpcbl_is_monthly ? $wpcbl_tier_monthly : $wpcbl_tier_yearly ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="cbl-up-field" for="wpcbl-daily-<?php echo esc_attr( $wpcbl_slug ); ?>">
								<span><?php esc_html_e( 'Ranking updates', 'check-for-broken-links' ); ?></span>
								<?php
								$wpcbl_daily_is_current = $wpcbl_is_current && '1' === $wpcbl_pre_daily;
								$wpcbl_daily_name       = __( 'Daily updates', 'check-for-broken-links' ) . ' · ';
								$wpcbl_daily_yearly     = $wpcbl_daily_name . ( $wpcbl_daily_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_daily_price['yearly'] ) . '/yr' );
								$wpcbl_daily_monthly    = $wpcbl_daily_name . ( $wpcbl_daily_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_daily_price['monthly'] ) . '/mo' );
								?>
								<select id="wpcbl-daily-<?php echo esc_attr( $wpcbl_slug ); ?>" name="daily" class="cbl-up-select wpcbl-sel-daily"
									data-prices="<?php echo esc_attr( (string) wp_json_encode( $wpcbl_plan['daily_by_tier'] ) ); ?>">
									<option value="0" <?php selected( '0', $wpcbl_pre_daily ); ?>><?php echo esc_html( __( 'Weekly updates', 'check-for-broken-links' ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_daily ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
									<option value="1" <?php selected( '1', $wpcbl_pre_daily ); ?>
										data-name="<?php echo esc_attr( $wpcbl_daily_name ); ?>"
										data-current="<?php echo esc_attr( $wpcbl_current_label ); ?>"
										data-label-yearly="<?php echo esc_attr( $wpcbl_daily_yearly ); ?>"
										data-label-monthly="<?php echo esc_attr( $wpcbl_daily_monthly ); ?>"><?php echo esc_html( $wpcbl_is_monthly ? $wpcbl_daily_monthly : $wpcbl_daily_yearly ); ?></option>
								</select>
							</label>
							<label class="cbl-up-field" for="wpcbl-ai-<?php echo esc_attr( $wpcbl_slug ); ?>">
								<span><?php esc_html_e( 'AI Visibility credits', 'check-for-broken-links' ); ?></span>
								<select id="wpcbl-ai-<?php echo esc_attr( $wpcbl_slug ); ?>" name="ai" class="cbl-up-select wpcbl-sel-ai">
									<option value="0" <?php selected( '0', $wpcbl_pre_ai ); ?> data-yearly="0" data-monthly="0"><?php echo esc_html( ( (int) $wpcbl_plan['ai_credits'] > 0 ? number_format_i18n( (int) $wpcbl_plan['ai_credits'] ) . ' ' . __( 'credits', 'check-for-broken-links' ) : __( 'No credits', 'check-for-broken-links' ) ) . ' · ' . ( $wpcbl_is_current && '0' === $wpcbl_pre_ai ? $wpcbl_current_label : __( 'included', 'check-for-broken-links' ) ) ); ?></option>
									<?php foreach ( $wpcbl_ai_tiers as $wpcbl_ai_key => $wpcbl_ai_tier ) : ?>
										<?php
										if ( (int) $wpcbl_ai_key <= (int) $wpcbl_plan['ai_credits'] ) {
											continue;
										}
										$wpcbl_ai_is_current = $wpcbl_is_current && (string) $wpcbl_ai_key === $wpcbl_pre_ai;
										$wpcbl_ai_name       = number_format_i18n( (int) $wpcbl_ai_key ) . ' ' . __( 'credits', 'check-for-broken-links' ) . ' · ';
										$wpcbl_ai_yearly     = $wpcbl_ai_name . ( $wpcbl_ai_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_ai_tier['yearly'] ) . '/yr' );
										$wpcbl_ai_monthly    = $wpcbl_ai_name . ( $wpcbl_ai_is_current ? $wpcbl_current_label : '+$' . $wpcbl_money( $wpcbl_ai_tier['monthly'] ) . '/mo' );
										?>
										<option value="<?php echo esc_attr( (string) $wpcbl_ai_key ); ?>" <?php selected( (string) $wpcbl_ai_key, $wpcbl_pre_ai ); ?>
											data-yearly="<?php echo esc_attr( (string) $wpcbl_ai_tier['yearly'] ); ?>"
											data-monthly="<?php echo esc_attr( (string) $wpcbl_ai_tier['monthly'] ); ?>"
											data-label-yearly="<?php echo esc_attr( $wpcbl_ai_yearly ); ?>"
											data-label-monthly="<?php echo esc_attr( $wpcbl_ai_monthly ); ?>"><?php echo esc_html( $wpcbl_is_monthly ? $wpcbl_ai_monthly : $wpcbl_ai_yearly ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
					</details>

			<?php if ( $wpcbl_form_open ) : ?>
				</form>
			<?php else : ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>

<?php if ( ! $wpcbl_is_connected_pro ) : ?>
	<ul class="cbl-up-trust">
		<?php
		$wpcbl_trust = array(
			__( 'Cancel anytime', 'check-for-broken-links' ),
			__( 'Test on the Free plan first', 'check-for-broken-links' ),
			__( 'Prorated plan changes', 'check-for-broken-links' ),
			__( 'No license key needed', 'check-for-broken-links' ),
		);
		foreach ( $wpcbl_trust as $wpcbl_trust_item ) :
			?>
			<li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg><?php echo esc_html( $wpcbl_trust_item ); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( ! $wpcbl_is_connected && function_exists( 'wpcbl_connect_url' ) ) : ?>
	<?php // Subscribers who land here (plan bought on another site or on the web) must not re-enter checkout. Connecting activates the plan they already own. ?>
	<div class="cbl-up-strip">
		<p><strong><?php esc_html_e( 'Already purchased a plan?', 'check-for-broken-links' ); ?></strong> <span><?php esc_html_e( 'Connect this site and Pro turns on right away. No license key needed.', 'check-for-broken-links' ); ?></span></p>
		<a class="cbl-up-strip-btn" href="<?php echo esc_url( wpcbl_connect_url() ); ?>"><?php esc_html_e( 'Connect this site to your account', 'check-for-broken-links' ); ?></a>
	</div>
<?php endif; ?>

<?php
// Comparison table. Paid-plan quotas come from the live catalog where it has
// them, the rest from $wpcbl_compare_static. Columns: free + the three plans.
$wpcbl_cols        = array( 'free', 'personal', 'business', 'agency' );
$wpcbl_current_col = $wpcbl_is_connected_pro && isset( $wpcbl_plans[ $wpcbl_current_plan ] ) ? $wpcbl_current_plan : ( $wpcbl_is_connected ? 'free' : '' );
$wpcbl_accent_col  = $wpcbl_is_connected_pro && '' !== $wpcbl_current_col ? $wpcbl_current_col : 'business';
$wpcbl_value       = static function ( $slug, $key ) use ( $wpcbl_plans, $wpcbl_compare_static ) {
	if ( isset( $wpcbl_compare_static[ $slug ][ $key ] ) ) {
		return $wpcbl_compare_static[ $slug ][ $key ];
	}

	return isset( $wpcbl_plans[ $slug ][ $key ] ) ? $wpcbl_plans[ $slug ][ $key ] : null;
};
$wpcbl_number      = static function ( $value ) {
	return null === $value ? __( 'Unlimited', 'check-for-broken-links' ) : ( is_numeric( $value ) ? number_format_i18n( (int) $value ) : $value );
};
$wpcbl_rows        = array(
	array( __( 'Monitored websites', 'check-for-broken-links' ), 'sites', 'number' ),
	array( __( 'Pages per scan, per site', 'check-for-broken-links' ), 'scan_pages', 'number' ),
	array( __( 'Monthly on-demand scans', 'check-for-broken-links' ), 'scans', 'number' ),
	array( __( 'Scan frequency', 'check-for-broken-links' ), 'frequency', 'text' ),
	array( __( 'Rank tracker keywords', 'check-for-broken-links' ), 'rank_keywords', 'number' ),
	array( __( 'On-demand ranking updates (per week)', 'check-for-broken-links' ), 'refreshes', 'number' ),
	array( __( 'SEO / AEO audits / month', 'check-for-broken-links' ), 'seo_audits', 'number' ),
	array( __( 'AI link fixes / month', 'check-for-broken-links' ), 'ai_fixes', 'number' ),
	array( __( 'AI Visibility credits', 'check-for-broken-links' ), 'ai_credits', 'credits' ),
	array( __( 'Uptime monitor', 'check-for-broken-links' ), 'uptime', 'text' ),
	array( __( 'Data retention', 'check-for-broken-links' ), 'retention', 'text' ),
	array( __( 'Email alerts when links break', 'check-for-broken-links' ), 'pro', 'check' ),
	array( __( 'Fix broken links with AI', 'check-for-broken-links' ), 'pro', 'check' ),
	array( __( 'SEO optimized report page', 'check-for-broken-links' ), 'pro', 'check' ),
	array( __( 'Internal Link Optimizer (AI)', 'check-for-broken-links' ), 'pro', 'check' ),
	array( __( 'Pro WordPress plugin', 'check-for-broken-links' ), 'pro', 'check' ),
);
// Free-plan values the catalog cannot give (it lists paid plans only).
$wpcbl_free_map = array(
	'rank_keywords' => 'keywords',
	'seo_audits'    => 'audits',
);
?>
<details class="cbl-up-compare">
	<summary>
		<?php esc_html_e( 'Compare all features', 'check-for-broken-links' ); ?>
		<span class="cbl-up-compare-show"><?php esc_html_e( 'Show table', 'check-for-broken-links' ); ?></span>
		<span class="cbl-up-compare-hide"><?php esc_html_e( 'Hide', 'check-for-broken-links' ); ?></span>
	</summary>
	<div class="cbl-up-table-wrap">
		<table class="cbl-up-table">
			<thead>
				<tr>
					<td></td>
					<?php foreach ( $wpcbl_cols as $wpcbl_col ) : ?>
						<th scope="col" class="<?php echo $wpcbl_col === $wpcbl_accent_col ? 'is-accent' : ''; ?>">
							<?php echo esc_html( 'free' === $wpcbl_col ? __( 'Free', 'check-for-broken-links' ) : $wpcbl_plans[ $wpcbl_col ]['name'] ); ?>
							<?php if ( $wpcbl_col === $wpcbl_current_col ) : ?>
								<span class="cbl-up-col-current"><?php esc_html_e( '(current)', 'check-for-broken-links' ); ?></span>
							<?php endif; ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wpcbl_rows as $wpcbl_row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $wpcbl_row[0] ); ?></th>
						<?php foreach ( $wpcbl_cols as $wpcbl_col ) : ?>
							<?php
							$wpcbl_key  = 'free' === $wpcbl_col && isset( $wpcbl_free_map[ $wpcbl_row[1] ] ) ? $wpcbl_free_map[ $wpcbl_row[1] ] : $wpcbl_row[1];
							$wpcbl_cell = $wpcbl_value( $wpcbl_col, $wpcbl_key );
							$wpcbl_td   = $wpcbl_col === $wpcbl_accent_col ? 'is-accent' : '';
							?>
							<td class="<?php echo esc_attr( $wpcbl_td ); ?>">
								<?php
								if ( 'check' === $wpcbl_row[2] || ( 'ai_fixes' === $wpcbl_row[1] && 'free' === $wpcbl_col ) ) {
									$wpcbl_has = 'free' !== $wpcbl_col;
									if ( $wpcbl_has && 'check' === $wpcbl_row[2] ) {
										echo '<span class="cbl-up-yes" aria-hidden="true">&#10003;</span><span class="screen-reader-text">' . esc_html__( 'Included', 'check-for-broken-links' ) . '</span>';
									} else {
										echo '<span class="cbl-up-no" aria-hidden="true">&#10005;</span><span class="screen-reader-text">' . esc_html__( 'Not included', 'check-for-broken-links' ) . '</span>';
									}
								} elseif ( 'credits' === $wpcbl_row[2] ) {
									echo esc_html( (int) $wpcbl_cell > 0 ? number_format_i18n( (int) $wpcbl_cell ) : __( 'Add-on', 'check-for-broken-links' ) );
								} elseif ( 'number' === $wpcbl_row[2] ) {
									echo esc_html( $wpcbl_number( $wpcbl_cell ) );
								} else {
									echo esc_html( (string) $wpcbl_cell );
								}
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</details>

<?php
$wpcbl_upgrade_faq = array(
	array(
		'question' => __( 'What happens if I cancel?', 'check-for-broken-links' ),
		'answer'   => __( 'Your account moves to the Free plan and this site loses its Pro features. Free keeps one month of history, so older scans, rankings and audits are deleted. The free plugin features keep working.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Can I change my plan later?', 'check-for-broken-links' ),
		'answer'   => __( 'Yes. Change plans any time on brokenlinkchecker.io, or on this page once the site is connected. Upgrades charge the prorated difference right away. Downgrades are credited to your next invoice.', 'check-for-broken-links' ),
	),
	array(
		'question' => __( 'Do you offer refunds?', 'check-for-broken-links' ),
		'answer'   => __( 'Refunds cover defects. If a paid feature does not work as described and we cannot fix it within 14 days of your report, you get a full refund of that charge. There is no general money-back window, so test the tools on the Free plan first. Duplicate charges are always refunded.', 'check-for-broken-links' ),
		'link'     => array( 'https://brokenlinkchecker.io/terms#refunds', __( 'Read the refund policy', 'check-for-broken-links' ) ),
	),
	array(
		'question' => __( 'Do I need a license key?', 'check-for-broken-links' ),
		'answer'   => __( 'No. Your plan lives on your brokenlinkchecker.io account. Connect this site to that account and the Pro features turn on here by themselves.', 'check-for-broken-links' ),
	),
);
?>

<section class="cbl-up-faq">
	<div class="cbl-up-faq-intro">
		<h2><?php esc_html_e( 'Questions', 'check-for-broken-links' ); ?></h2>
		<p>
			<?php esc_html_e( 'Prices in USD. Every plan is billed yearly or monthly.', 'check-for-broken-links' ); ?>
			<a href="https://brokenlinkchecker.io/pricing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See full pricing details', 'check-for-broken-links' ); ?></a>
		</p>
	</div>
	<div class="cbl-up-faq-list">
		<?php foreach ( $wpcbl_upgrade_faq as $wpcbl_faq_item ) : ?>
			<details class="cbl-up-faq-item">
				<summary><?php echo esc_html( $wpcbl_faq_item['question'] ); ?></summary>
				<p>
					<?php echo esc_html( $wpcbl_faq_item['answer'] ); ?>
					<?php if ( ! empty( $wpcbl_faq_item['link'] ) ) : ?>
						<a href="<?php echo esc_url( $wpcbl_faq_item['link'][0] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $wpcbl_faq_item['link'][1] ); ?></a>
					<?php endif; ?>
				</p>
			</details>
		<?php endforeach; ?>
	</div>
</section>

<p class="cbl-up-foot">
	<?php esc_html_e( 'Built by', 'check-for-broken-links' ); ?>
	<a href="https://brokenlinkchecker.io/pricing" target="_blank" rel="noopener noreferrer">brokenlinkchecker.io</a>
</p>

</div><!-- .cbl-up -->

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
