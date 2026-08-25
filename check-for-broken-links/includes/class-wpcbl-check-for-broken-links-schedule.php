<?php
/**
 * The WPCBL_Check_Broken_Links_Schedule class.
 *
 * @package WPCBL_Check_Broken_Links
 * @author  Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Schedule' ) ) :
	/**
	 * The schedule class.
	 *
	 * Handles the schedule event.
	 *
	 * @since 1.0.0
	 */
	class WPCBL_Check_Broken_Links_Schedule {
		/**
		 * The constructor.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function __construct() {
			// Actions.
			add_action( 'wpcbl_check_for_broken_links_scheduled_event', array( $this, 'schedule_event' ) );
			add_action( 'update_option', array( $this, 'on_update_option' ), 10, 3 );

			// Filters.
			add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		}

		/**
		 * Schedule event.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function schedule_event() {
			return;
		}

		/**
		 * Add schedule.
		 *
		 * @param array $schedules - The schedules array.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function add_schedule( $schedules ) {
			$schedules['five_minutes'] = array(
				'interval' => 300,
				'display'  => esc_html__( 'Every 5 minutes', 'check-for-broken-links' ),
			);

			$schedules['monthly'] = array(
				'interval' => 2592000,
				'display'  => esc_html__( 'Monthly', 'check-for-broken-links' ),
			);

			return $schedules;
		}

		/**
		 * Get the next timestamp for the saved scan settings.
		 *
		 * @param string $frequency Scan frequency.
		 * @param string $time      Scan time in HH:MM format.
		 * @param string $timezone  PHP timezone identifier.
		 *
		 * @since 1.0.2
		 *
		 * @return int
		 */
		public static function get_next_scan_timestamp( $frequency = 'weekly', $time = '00:00', $timezone = '' ) {
			if ( empty( $timezone ) ) {
				$timezone = wp_timezone_string();
			}

			try {
				$timezone_object = new DateTimeZone( $timezone );
			} catch ( Exception $e ) {
				$timezone_object = wp_timezone();
			}

			if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches ) ) {
				$time    = '00:00';
				$matches = array( '', '00', '00' );
			}

			$now = new DateTime( 'now', $timezone_object );
			$run = new DateTime( 'now', $timezone_object );
			$run->setTime( (int) $matches[1], (int) $matches[2], 0 );

			if ( $run <= $now ) {
				if ( 'five_minutes' === $frequency ) {
					$run->modify( '+5 minutes' );
				} elseif ( 'monthly' === $frequency ) {
					$run->modify( '+1 month' );
				} elseif ( 'weekly' === $frequency ) {
					$run->modify( '+1 week' );
				} else {
					$run->modify( '+1 day' );
				}
			}

			return $run->getTimestamp();
		}

		/**
		 * Schedule the scan event from settings.
		 *
		 * @param array $settings Plugin settings.
		 *
		 * @since 1.0.2
		 *
		 * @return void
		 */
		public static function update_scan_schedule( $settings ) {
			wp_clear_scheduled_hook( 'wpcbl_check_for_broken_links_scheduled_event' );
		}

		/**
		 * Update scan frequency.
		 *
		 * @param string $frequency - The frequency.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function update_scan_frequency( $frequency ) {
			$settings                   = get_option( 'wpcbl_check_for_broken_links_settings', array() );
			$settings['scan_frequency'] = $frequency;

			self::update_scan_schedule( $settings );
		}

		/**
		 * On update option.
		 *
		 * @param string $option - The option name.
		 * @param mixed  $old_value - The old value.
		 * @param mixed  $new_value - The new value.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function on_update_option( $option, $old_value, $new_value ) {
			if ( 'wpcbl_check_for_broken_links_settings' !== $option || ! is_array( $new_value ) ) {
				return;
			}

			$schedule_keys = array( 'scan_frequency', 'scan_time', 'scan_timezone' );

			foreach ( $schedule_keys as $key ) {
				$old_setting = isset( $old_value[ $key ] ) ? $old_value[ $key ] : '';
				$new_setting = isset( $new_value[ $key ] ) ? $new_value[ $key ] : '';

				if ( $old_setting !== $new_setting ) {
					self::update_scan_schedule( $new_value );
					return;
				}
			}
		}
	}

	new WPCBL_Check_Broken_Links_Schedule();

endif;
