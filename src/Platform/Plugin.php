<?php
/**
 * Main plugin controller.
 *
 * @package Hogpress
 */

namespace Hogpress\Platform;

use Hogpress\Platform\Admin\Notices;
use Hogpress\Platform\Admin\SettingsPage;
use Hogpress\Platform\Frontend\Enqueue;

/**
 * Wires the plugin together on init.
 *
 * This is the WordPress glue entry point. It is intentionally thin: it loads
 * text domain and, in later phases, registers settings, listeners, blocks, and
 * the admin UI. All business logic lives in the platform-agnostic Core.
 */
final class Plugin {

	/**
	 * Option key under which the text domain / naming decision lives.
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'hogpress';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether init() has already run, to guard against double-boot.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for the singleton.
	 */
	private function __construct() {}

	/**
	 * Initialise the plugin. Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin UI.
		( new SettingsPage() )->register();
		( new Notices() )->register();

		// Front-end posthog-js injection.
		( new Enqueue() )->register();

		// Subsequent phases register their components here.
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( HOGPRESS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Activation callback. Kept side-effect-free and safe to re-run.
	 *
	 * @return void
	 */
	public static function activate() {
		// Phase 0: nothing to set up yet. Later phases seed default options here.
		// Flush rewrite rules defensively in case later phases add rewrites.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
