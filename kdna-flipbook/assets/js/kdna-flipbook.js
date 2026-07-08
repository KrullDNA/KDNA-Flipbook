/**
 * KDNA PDF Flipbook: front-end viewer.
 *
 * Renders a PDF with PDF.js and presents it with StPageFlip. Desktop shows a
 * two-page spread with the first page alone as a cover. Narrow screens show a
 * single page with swipe. Pages render on demand, with a spinner while a page is
 * being drawn.
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
	 * Single viewer instance.
	 *
	 * @param {HTMLElement} root The .kdna-flipbook container.
	 * @constructor
	 */
	function KdnaFlipbookViewer( root ) {
		this.root = root;
		this.pdfUrl = root.getAttribute( 'data-pdf-url' );
		this.stage = root.querySelector( '.kdna-flipbook__stage' );
		this.book = root.querySelector( '.kdna-flipbook__book' );
		this.overlay = root.querySelector( '.kdna-flipbook__overlay' );
		this.message = root.querySelector( '.kdna-flipbook__message' );

		this.pdf = null;
		this.numPages = 0;
		this.pageEls = [];
		this.rendered = {};
		this.pageFlip = null;
		this.pageRatio = 1.414; // Height over width, defaults to A4 portrait.
		this.basePageWidth = 480;
	}

	/**
	 * Kick off loading and rendering.
	 */
	KdnaFlipbookViewer.prototype.init = function () {
		if ( ! this.pdfUrl || ! window.pdfjsLib || ! window.St || ! window.St.PageFlip ) {
			this.showError();
			return;
		}

		var self = this;
		this.showOverlay( true );

		window.pdfjsLib.getDocument( { url: this.pdfUrl } ).promise
			.then( function ( pdf ) {
				self.pdf = pdf;
				self.numPages = pdf.numPages;
				return pdf.getPage( 1 );
			} )
			.then( function ( page ) {
				var viewport = page.getViewport( { scale: 1 } );
				self.pageRatio = viewport.height / viewport.width;
				self.buildPages();
				self.initFlip();
				return self.renderAround( self.currentIndex() );
			} )
			.then( function () {
				self.showOverlay( false );
			} )
			.catch( function ( error ) {
				self.showError();
				if ( window.console && window.console.error ) {
					window.console.error( 'KDNA Flipbook:', error );
				}
			} );
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

		var promise = this.pdf.getPage( pageNum ).then( function ( page ) {
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
	 * Initialise every viewer inside a scope that has not been started yet.
	 *
	 * @param {Document|HTMLElement} scope Optional scope to search within.
	 */
	function initAll( scope ) {
		var context = scope || document;
		var nodes = context.querySelectorAll( '.kdna-flipbook[data-pdf-url]' );

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
