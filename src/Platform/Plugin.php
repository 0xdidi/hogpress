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
 * This is the WordPress glue entry point. It is intentionally thin: it registers
 * the settings, admin UI, and front-end injection. All business logic lives in
 * the platform-agnostic Core.
 */
final class Plugin {

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

		// Translations for WordPress.org-hosted plugins are loaded automatically.

		// Admin UI.
		( new SettingsPage() )->register();
		( new Notices() )->register();

		// Front-end posthog-js injection.
		( new Enqueue() )->register();
	}

	/**
	 * Activation callback. Kept side-effect-free and safe to re-run.
	 *
	 * @return void
	 */
	public static function activate() {
		// Flush rewrite rules defensively in case future rewrites are added.
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
