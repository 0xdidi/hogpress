<?php
/**
 * Settings storage and typed accessors.
 *
 * Reads and writes the single prefixed option array. Everything that needs a
 * setting goes through here so defaults and sanitization live in one place.
 *
 * @package Hogpress\Platform
 */

namespace Hogpress\Platform\Settings;

use Hogpress\Core\Connection\Host;

/**
 * Central settings accessor.
 */
final class Options {

	/**
	 * Option name for the main settings array.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'hogpress_settings';

	/**
	 * Cached merged settings for the current request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default settings. These are the shape; later phases extend it.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'project_api_key' => '',
			'region'          => Host::REGION_US,
			'custom_host'     => '',
			'client'          => array(
				'autocapture'       => true,
				'pageviews'         => true,
				'session_recording' => false,
				'person_profiles'   => 'identified_only',
				'cookieless'        => false,
			),
		);
	}

	/**
	 * Get the full, defaults-merged settings array.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults         = self::defaults();
		$merged           = array_merge( $defaults, $stored );
		$merged['client'] = array_merge(
			$defaults['client'],
			isset( $stored['client'] ) && is_array( $stored['client'] ) ? $stored['client'] : array()
		);
		self::$cache      = $merged;

		return $merged;
	}

	/**
	 * Persist a sanitized settings array and clear the request cache.
	 *
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION_KEY, $settings );
		self::$cache = null;
	}

	/**
	 * Clear the in-request cache (useful in tests).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * The PostHog project API key (public, safe for the browser).
	 *
	 * @return string
	 */
	public static function project_api_key() {
		return (string) self::all()['project_api_key'];
	}

	/**
	 * The selected region identifier.
	 *
	 * @return string
	 */
	public static function region() {
		return (string) self::all()['region'];
	}

	/**
	 * The raw custom host (only meaningful when region is custom).
	 *
	 * @return string
	 */
	public static function custom_host() {
		return (string) self::all()['custom_host'];
	}

	/**
	 * The resolved base ingestion host URL for the current settings.
	 *
	 * @return string Empty if not resolvable (e.g. invalid custom host).
	 */
	public static function host() {
		return Host::resolve( self::region(), self::custom_host() );
	}

	/**
	 * The client (posthog-js) configuration sub-array.
	 *
	 * @return array<string,mixed>
	 */
	public static function client() {
		return (array) self::all()['client'];
	}

	/**
	 * Whether the plugin has enough to load posthog-js (key + resolvable host).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::project_api_key() && '' !== self::host();
	}

	/**
	 * Sanitize a raw input array (e.g. from $_POST) into clean settings.
	 *
	 * Merges over current settings so a partial form does not wipe other values.
	 *
	 * @param array<string,mixed> $input Raw, untrusted input.
	 * @return array<string,mixed> Sanitized settings ready to save.
	 */
	public static function sanitize( array $input ) {
		$current = self::all();

		// Project API key: keep to a safe character set.
		if ( isset( $input['project_api_key'] ) ) {
			$key                        = sanitize_text_field( wp_unslash( $input['project_api_key'] ) );
			$current['project_api_key'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
		}

		// Region: must be one of the known regions.
		if ( isset( $input['region'] ) ) {
			$region            = sanitize_text_field( wp_unslash( $input['region'] ) );
			$current['region'] = in_array( $region, Host::regions(), true ) ? $region : Host::REGION_US;
		}

		// Custom host: store the normalized URL form.
		if ( isset( $input['custom_host'] ) ) {
			$raw                    = esc_url_raw( wp_unslash( $input['custom_host'] ) );
			$current['custom_host'] = Host::normalize_url( $raw );
		}

		// Client toggles. Checkboxes are present only when checked.
		$client = $current['client'];
		if ( isset( $input['client'] ) && is_array( $input['client'] ) ) {
			$raw_client                  = wp_unslash( $input['client'] );
			$client['autocapture']       = ! empty( $raw_client['autocapture'] );
			$client['pageviews']         = ! empty( $raw_client['pageviews'] );
			$client['session_recording'] = ! empty( $raw_client['session_recording'] );
			$client['cookieless']        = ! empty( $raw_client['cookieless'] );

			$profiles                  = isset( $raw_client['person_profiles'] )
				? sanitize_text_field( $raw_client['person_profiles'] )
				: 'identified_only';
			$client['person_profiles'] = in_array( $profiles, array( 'identified_only', 'always' ), true )
				? $profiles
				: 'identified_only';
		}
		$current['client'] = $client;

		return $current;
	}
}
