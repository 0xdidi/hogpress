/**
 * Connect for PostHog admin script.
 *
 * Small, dependency-free progressive enhancement: show the custom host field
 * only when the "self-hosted" region is selected.
 */
( function () {
	'use strict';

	function init() {
		var region = document.querySelector( '[data-hogpress-region]' );
		var customHost = document.querySelector( '[data-hogpress-custom-host]' );

		if ( ! region || ! customHost ) {
			return;
		}

		function sync() {
			var isCustom = 'custom' === region.value;
			customHost.hidden = ! isCustom;
		}

		region.addEventListener( 'change', sync );
		sync();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
