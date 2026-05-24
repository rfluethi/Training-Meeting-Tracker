<?php
/**
 * Settings page.
 *
 * @package TrainingMeetingTracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the plugin settings via the Settings API.
 */
class TMT_Settings {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'tmt-settings';

	/**
	 * Settings group.
	 */
	const GROUP = 'tmt_settings_group';

	/**
	 * Nonce action for "Clear cache".
	 */
	const NONCE_CLEAR_CACHE = 'tmt_clear_cache';

	/**
	 * Nonce action for "Refresh now".
	 */
	const NONCE_REFRESH = 'tmt_refresh_now';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_tmt_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_tmt_refresh_now', array( $this, 'handle_refresh_now' ) );
	}

	/**
	 * Settings API registration.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			TMT_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'json_url'    => TMT_DEFAULT_JSON_URL,
					'cache_hours' => TMT_DEFAULT_CACHE_HOURS,
				),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'tmt_main_section',
			__( 'Data source and cache', 'training-meeting-tracker' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'tmt_json_url',
			__( 'JSON URL', 'training-meeting-tracker' ),
			array( $this, 'render_field_json_url' ),
			self::PAGE_SLUG,
			'tmt_main_section',
			array( 'label_for' => 'tmt_json_url' )
		);

		add_settings_field(
			'tmt_cache_hours',
			__( 'Cache duration (hours)', 'training-meeting-tracker' ),
			array( $this, 'render_field_cache_hours' ),
			self::PAGE_SLUG,
			'tmt_main_section',
			array( 'label_for' => 'tmt_cache_hours' )
		);
	}

	/**
	 * Register menu entry.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'DACH Sessions List', 'training-meeting-tracker' ),
			__( 'DACH Sessions List', 'training-meeting-tracker' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Sanitize callback for the whole options array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = array(
			'json_url'    => TMT_DEFAULT_JSON_URL,
			'cache_hours' => TMT_DEFAULT_CACHE_HOURS,
		);

		if ( ! is_array( $input ) ) {
			return $out;
		}

		if ( isset( $input['json_url'] ) ) {
			$url = esc_url_raw( trim( (string) $input['json_url'] ) );
			if ( '' !== $url ) {
				$out['json_url'] = $url;
			}
		}

		if ( isset( $input['cache_hours'] ) ) {
			$hours = (int) $input['cache_hours'];
			if ( $hours < 1 ) {
				$hours = 1;
			}
			if ( $hours > 168 ) {
				$hours = 168;
			}
			$out['cache_hours'] = $hours;
		}

		// Drop the cache after settings changes so the new URL takes effect immediately.
		delete_transient( TMT_TRANSIENT_DATA );

		return $out;
	}

	/**
	 * Section intro text.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__(
			'The session list is fed by a JSON file. Usually that is the file generated automatically on the data branch of the team repository.',
			'training-meeting-tracker'
		) . '</p>';
	}

	/**
	 * Field: JSON URL.
	 *
	 * @return void
	 */
	public function render_field_json_url() {
		$settings = (array) get_option( TMT_OPTION_SETTINGS, array() );
		$value    = isset( $settings['json_url'] ) ? (string) $settings['json_url'] : TMT_DEFAULT_JSON_URL;

		printf(
			'<input type="url" name="%1$s[json_url]" id="tmt_json_url" value="%2$s" class="regular-text" />',
			esc_attr( TMT_OPTION_SETTINGS ),
			esc_attr( $value )
		);

		echo '<p class="description">' . esc_html__(
			'Full URL to sitzungen.json.',
			'training-meeting-tracker'
		) . '</p>';
	}

	/**
	 * Field: cache duration.
	 *
	 * @return void
	 */
	public function render_field_cache_hours() {
		$settings = (array) get_option( TMT_OPTION_SETTINGS, array() );
		$value    = isset( $settings['cache_hours'] ) ? (int) $settings['cache_hours'] : TMT_DEFAULT_CACHE_HOURS;

		printf(
			'<input type="number" name="%1$s[cache_hours]" id="tmt_cache_hours" value="%2$d" min="1" max="168" step="1" class="small-text" />',
			esc_attr( TMT_OPTION_SETTINGS ),
			(int) $value
		);

		echo '<p class="description">' . esc_html__(
			'How long a successful fetch is cached before it is fetched again. Values: 1 to 168.',
			'training-meeting-tracker'
		) . '</p>';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last_good    = get_option( TMT_OPTION_LAST_GOOD, null );
		$generated_at = ( is_array( $last_good ) && ! empty( $last_good['generated_at'] ) )
			? (string) $last_good['generated_at']
			: '';

		// Notice flag after cache reset: read-only comparison, hence non-critical.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cache_cleared = isset( $_GET['tmt-cache-clear'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& '1' === sanitize_text_field( wp_unslash( $_GET['tmt-cache-clear'] ) );

		// Notice flag after manual refresh.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$refresh_status = isset( $_GET['tmt-refresh'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['tmt-refresh'] ) )
			: '';

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DACH Sessions List', 'training-meeting-tracker' ); ?></h1>

			<?php if ( $cache_cleared ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Cache cleared. The next page view will reload the session data.', 'training-meeting-tracker' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'ok' === $refresh_status ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Session data was refreshed successfully.', 'training-meeting-tracker' ); ?></p>
				</div>
			<?php elseif ( 'fail' === $refresh_status ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html__( 'Refresh failed. The source could not be loaded; the last successful state is kept.', 'training-meeting-tracker' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Cache', 'training-meeting-tracker' ); ?></h2>

			<?php if ( '' !== $generated_at ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: time of the last successful JSON generation. */
						esc_html__( 'Last successfully read data state: %s', 'training-meeting-tracker' ),
						'<code>' . esc_html( $generated_at ) . '</code>'
					);
					?>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__( 'No data loaded yet.', 'training-meeting-tracker' ); ?></p>
			<?php endif; ?>

			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:0.5em;">
					<input type="hidden" name="action" value="tmt_refresh_now" />
					<?php wp_nonce_field( self::NONCE_REFRESH ); ?>
					<?php
					submit_button(
						__( 'Refresh now', 'training-meeting-tracker' ),
						'primary',
						'submit',
						false
					);
					?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="tmt_clear_cache" />
					<?php wp_nonce_field( self::NONCE_CLEAR_CACHE ); ?>
					<?php
					submit_button(
						__( 'Clear cache now', 'training-meeting-tracker' ),
						'secondary',
						'submit',
						false
					);
					?>
				</form>
			</p>

			<p class="description">
				<?php
				echo esc_html__(
					'"Refresh now" immediately fetches the data from the source. "Clear cache now" only drops the cache; the next page view triggers a new fetch.',
					'training-meeting-tracker'
				);
				?>
			</p>

			<hr />

			<h2><?php echo esc_html__( 'Usage', 'training-meeting-tracker' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Place the shortcode on a page or post:', 'training-meeting-tracker' ); ?>
				<code>[training_meeting_tracker]</code>
			</p>
			<p>
				<?php echo esc_html__( 'With optional attributes:', 'training-meeting-tracker' ); ?>
				<code>[training_meeting_tracker show_next="true" show_past="true" years="3"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Receive "Clear cache".
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Permission denied.', 'training-meeting-tracker' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_CLEAR_CACHE );

		delete_transient( TMT_TRANSIENT_DATA );

		$redirect = add_query_arg(
			array(
				'page'             => self::PAGE_SLUG,
				'tmt-cache-clear' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Receive "Refresh now". Force a fetch.
	 *
	 * @return void
	 */
	public function handle_refresh_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Permission denied.', 'training-meeting-tracker' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_REFRESH );

		$fetcher = new TMT_Fetcher();
		$result  = $fetcher->get_data( true );

		$status = ( null !== $result['data'] && empty( $result['stale'] ) ) ? 'ok' : 'fail';

		$redirect = add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'tmt-refresh' => $status,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
