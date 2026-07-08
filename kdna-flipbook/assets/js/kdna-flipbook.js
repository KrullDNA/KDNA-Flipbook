/**
 * KDNA PDF Flipbook: front-end viewer.
 *
 * Renders a PDF with PDF.js and presents it with StPageFlip. Desktop shows a
 * two-page spread with the first page alone as a cover. Narrow screens show a
 * single page with swipe. Pages render on demand, with a spinner while a page is
 * being drawn.
 *
 * A sidebar lists every flipbook of the entry. Clicking one loads it into the
 * viewer without a full page reload.
 *
 * Initialises on both DOM ready and the kdna:content-added event so it works when
 * content is injected dynamically.
 */
( function () {
	'use strict';

	var settings = window.kdnaFlipbook || {};
	var i18n = settings.i18n || {};

	// Point PDF.js at its bundled worker.
	if ( window.pdfjsLib && settings.workerSrc ) {
		window.pdfjsLib.GlobalWorkerOptions.workerSrc = settings.workerSrc;
	}

	/**
	 * Single viewer instance, managing a set of flipbooks.
	 *
	 * @param {HTMLElement} root The .kdna-flipbook container.
	 * @constructor
	 */
	function KdnaFlipbookViewer( root ) {
		this.root = root;
		this.stage = root.querySelector( '.kdna-flipbook__stage' );
		this.book = root.querySelector( '.kdna-flipbook__book' );
		this.overlay = root.querySelector( '.kdna-flipbook__overlay' );
		this.message = root.querySelector( '.kdna-flipbook__message' );
		this.items = Array.prototype.slice.call( root.querySelectorAll( '.kdna-flipbook__item' ) );

		this.pdf = null;
		this.numPages = 0;
		this.pageEls = [];
		this.rendered = {};
		this.pageFlip = null;
		this.pageRatio = 1.414; // Height over width, defaults to A4 portrait.
		this.basePageWidth = 480;
		this.activeIndex = -1;
		this.loadToken = 0;
	}

	/**
	 * Wire up the sidebar and load the active flipbook.
	 */
	KdnaFlipbookViewer.prototype.init = function () {
		if ( ! window.pdfjsLib || ! window.St || ! window.St.PageFlip ) {
			this.showError();
			return;
		}

		var self = this;

		// Bind the sidebar items to switch flipbook.
		this.items.forEach( function ( item, index ) {
			item.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				self.loadFlipbook( index );
			} );
		} );

		this.loadFlipbook( this.startIndex() );
	};

	/**
	 * Work out which flipbook should open first.
	 *
	 * @return {number} A zero-based flipbook index.
	 */
	KdnaFlipbookViewer.prototype.startIndex = function () {
		for ( var i = 0; i < this.items.length; i++ ) {
			if ( this.items[ i ].classList.contains( 'is-active' ) ) {
				return i;
			}
		}
		return 0;
	};

	/**
	 * Return the PDF URL for a flipbook index.
	 *
	 * Falls back to the root data attribute so a single-PDF container still works.
	 *
	 * @param {number} index Flipbook index.
	 * @return {string}
	 */
	KdnaFlipbookViewer.prototype.pdfUrlFor = function ( index ) {
		if ( this.items.length && this.items[ index ] ) {
			return this.items[ index ].getAttribute( 'data-pdf-url' ) || '';
		}
		return this.root.getAttribute( 'data-pdf-url' ) || '';
	};

	/**
	 * Load a flipbook into the viewer, replacing whatever is showing.
	 *
	 * @param {number} index Flipbook index.
	 */
	KdnaFlipbookViewer.prototype.loadFlipbook = function ( index ) {
		if ( index === this.activeIndex ) {
			return;
		}

		var url = this.pdfUrlFor( index );
		if ( ! url ) {
			this.showError();
			return;
		}

		this.activeIndex = index;
		this.setActiveItem( index );

		// Guard against overlapping loads when a reader switches quickly.
		var token = ++this.loadToken;
		var self = this;

		this.hideError();
		this.showOverlay( true );
		this.teardown();

		window.pdfjsLib.getDocument( { url: url } ).promise
			.then( function ( pdf ) {
				if ( token !== self.loadToken ) {
					return null;
				}
				self.pdf = pdf;
				self.numPages = pdf.numPages;
				return pdf.getPage( 1 );
			} )
			.then( function ( page ) {
				if ( ! page || token !== self.loadToken ) {
					return null;
				}
				var viewport = page.getViewport( { scale: 1 } );
				self.pageRatio = viewport.height / viewport.width;
				self.buildPages();
				self.initFlip();
				return self.renderAround( self.currentIndex() );
			} )
			.then( function () {
				if ( token !== self.loadToken ) {
					return;
				}
				self.showOverlay( false );
			} )
			.catch( function ( error ) {
				if ( token !== self.loadToken ) {
					return;
				}
				self.showError();
				if ( window.console && window.console.error ) {
					window.console.error( 'KDNA Flipbook:', error );
				}
			} );
	};

	/**
	 * Highlight the active sidebar item.
	 *
	 * @param {number} index Active index.
	 */
	KdnaFlipbookViewer.prototype.setActiveItem = function ( index ) {
		this.items.forEach( function ( item, i ) {
			var active = i === index;
			item.classList.toggle( 'is-active', active );
			if ( active ) {
				item.setAttribute( 'aria-current', 'true' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
	};

	/**
	 * Tear down the current StPageFlip instance and page state.
	 */
	KdnaFlipbookViewer.prototype.teardown = function () {
		if ( this.pageFlip && typeof this.pageFlip.destroy === 'function' ) {
			try {
				this.pageFlip.destroy();
			} catch ( e ) {
				// StPageFlip can throw if it never fully initialised, ignore.
			}
		}
		this.pageFlip = null;
		this.pdf = null;
		this.numPages = 0;
		this.pageEls = [];
		this.rendered = {};
		if ( this.book ) {
			this.book.innerHTML = '';
		}
	};

	/**
	 * Create a page element per PDF page, each with its own loading spinner.
	 */
	KdnaFlipbookViewer.prototype.buildPages = function () {
		this.book.innerHTML = '';
		this.pageEls = [];

		for ( var i = 1; i <= this.numPages; i++ ) {
			var pageEl = document.createElement( 'div' );
			pageEl.className = 'kdna-flipbook__page';
			pageEl.setAttribute( 'data-page', String( i ) );
			pageEl.setAttribute( 'data-density', i === 1 || i === this.numPages ? 'hard' : 'soft' );

			var spinner = document.createElement( 'span' );
			spinner.className = 'kdna-flipbook__page-spinner';
			pageEl.appendChild( spinner );

			this.book.appendChild( pageEl );
			this.pageEls.push( pageEl );
		}
	};

	/**
	 * Initialise StPageFlip over the page elements.
	 */
	KdnaFlipbookViewer.prototype.initFlip = function () {
		var self = this;
		var stageWidth = this.stage.clientWidth || 960;
		var pageWidth = Math.min( 560, Math.max( 260, Math.floor( stageWidth / 2 ) ) );
		var pageHeight = Math.round( pageWidth * this.pageRatio );

		this.basePageWidth = pageWidth;

		this.pageFlip = new window.St.PageFlip( this.book, {
			width: pageWidth,
			height: pageHeight,
			size: 'stretch',
			minWidth: 220,
			maxWidth: 2000,
			minHeight: 300,
			maxHeight: 2800,
			showCover: true,
			usePortrait: true,
			mobileScrollSupport: true,
			maxShadowOpacity: 0.5,
			drawShadow: true,
			flippingTime: 700,
			useMouseEvents: true,
			swipeDistance: 30,
			autoSize: true
		} );

		this.pageFlip.loadFromHTML( this.book.querySelectorAll( '.kdna-flipbook__page' ) );

		this.pageFlip.on( 'flip', function ( event ) {
			self.renderAround( event.data );
		} );

		// Re-render the visible window when the layout switches orientation.
		this.pageFlip.on( 'changeOrientation', function () {
			self.renderAround( self.currentIndex() );
		} );
	};

	/**
	 * Current zero-based page index reported by StPageFlip.
	 *
	 * @return {number}
	 */
	KdnaFlipbookViewer.prototype.currentIndex = function () {
		if ( this.pageFlip && typeof this.pageFlip.getCurrentPageIndex === 'function' ) {
			return this.pageFlip.getCurrentPageIndex();
		}
		return 0;
	};

	/**
	 * Render the pages around a zero-based index, so turns feel instant.
	 *
	 * @param {number} index Zero-based current page index.
	 * @return {Promise}
	 */
	KdnaFlipbookViewer.prototype.renderAround = function ( index ) {
		var current = index + 1;
		var jobs = [];

		for ( var page = current - 2; page <= current + 3; page++ ) {
			if ( page >= 1 && page <= this.numPages ) {
				jobs.push( this.renderPage( page ) );
			}
		}

		return Promise.all( jobs );
	};

	/**
	 * Render a single PDF page to a canvas inside its page element.
	 *
	 * @param {number} pageNum One-based page number.
	 * @return {Promise}
	 */
	KdnaFlipbookViewer.prototype.renderPage = function ( pageNum ) {
		var self = this;

		if ( pageNum < 1 || pageNum > this.numPages ) {
			return Promise.resolve();
		}

		if ( this.rendered[ pageNum ] ) {
			return this.rendered[ pageNum ];
		}

		var pageEl = this.pageEls[ pageNum - 1 ];
		if ( ! pageEl ) {
			return Promise.resolve();
		}

		var pdf = this.pdf;

		var promise = pdf.getPage( pageNum ).then( function ( page ) {
			// Bail if the flipbook was swapped out while this was pending.
			if ( self.pdf !== pdf ) {
				return;
			}

			var dpr = window.devicePixelRatio || 1;
			var targetWidth = pageEl.clientWidth || self.basePageWidth;
			var baseViewport = page.getViewport( { scale: 1 } );
			var scale = ( targetWidth * dpr ) / baseViewport.width;
			var viewport = page.getViewport( { scale: scale } );

			var canvas = document.createElement( 'canvas' );
			canvas.className = 'kdna-flipbook__canvas';
			canvas.width = Math.floor( viewport.width );
			canvas.height = Math.floor( viewport.height );

			var context = canvas.getContext( '2d' );

			return page.render( { canvasContext: context, viewport: viewport } ).promise.then( function () {
				if ( self.pdf !== pdf ) {
					return;
				}
				var spinner = pageEl.querySelector( '.kdna-flipbook__page-spinner' );
				if ( spinner ) {
					spinner.parentNode.removeChild( spinner );
				}
				var old = pageEl.querySelector( '.kdna-flipbook__canvas' );
				if ( old ) {
					old.parentNode.removeChild( old );
				}
				pageEl.appendChild( canvas );
			} );
		} ).catch( function ( error ) {
			// Allow a later retry if a render failed.
			self.rendered[ pageNum ] = null;
			if ( window.console && window.console.error ) {
				window.console.error( 'KDNA Flipbook page ' + pageNum + ':', error );
			}
		} );

		this.rendered[ pageNum ] = promise;
		return promise;
	};

	/**
	 * Show or hide the loading overlay.
	 *
	 * @param {boolean} show Whether to show it.
	 */
	KdnaFlipbookViewer.prototype.showOverlay = function ( show ) {
		if ( ! this.overlay ) {
			return;
		}
		this.overlay.style.display = show ? '' : 'none';
		this.root.classList.toggle( 'is-loading', !! show );
	};

	/**
	 * Show the error message and hide the spinner.
	 */
	KdnaFlipbookViewer.prototype.showError = function () {
		this.showOverlay( false );
		if ( this.message ) {
			this.message.hidden = false;
			if ( i18n.error ) {
				this.message.textContent = i18n.error;
			}
		}
	};

	/**
	 * Hide the error message.
	 */
	KdnaFlipbookViewer.prototype.hideError = function () {
		if ( this.message ) {
			this.message.hidden = true;
		}
	};

	/**
	 * Initialise every viewer inside a scope that has not been started yet.
	 *
	 * @param {Document|HTMLElement} scope Optional scope to search within.
	 */
	function initAll( scope ) {
		var context = scope || document;
		var nodes = context.querySelectorAll( '.kdna-flipbook' );

		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( node.getAttribute( 'data-kdna-initialised' ) ) {
				return;
			}
			node.setAttribute( 'data-kdna-initialised', '1' );
			new KdnaFlipbookViewer( node ).init();
		} );
	}

	// Initialise on DOM ready.
	if ( 'loading' !== document.readyState ) {
		initAll();
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	}

	// Re-initialise when content is injected dynamically.
	document.addEventListener( 'kdna:content-added', function ( event ) {
		var scope = event && event.detail && event.detail.container ? event.detail.container : document;
		initAll( scope );
	} );

	// Expose a manual hook for later stages.
	window.kdnaFlipbookInit = initAll;
} )();
