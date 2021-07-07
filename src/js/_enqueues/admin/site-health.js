/**
 * Interactions used by the Site Health modules in WordPress.
 *
 * @output wp-admin/js/site-health.js
 */

/* global ajaxurl, ClipboardJS, SiteHealth, wp */

jQuery( function( $ ) {

	var __ = wp.i18n.__,
		_n = wp.i18n._n,
		sprintf = wp.i18n.sprintf,
		clipboard = new ClipboardJS( '.site-health-copy-buttons .copy-button' ),
		isStatusTab = $( '.health-check-body.health-check-status-tab' ).length,
		isDebugTab = $( '.health-check-body.health-check-debug-tab' ).length,
		successTimeout;

	// Debug information copy section.
	clipboard.on( 'success', function( e ) {
		var triggerElement = $( e.trigger ),
			successElement = $( '.success', triggerElement.closest( 'div' ) );

		// Clear the selection and move focus back to the trigger.
		e.clearSelection();
		// Handle ClipboardJS focus bug, see https://github.com/zenorocha/clipboard.js/issues/680
		triggerElement.trigger( 'focus' );

		// Show success visual feedback.
		clearTimeout( successTimeout );
		successElement.removeClass( 'hidden' );

		// Hide success visual feedback after 3 seconds since last success.
		successTimeout = setTimeout( function() {
			successElement.addClass( 'hidden' );
			// Remove the visually hidden textarea so that it isn't perceived by assistive technologies.
			if ( clipboard.clipboardAction.fakeElem && clipboard.clipboardAction.removeFake ) {
				clipboard.clipboardAction.removeFake();
			}
		}, 3000 );

		// Handle success audible feedback.
		wp.a11y.speak( __( 'Site information has been copied to your clipboard.' ) );
	} );

	// Accordion handling in various areas.
	$( '.health-check-accordion' ).on( 'click', '.health-check-accordion-trigger', function() {
		var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

		if ( isExpanded ) {
			$( this ).attr( 'aria-expanded', 'false' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', true );
		} else {
			$( this ).attr( 'aria-expanded', 'true' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', false );
		}
	} );

	// Site Health test handling.

	$( '.site-health-view-passed' ).on( 'click', function() {
		var goodIssuesWrapper = $( '#health-check-issues-good' );

		goodIssuesWrapper.toggleClass( 'hidden' );
		$( this ).attr( 'aria-expanded', ! goodIssuesWrapper.hasClass( 'hidden' ) );
	} );

	/**
	 * Validates the Site Health test result format.
	 *
	 * @since 5.6.0
	 *
	 * @param {Object} issue
	 *
	 * @return {boolean}
	 */
	function validateIssueData( issue ) {
		// Expected minimum format of a valid SiteHealth test response.
		var minimumExpected = {
				test: 'string',
				label: 'string',
				description: 'string'
			},
			passed = true,
			key, value, subKey, subValue;

		// If the issue passed is not an object, return a `false` state early.
		if ( 'object' !== typeof( issue ) ) {
			return false;
		}

		// Loop over expected data and match the data types.
		for ( key in minimumExpected ) {
			value = minimumExpected[ key ];

			if ( 'object' === typeof( value ) ) {
				for ( subKey in value ) {
					subValue = value[ subKey ];

					if ( 'undefined' === typeof( issue[ key ] ) ||
						'undefined' === typeof( issue[ key ][ subKey ] ) ||
						subValue !== typeof( issue[ key ][ subKey ] )
					) {
						passed = false;
					}
				}
			} else {
				if ( 'undefined' === typeof( issue[ key ] ) ||
					value !== typeof( issue[ key ] )
				) {
					passed = false;
				}
			}
		}

		return passed;
	}

	/**
	 * Appends a new issue to the issue list.
	 *
	 * @since 5.2.0
	 *
	 * @param {Object} issue The issue data.
	 */
	function appendIssue( issue ) {
		var template = wp.template( 'health-check-issue' ),
			issueWrapper = $( '#health-check-issues-' + issue.status ),
			heading,
			count;

		/*
		 * Validate the issue data format before using it.
		 * If the output is invalid, discard it.
		 */
		if ( ! validateIssueData( issue ) ) {
			return false;
		}

		SiteHealth.site_status.issues[ issue.status ]++;

		count = SiteHealth.site_status.issues[ issue.status ];

		// If no test name is supplied, append a placeholder for markup references.
		if ( typeof issue.test === 'undefined' ) {
			issue.test = issue.status + count;
		}

		if ( 'critical' === issue.status ) {
			heading = sprintf(
				_n( '%s critical issue', '%s critical issues', count ),
				'<span class="issue-count">' + count + '</span>'
			);
		} else if ( 'recommended' === issue.status ) {
			heading = sprintf(
				_n( '%s recommended improvement', '%s recommended improvements', count ),
				'<span class="issue-count">' + count + '</span>'
			);
		} else if ( 'good' === issue.status ) {
			heading = sprintf(
				_n( '%s item with no issues detected', '%s items with no issues detected', count ),
				'<span class="issue-count">' + count + '</span>'
			);
		}

		if ( heading ) {
			$( '.site-health-issue-count-title', issueWrapper ).html( heading );
		}

		$( '.issues', '#health-check-issues-' + issue.status ).append( template( issue ) );
	}

	/**
	 * Updates site health status indicator as asynchronous tests are run and returned.
	 *
	 * @since 5.2.0
	 */
	function recalculateProgression() {
		var r, c, pct;
		var $progress = $( '.site-health-progress' );
		var $wrapper = $progress.closest( '.site-health-progress-wrapper' );
		var $progressLabel = $( '.site-health-progress-label', $wrapper );
		var $circle = $( '.site-health-progress svg #bar' );
		var totalTests = parseInt( SiteHealth.site_status.issues.good, 0 ) +
			parseInt( SiteHealth.site_status.issues.recommended, 0 ) +
			( parseInt( SiteHealth.site_status.issues.critical, 0 ) * 1.5 );
		var failedTests = ( parseInt( SiteHealth.site_status.issues.recommended, 0 ) * 0.5 ) +
			( parseInt( SiteHealth.site_status.issues.critical, 0 ) * 1.5 );
		var val = 100 - Math.ceil( ( failedTests / totalTests ) * 100 );

		if ( 0 > val ) {
			val = 0;
		}
		if ( 100 < val ) {
			val = 100;
		}

		pct = ( ( 100 - val ) / 100 ) * c + 'px';

		$circle.css( { strokeDashoffset: pct } );

		if ( 1 > parseInt( SiteHealth.site_status.issues.critical, 0 ) ) {
			$( '#health-check-issues-critical' ).addClass( 'hidden' );
		}

		if ( 1 > parseInt( SiteHealth.site_status.issues.recommended, 0 ) ) {
			$( '#health-check-issues-recommended' ).addClass( 'hidden' );
		}

		if ( 100 === val ) {
			$( '.site-status-all-clear' ).removeClass( 'hide' );
			$( '.site-status-has-issues' ).addClass( 'hide' );
		}

		if ( isStatusTab ) {
			$.post(
				ajaxurl,
				{
					'action': 'health-check-site-status-result',
					'_wpnonce': SiteHealth.nonce.site_status_result,
					'counts': SiteHealth.site_status.issues
				}
			);

			if ( 100 === val ) {
				$( '.site-status-all-clear' ).removeClass( 'hide' );
				$( '.site-status-has-issues' ).addClass( 'hide' );
			}
		}
	}

	/**
	 * Queues the next asynchronous test when we're ready to run it.
	 *
	 * @since 5.2.0
	 */
	function maybeRunNextAsyncTest() {
		var doCalculation = true;

		if ( 1 <= SiteHealth.site_status.async.length ) {
			$.each( SiteHealth.site_status.async, function() {
				var data = {
					'action': 'health-check-' + this.test.replace( '_', '-' ),
					'_wpnonce': SiteHealth.nonce.site_status
				};

				if ( this.completed ) {
					return true;
				}

				doCalculation = false;

				this.completed = true;

				if ( 'undefined' !== typeof( this.has_rest ) && this.has_rest ) {
					wp.apiRequest( {
						url: wp.url.addQueryArgs( this.test, { _locale: 'user' } ),
						headers: this.headers
					} )
						.done( function( response ) {
							/** This filter is documented in wp-admin/includes/class-wp-site-health.php */
							appendIssue( wp.hooks.applyFilters( 'site_status_test_result', response ) );
						} )
						.fail( function( response ) {
							var description;

							if ( 'undefined' !== typeof( response.responseJSON ) && 'undefined' !== typeof( response.responseJSON.message ) ) {
								description = response.responseJSON.message;
							} else {
								description = __( 'No details available' );
							}

							addFailedSiteHealthCheckNotice( this.url, description );
						} )
						.always( function() {
							maybeRunNextAsyncTest();
						} );
				} else {
					$.post(
						ajaxurl,
						data
					).done( function( response ) {
						/** This filter is documented in wp-admin/includes/class-wp-site-health.php */
						appendIssue( wp.hooks.applyFilters( 'site_status_test_result', response.data ) );
					} ).fail( function( response ) {
						var description;

						if ( 'undefined' !== typeof( response.responseJSON ) && 'undefined' !== typeof( response.responseJSON.message ) ) {
							description = response.responseJSON.message;
						} else {
							description = __( 'No details available' );
						}

						addFailedSiteHealthCheckNotice( this.url, description );
					} ).always( function() {
						maybeRunNextAsyncTest();
					} );
				}

				return false;
			} );
		}

		if ( doCalculation ) {
			recalculateProgression();
		}
	}

	/**
	 * Add the details of a failed asynchronous test to the list of test results.
	 *
	 * @since 5.6.0
	 */
	function addFailedSiteHealthCheckNotice( url, description ) {
		var issue;

		issue = {
			'status': 'recommended',
			'label': __( 'A test is unavailable' ),
			'badge': {
				'color': 'red',
				'label': __( 'Unavailable' )
			},
			'description': '<p>' + url + '</p><p>' + description + '</p>',
			'actions': ''
		};

		/** This filter is documented in wp-admin/includes/class-wp-site-health.php */
		appendIssue( wp.hooks.applyFilters( 'site_status_test_result', issue ) );
	}

	if ( 'undefined' !== typeof SiteHealth ) {
		if ( 0 === SiteHealth.site_status.direct.length && 0 === SiteHealth.site_status.async.length ) {
			recalculateProgression();
		} else {
			SiteHealth.site_status.issues = {
				'good': 0,
				'recommended': 0,
				'critical': 0
			};
		}

		if ( 0 < SiteHealth.site_status.direct.length ) {
			$.each( SiteHealth.site_status.direct, function() {
				appendIssue( this );
			} );
		}

		if ( 0 < SiteHealth.site_status.async.length ) {
			maybeRunNextAsyncTest();
		} else {
			recalculateProgression();
		}
	}

	if ( isDebugTab ) {
		RecalculateProgression();
	}

	// Trigger a class toggle when the extended menu button is clicked.
	$( '.health-check-offscreen-nav-wrapper' ).on( 'click', function() {
		$( this ).toggleClass( 'visible' );
	} );
} );
