/**
 * KDNA PDF Flipbook: admin repeater.
 *
 * Powers the Flipbooks metabox. Adds and removes rows, picks a PDF and an icon
 * with the standard WordPress media uploader, and reorders rows by dragging with
 * jquery-ui-sortable. Written as lean vanilla JS, with jQuery used only for the
 * bundled sortable behaviour. No build step.
 */
( function () {
	'use strict';

	var settings = window.kdnaFlipbookRepeater || {};
	var i18n = settings.i18n || {};

	/**
	 * Read a translated string with a fallback.
	 *
	 * @param {string} key      Key in the localised i18n object.
	 * @param {string} fallback Fallback text.
	 * @return {string} The string.
	 */
	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var repeater = document.getElementById( 'kdna-flipbook-repeater' );
		if ( ! repeater ) {
			return;
		}

		var list = document.getElementById( 'kdna-flipbook-rows' );
		var addButton = document.getElementById( 'kdna-flipbook-add-row' );
		var emptyMessage = document.getElementById( 'kdna-flipbook-empty' );
		var template = document.getElementById( 'kdna-flipbook-row-template' );
		var nextIndex = list ? list.querySelectorAll( '.kdna-flipbook-row' ).length : 0;

		/**
		 * Renumber the hidden sort inputs and data-index by current DOM order.
		 */
		function updateSortOrder() {
			var rows = list.querySelectorAll( '.kdna-flipbook-row' );
			Array.prototype.forEach.call( rows, function ( row, position ) {
				var sortInput = row.querySelector( '.kdna-flipbook-input-sort' );
				if ( sortInput ) {
					sortInput.value = position;
				}
			} );
		}

		/**
		 * Show or hide the empty-state message.
		 */
		function refreshEmptyState() {
			if ( ! emptyMessage ) {
				return;
			}
			var hasRows = list.querySelectorAll( '.kdna-flipbook-row' ).length > 0;
			emptyMessage.style.display = hasRows ? 'none' : '';
		}

		/**
		 * Add a new blank row from the template.
		 */
		function addRow() {
			if ( ! template ) {
				return;
			}
			var markup = template.innerHTML.replace( /__INDEX__/g, String( nextIndex ) );
			nextIndex++;

			var wrapper = document.createElement( 'div' );
			wrapper.innerHTML = markup.trim();
			var row = wrapper.querySelector( '.kdna-flipbook-row' );
			if ( ! row ) {
				return;
			}

			list.appendChild( row );
			refreshEmptyState();
			updateSortOrder();

			var nameInput = row.querySelector( '.kdna-flipbook-input-name' );
			if ( nameInput ) {
				nameInput.focus();
			}
		}

		/**
		 * Remove a row.
		 *
		 * @param {HTMLElement} row The row element.
		 */
		function removeRow( row ) {
			if ( ! window.confirm( t( 'confirmRemove', 'Remove this flipbook?' ) ) ) {
				return;
			}
			row.parentNode.removeChild( row );
			refreshEmptyState();
			updateSortOrder();
		}

		/**
		 * Open the media uploader to choose a PDF for a row.
		 *
		 * @param {HTMLElement} row The row element.
		 */
		function choosePdf( row ) {
			var frame = window.wp.media( {
				title: t( 'selectPdf', 'Select a PDF' ),
				button: { text: t( 'usePdf', 'Use this PDF' ) },
				library: { type: 'application/pdf' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var idInput = row.querySelector( '.kdna-flipbook-input-pdf-id' );
				var nameWrap = row.querySelector( '.kdna-flipbook-pdf-name' );
				var nameText = row.querySelector( '.kdna-flipbook-pdf-name__text' );
				var chooseButton = row.querySelector( '.kdna-flipbook-choose-pdf' );
				var removeButton = row.querySelector( '.kdna-flipbook-remove-pdf' );

				if ( idInput ) {
					idInput.value = attachment.id;
				}
				if ( nameText ) {
					nameText.textContent = attachment.title || attachment.filename || '';
				}
				if ( nameWrap ) {
					nameWrap.style.display = '';
				}
				if ( chooseButton ) {
					chooseButton.textContent = t( 'changePdf', 'Change PDF' );
				}
				if ( removeButton ) {
					removeButton.style.display = '';
				}
			} );

			frame.open();
		}

		/**
		 * Clear the chosen PDF for a row.
		 *
		 * @param {HTMLElement} row The row element.
		 */
		function removePdf( row ) {
			var idInput = row.querySelector( '.kdna-flipbook-input-pdf-id' );
			var nameWrap = row.querySelector( '.kdna-flipbook-pdf-name' );
			var chooseButton = row.querySelector( '.kdna-flipbook-choose-pdf' );
			var removeButton = row.querySelector( '.kdna-flipbook-remove-pdf' );

			if ( idInput ) {
				idInput.value = '';
			}
			if ( nameWrap ) {
				nameWrap.style.display = 'none';
			}
			if ( chooseButton ) {
				chooseButton.textContent = t( 'choosePdf', 'Choose PDF' );
			}
			if ( removeButton ) {
				removeButton.style.display = 'none';
			}
		}

		/**
		 * Show or hide the built-in icon picker for a row.
		 *
		 * @param {HTMLElement} row    The row element.
		 * @param {HTMLElement} toggle The toggle button.
		 */
		function toggleIconPicker( row, toggle ) {
			var picker = row.querySelector( '.kdna-flipbook-iconpicker' );
			if ( ! picker ) {
				return;
			}
			var open = picker.hidden;
			picker.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		/**
		 * Reflect a chosen icon in the row: preview, buttons and picker state.
		 *
		 * @param {HTMLElement} row      The row element.
		 * @param {string}      previewHtml Inner HTML for the preview.
		 */
		function markIconChosen( row, previewHtml ) {
			var preview = row.querySelector( '.kdna-flipbook-icon-preview' );
			var toggle = row.querySelector( '.kdna-flipbook-toggle-iconpicker' );
			var removeButton = row.querySelector( '.kdna-flipbook-remove-icon' );
			var picker = row.querySelector( '.kdna-flipbook-iconpicker' );

			if ( preview ) {
				preview.innerHTML = previewHtml;
				preview.style.display = '';
			}
			if ( toggle ) {
				toggle.textContent = t( 'changeIcon', 'Change icon' );
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
			if ( removeButton ) {
				removeButton.style.display = '';
			}
			if ( picker ) {
				picker.hidden = true;
			}
		}

		/**
		 * Choose one of the built-in icons.
		 *
		 * @param {HTMLElement} row    The row element.
		 * @param {HTMLElement} button The clicked icon button.
		 */
		function pickBuiltinIcon( row, button ) {
			var idInput = row.querySelector( '.kdna-flipbook-input-icon-id' );
			var keyInput = row.querySelector( '.kdna-flipbook-input-icon-key' );
			var key = button.getAttribute( 'data-icon' );

			if ( idInput ) {
				idInput.value = '';
			}
			if ( keyInput ) {
				keyInput.value = key;
			}

			// Highlight the selected icon within this row.
			row.querySelectorAll( '.kdna-flipbook-iconpick' ).forEach( function ( item ) {
				item.classList.toggle( 'is-selected', item === button );
			} );

			markIconChosen( row, button.innerHTML );
		}

		/**
		 * Open the media uploader to choose an SVG or image icon for a row.
		 *
		 * @param {HTMLElement} row The row element.
		 */
		function chooseIcon( row ) {
			var frame = window.wp.media( {
				title: t( 'selectIcon', 'Select or upload an icon' ),
				button: { text: t( 'useIcon', 'Use this icon' ) },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var idInput = row.querySelector( '.kdna-flipbook-input-icon-id' );
				var keyInput = row.querySelector( '.kdna-flipbook-input-icon-key' );
				var url = attachment.url;

				if ( attachment.sizes && attachment.sizes.thumbnail ) {
					url = attachment.sizes.thumbnail.url;
				}

				if ( idInput ) {
					idInput.value = attachment.id;
				}
				if ( keyInput ) {
					keyInput.value = '';
				}

				// Uploaded icon wins, so clear any built-in selection.
				row.querySelectorAll( '.kdna-flipbook-iconpick.is-selected' ).forEach( function ( item ) {
					item.classList.remove( 'is-selected' );
				} );

				markIconChosen( row, '<img src="' + url + '" alt="" />' );
			} );

			frame.open();
		}

		/**
		 * Clear the chosen icon for a row.
		 *
		 * @param {HTMLElement} row The row element.
		 */
		function removeIcon( row ) {
			var idInput = row.querySelector( '.kdna-flipbook-input-icon-id' );
			var keyInput = row.querySelector( '.kdna-flipbook-input-icon-key' );
			var preview = row.querySelector( '.kdna-flipbook-icon-preview' );
			var toggle = row.querySelector( '.kdna-flipbook-toggle-iconpicker' );
			var removeButton = row.querySelector( '.kdna-flipbook-remove-icon' );

			if ( idInput ) {
				idInput.value = '';
			}
			if ( keyInput ) {
				keyInput.value = '';
			}
			if ( preview ) {
				preview.innerHTML = '';
				preview.style.display = 'none';
			}
			if ( toggle ) {
				toggle.textContent = t( 'chooseIcon', 'Choose icon' );
			}
			if ( removeButton ) {
				removeButton.style.display = 'none';
			}
			row.querySelectorAll( '.kdna-flipbook-iconpick.is-selected' ).forEach( function ( item ) {
				item.classList.remove( 'is-selected' );
			} );
		}

		// Add row.
		if ( addButton ) {
			addButton.addEventListener( 'click', addRow );
		}

		// Delegate the per-row button clicks so cloned rows work too.
		list.addEventListener( 'click', function ( event ) {
			var target = event.target.closest( 'button' );
			if ( ! target ) {
				return;
			}
			var row = target.closest( '.kdna-flipbook-row' );
			if ( ! row ) {
				return;
			}

			if ( target.classList.contains( 'kdna-flipbook-row__remove' ) ) {
				event.preventDefault();
				removeRow( row );
			} else if ( target.classList.contains( 'kdna-flipbook-choose-pdf' ) ) {
				event.preventDefault();
				choosePdf( row );
			} else if ( target.classList.contains( 'kdna-flipbook-remove-pdf' ) ) {
				event.preventDefault();
				removePdf( row );
			} else if ( target.classList.contains( 'kdna-flipbook-toggle-iconpicker' ) ) {
				event.preventDefault();
				toggleIconPicker( row, target );
			} else if ( target.classList.contains( 'kdna-flipbook-iconpick' ) ) {
				event.preventDefault();
				pickBuiltinIcon( row, target );
			} else if ( target.classList.contains( 'kdna-flipbook-choose-icon' ) ) {
				event.preventDefault();
				chooseIcon( row );
			} else if ( target.classList.contains( 'kdna-flipbook-remove-icon' ) ) {
				event.preventDefault();
				removeIcon( row );
			}
		} );

		// Drag to reorder with the bundled jquery-ui-sortable.
		if ( window.jQuery && window.jQuery.fn.sortable ) {
			window.jQuery( list ).sortable( {
				handle: '.kdna-flipbook-row__handle',
				items: '> .kdna-flipbook-row',
				axis: 'y',
				cursor: 'grabbing',
				placeholder: 'kdna-flipbook-row--placeholder',
				forcePlaceholderSize: true,
				update: function () {
					updateSortOrder();
				}
			} );
		}

		// Make sure the sort order is correct on first load.
		updateSortOrder();
		refreshEmptyState();
	} );
} )();
