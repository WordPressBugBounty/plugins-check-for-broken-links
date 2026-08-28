<?php
/**
 * Closes the redesigned admin layout.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// The Dashboard and Broken link scan first-run views set $wpcbl_hide_footer
// so the rating footer stays out of the fresh-install experience.
if ( empty( $wpcbl_hide_footer ) ) :
	?>
			<div class="cbl-footer-text">
				<?php if ( (int) get_option( 'wpcbl_completed_scans', 0 ) >= 2 ) : ?>
					<?php
					// Only ask for a review once the plugin has delivered value (two completed scans).
					echo wp_kses_post( sprintf(
						/* translators: 1: review URL, 2: plugin site URL. */
						__( 'If Check for Broken Links helps you, please <a href="%1$s" target="_blank" rel="noopener noreferrer">leave us a &#9733;&#9733;&#9733;&#9733;&#9733; rating</a>. Built with <span class="cbl-footer-heart">&#9829;</span> by <a href="%2$s" target="_blank" rel="noopener noreferrer">brokenlinkchecker.io</a>', 'check-for-broken-links' ),
						'https://wordpress.org/support/plugin/check-for-broken-links/reviews/?rate=5#new-post',
						'https://brokenlinkchecker.io'
					) );
					?>
				<?php else : ?>
					<?php
					echo wp_kses_post( sprintf(
						/* translators: %s: plugin site URL. */
						__( 'Built with <span class="cbl-footer-heart">&#9829;</span> by <a href="%s" target="_blank" rel="noopener noreferrer">brokenlinkchecker.io</a>', 'check-for-broken-links' ),
						'https://brokenlinkchecker.io'
					) );
					?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		</div><!-- .cbl-main-content -->
	</div><!-- .cbl-main -->
</div><!-- .cbl-admin-wrap -->
