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
		/**
		 * The recurring cron hook, fired at the frequency and time the
		 * settings say.
		 */
		const EVENT = 'wpcbl_check_for_broken_links_scheduled_event';

		/**
		 * The single event a cron tick schedules for itself when the host's
		 * execution limit only allows one scan step per request.
		 */
		const STEP_EVENT = 'wpcbl_check_for_broken_links_scan_step';

		/**
		 * Frequencies the recurring event accepts. daily and weekly are
		 * WordPress's own, the rest come from add_schedule().
		 */
		const FREQUENCIES = array( 'five_minutes', 'daily', 'weekly', 'monthly' );

		public function __construct() {
			// Actions.
			add_action( self::EVENT, array( $this, 'schedule_event' ) );
			add_action( self::STEP_EVENT, array( $this, 'continue_scan' ) );
			add_action( 'update_option', array( $this, 'on_update_option' ), 10, 3 );
			// Late enough for the connect class to have hooked wpcbl_has_pro.
			add_action( 'init', array( $this, 'ensure_scheduled' ), 20 );

			// Filters.
			add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		}

		/**
		 * The cron tick: start a scheduled scan and run it through the same
		 * chunked engine as the manual scan.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function schedule_event() {
			// A manual scan in progress keeps its job. One that never
			// finished (browser closed) is stale after an hour and gets
			// replaced.
			$job = get_option( 'wpcbl_scan_job', false );
			if ( is_array( $job ) && isset( $job['started'] ) && ( microtime( true ) - (float) $job['started'] ) < HOUR_IN_SECONDS ) {
				return;
			}

			WPCBL_Check_Broken_Links_Utilities::scan_begin( 'scheduled', true );

			$this->continue_scan();
		}

		/**
		 * Run scan steps. With no execution limit the scan runs to the end
		 * in this request. Under a hard limit it runs one step and schedules
		 * the next tick, so a big site finishes over several cron runs
		 * instead of dying at the limit.
		 *
		 * @since 3.0.10
		 *
		 * @param int|null   $max_steps Steps to run before handing over to cron, null to derive from the host limit, 0 for no cap.
		 * @param float|null $budget    Seconds of checking per step, null to derive from the host limit.
		 *
		 * @return void
		 */
		public function continue_scan( $max_steps = null, $budget = null ) {
			ignore_user_abort( true );
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Disabled on some hosts, and that is handled.

			list( $step_budget, $link_timeout ) = WPCBL_Check_Broken_Links_Utilities::scan_step_limits();
			if ( null !== $budget ) {
				$step_budget = $budget;
			}
			if ( null === $max_steps ) {
				$max_steps = self::steps_per_run( (int) ini_get( 'max_execution_time' ) );
			}

			$ran = 0;
			do {
				$state = WPCBL_Check_Broken_Links_Utilities::scan_step( $step_budget, $link_timeout );
				++$ran;

				if ( ! is_array( $state ) || $state['done'] ) {
					return;
				}
			} while ( 0 === $max_steps || $ran < $max_steps );

			wp_schedule_single_event( time(), self::STEP_EVENT );
		}

		/**
		 * How many scan steps one cron request may run: one under a hard
		 * execution limit, unlimited (0) when there is none.
		 *
		 * @since 3.0.10
		 *
		 * @param int $max_execution_time The host's limit in seconds, 0 for none.
		 *
		 * @return int
		 */
		public static function steps_per_run( $max_execution_time ) {
			return ( $max_execution_time > 0 && $max_execution_time < 120 ) ? 1 : 0;
		}

		/**
		 * Keep the cron event in step with the settings and the plan: a Pro
		 * site with a frequency gets one, everyone else has none. Runs on
		 * init so a site that turns Pro (or loses it) is fixed on the next
		 * admin or cron request without re-saving settings.
		 *
		 * @since 3.0.10
		 *
		 * @return void
		 */
		public function ensure_scheduled() {
			if ( ! is_admin() && ! wp_doing_cron() ) {
				return;
			}

			$settings  = get_option( 'wpcbl_check_for_broken_links_settings', array() );
			$wanted    = self::wants_schedule( is_array( $settings ) ? $settings : array() );
			$scheduled = (bool) wp_next_scheduled( self::EVENT );

			if ( $wanted && ! $scheduled ) {
				self::update_scan_schedule( $settings );
			} elseif ( ! $wanted && $scheduled ) {
				wp_clear_scheduled_hook( self::EVENT );
			}
		}

		/**
		 * Whether these settings, on this plan, call for a recurring scan.
		 *
		 * @since 3.0.10
		 *
		 * @param array $settings Plugin settings.
		 *
		 * @return bool
		 */
		private static function wants_schedule( $settings ) {
			$frequency = isset( $settings['scan_frequency'] ) ? $settings['scan_frequency'] : 'never';

			return wpcbl_has_pro() && in_array( $frequency, self::FREQUENCIES, true );
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
			wp_clear_scheduled_hook( self::EVENT );

			if ( ! is_array( $settings ) || ! self::wants_schedule( $settings ) ) {
				return;
			}

			$time     = isset( $settings['scan_time'] ) ? $settings['scan_time'] : '00:00';
			$timezone = isset( $settings['scan_timezone'] ) ? $settings['scan_timezone'] : '';

			wp_schedule_event( self::get_next_scan_timestamp( $settings['scan_frequency'], $time, $timezone ), $settings['scan_frequency'], self::EVENT );
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
