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
class TMTracker_Settings {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'tmtracker-settings';

	/**
	 * Settings group.
	 */
	const GROUP = 'tmtracker_settings_group';

	/**
	 * Action name and nonce action of the "Refresh now" and "Clear cache now" buttons.
	 */
	const CACHE_ACTION = 'tmtracker_cache_action';

	/**
	 * Fetcher.
	 *
	 * @var TMTracker_Fetcher
	 */
	private $fetcher;

	/**
	 * Whether an invalid URL has already been reported in this request.
	 *
	 * WordPress runs the sanitize callback twice when the option is saved
	 * for the first time. The flag keeps the notice from appearing twice.
	 *
	 * @var bool
	 */
	private $url_error_reported = false;

	/**
	 * Constructor.
	 *
	 * @param TMTracker_Fetcher $fetcher Fetcher instance.
	 */
	public function __construct( TMTracker_Fetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::CACHE_ACTION, array( $this, 'handle_cache_action' ) );
		add_action( 'add_option_' . TMTRACKER_OPTION_SETTINGS, array( $this, 'handle_settings_change' ) );
		add_action( 'update_option_' . TMTRACKER_OPTION_SETTINGS, array( $this, 'handle_settings_change' ) );
		add_filter( 'plugin_action_links_' . TMTRACKER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		add_filter( 'removable_query_args', array( $this, 'add_removable_query_args' ) );
	}

	/**
	 * Settings API registration.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			TMTRACKER_OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'json_url'    => TMTRACKER_DEFAULT_JSON_URL,
					'cache_hours' => TMTRACKER_DEFAULT_CACHE_HOURS,
				),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'tmtracker_main_section',
			__( 'Data source and cache', 'training-meeting-tracker' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'tmtracker_json_url',
			__( 'JSON URL', 'training-meeting-tracker' ),
			array( $this, 'render_field_json_url' ),
			self::PAGE_SLUG,
			'tmtracker_main_section',
			array( 'label_for' => 'tmtracker_json_url' )
		);

		add_settings_field(
			'tmtracker_cache_hours',
			__( 'Cache duration (hours)', 'training-meeting-tracker' ),
			array( $this, 'render_field_cache_hours' ),
			self::PAGE_SLUG,
			'tmtracker_main_section',
			array( 'label_for' => 'tmtracker_cache_hours' )
		);
	}

	/**
	 * Register menu entry.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Training Meeting Tracker', 'training-meeting-tracker' ),
			__( 'Training Meeting Tracker', 'training-meeting-tracker' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Adds a "Settings" link to the plugin row on the Plugins screen.
	 *
	 * @param mixed $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$links = (array) $links;

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->get_page_url() ),
				esc_html__( 'Settings', 'training-meeting-tracker' )
			)
		);

		return $links;
	}

	/**
	 * Lets WordPress strip the notice flags from the address bar, so a
	 * reload does not show the same notice again.
	 *
	 * @param mixed $args Query arguments WordPress removes from the URL.
	 * @return array
	 */
	public function add_removable_query_args( $args ) {
		$args   = (array) $args;
		$args[] = 'tmtracker-cache-clear';
		$args[] = 'tmtracker-refresh';

		return $args;
	}

	/**
	 * Sanitize callback for the whole options array.
	 *
	 * An empty URL falls back to the default source. An invalid URL keeps
	 * the URL saved before and reports the problem. A key that is missing
	 * in the input keeps its saved value.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = array(
			'json_url'    => TMTRACKER_DEFAULT_JSON_URL,
			'cache_hours' => TMTRACKER_DEFAULT_CACHE_HOURS,
		);

		if ( ! is_array( $input ) ) {
			return $out;
		}

		$saved_url          = $this->fetcher->get_url();
		$out['cache_hours'] = $this->fetcher->get_cache_hours();

		if ( ! isset( $input['json_url'] ) || ! is_scalar( $input['json_url'] ) ) {
			$out['json_url'] = $saved_url;
		} else {
			$raw = trim( (string) $input['json_url'] );
			$url = esc_url_raw( $raw, array( 'http', 'https' ) );

			if ( '' !== $raw && '' !== $url && wp_http_validate_url( $url ) ) {
				$out['json_url'] = $url;
			} elseif ( '' !== $raw ) {
				$out['json_url'] = $saved_url;
				$this->report_invalid_url();
			}
		}

		if ( isset( $input['cache_hours'] ) && is_scalar( $input['cache_hours'] ) ) {
			$out['cache_hours'] = TMTracker_Fetcher::clamp_cache_hours( (int) $input['cache_hours'] );
		}

		return $out;
	}

	/**
	 * Drops the cache when the saved settings changed, so a new URL or cache
	 * duration takes effect on the next page view.
	 *
	 * @return void
	 */
	public function handle_settings_change() {
		$this->fetcher->clear_cache();
	}

	/**
	 * Section intro text.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__(
			'The meeting list is fed by a JSON file. Usually that is the file generated automatically on the data branch of the team repository.',
			'training-meeting-tracker'
		) . '</p>';
	}

	/**
	 * Field: JSON URL.
	 *
	 * @return void
	 */
	public function render_field_json_url() {
		printf(
			'<input type="url" name="%1$s[json_url]" id="tmtracker_json_url" value="%2$s" placeholder="%3$s" class="large-text code" aria-describedby="tmtracker_json_url_description" />',
			esc_attr( TMTRACKER_OPTION_SETTINGS ),
			esc_attr( $this->fetcher->get_url() ),
			esc_attr( TMTRACKER_DEFAULT_JSON_URL )
		);

		echo '<p class="description" id="tmtracker_json_url_description">' . esc_html__(
			'Full URL to sitzungen.json, starting with http or https. Leave the field empty to use the default source.',
			'training-meeting-tracker'
		) . '</p>';
	}

	/**
	 * Field: cache duration.
	 *
	 * @return void
	 */
	public function render_field_cache_hours() {
		printf(
			'<input type="number" name="%1$s[cache_hours]" id="tmtracker_cache_hours" value="%2$d" min="%3$d" max="%4$d" step="1" class="small-text" aria-describedby="tmtracker_cache_hours_description" />',
			esc_attr( TMTRACKER_OPTION_SETTINGS ),
			(int) $this->fetcher->get_cache_hours(),
			(int) TMTracker_Fetcher::MIN_CACHE_HOURS,
			(int) TMTracker_Fetcher::MAX_CACHE_HOURS
		);

		echo '<p class="description" id="tmtracker_cache_hours_description">';
		printf(
			/* translators: 1: lowest allowed number of hours, 2: highest allowed number of hours. */
			esc_html__( 'How long a successful fetch is cached before it is fetched again. Values: %1$d to %2$d.', 'training-meeting-tracker' ),
			(int) TMTracker_Fetcher::MIN_CACHE_HOURS,
			(int) TMTracker_Fetcher::MAX_CACHE_HOURS
		);
		echo '</p>';
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

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Training Meeting Tracker', 'training-meeting-tracker' ); ?></h1>

			<?php $this->render_notices(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<h2><?php echo esc_html__( 'Status', 'training-meeting-tracker' ); ?></h2>

			<?php $this->render_status(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CACHE_ACTION ); ?>" />
				<?php wp_nonce_field( self::CACHE_ACTION ); ?>
				<p class="submit">
					<?php
					submit_button( __( 'Refresh now', 'training-meeting-tracker' ), 'secondary', 'tmtracker_refresh', false );
					echo ' ';
					submit_button( __( 'Clear cache now', 'training-meeting-tracker' ), 'secondary', 'tmtracker_clear', false );
					?>
				</p>
			</form>

			<p class="description">
				<?php
				echo esc_html__(
					'"Refresh now" immediately fetches the data from the source. "Clear cache now" only drops the cache; the next page view triggers a new fetch.',
					'training-meeting-tracker'
				);
				?>
			</p>

			<h2><?php echo esc_html__( 'Usage', 'training-meeting-tracker' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Place the shortcode on a page or post:', 'training-meeting-tracker' ); ?>
				<code>[training_meeting_tracker]</code>
			</p>
			<p>
				<?php echo esc_html__( 'With optional attributes:', 'training-meeting-tracker' ); ?>
				<code>[training_meeting_tracker show_upcoming="true" show_in_progress="true" show_past="true" years="3" heading_level="2"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Receive the "Refresh now" and "Clear cache now" buttons.
	 *
	 * @return void
	 */
	public function handle_cache_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Permission denied.', 'training-meeting-tracker' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::CACHE_ACTION );

		if ( isset( $_POST['tmtracker_refresh'] ) ) {
			$result = $this->fetcher->get_data( true );
			$flag   = 'tmtracker-refresh';
			$value  = ( null !== $result['data'] && empty( $result['stale'] ) ) ? 'ok' : 'fail';
		} else {
			$this->fetcher->clear_cache();
			$flag  = 'tmtracker-cache-clear';
			$value = '1';
		}

		wp_safe_redirect( add_query_arg( $flag, $value, $this->get_page_url() ) );
		exit;
	}

	/**
	 * Notices after a cache action.
	 *
	 * @return void
	 */
	private function render_notices() {
		// The flags only select a notice text, they change nothing: no nonce needed.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$cache_cleared  = isset( $_GET['tmtracker-cache-clear'] );
		$refresh_status = isset( $_GET['tmtracker-refresh'] )
			? sanitize_key( wp_unslash( $_GET['tmtracker-refresh'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $cache_cleared ) {
			$this->render_notice(
				'success',
				__( 'Cache cleared. The next page view will reload the meeting data.', 'training-meeting-tracker' )
			);
		}

		if ( 'ok' === $refresh_status ) {
			$this->render_notice(
				'success',
				__( 'Meeting data was refreshed successfully.', 'training-meeting-tracker' )
			);
		} elseif ( 'fail' === $refresh_status ) {
			$status  = $this->fetcher->get_status();
			$message = __( 'Refresh failed. The source could not be loaded; the last successful state is kept.', 'training-meeting-tracker' );

			if ( '' !== $status['last_error'] ) {
				$message .= ' ' . sprintf(
					/* translators: %s: error message of the failed fetch. */
					__( 'Reason: %s', 'training-meeting-tracker' ),
					$status['last_error']
				);
			}

			$this->render_notice( 'error', $message );
		}
	}

	/**
	 * Prints a single admin notice.
	 *
	 * @param string $type    Notice type: success or error.
	 * @param string $message Unescaped message.
	 * @return void
	 */
	private function render_notice( $type, $message ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Status table: when the data was fetched and generated, and the last error.
	 *
	 * @return void
	 */
	private function render_status() {
		$status       = $this->fetcher->get_status();
		$last_good    = get_option( TMTRACKER_OPTION_LAST_GOOD, null );
		$generated_at = ( is_array( $last_good ) && ! empty( $last_good['generated_at'] ) )
			? strtotime( (string) $last_good['generated_at'] )
			: false;

		if ( ! is_array( $last_good ) ) {
			$fetched = __( 'No data loaded yet.', 'training-meeting-tracker' );
		} elseif ( $status['last_success'] > 0 ) {
			$fetched = $this->format_timestamp( $status['last_success'] );
		} else {
			$fetched = __( 'Not recorded yet. The time appears after the next fetch.', 'training-meeting-tracker' );
		}

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Last successful fetch', 'training-meeting-tracker' ); ?></th>
				<td><?php echo esc_html( $fetched ); ?></td>
			</tr>
			<?php if ( false !== $generated_at ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Data generated by the source', 'training-meeting-tracker' ); ?></th>
					<td>
						<?php echo esc_html( $this->format_timestamp( $generated_at ) ); ?>
						<p class="description">
							<?php echo esc_html__( 'The source only writes a new file when a meeting changed. An old date here is normal.', 'training-meeting-tracker' ); ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( '' !== $status['last_error'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Last fetch failed', 'training-meeting-tracker' ); ?></th>
					<td><?php echo esc_html( $this->format_timestamp( $status['last_attempt'] ) . ': ' . $status['last_error'] ); ?></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Formats a Unix timestamp with the date and time format of the site.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private function format_timestamp( $timestamp ) {
		return (string) wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $timestamp
		);
	}

	/**
	 * URL of the settings page.
	 *
	 * @return string
	 */
	private function get_page_url() {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) );
	}

	/**
	 * Reports an invalid JSON URL on the settings page.
	 *
	 * @return void
	 */
	private function report_invalid_url() {
		// The function only exists in the admin. The callback also runs for
		// update_option() calls outside of it, for example from WP-CLI.
		if ( $this->url_error_reported || ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		$this->url_error_reported = true;

		add_settings_error(
			TMTRACKER_OPTION_SETTINGS,
			'tmtracker_invalid_url',
			__( 'The JSON URL is not a valid http or https address. The previous URL was kept.', 'training-meeting-tracker' ),
			'error'
		);
	}
}
