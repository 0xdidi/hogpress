<?php
/**
 * Identity glue.
 *
 * Bridges WordPress (cookies, the current user, site salt) to the
 * platform-agnostic identity Resolver. This is where the shared
 * ph_<project_api_key>_posthog cookie is read so the server and client resolve
 * to one person.
 *
 * @package Hogpress\Platform
 */

namespace Hogpress\Platform\Identity;

use Hogpress\Core\Identity\Resolver;
use Hogpress\Platform\Settings\Options;

/**
 * Resolves the current visitor's PostHog identity from WordPress state.
 */
final class Identity {

	/**
	 * The distinct id stored by posthog-js in its cookie, if present.
	 *
	 * @return string|null
	 */
	public static function cookie_distinct_id() {
		$key = Options::project_api_key();
		if ( '' === $key ) {
			return null;
		}

		$name = Resolver::cookie_name( $key );
		if ( empty( $_COOKIE[ $name ] ) ) {
			return null;
		}

		// The cookie holds JSON; the extracted distinct id is sanitized below.
		$raw = wp_unslash( $_COOKIE[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = is_string( $raw ) ? $raw : '';

		$distinct_id = Resolver::parse_distinct_id( $raw );

		return null === $distinct_id ? null : sanitize_text_field( $distinct_id );
	}

	/**
	 * The identity (stable distinct id + person properties) for the logged-in
	 * user, or null when the visitor is not logged in.
	 *
	 * @return array{distinct_id:string,properties:array<string,string>}|null
	 */
	public static function current_user_identity() {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return null;
		}

		$distinct_id = Resolver::stable_user_id( $user->ID, self::salt() );

		$role  = ( is_array( $user->roles ) && ! empty( $user->roles ) ) ? (string) reset( $user->roles ) : '';
		$props = Resolver::person_properties(
			array(
				'email' => $user->user_email,
				'name'  => $user->display_name,
				'role'  => $role,
			)
		);

		return array(
			'distinct_id' => $distinct_id,
			'properties'  => $props,
		);
	}

	/**
	 * Resolve the distinct id a server-side event should use.
	 *
	 * Logged-in users resolve to their stable id; anonymous visitors resolve to
	 * the posthog-js cookie id. Used by server-side capture.
	 *
	 * @param string|null $fallback Optional fallback id when nothing else exists.
	 * @return array{distinct_id:string,source:string}
	 */
	public static function server_distinct_id( $fallback = null ) {
		$identity      = self::current_user_identity();
		$identified_id = $identity ? $identity['distinct_id'] : null;

		return Resolver::resolve_distinct_id( self::cookie_distinct_id(), $identified_id, $fallback );
	}

	/**
	 * The site-specific salt used to derive stable, non-reversible user ids.
	 *
	 * @return string
	 */
	private static function salt() {
		return wp_salt( 'auth' );
	}
}
