/* global pagenow, ajaxurl, postboxes, wpActiveEditor:true */
var ajaxPopulateWidgets, quickPressLoad;
window.wp = window.wp || {};

jQuery(document).ready( function($) {
	var welcomePanel = $( '#welcome-panel' ),
		welcomePanelHide = $('#wp_welcome_panel-hide'),
		updateWelcomePanel;

	updateWelcomePanel = function( visible ) {
		$.post( ajaxurl, {
			action: 'update-welcome-panel',
			visible: visible,
			welcomepanelnonce: $( '#welcomepanelnonce' ).val()
		});
	};

	if ( welcomePanel.hasClass('hidden') && welcomePanelHide.prop('checked') ) {
		welcomePanel.removeClass('hidden');
	}

	$('.welcome-panel-close, .welcome-panel-dismiss a', welcomePanel).click( function(e) {
		e.preventDefault();
		welcomePanel.addClass('hidden');
		updateWelcomePanel( 0 );
		$('#wp_welcome_panel-hide').prop('checked', false);
	});

	welcomePanelHide.click( function() {
		welcomePanel.toggleClass('hidden', ! this.checked );
		updateWelcomePanel( this.checked ? 1 : 0 );
	});

	postboxes.add_postbox_toggles(pagenow, { pbshow: ajaxPopulateWidgets } );

	/* QuickPress */
	quickPressLoad = function() {
		var act = $('#quickpost-action'), t;

		$( '#quick-press .submit input[type="submit"], #quick-press .submit input[type="reset"]' ).prop( 'disabled' , false );

		t = $('#quick-press').submit( function( e ) {
			e.preventDefault();
			$('#dashboard_quick_press #publishing-action .spinner').show();
			$('#quick-press .submit input[type="submit"], #quick-press .submit input[type="reset"]').prop('disabled', true);

			$.post( t.attr( 'action' ), t.serializeArray(), function( data ) {
				// Replace the form, and prepend the published post.
				$('#dashboard_quick_press .inside').html( data );
				$('#quick-press').removeClass('initial-form');
				quickPressLoad();
				highlightLatestPost();
				$('#title').focus();
			});

			function highlightLatestPost () {
				var latestPost = $('.drafts ul li').first();
				latestPost.css('background', '#fffbe5');
				setTimeout(function () {
					latestPost.css('background', 'none');
				}, 1000);
			}
		} );

		$('#publish').click( function() { act.val( 'post-quickpress-publish' ); } );

		$('#title, #tags-input, #content').each( function() {
			var input = $(this), prompt = $('#' + this.id + '-prompt-text');

			if ( '' === this.value ) {
				prompt.removeClass('screen-reader-text');
			}

			prompt.click( function() {
				$(this).addClass('screen-reader-text');
				input.focus();
			});

			input.blur( function() {
				if ( '' === this.value ) {
					prompt.removeClass('screen-reader-text');
				}
			});

			input.focus( function() {
				prompt.addClass('screen-reader-text');
			});
		});

		$('#quick-press').on( 'click focusin', function() {
			wpActiveEditor = 'content';
		});

		autoResizeTextarea();
	};
	quickPressLoad();

	$( '.meta-box-sortables' ).sortable( 'option', 'containment', '#wpwrap' );

	function autoResizeTextarea() {
		if ( document.documentMode && document.documentMode < 9 ) {
			return;
		}

		// Add a hidden div. We'll copy over the text from the textarea to measure its height.
		$('body').append( '<div class="quick-draft-textarea-clone" style="display: none;"></div>' );

		var clone = $('.quick-draft-textarea-clone'),
			editor = $('#content'),
			editorHeight = editor.height(),
			// 100px roughly accounts for browser chrome and allows the
			// save draft button to show on-screen at the same time.
			editorMaxHeight = $(window).height() - 100;

		// Match up textarea and clone div as much as possible.
		// Padding cannot be reliably retrieved using shorthand in all browsers.
		clone.css({
			'font-family': editor.css('font-family'),
			'font-size':   editor.css('font-size'),
			'line-height': editor.css('line-height'),
			'padding-bottom': editor.css('paddingBottom'),
			'padding-left': editor.css('paddingLeft'),
			'padding-right': editor.css('paddingRight'),
			'padding-top': editor.css('paddingTop'),
			'white-space': 'pre-wrap',
			'word-wrap': 'break-word',
			'display': 'none'
		});

		// propertychange is for IE < 9
		editor.on('focus input propertychange', function() {
			var $this = $(this),
				// &nbsp; is to ensure that the height of a final trailing newline is included.
				textareaContent = $this.val() + '&nbsp;',
				// 2px is for border-top & border-bottom
				cloneHeight = clone.css('width', $this.css('width')).text(textareaContent).outerHeight() + 2;

			// Default to having scrollbars
			editor.css('overflow-y', 'auto');

			// Only change the height if it has indeed changed and both heights are below the max.
			if ( cloneHeight === editorHeight || ( cloneHeight >= editorMaxHeight && editorHeight >= editorMaxHeight ) ) {
				return;
			}

			// Don't allow editor to exceed height of window.
			// This is also bound in CSS to a max-height of 1300px to be extra safe.
			if ( cloneHeight > editorMaxHeight ) {
				editorHeight = editorMaxHeight;
			} else {
				editorHeight = cloneHeight;
			}

			// No scrollbars as we change height, not for IE < 9
			editor.css('overflow', 'hidden');

			$this.css('height', editorHeight + 'px');
		});
	}

} );
