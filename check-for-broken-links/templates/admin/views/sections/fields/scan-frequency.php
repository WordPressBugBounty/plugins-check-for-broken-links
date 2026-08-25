<?php
/**
 * Settings - Admin - Views - Sections - Fields.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views/Sections/Fields
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<?php if ( ! $has_scheduled_scans_access ) : ?>

	<?php // Free: one line instead of three disabled inputs. Display-only. ?>
	<p class="cbl-frequency-off">
		<?php esc_html_e( 'Automatic scans: Off', 'check-for-broken-links' ); ?>
		<span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span>
		<a href="#cbl-unlock-pro"><?php esc_html_e( 'See Pro features', 'check-for-broken-links' ); ?></a>
	</p>

<?php else : ?>

<div class="cbl-field-grid-3">
	<div>
		<label class="screen-reader-text" for="wpcbl_scan_frequency"><?php esc_html_e( 'Frequency', 'check-for-broken-links' ); ?></label>
		<select id="wpcbl_scan_frequency" name="wpcbl_check_for_broken_links_settings[scan_frequency]">
			<option value="never" <?php selected( $scan_frequency, 'never' ); ?>><?php esc_html_e( 'Never', 'check-for-broken-links' ); ?></option>
			<option value="daily" <?php selected( $scan_frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'check-for-broken-links' ); ?></option>
			<option value="weekly" <?php selected( $scan_frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'check-for-broken-links' ); ?></option>
			<option value="monthly" <?php selected( $scan_frequency, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'check-for-broken-links' ); ?></option>
		</select>
	</div>
	<div>
		<label class="screen-reader-text" for="wpcbl_scan_time"><?php esc_html_e( 'Scan Time', 'check-for-broken-links' ); ?></label>
		<input type="time" id="wpcbl_scan_time" name="wpcbl_check_for_broken_links_settings[scan_time]" value="<?php echo esc_attr( $scan_time ); ?>">
	</div>
	<div>
		<label class="screen-reader-text" for="wpcbl_scan_timezone"><?php esc_html_e( 'Timezone', 'check-for-broken-links' ); ?></label>
		<select id="wpcbl_scan_timezone" name="wpcbl_check_for_broken_links_settings[scan_timezone]">
			<?php echo wp_timezone_choice( $scan_timezone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</select>
	</div>
</div>

<?php endif; ?>

<?php if ( $has_scheduled_scans_access && 'never' !== $scan_frequency && ! empty( $next_scheduled ) ) : ?>
	<p class="description">
		<?php
		try {
			$wpcbl_scan_timezone = new DateTimeZone( $scan_timezone );
		} catch ( Exception $e ) {
			$wpcbl_scan_timezone = wp_timezone();
		}

		$wpcbl_next_scan = new DateTime( '@' . $next_scheduled );
		$wpcbl_next_scan->setTimezone( $wpcbl_scan_timezone );

		printf(
			/* translators: %s: Next scheduled scan date/time. */
			esc_html__( 'Next automatic scan: %s.', 'check-for-broken-links' ),
			esc_html( $wpcbl_next_scan->format( 'j F Y \a\t H:i:s' ) )
		);
		?>
	</p>
<?php elseif ( $has_scheduled_scans_access ) : ?>
	<p class="description"><?php esc_html_e( 'Choose a time and timezone for automatic scans.', 'check-for-broken-links' ); ?></p>
<?php endif; ?>
