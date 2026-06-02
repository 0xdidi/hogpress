<?php
/**
 * Settings page: connection and tracking.
 *
 * Renders the admin UI, handles the save action (nonce + capability + sanitize),
 * and validates the connection with a live test call before saving the key.
 *
 * @package Hogpress\Platform
 */

namespace Hogpress\Platform\Admin;

use Hogpress\Core\Connection\Host;
use Hogpress\Platform\Connection\Validator;
use Hogpress\Platform\Settings\Options;

/**
 * The plugin's top-level settings screen.
 */
final class SettingsPage {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const SLUG = 'hogpress';

	/**
	 * The admin-post action name used to save settings.
	 *
	 * @var string
	 */
	const ACTION = 'hogpress_save_settings';

	/**
	 * Nonce action/name.
	 *
	 * @var string
	 */
	const NONCE = 'hogpress_save_settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the top-level admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Connect for PostHog', 'hogpress' ),
			__( 'PostHog', 'hogpress' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-chart-bar',
			58
		);
	}

	/**
	 * The admin page hook suffix for our screen.
	 *
	 * @return string
	 */
	public static function hook_suffix() {
		return 'toplevel_page_' . self::SLUG;
	}

	/**
	 * Enqueue page assets only on our settings screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( self::hook_suffix() !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'hogpress-admin',
			HOGPRESS_URL . 'assets/css/admin.css',
			array(),
			HOGPRESS_VERSION
		);

		wp_enqueue_script(
			'hogpress-admin',
			HOGPRESS_URL . 'assets/js/admin.js',
			array(),
			HOGPRESS_VERSION,
			true
		);
	}

	/**
	 * Capability check helper.
	 *
	 * @return bool
	 */
	private function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle the settings save: validate then persist.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'hogpress' ), 403 );
		}

		check_admin_referer( self::NONCE, 'hogpress_nonce' );

		// Nonce verified above; the raw array is sanitized field-by-field in Options::sanitize().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw       = isset( $_POST['hogpress'] ) && is_array( $_POST['hogpress'] ) ? wp_unslash( $_POST['hogpress'] ) : array();
		$sanitized = Options::sanitize( $raw );

		$key  = (string) $sanitized['project_api_key'];
		$host = Host::resolve( $sanitized['region'], $sanitized['custom_host'] );

		// Validate the connection with a live call before saving the key.
		if ( '' !== $key ) {
			$result = Validator::validate( $key, $host );
			if ( ! $result['ok'] ) {
				$this->flash( 'error', $result['message'] );
				$this->redirect_back();
				return;
			}
		}

		Options::save( $sanitized );

		$this->flash(
			'success',
			'' === $key
				? __( 'Settings saved.', 'hogpress' )
				: __( 'Connected and saved. PostHog will start receiving events on your site.', 'hogpress' )
		);
		$this->redirect_back();
	}

	/**
	 * Store a one-time flash message for the current user.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Human-readable message.
	 * @return void
	 */
	private function flash( $type, $message ) {
		set_transient(
			$this->flash_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Read and clear the flash message.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function read_flash() {
		$flash = get_transient( $this->flash_key() );
		if ( ! is_array( $flash ) ) {
			return null;
		}
		delete_transient( $this->flash_key() );
		return $flash;
	}

	/**
	 * Per-user flash transient key.
	 *
	 * @return string
	 */
	private function flash_key() {
		return 'hogpress_flash_' . get_current_user_id();
	}

	/**
	 * Redirect back to the settings page after a save.
	 *
	 * @return void
	 */
	private function redirect_back() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'hogpress' ), 403 );
		}

		$flash       = $this->read_flash();
		$connected   = Options::is_configured();
		$api_key     = Options::project_api_key();
		$region      = Options::region();
		$custom_host = Options::custom_host();
		$client      = Options::client();
		$is_custom   = Host::REGION_CUSTOM === $region;
		?>
		<div class="wrap hogpress-wrap">
			<div class="hogpress-topbar">
				<h1 class="hogpress-title"><?php esc_html_e( 'Connect for PostHog', 'hogpress' ); ?></h1>
				<?php if ( $connected ) : ?>
					<span class="hogpress-pill hogpress-pill--ok">
						<span class="hogpress-dot"></span><?php esc_html_e( 'Connected', 'hogpress' ); ?>
					</span>
				<?php else : ?>
					<span class="hogpress-pill hogpress-pill--neutral">
						<span class="hogpress-dot"></span><?php esc_html_e( 'Not connected', 'hogpress' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( $flash ) : ?>
				<div class="hogpress-flash hogpress-flash--<?php echo esc_attr( 'success' === $flash['type'] ? 'ok' : 'error' ); ?>">
					<?php echo esc_html( $flash['message'] ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hogpress-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE, 'hogpress_nonce' ); ?>

				<section class="hogpress-card">
					<h2 class="hogpress-card__title"><?php esc_html_e( 'Connection', 'hogpress' ); ?></h2>
					<p class="hogpress-help">
						<?php esc_html_e( 'Paste your PostHog project API key and pick your region. We check it before saving.', 'hogpress' ); ?>
					</p>

					<div class="hogpress-field">
						<label class="hogpress-label" for="hogpress-key"><?php esc_html_e( 'Project API key', 'hogpress' ); ?></label>
						<input
							type="text"
							id="hogpress-key"
							class="hogpress-input hogpress-input--mono regular-text"
							name="hogpress[project_api_key]"
							value="<?php echo esc_attr( $api_key ); ?>"
							spellcheck="false"
							autocomplete="off"
							placeholder="phc_..."
						/>
						<p class="hogpress-help hogpress-help--field">
							<?php esc_html_e( 'Find this in PostHog under Settings, Project, Project API key. It is safe to expose in the browser.', 'hogpress' ); ?>
						</p>
					</div>

					<div class="hogpress-field">
						<label class="hogpress-label" for="hogpress-region"><?php esc_html_e( 'Region', 'hogpress' ); ?></label>
						<select id="hogpress-region" class="hogpress-input" name="hogpress[region]" data-hogpress-region>
							<option value="<?php echo esc_attr( Host::REGION_US ); ?>" <?php selected( $region, Host::REGION_US ); ?>><?php esc_html_e( 'US cloud (us.i.posthog.com)', 'hogpress' ); ?></option>
							<option value="<?php echo esc_attr( Host::REGION_EU ); ?>" <?php selected( $region, Host::REGION_EU ); ?>><?php esc_html_e( 'EU cloud (eu.i.posthog.com)', 'hogpress' ); ?></option>
							<option value="<?php echo esc_attr( Host::REGION_CUSTOM ); ?>" <?php selected( $region, Host::REGION_CUSTOM ); ?>><?php esc_html_e( 'Self-hosted or reverse proxy', 'hogpress' ); ?></option>
						</select>
					</div>

					<div class="hogpress-field" data-hogpress-custom-host <?php echo $is_custom ? '' : 'hidden'; ?>>
						<label class="hogpress-label" for="hogpress-host"><?php esc_html_e( 'Custom host URL', 'hogpress' ); ?></label>
						<input
							type="url"
							id="hogpress-host"
							class="hogpress-input hogpress-input--mono regular-text"
							name="hogpress[custom_host]"
							value="<?php echo esc_attr( $custom_host ); ?>"
							spellcheck="false"
							autocomplete="off"
							placeholder="https://posthog.example.com"
						/>
						<p class="hogpress-help hogpress-help--field">
							<?php esc_html_e( 'The full URL of your PostHog instance or reverse proxy, including https://.', 'hogpress' ); ?>
						</p>
					</div>
				</section>

				<section class="hogpress-card">
					<h2 class="hogpress-card__title"><?php esc_html_e( 'Tracking', 'hogpress' ); ?></h2>
					<p class="hogpress-help">
						<?php esc_html_e( 'Choose what PostHog captures in the browser. You can change these any time.', 'hogpress' ); ?>
					</p>

					<?php
					$this->toggle(
						'pageviews',
						__( 'Track pageviews', 'hogpress' ),
						__( 'Record a pageview each time someone loads a page.', 'hogpress' ),
						! empty( $client['pageviews'] )
					);
					$this->toggle(
						'autocapture',
						__( 'Autocapture clicks and forms', 'hogpress' ),
						__( 'Automatically capture clicks, form submissions, and other interactions.', 'hogpress' ),
						! empty( $client['autocapture'] )
					);
					$this->toggle(
						'session_recording',
						__( 'Record sessions', 'hogpress' ),
						__( 'Capture session replays. Heavier, so it is off by default.', 'hogpress' ),
						! empty( $client['session_recording'] )
					);
					$this->toggle(
						'cookieless',
						__( 'Privacy-first cookieless mode', 'hogpress' ),
						__( 'Keep visitor state in memory only, so no PostHog cookie is set.', 'hogpress' ),
						! empty( $client['cookieless'] )
					);
					?>

					<div class="hogpress-field">
						<label class="hogpress-label" for="hogpress-profiles"><?php esc_html_e( 'Person profiles', 'hogpress' ); ?></label>
						<select id="hogpress-profiles" class="hogpress-input" name="hogpress[client][person_profiles]">
							<option value="identified_only" <?php selected( $client['person_profiles'], 'identified_only' ); ?>><?php esc_html_e( 'Identified people only (recommended)', 'hogpress' ); ?></option>
							<option value="always" <?php selected( $client['person_profiles'], 'always' ); ?>><?php esc_html_e( 'Everyone, including anonymous', 'hogpress' ); ?></option>
						</select>
						<p class="hogpress-help hogpress-help--field">
							<?php esc_html_e( 'Identified-only keeps anonymous visitors lightweight and is the usual choice.', 'hogpress' ); ?>
						</p>
					</div>
				</section>

				<div class="hogpress-actions">
					<button type="submit" class="button button-primary button-hero hogpress-btn">
						<?php esc_html_e( 'Validate and save', 'hogpress' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single labeled toggle (checkbox styled as a switch).
	 *
	 * @param string $name    Client setting key.
	 * @param string $label   Visible label.
	 * @param string $help    One-line help text.
	 * @param bool   $checked Whether currently enabled.
	 * @return void
	 */
	private function toggle( $name, $label, $help, $checked ) {
		$id = 'hogpress-toggle-' . $name;
		?>
		<div class="hogpress-field hogpress-toggle">
			<label class="hogpress-switch" for="<?php echo esc_attr( $id ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $id ); ?>"
					name="hogpress[client][<?php echo esc_attr( $name ); ?>]"
					value="1"
					<?php checked( $checked ); ?>
				/>
				<span class="hogpress-switch__track"><span class="hogpress-switch__thumb"></span></span>
				<span class="hogpress-switch__text">
					<span class="hogpress-switch__label"><?php echo esc_html( $label ); ?></span>
					<span class="hogpress-help hogpress-help--field"><?php echo esc_html( $help ); ?></span>
				</span>
			</label>
		</div>
		<?php
	}
}
