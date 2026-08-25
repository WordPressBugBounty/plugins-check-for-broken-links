<?php
/**
 * SEO / AEO Tip page: cross-promotion for Text to Speech - TTSWP.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'SEO / AEO Tip', 'check-for-broken-links' );

// TTSWP install state drives the call to action.
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$wpcbl_ttswp_file      = 'text-to-speech-tts/text-to-speech-tts.php';
$wpcbl_ttswp_installed = file_exists( WP_PLUGIN_DIR . '/' . $wpcbl_ttswp_file );
$wpcbl_ttswp_active    = $wpcbl_ttswp_installed && is_plugin_active( $wpcbl_ttswp_file );

$wpcbl_ttswp_install_url = wp_nonce_url(
	self_admin_url( 'update.php?action=install-plugin&plugin=text-to-speech-tts' ),
	'install-plugin_text-to-speech-tts'
);
$wpcbl_ttswp_activate_url = wp_nonce_url(
	self_admin_url( 'plugins.php?action=activate&plugin=' . $wpcbl_ttswp_file ),
	'activate-plugin_' . $wpcbl_ttswp_file
);

// TTSWP rating from WordPress.org, cached for a week. The strip only shows
// real numbers; when the lookup fails the rating item is simply left out.
$wpcbl_ttswp_stats = get_transient( 'wpcbl_ttswp_org_stats' );
if ( false === $wpcbl_ttswp_stats ) {
	$wpcbl_ttswp_stats = array(
		'rating'      => 0,
		'num_ratings' => 0,
	);

	$wpcbl_ttswp_response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/text-to-speech-tts.json?fields=rating,num_ratings', array( 'timeout' => 5 ) );
	if ( ! is_wp_error( $wpcbl_ttswp_response ) && 200 === wp_remote_retrieve_response_code( $wpcbl_ttswp_response ) ) {
		$wpcbl_ttswp_data = json_decode( wp_remote_retrieve_body( $wpcbl_ttswp_response ), true );
		if ( isset( $wpcbl_ttswp_data['rating'] ) ) {
			$wpcbl_ttswp_stats['rating']      = round( (float) $wpcbl_ttswp_data['rating'] / 20, 1 );
			$wpcbl_ttswp_stats['num_ratings'] = isset( $wpcbl_ttswp_data['num_ratings'] ) ? (int) $wpcbl_ttswp_data['num_ratings'] : 0;
		}
	}

	set_transient( 'wpcbl_ttswp_org_stats', $wpcbl_ttswp_stats, WEEK_IN_SECONDS );
}

$wpcbl_show_rating = $wpcbl_ttswp_stats['rating'] >= 4 && $wpcbl_ttswp_stats['num_ratings'] >= 1;
$wpcbl_star_count  = (int) round( $wpcbl_ttswp_stats['rating'] );

$wpcbl_demo_audio_url = WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/audio/broken-links-audio.mp3';

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<div class="cbl-seo-hero">
	<h2><?php esc_html_e( 'Your links are healthy. Now let visitors listen.', 'check-for-broken-links' ); ?></h2>
	<p class="cbl-seo-hero-sub"><?php esc_html_e( 'Add audio versions of your posts in two minutes. No account needed.', 'check-for-broken-links' ); ?></p>

	<div class="cbl-hero-grid">
	<div class="cbl-audio-player">
		<button type="button" class="cbl-audio-play" id="wpcbl-audio-play" aria-label="<?php esc_attr_e( 'Play audio demo', 'check-for-broken-links' ); ?>" data-label-play="<?php esc_attr_e( 'Play audio demo', 'check-for-broken-links' ); ?>" data-label-pause="<?php esc_attr_e( 'Pause audio demo', 'check-for-broken-links' ); ?>">
			<svg class="cbl-audio-icon-play" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
			<svg class="cbl-audio-icon-pause" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
		</button>
		<img class="cbl-audio-avatar" src="<?php echo esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/audio/liam.jpg' ); ?>" width="32" height="32" alt="<?php esc_attr_e( 'Liam, the demo voice', 'check-for-broken-links' ); ?>">
		<div class="cbl-audio-body">
			<p class="cbl-audio-title">
				<span class="cbl-audio-title-main"><?php esc_html_e( '"How to fix broken links in WordPress"', 'check-for-broken-links' ); ?></span>
				<span class="cbl-audio-title-sub">
					<?php
					printf(
						/* translators: %s: TTSWP link. */
						esc_html__( '· read by Liam using %s', 'check-for-broken-links' ),
						'<a href="https://ttswp.com/" target="_blank" rel="noopener noreferrer">TTSWP</a>'
					);
					?>
				</span>
			</p>
			<div class="cbl-audio-wave" id="wpcbl-audio-wave">
				<div class="cbl-audio-wave-layer cbl-audio-wave-base" id="wpcbl-wave-base"></div>
				<div class="cbl-audio-wave-progress" id="wpcbl-wave-progress">
					<div class="cbl-audio-wave-layer cbl-audio-wave-played" id="wpcbl-wave-played"></div>
				</div>
			</div>
		</div>
		<span class="cbl-audio-time" id="wpcbl-audio-time">0:00</span>
	</div>

	<div class="cbl-hero-cta-card">
		<?php if ( $wpcbl_ttswp_active ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=text-to-speech-tts-settings' ) ); ?>" class="cbl-btn cbl-hero-cta-btn"><?php esc_html_e( 'Open TTSWP settings', 'check-for-broken-links' ); ?></a>
		<?php elseif ( $wpcbl_ttswp_installed && current_user_can( 'activate_plugins' ) ) : ?>
			<a href="<?php echo esc_url( $wpcbl_ttswp_activate_url ); ?>" class="cbl-btn cbl-hero-cta-btn"><?php esc_html_e( 'Activate TTSWP', 'check-for-broken-links' ); ?></a>
		<?php elseif ( current_user_can( 'install_plugins' ) ) : ?>
			<a href="<?php echo esc_url( $wpcbl_ttswp_install_url ); ?>" class="cbl-btn cbl-hero-cta-btn"><?php esc_html_e( 'Install TTSWP', 'check-for-broken-links' ); ?></a>
		<?php else : ?>
			<a href="https://wordpress.org/plugins/text-to-speech-tts/" target="_blank" rel="noopener noreferrer" class="cbl-btn cbl-hero-cta-btn"><?php esc_html_e( 'Install TTSWP', 'check-for-broken-links' ); ?></a>
		<?php endif; ?>
		<span class="cbl-hero-cta-note">
			<span class="cbl-hero-cta-stars" aria-hidden="true"><?php echo esc_html( str_repeat( "\xE2\x98\x85", 5 ) ); ?></span>
			<?php esc_html_e( '5.0 · Free Plugin', 'check-for-broken-links' ); ?>
		</span>
	</div>
	</div>
	<audio id="wpcbl-audio-demo" preload="metadata" src="<?php echo esc_url( $wpcbl_demo_audio_url ); ?>"></audio>
</div>

<div class="cbl-pro-grid">

	<div class="cbl-card cbl-benefit-card">
		<div class="cbl-benefit-icon cbl-benefit-icon-green">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
		</div>
		<h3 class="cbl-benefit-heading"><?php esc_html_e( 'Longer visits', 'check-for-broken-links' ); ?></h3>
		<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Listeners stay on the page. Google notices.', 'check-for-broken-links' ); ?></p>
	</div>

	<div class="cbl-card cbl-benefit-card">
		<div class="cbl-benefit-icon cbl-benefit-icon-orange">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="8" r="2"/><path d="M6.5 11h11M12 10v4l-3 5M12 14l3 5"/></svg>
		</div>
		<h3 class="cbl-benefit-heading"><?php esc_html_e( 'More readers reached', 'check-for-broken-links' ); ?></h3>
		<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Audio opens your content to everyone.', 'check-for-broken-links' ); ?></p>
	</div>

	<div class="cbl-card cbl-benefit-card">
		<div class="cbl-benefit-icon cbl-benefit-icon-purple">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
		</div>
		<h3 class="cbl-benefit-heading"><?php esc_html_e( '600+ voices, 70+ languages', 'check-for-broken-links' ); ?></h3>
		<p class="cbl-pro-feature-desc"><?php esc_html_e( 'Works with any theme or page builder.', 'check-for-broken-links' ); ?></p>
	</div>

</div>

<div class="cbl-card cbl-post-preview">
	<p class="cbl-post-preview-label"><?php esc_html_e( 'How it looks on your posts', 'check-for-broken-links' ); ?></p>
	<div class="cbl-post-preview-inner">
		<p class="cbl-post-preview-title"><?php esc_html_e( '10 Tips for Better Site Speed', 'check-for-broken-links' ); ?></p>
		<p class="cbl-post-preview-meta"><?php esc_html_e( 'Published March 3 · 6 min read', 'check-for-broken-links' ); ?></p>
		<span class="cbl-post-preview-player">
			<span class="cbl-post-preview-play"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
			<?php esc_html_e( 'Listen to this article', 'check-for-broken-links' ); ?>
			<span class="cbl-post-preview-duration">4:38</span>
		</span>
		<span class="cbl-post-preview-line" style="width: 92%;"></span>
		<span class="cbl-post-preview-line" style="width: 84%;"></span>
		<span class="cbl-post-preview-line" style="width: 55%;"></span>
	</div>
</div>

<div class="cbl-proof-strip">
	<?php if ( $wpcbl_show_rating ) : ?>
		<span class="cbl-proof-item">
			<span class="cbl-stars" aria-hidden="true"><?php echo esc_html( str_repeat( "\xE2\x98\x85", max( 1, min( 5, $wpcbl_star_count ) ) ) ); ?></span>
			<?php
			printf(
				/* translators: %s: star rating, e.g. 5.0. */
				esc_html__( '%s on WordPress.org', 'check-for-broken-links' ),
				esc_html( number_format_i18n( $wpcbl_ttswp_stats['rating'], 1 ) )
			);
			?>
		</span>
	<?php endif; ?>
	<span class="cbl-proof-item"><?php esc_html_e( 'From the team behind Broken Links', 'check-for-broken-links' ); ?></span>
	<span class="cbl-proof-item"><?php esc_html_e( 'Free plan, no account needed', 'check-for-broken-links' ); ?></span>
</div>

<div class="cbl-seo-tip-cta">
	<?php if ( $wpcbl_ttswp_active ) : ?>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=text-to-speech-tts-settings' ) ); ?>" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Open TTSWP settings', 'check-for-broken-links' ); ?></a>
		<p class="cbl-seo-tip-note"><?php esc_html_e( 'Text to Speech - TTSWP is installed and active on this site.', 'check-for-broken-links' ); ?></p>

	<?php elseif ( $wpcbl_ttswp_installed && current_user_can( 'activate_plugins' ) ) : ?>

		<a href="<?php echo esc_url( $wpcbl_ttswp_activate_url ); ?>" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Activate TTSWP', 'check-for-broken-links' ); ?></a>
		<p class="cbl-seo-tip-note"><?php esc_html_e( 'Text to Speech - TTSWP is installed but not active.', 'check-for-broken-links' ); ?></p>

	<?php elseif ( current_user_can( 'install_plugins' ) ) : ?>

		<a href="<?php echo esc_url( $wpcbl_ttswp_install_url ); ?>" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Install TTSWP free', 'check-for-broken-links' ); ?></a>

	<?php else : ?>

		<a href="https://wordpress.org/plugins/text-to-speech-tts/" target="_blank" rel="noopener noreferrer" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'View TTSWP on WordPress.org', 'check-for-broken-links' ); ?></a>

	<?php endif; ?>

	<p class="cbl-seo-tip-note">
		<?php esc_html_e( 'One click from WordPress.org. AI answer engines favor accessible, multi-format content.', 'check-for-broken-links' ); ?>
		<a href="https://ttswp.com/blog/ai-search-engines-audio-content" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn how audio helps AEO', 'check-for-broken-links' ); ?></a>
	</p>
</div>

<script>
( function () {
	var audio = document.getElementById( 'wpcbl-audio-demo' );
	var button = document.getElementById( 'wpcbl-audio-play' );
	if ( ! audio || ! button ) {
		return;
	}

	var time = document.getElementById( 'wpcbl-audio-time' );
	var wave = document.getElementById( 'wpcbl-audio-wave' );
	var waveBase = document.getElementById( 'wpcbl-wave-base' );
	var waveProgress = document.getElementById( 'wpcbl-wave-progress' );
	var wavePlayed = document.getElementById( 'wpcbl-wave-played' );
	var iconPlay = button.querySelector( '.cbl-audio-icon-play' );
	var iconPause = button.querySelector( '.cbl-audio-icon-pause' );
	var firedPlayEvent = false;

	// Static hand-tuned bar heights (8-28px, organic rhythm). Verbatim from
	// the design spec: the waveform must render identically on every load.
	var BAR_HEIGHTS = [
		12, 20, 16, 24, 14, 26, 18, 10, 22, 28,
		16, 12, 24, 18, 26, 14, 20, 10, 24, 16,
		28, 18, 12, 22, 26, 14, 24, 16, 20, 10,
		26, 18, 22, 12, 24, 14, 28, 16, 20, 12
	];
	var playerCard = button.closest( '.cbl-audio-player' ) || wave;
	var builtCount = 0;

	// 40 fixed-width bars (first 28 of the array when the player card is
	// narrower than 600px), spread by the layer's space-between so only
	// the gaps flex with the container.
	function barCount() {
		return playerCard.clientWidth < 600 ? 28 : 40;
	}

	// Fill both waveform layers with the fixed bar count. The played layer
	// sits in a clipped overlay whose width tracks progress, so its inner
	// strip must be locked to the full waveform width.
	function buildWave() {
		var count = barCount();
		var html = '';
		for ( var i = 0; i < count; i++ ) {
			html += '<span class="cbl-audio-bar" style="height:' + BAR_HEIGHTS[ i ] + 'px;"></span>';
		}
		waveBase.innerHTML = html;
		wavePlayed.innerHTML = html;
		wavePlayed.style.width = wave.clientWidth + 'px';
		builtCount = count;
	}

	function fmt( seconds ) {
		if ( ! isFinite( seconds ) ) {
			return '0:00';
		}
		var m = Math.floor( seconds / 60 );
		var s = Math.floor( seconds % 60 );
		return m + ':' + ( s < 10 ? '0' : '' ) + s;
	}

	function paint() {
		var idle = audio.paused && 0 === audio.currentTime;
		var progress = audio.duration ? audio.currentTime / audio.duration : 0;
		waveProgress.style.width = ( progress * 100 ) + '%';
		time.textContent = idle ? fmt( audio.duration ) : fmt( audio.currentTime ) + ' / ' + fmt( audio.duration );
	}

	button.addEventListener( 'click', function () {
		if ( audio.paused ) {
			audio.play();
		} else {
			audio.pause();
		}
	} );

	audio.addEventListener( 'play', function () {
		iconPlay.style.display = 'none';
		iconPause.style.display = '';
		button.setAttribute( 'aria-label', button.getAttribute( 'data-label-pause' ) );
		if ( ! firedPlayEvent ) {
			firedPlayEvent = true;
			document.dispatchEvent( new CustomEvent( 'cfbl_demo_play' ) );
		}
	} );

	audio.addEventListener( 'pause', function () {
		iconPlay.style.display = '';
		iconPause.style.display = 'none';
		button.setAttribute( 'aria-label', button.getAttribute( 'data-label-play' ) );
	} );

	audio.addEventListener( 'timeupdate', paint );
	audio.addEventListener( 'loadedmetadata', paint );

	audio.addEventListener( 'ended', function () {
		audio.currentTime = 0;
		paint();
	} );

	// Bars only rebuild when the width crosses the 600px bucket; otherwise
	// just re-lock the played strip to the new container width.
	var resizeTimer = null;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( function () {
			if ( barCount() !== builtCount ) {
				buildWave();
			} else {
				wavePlayed.style.width = wave.clientWidth + 'px';
			}
			paint();
		}, 250 );
	} );

	buildWave();
	paint();
} )();
</script>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
