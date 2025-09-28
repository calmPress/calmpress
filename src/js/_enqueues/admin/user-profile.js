/**
 * @output wp-admin/js/user-profile.js
 */

/* global ajaxurl, pwsL10n, userProfileL10n */
(function($) {
	var updateLock = false,
		__ = wp.i18n.__,
		$pass1Row,
		$pass1,
		$pass2,
		$weakRow,
		$weakCheckbox,
		$toggleButton,
		$submitButtons,
		$submitButton,
		currentPass,
		avatar_images_modal,
		$passwordWrapper;

	function generatePassword() {
		if ( typeof zxcvbn !== 'function' ) {
			setTimeout( generatePassword, 50 );
			return;
		} else if ( ! $pass1.val() || $passwordWrapper.hasClass( 'is-open' ) ) {
			// zxcvbn loaded before user entered password, or generating new password.
			$pass1.val( $pass1.data( 'pw' ) );
			$pass1.trigger( 'pwupdate' );
			showOrHideWeakPasswordCheckbox();
		} else {
			// zxcvbn loaded after the user entered password, check strength.
			check_pass_strength();
			showOrHideWeakPasswordCheckbox();
		}

		// Install screen.
		if ( 1 !== parseInt( $toggleButton.data( 'start-masked' ), 10 ) ) {
			// Show the password not masked if admin_password hasn't been posted yet.
			$pass1.attr( 'type', 'text' );
		} else {
			// Otherwise, mask the password.
			$toggleButton.trigger( 'click' );
		}

		// Once zxcvbn loads, passwords strength is known.
		$( '#pw-weak-text-label' ).text( __( 'Confirm use of weak password' ) );

		// Focus the password field.
		$( $pass1 ).trigger( 'focus' );
	}

	function bindPass1() {
		currentPass = $pass1.val();

		if ( 1 === parseInt( $pass1.data( 'reveal' ), 10 ) ) {
			generatePassword();
		}

		$pass1.on( 'input' + ' pwupdate', function () {
			if ( $pass1.val() === currentPass ) {
				return;
			}

			currentPass = $pass1.val();

			// Refresh password strength area.
			$pass1.removeClass( 'short bad good strong' );
			showOrHideWeakPasswordCheckbox();
		} );
	}

	function resetToggle( show ) {
		$toggleButton
			.attr({
				'aria-label': show ? __( 'Show password' ) : __( 'Hide password' )
			})
			.find( '.text' )
				.text( show ? __( 'Show' ) : __( 'Hide' ) )
			.end()
			.find( '.dashicons' )
				.removeClass( show ? 'dashicons-hidden' : 'dashicons-visibility' )
				.addClass( show ? 'dashicons-visibility' : 'dashicons-hidden' );
	}

	function bindToggleButton() {
		$toggleButton = $pass1Row.find('.wp-hide-pw');
		$toggleButton.show().on( 'click', function () {
			if ( 'password' === $pass1.attr( 'type' ) ) {
				$pass1.attr( 'type', 'text' );
				resetToggle( false );
			} else {
				$pass1.attr( 'type', 'password' );
				resetToggle( true );
			}
		});
	}

	/**
	 * Handle the password reset button. Sets up an ajax callback to trigger sending
	 * a password reset email.
	 */
	function bindPasswordResetLink() {
		$( '#generate-reset-link' ).on( 'click', function() {
			var $this  = $(this),
				data = {
					'user_id': userProfileL10n.user_id, // The user to send a reset to.
					'nonce':   userProfileL10n.nonce    // Nonce to validate the action.
				};

				// Remove any previous error messages.
				$this.parent().find( '.notice-error' ).remove();

				// Send the reset request.
				var resetAction =  wp.ajax.post( 'send-password-reset', data );

				// Handle reset success.
				resetAction.done( function( response ) {
					addInlineNotice( $this, true, response );
				} );

				// Handle reset failure.
				resetAction.fail( function( response ) {
					addInlineNotice( $this, false, response );
				} );

		});

	}

	/**
	 * Helper function to insert an inline notice of success or failure.
	 *
	 * @param {string} container The id of the message container at which to insert the
	 *                          message.
	 * @param {string} type The type of message being inserted, can be either
	 *                      'success', 'error', 'info'.
	 * @param {string}        message The message to insert.
	 */
	function addInlineNotice( container, type, message ) {
		const $resultDiv = $( '#' + container );

		// Remove previous messages if there are any.
		$resultDiv.empty();

		const $notice = jQuery(`
			<div class="notice notice-${type} is-dismissible">
				<p>${message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`);

		$resultDiv.append( $notice );
	}

	function bindPasswordForm() {
		var $generateButton,
			$cancelButton;

		$pass1Row = $( '.user-pass1-wrap, .user-pass-wrap, .reset-pass-submit' );

		// Hide the confirm password field when JavaScript support is enabled.
		$('.user-pass2-wrap').hide();

		$submitButton = $( '#submit, #wp-submit' ).on( 'click', function () {
			updateLock = false;
		});

		$submitButtons = $submitButton.add( ' #createusersub' );

		$weakRow = $( '.pw-weak' );
		$weakCheckbox = $weakRow.find( '.pw-checkbox' );
		$weakCheckbox.on( 'change', function() {
			$submitButtons.prop( 'disabled', ! $weakCheckbox.prop( 'checked' ) );
		} );

		$pass1 = $('#pass1');
		if ( $pass1.length ) {
			bindPass1();
		} else {
			// Password field for the login form.
			$pass1 = $( '#user_pass' );
		}

		/*
		 * Fix a LastPass mismatch issue, LastPass only changes pass2.
		 *
		 * This fixes the issue by copying any changes from the hidden
		 * pass2 field to the pass1 field, then running check_pass_strength.
		 */
		$pass2 = $( '#pass2' ).on( 'input', function () {
			if ( $pass2.val().length > 0 ) {
				$pass1.val( $pass2.val() );
				$pass2.val('');
				currentPass = '';
				$pass1.trigger( 'pwupdate' );
			}
		} );

		// Disable hidden inputs to prevent autofill and submission.
		if ( $pass1.is( ':hidden' ) ) {
			$pass1.prop( 'disabled', true );
			$pass2.prop( 'disabled', true );
		}

		$passwordWrapper = $pass1Row.find( '.wp-pwd' );
		$generateButton  = $pass1Row.find( 'button.wp-generate-pw' );

		bindToggleButton();

		$generateButton.show();
		$generateButton.on( 'click', function () {
			updateLock = true;

			// Make sure the password fields are shown.
			$generateButton.attr( 'aria-expanded', 'true' );
			$passwordWrapper
				.show()
				.addClass( 'is-open' );

			// Enable the inputs when showing.
			$pass1.attr( 'disabled', false );
			$pass2.attr( 'disabled', false );

			// Set the password to the generated value.
			generatePassword();

			// Show generated password in plaintext by default.
			resetToggle ( false );

			// Generate the next password and cache.
			wp.ajax.post( 'generate-password' )
				.done( function( data ) {
					$pass1.data( 'pw', data );
				} );
		} );

		$cancelButton = $pass1Row.find( 'button.wp-cancel-pw' );
		$cancelButton.on( 'click', function () {
			updateLock = false;

			// Disable the inputs when hiding to prevent autofill and submission.
			$pass1.prop( 'disabled', true );
			$pass2.prop( 'disabled', true );

			// Clear password field and update the UI.
			$pass1.val( '' ).trigger( 'pwupdate' );
			resetToggle( false );

			// Hide password controls.
			$passwordWrapper
				.hide()
				.removeClass( 'is-open' );

			// Stop an empty password from being submitted as a change.
			$submitButtons.prop( 'disabled', false );
		} );

		$pass1Row.closest( 'form' ).on( 'submit', function () {
			updateLock = false;

			$pass1.prop( 'disabled', false );
			$pass2.prop( 'disabled', false );
			$pass2.val( $pass1.val() );
		});
	}

	function check_pass_strength() {
		var pass1 = $('#pass1').val(), strength;

		$('#pass-strength-result').removeClass('short bad good strong empty');
		if ( ! pass1 || '' ===  pass1.trim() ) {
			$( '#pass-strength-result' ).addClass( 'empty' ).html( '&nbsp;' );
			return;
		}

		strength = wp.passwordStrength.meter( pass1, wp.passwordStrength.userInputDisallowedList(), pass1 );

		switch ( strength ) {
			case -1:
				$( '#pass-strength-result' ).addClass( 'bad' ).html( pwsL10n.unknown );
				break;
			case 2:
				$('#pass-strength-result').addClass('bad').html( pwsL10n.bad );
				break;
			case 3:
				$('#pass-strength-result').addClass('good').html( pwsL10n.good );
				break;
			case 4:
				$('#pass-strength-result').addClass('strong').html( pwsL10n.strong );
				break;
			case 5:
				$('#pass-strength-result').addClass('short').html( pwsL10n.mismatch );
				break;
			default:
				$('#pass-strength-result').addClass('short').html( pwsL10n['short'] );
		}
	}

	function showOrHideWeakPasswordCheckbox() {
		var passStrength = $('#pass-strength-result')[0];

		if ( passStrength.className ) {
			$pass1.addClass( passStrength.className );
			if ( $( passStrength ).is( '.short, .bad' ) ) {
				if ( ! $weakCheckbox.prop( 'checked' ) ) {
					$submitButtons.prop( 'disabled', true );
				}
				$weakRow.show();
			} else {
				if ( $( passStrength ).is( '.empty' ) ) {
					$submitButtons.prop( 'disabled', true );
					$weakCheckbox.prop( 'checked', false );
				} else {
					$submitButtons.prop( 'disabled', false );
				}
				$weakRow.hide();
			}
		}
	}

	/**
	 * Convert a string which is assumed to be base64url encoded to its original
	 * an Uint8Array representation.
	 * 
	 * @param {string} base64url A string assumed to be a base64url encoded string.
	 * 
	 * @returns Uint8Array
	 */
	function base64urlToUint8Array( base64url ) {
		const padding = '='.repeat( ( 4 - base64url.length % 4 ) % 4 );
		const base64 = ( base64url + padding )
			.replace( /-/g, '+' )
			.replace( /_/g, '/' );
		const rawData = window.atob( base64 );
		return Uint8Array.from( [...rawData].map( c => c.charCodeAt( 0 ) ) );
	}

	/**
	 * If the device have authenticator, and devices can be added at the server
	 * enable the register device input.
	 */
	function maybe_enable_webauthn_register_device() {
		// enable the register webauthn button if webauthn is supported.
		if ( webauthn_can_add_device && window.PublicKeyCredential ) {
			PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
				.then( ( available ) => {
					if ( available ) {
						$( '#register_device_webauthn' ).show();
						$( '#device_do_not_support_webauthn' ).hide();
					} 
				})
		}
	}

	$( function() {
		var $colorpicker, $stylesheet, user_id, current_user_id,
			display_name_input = $( '#display_name' ),
			current_name = display_name_input.val(),
			greeting     = $( '#wp-admin-bar-my-account' ).find( '.display-name' );

		$( '#pass1' ).val( '' ).on( 'input' + ' pwupdate', check_pass_strength );
		$('#pass-strength-result').show();
		$('.color-palette').on( 'click', function() {
			$(this).siblings('input[name="admin_color"]').prop('checked', true);
		});

		// At least firefox seems to ignore the disabled state of the button
		// after page "normal" refresh if button was enabled before it.
		$( '#register_button' ).prop( 'disabled', 'disabled' );

		if ( display_name_input ) {

			/**
			 * Replaces "Howdy, *" in the admin toolbar whenever the display name dropdown is updated for one's own profile.
			 */
			display_name_input.on( 'change', function() {
				if ( user_id !== current_user_id ) {
					return;
				}

				var display_name = this.value.trim() || current_name;

				greeting.text( display_name );
			} );
		}

		$colorpicker = $( '#color-picker' );
		$stylesheet = $( '#colors-css' );
		user_id = $( 'input#user_id' ).val();
		current_user_id = $( 'input[name="checkuser_id"]' ).val();

		$colorpicker.on( 'click.colorpicker', '.color-option', function() {
			var colors,
				$this = $(this);

			if ( $this.hasClass( 'selected' ) ) {
				return;
			}

			$this.siblings( '.selected' ).removeClass( 'selected' );
			$this.addClass( 'selected' ).find( 'input[type="radio"]' ).prop( 'checked', true );

			// Set color scheme.
			if ( user_id === current_user_id ) {
				// Load the colors stylesheet.
				// The default color scheme won't have one, so we'll need to create an element.
				if ( 0 === $stylesheet.length ) {
					$stylesheet = $( '<link rel="stylesheet" />' ).appendTo( 'head' );
				}
				$stylesheet.attr( 'href', $this.children( '.css_url' ).val() );

				// Repaint icons.
				if ( typeof wp !== 'undefined' && wp.svgPainter ) {
					try {
						colors = JSON.parse( $this.children( '.icon_colors' ).val() );
					} catch ( error ) {}

					if ( colors ) {
						wp.svgPainter.setColors( colors );
						wp.svgPainter.paint();
					}
				}

				// Update user option.
				$.post( ajaxurl, {
					action:       'save-user-color-scheme',
					color_scheme: $this.children( 'input[name="admin_color"]' ).val(),
					nonce:        $('#color-nonce').val()
				}).done( function( response ) {
					if ( response.success ) {
						$( 'body' ).removeClass( response.data.previousScheme ).addClass( response.data.currentScheme );
					}
				});
			}
		});

		bindPasswordForm();
		bindPasswordResetLink();
		bindPasswordResetLink();
		maybe_enable_webauthn_register_device();

		// if we have a fragment of password show password fields.
		if ( window.location.hash === '#password' ) {
			var $pwButton = $( '.wp-generate-pw' );
       		$pwButton.trigger('click');
		}
	});

	/**
	 * An handler for enabling and disabeling the register new device button based
	 * on whether there is non empty string at the device name input.
	 */
	$( '#new_webautn_device_name' ).on( 'input', function () {
		val = $( '#new_webautn_device_name' ).val();
		if ( val.trim() != '' ) {
			$( '#register_button' ).prop( 'disabled', '' );
		} else {
			$( '#register_button' ).prop( 'disabled', 'disabled' );
		}
	});

	/**
	 * An handler for starting registretion of new device triggered by enter on the input.
	 *
	 * @param {object} event The event
	 */
	$( '#new_webautn_device_name' ).on( 'keypress', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			val = event.target.value;
			if ( val.trim() != '' ) {
				$( '#register_button' ).trigger( 'click' );
			}
		}
	});

	$( '#destroy-sessions' ).on( 'click', function( e ) {
		var $this = $(this);

		wp.ajax.post( 'destroy-sessions', {
			nonce: $( '#_wpnonce' ).val(),
			user_id: $( '#user_id' ).val()
		}).done( function( response ) {
			$this.prop( 'disabled', true );
			$this.siblings( '.notice' ).remove();
			$this.before( '<div class="notice notice-success inline"><p>' + response.message + '</p></div>' );
		}).fail( function( response ) {
			$this.siblings( '.notice' ).remove();
			$this.before( '<div class="notice notice-error inline"><p>' + response.message + '</p></div>' );
		});

		e.preventDefault();
	});

	window.generatePassword = generatePassword;

	// Warn the user if password was generated but not saved.
	$( window ).on( 'beforeunload', function () {
		if ( true === updateLock ) {
			return __( 'Your new password has not been saved.' );
		}
	} );

	$( '#select_avatar_image' )

		/**
		 * Invoke the media modal
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {
			event.preventDefault();

			// Initialize the modal the first time.
			if ( ! avatar_images_modal ) {
				avatar_images_modal = wp.media.frames.author_images_modal || wp.media( {
					title:    userProfileL10n.avatarMediaTitle,
					button:   { text: userProfileL10n.avatarSelectText },
					library:  { type: 'image' },
					multiple: false
				} );

				// Picking an image
				avatar_images_modal.on( 'select', function () {

					// Get the image URL
					var image = avatar_images_modal.state().get( 'selection' ).first().toJSON();

					if ( '' !== image ) {
						$( '#calm_avatar_image_attachement_id' ).val( image.id );
						$( '#avatar_image_preview img' ).attr( 'src', image.url );
						$( '#avatar_image_preview img' ).attr( 'srcset', '' );
						$( '#avatar_image_preview img' ).attr( 'sizes', '' );
						$( '#revert_avatar_image' ).removeAttr( 'disabled' );
						$( '#avatar_image_preview' ).show();
						$( '#avatar_text_preview' ).hide();
					}
				} );
			}

			// Open the modal
			avatar_images_modal.open();
		} );

	$( '#revert_avatar_image' )
		/**
		 * Revert avatar to textual form
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {
			$( '#calm_avatar_image_attachement_id' ).val( 0 );
			$( '#revert_avatar_image' ).attr( 'disabled', '' );
			$( '#avatar_image_preview' ).hide();
			$( '#avatar_text_preview' ).show();
		} );

	$( '#resend-activation' )
		/**
		 * Resend activation
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {
			var $this  = $(this);
			var	data = {
				'user_id': userProfileL10n.user_id, // The user to send a reset to.
				'nonce':   userProfileL10n.nonce    // Nonce to validate the action.
			};

			// Send the resend activation request.
			var resetAction =  wp.ajax.post( 'resend-activation', data );

			// Handle success.
			resetAction.done( function( response ) {
				addInlineNotice( $this, true, response );
			} );

			// Handle failure.
			resetAction.fail( function( response ) {
				addInlineNotice( $this, false, response );
			} );
		} );
		
	$( '#verify-installer' )
		/**
		 * Send installer email verificattion mail.
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {
			var $this  = $(this);
			var	data = {
				'user_id': userProfileL10n.user_id, // The user to send a reset to.
				'nonce':   userProfileL10n.nonce    // Nonce to validate the action.
			};

			// Send the resend activation request.
			var resetAction =  wp.ajax.post( 'installer-email-verification', data );

			// Handle success.
			resetAction.done( function( response ) {
				addInlineNotice( $this, true, response );
			} );

			// Handle failure.
			resetAction.fail( function( response ) {
				addInlineNotice( $this, false, response );
			} );
		} );
		
	$( '#cancel-email-change' )
		/**
		 * Cancel/undo email change.
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {
			var $this  = $(this);
			var	data = {
				'user_id': userProfileL10n.user_id, // The user to send a reset to.
				'nonce':   userProfileL10n.nonce    // Nonce to validate the action.
			};

			// Send the resend activation request.
			var action =  wp.ajax.post( 'undo-email-change', data );

			// Handle success.
			action.done( function( response ) {
				$( '#email' ).removeAttr( 'readonly' ).val( response );
				$( '#email' ).siblings( '.notice' ).hide();
				$( '#email' ).siblings( '.description' ).show();
			} );

			// Handle failure.
			action.fail( function( response ) {
				addInlineNotice( $this, false, response );
			} );
		} );

	/**
	 * Use a template to insert a new device row into the table.
	 * 
	 * @param {string} row  The row number
	 * @param {string} cred The credential id of the device.
	 * @param {string} description The description of the device.
	 * @param {string} last_used   The 5textual description when the device was last used.
	 */
	function webauthn_add_row( row, cred, description, last_used ) {
		const tmpl = document.getElementById( 'webauthn_row_template' );
		const clone = tmpl.content.cloneNode( true );

		// Process all elements in the clone to replace place holder with their actual values
		clone.querySelectorAll('*').forEach( el => {
			// replace place holders in attributes.
			Array.from( el.attributes ).forEach( attr => {
				if ( attr.value.includes( '__ROW__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__ROW__/g, row ) );
				}
				if ( attr.value.includes( '__CRED__' ) ) {
					el.setAttribute( attr.name, attr.value.replace(/__CRED__/g, cred ) );
				}
			});

			// Replace placeholders in text.
			el.childNodes.forEach( node => {
      			if ( node.nodeType === Node.TEXT_NODE ) {
					if ( node.nodeValue.includes( '__DESC__' ) ) {
						node.nodeValue = node.nodeValue.replace(/__DESC__/g, description);
					}
					if ( node.nodeValue.includes( '__LAST_USED__' ) ) {
						node.nodeValue = node.nodeValue.replace(/__LAST_USED__/g, last_used);
					}
				}
			} );
		} );

		document.querySelector( '#devices-grid tbody' ).appendChild( clone );
	}

	$( '#register_button' )
		/**
		 * Request an registeration challenge webauthn authentication.
		 *
		 * @param {object} event The event
		 */
		.on( 'click', function ( event ) {

			var	data = {
				'nonce':   userProfileL10n.nonce    // Nonce to validate the action.
			};

			// Send the request challenge request.
			var action =  wp.ajax.post( 'webauthn-challenge', data );

			// Handle success.
			action.done( async function( response ) {
				try {
					const res = JSON.parse( response );
					const options = { 'publicKey' : res };

					// Convert base64url → ArrayBuffer for required fields
					options.publicKey.challenge = base64urlToUint8Array( options.publicKey.challenge );
					options.publicKey.user.id = base64urlToUint8Array( options.publicKey.user.id );
					if ( options.publicKey.excludeCredentials ) {
						options.publicKey.excludeCredentials = options.publicKey.excludeCredentials.map( cred => ( {
							...cred,
							id: base64urlToUint8Array( cred.id ),
						} ) );
					}

					// Ask browser to create new credential
					const credential = await navigator.credentials.create( options );

					const payload = {
						id: credential.id,
						rawId: btoa( String.fromCharCode( ...new Uint8Array( credential.rawId ) ) ),
            			type: credential.type,
						response: {
							attestationObject: btoa( String.fromCharCode( ...new Uint8Array( credential.response.attestationObject ) ) ),
							clientDataJSON: btoa( String.fromCharCode( ...new Uint8Array( credential.response.clientDataJSON) ) ),
						},
					};

					const data = {
						'nonce'     : userProfileL10n.nonce,    // Nonce to validate the action.
						'name'      : $( '#new_webautn_device_name' ).val(),
						'payload'   : payload,
					}

					// Send AJAX request to verify & store the credential
					const regiter = wp.ajax.post( 'webauthn-register-device', data );
					regiter.done( async function( response ) {
						$( '#new_webautn_device_name' ).val( '' );
						$( '#register_button' ).prop( 'disabled', 'disabled' );
						inline_notice_manager.show( 'webauthn_register_device_message', 'success', response.message );
						if ( ! response.can_add ) {
							$( '#register_device_webauthn' ).hide();
						}
						const $tbody = $( '#devices-grid tbody' );
						row = $tbody.find( 'tr' ).length + 1;
						webauthn_add_row( row, response.cred, response.description, response.last_used );
						$( '#devices-grid' ).show();
						$( '#no_devices_message' ).hide();
					} );
					regiter.fail( function( response ) {
						inline_notice_manager.show( 'webauthn_register_device_message', 'error', response.responseJSON.data );
					} );
				} catch ( e ) {
					switch ( e.name ) {
						case 'NotAllowedError':
							; // User canceled no need to do anything.
							break;
						case 'InvalidStateError':
							; // Device is already registered
							break;
						default:
							// Device can not authenticate or bug
							inline_notice_manager.show( 'webauthn_register_device_message', 'error', userProfileL10n.error_can_not_register_device );
							console.log( e );
					}
				}
			} );

			// Handle failure.
			action.fail( function( response ) {
				if ( response.status === 0 ) {
					inline_notice_manager.show( 'webauthn_register_device_message', 'error', userProfileL10n.error_connetivity );
				} else {
					inline_notice_manager.show( 'webauthn_register_device_message', 'error', response.responseJSON.data );
				}
			} );
		} );

	/**
	 * an handler for revoke of device authentication.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'click', '.actions .revoke',  function ( event ) {
		const cred = $(this).parent().parent().data( 'cred' );
		const $row = $(this).closest('tr');

		var	data = {
			'nonce':          userProfileL10n.nonce,    // Nonce to validate the action.
			'credential_id' : cred
		};

		// Send the revoke request.
		wp.ajax.post( 'webauthn-revoke', data )

			// Handle success.
			.done( async function( response ) {
				inline_notice_manager.show( 'webauthn_devices_table_message', 'success', response.message );
				webauthn_can_add_device = response.can_add;
				maybe_enable_webauthn_register_device();
				$row.fadeOut( 300, function() {
					$row.remove();
					const $tbody = $( '#devices-grid tbody' );
					if ( $tbody.find( 'tr' ).length === 0 ) {
						$( '#no_devices_message' ).show();
						$( '#devices-grid' ).hide();
					}
				});
			} )

			// Handle failure.
			.fail( function( response ) {
				if ( response.status === 0 ) {
					inline_notice_manager.show( 'webauthn_devices_table_message', 'error', userProfileL10n.error_connetivity );
				} else {
					inline_notice_manager.show( 'webauthn_devices_table_message', 'error', response.responseJSON.data );
				}
			} );
	});

	/**
	 * an handler for starting edditing the device description.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'click', '.actions .edit', function ( event ) {
	
		const $row = $(this).closest( 'tr' );
		const $box = $row.find( 'div' );
		const $input = $box.find( 'input' );

		// Set the text in the input to current description.
		const text = $box.parent().find( 'span' ).text();
		$input.val( text );

		$box.show();
		$(this).attr( 'aria-expanded', 'true' );
		$input.trigger( 'focus' );
	});

	/**
	 * An handler for canceling edit of a device description.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'click', '.close_change', function ( event ) {
	
		$(this).parent().parent().hide();

		const $row = $(this).closest( 'tr' );
		const $edit = $row.find( '.edit' );

		$edit.attr( 'aria-expanded', 'false' );
	});

	/** 
	 * A DRY for handling the submittion of the updated description to the server
	 * @param {object} element The element on which the update was triggered either
	 *                         the update button or the input.
	 */
	function update_description( element ) {
		const $row   = element.closest( 'tr' );
		const cred   = $row.data( 'cred' );
		const $box   = $row.find( '.edit_form' );
		const $input = $box.find( 'input' );

		const data = {
			'nonce'         : userProfileL10n.nonce, // Nonce to validate the action.
			'credential_id' : cred,
			'description'   : $input.val(),
		};

		// Send the update request.
		wp.ajax.post( 'webauthn-set-description', data )

			// Handle success.
			.done( async function( response ) {
				inline_notice_manager.show( 'webauthn_devices_table_message', 'success', response.message );
				$box.hide(); // Hide edit box

				// Indicate on edit button box is closed.
				const $edit = $row.find( '.edit' );
				$edit.attr( 'aria-expanded', 'false' );

				// Update description.
				$box.parent().find( 'span' ).text( response.description );
			} )

			// Handle failure.
			.fail( function( response ) {
				if ( response.status === 0 ) {
					inline_notice_manager.show( 'webauthn_devices_table_message', 'error', userProfileL10n.error_connetivity );
				} else {
					inline_notice_manager.show( 'webauthn_devices_table_message', 'error', response.responseJSON.data );
				}
			} );
	}

	/**
	 * an handler for sending updated device description to the server.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'click', '.update_description', function ( event ) {
		update_description( $(this) );
	});

	/**
	 * an handler for sending updated device description to the server.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'keypress', 'input', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			val = event.target.value;
			if ( val.trim() != '' ) {
				update_description( $(this) );
			}
		}
	});

	/**
	 * an handler to disable update button if no description text given.
	 *
	 * @param {object} event The event
	 */
	$( '#devices-grid' ).on( 'input', 'input', function ( event ) {
		val = event.target.value;
		const $row = $( event.target ).closest( 'tr' );
		const $but = $row.find( '.update_description' );
		if ( val.trim() != '' ) {
			$but.prop( 'disabled', '' );
		} else {
			$but.prop( 'disabled', 'disabled' );
		}
	});
		
	/*
	 * We need to generate a password as soon as the Reset Password page is loaded,
	 * to avoid double clicking the button to retrieve the first generated password.
	 * See ticket #39638.
	 */
	$( function() {
		if ( $( '.reset-pass-submit' ).length ) {
			$( '.reset-pass-submit button.wp-generate-pw' ).trigger( 'click' );
		}
	});

})(jQuery);
