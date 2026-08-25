<?php
/**
 * Settings page (redesigned).
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'Settings', 'check-for-broken-links' );

// Saving is handled by the sticky save bar below, which appears only after a
// change. No topbar button (avoids two competing Save buttons).
$wpcbl_topbar_actions = '';

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="cbl-banner-success"><?php esc_html_e( 'Settings saved.', 'check-for-broken-links' ); ?></div>
<?php endif; ?>

<?php settings_errors( 'wpcbl_connect' ); ?>
<?php include WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/connect.php'; ?>

<form action="options.php" method="post" id="cbl-settings-form" class="cbl-settings-form">
	<?php
	settings_fields( 'wpcbl_check_for_broken_links_settings' );
	do_settings_sections( 'wpcbl-check-for-broken-links' );
	?>
</form>

<div class="cbl-save-bar" id="cbl-save-bar">
	<span class="cbl-save-bar-text"><?php esc_html_e( 'Automatic saving failed. Save your changes here.', 'check-for-broken-links' ); ?></span>
	<button type="submit" form="cbl-settings-form" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Save Settings', 'check-for-broken-links' ); ?></button>
</div>

<script>
( function () {
	var form = document.getElementById( 'cbl-settings-form' );
	var bar = document.getElementById( 'cbl-save-bar' );
	var hint = document.getElementById( 'cbl-autosave-hint' );
	if ( ! form ) {
		return;
	}

	// Mockup layout: field descriptions sit under the label in the left
	// column, except in the SEO card where helpers stay with their toggle.
	form.querySelectorAll( '.cbl-settings-card:not(.cbl-settings-card-seo) .form-table tr' ).forEach( function ( tr ) {
		var th = tr.querySelector( 'th' );
		if ( ! th ) {
			return;
		}
		tr.querySelectorAll( 'td > p.description' ).forEach( function ( desc ) {
			th.appendChild( desc );
		} );
	} );

	// Autosave: debounce, POST the form in the background, report in the
	// General card's hint. The sticky save bar only appears if a save fails.
	function setHint( state ) {
		if ( hint ) {
			hint.textContent = hint.getAttribute( 'data-' + state );
			hint.classList.toggle( 'is-saved', 'saved' === state );
		}
	}

	var timer = null;
	var revert = null;

	function save() {
		setHint( 'saving' );
		// getAttribute, not form.action: the WP settings form contains a
		// hidden input named "action", which shadows the action property.
		window.fetch( form.getAttribute( 'action' ), {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'save-failed' );
			}
			if ( bar ) {
				bar.classList.remove( 'is-visible' );
			}
			setHint( 'saved' );
			window.clearTimeout( revert );
			revert = window.setTimeout( function () { setHint( 'idle' ); }, 2500 );
		} ).catch( function () {
			setHint( 'idle' );
			if ( bar ) {
				bar.classList.add( 'is-visible' );
			}
		} );
	}

	function queueSave() {
		window.clearTimeout( timer );
		timer = window.setTimeout( save, 900 );
	}

	form.addEventListener( 'input', queueSave );
	form.addEventListener( 'change', queueSave );
} )();
</script>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
