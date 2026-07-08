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

	// Only the first viewer that wants deep-linking manages the address bar.
	var deepLinkClaimed = false;

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
		this.viewer = root.querySelector( '.kdna-flipbook__viewer' );
		this.stage = root.querySelector( '.kdna-flipbook__stage' );
		this.book = root.querySelector( '.kdna-flipbook__book' );
		this.overlay = root.querySelector( '.kdna-flipbook__overlay' );
		this.message = root.querySelector( '.kdna-flipbook__message' );
		this.items = Array.prototype.slice.call( root.querySelectorAll( '.kdna-flipbook__item' ) );
		this.toolbar = root.querySelector( '.kdna-flipbook__toolbar' );
		this.zoomLayer = root.querySelector( '.kdna-flipbook__zoom' );
		this.zoomCanvas = root.querySelector( '.kdna-flipbook__zoom-canvas' );
		this.zoomLevelEl = root.querySelector( '.kdna-flipbook__zoom-level' );
		this.pageCountEl = root.querySelector( '.kdna-flipbook__page-count' );
		this.thumbsPanel = root.querySelector( '.kdna-flipbook__thumbs' );
		this.thumbsTrack = root.querySelector( '.kdna-flipbook__thumbs-track' );
		this.tocPanel = root.querySelector( '.kdna-flipbook__toc' );
		this.tocBody = root.querySelector( '.kdna-flipbook__toc-body' );
		this.toast = root.querySelector( '.kdna-flipbook__toast' );

		this.config = this.parseConfig();
		this.controls = this.config.controls || {};
		this.behaviour = this.config.behaviour || 'persistent';

		this.pdf = null;
		this.numPages = 0;
		this.pageEls = [];
		this.rendered = {};
		this.pageFlip = null;
		this.pageRatio = 1.414; // Height over width, defaults to A4 portrait.
		this.basePageWidth = 480;
		this.activeIndex = -1;
		this.loadToken = 0;

		// Zoom and pan state.
		this.zoom = 1;
		this.minZoom = 1;
		this.maxZoom = 5;
		this.zoomStep = 0.5;
		this.zoomActive = false;
		this.pan = { x: 0, y: 0 };
		this.liveScale = 1;
		this.zoomPageNum = 1;
		this.zoomRenderToken = 0;
		this.zoomCanvasCss = null;
		this.drag = null;
		this.touch = null;
		this.pinchTarget = null;

		// Toolbar control state.
		this.soundOn = false;
		this.audioCtx = null;
		this.thumbsBuilt = false;
		this.tocBuilt = false;
		this.thumbObserver = null;
		this.ownsDeepLink = false;
		this.startPage = 1;
	}

	/**
	 * Read the per-instance config from the root data attribute.
	 *
	 * @return {Object}
	 */
	KdnaFlipbookViewer.prototype.parseConfig = function () {
		var fallback = { controls: {}, behaviour: 'persistent', start: { flipbook: 0, page: 1 } };
		var raw = this.root.getAttribute( 'data-kdna-config' );
		if ( ! raw ) {
			return fallback;
		}
		try {
			var parsed = JSON.parse( raw );
			return {
				controls: parsed.controls || {},
				behaviour: parsed.behaviour || 'persistent',
				start: parsed.start || { flipbook: 0, page: 1 }
			};
		} catch ( e ) {
			return fallback;
		}
	};

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

		this.setupControls();
		this.setupZoom();
		this.setupFullscreen();
		this.setupToolbarBehaviour();

		// Claim deep-linking for the first viewer that wants it on the page.
		if ( this.controls.deeplink && ! deepLinkClaimed ) {
			this.ownsDeepLink = true;
			deepLinkClaimed = true;
		}

		var target = this.startTarget();
		this.loadFlipbook( target.flipbook, target.page );
	};

	/**
	 * Work out the flipbook and page to open first.
	 *
	 * Prefers the deep-link URL when this viewer owns it, otherwise the config.
	 *
	 * @return {Object}
	 */
	KdnaFlipbookViewer.prototype.startTarget = function () {
		var flipbook = this.startIndex();
		var page = this.config.start && this.config.start.page ? this.config.start.page : 1;

		if ( this.ownsDeepLink ) {
			var params = new URLSearchParams( window.location.search );
			if ( params.has( 'kdnafb' ) ) {
				var fb = parseInt( params.get( 'kdnafb' ), 10 );
				if ( ! isNaN( fb ) && fb >= 0 && ( ! this.items.length || fb < this.items.length ) ) {
					flipbook = fb;
				}
			}
			if ( params.has( 'kdnapg' ) ) {
				var pg = parseInt( params.get( 'kdnapg' ), 10 );
				if ( ! isNaN( pg ) && pg >= 1 ) {
					page = pg;
				}
			}
		}

		return { flipbook: flipbook, page: page };
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
	KdnaFlipbookViewer.prototype.loadFlipbook = function ( index, startPage ) {
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
		this.startPage = startPage && startPage > 1 ? startPage : 1;

		// Guard against overlapping loads when a reader switches quickly.
		var token = ++this.loadToken;
		var self = this;

		this.resetZoom();
		this.closePanels();
		this.resetPanels();
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

				// Jump to the requested start page without an animation.
				if ( self.startPage > 1 && self.pageFlip && typeof self.pageFlip.turnToPage === 'function' ) {
					self.pageFlip.turnToPage( clamp( self.startPage - 1, 0, self.numPages - 1 ) );
				}

				return self.renderAround( self.currentIndex() );
			} )
			.then( function () {
				if ( token !== self.loadToken ) {
					return;
				}
				self.showOverlay( false );
				self.onFlipbookReady();
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
			self.onFlip( event.data );
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

	/* -------------------------------------------------------------------------
	 * Zoom, pan and fullscreen.
	 * ---------------------------------------------------------------------- */

	/**
	 * Keep a number within a range.
	 *
	 * @param {number} value Value.
	 * @param {number} min   Minimum.
	 * @param {number} max   Maximum.
	 * @return {number}
	 */
	function clamp( value, min, max ) {
		return Math.min( max, Math.max( min, value ) );
	}

	/**
	 * Bind the toolbar buttons.
	 */
	KdnaFlipbookViewer.prototype.setupControls = function () {
		if ( ! this.toolbar ) {
			return;
		}

		var self = this;
		this.toolbar.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button[data-action]' );
			if ( ! button ) {
				return;
			}
			var action = button.getAttribute( 'data-action' );

			if ( 'zoom-in' === action ) {
				self.zoomBy( self.zoomStep );
			} else if ( 'zoom-out' === action ) {
				self.zoomBy( -self.zoomStep );
			} else if ( 'fullscreen' === action ) {
				self.toggleFullscreen();
			} else if ( 'prev' === action ) {
				self.flipPrev();
			} else if ( 'next' === action ) {
				self.flipNext();
			} else if ( 'thumbnails' === action ) {
				self.toggleThumbs();
			} else if ( 'toc' === action ) {
				self.toggleToc();
			} else if ( 'download' === action ) {
				self.download();
			} else if ( 'share' === action ) {
				self.share();
			} else if ( 'sound' === action ) {
				self.toggleSound();
			}
		} );

		this.updateZoomUI();
	};

	/**
	 * One-based current page number, clamped to the document.
	 *
	 * @return {number}
	 */
	KdnaFlipbookViewer.prototype.currentPageNum = function () {
		return clamp( this.currentIndex() + 1, 1, this.numPages || 1 );
	};

	/**
	 * Change the zoom by a delta, centred on the viewer.
	 *
	 * @param {number} delta Zoom delta.
	 */
	KdnaFlipbookViewer.prototype.zoomBy = function ( delta ) {
		this.setZoom( this.zoom + delta, { x: 0, y: 0 } );
	};

	/**
	 * Set the zoom level, keeping a focal point steady.
	 *
	 * @param {number} newZoom Target zoom.
	 * @param {Object} focal   Point relative to the viewer centre, in pixels.
	 */
	KdnaFlipbookViewer.prototype.setZoom = function ( newZoom, focal ) {
		newZoom = clamp( newZoom, this.minZoom, this.maxZoom );
		focal = focal || { x: 0, y: 0 };

		if ( newZoom <= this.minZoom ) {
			this.resetZoom();
			return;
		}

		var prev = this.zoom;
		if ( ! this.zoomActive ) {
			this.activateZoom();
			prev = 1;
		}

		var f = newZoom / prev;
		this.pan.x = focal.x * ( 1 - f ) + f * this.pan.x;
		this.pan.y = focal.y * ( 1 - f ) + f * this.pan.y;
		this.zoom = newZoom;

		this.renderZoomPage();
		this.updateZoomUI();
	};

	/**
	 * Enter zoom mode over the current page.
	 */
	KdnaFlipbookViewer.prototype.activateZoom = function () {
		this.zoomActive = true;
		this.zoomPageNum = this.currentPageNum();
		this.pan = { x: 0, y: 0 };
		this.liveScale = 1;
		if ( this.zoomLayer ) {
			this.zoomLayer.hidden = false;
		}
		this.root.classList.add( 'is-zoomed' );
	};

	/**
	 * Leave zoom mode and return to the flipbook.
	 */
	KdnaFlipbookViewer.prototype.resetZoom = function () {
		this.zoomActive = false;
		this.zoom = 1;
		this.pan = { x: 0, y: 0 };
		this.liveScale = 1;
		this.pinchTarget = null;
		if ( this.zoomLayer ) {
			this.zoomLayer.hidden = true;
		}
		this.root.classList.remove( 'is-zoomed' );
		this.updateZoomUI();
	};

	/**
	 * Render the zoomed page crisply at the current zoom.
	 */
	KdnaFlipbookViewer.prototype.renderZoomPage = function () {
		if ( ! this.pdf || ! this.zoomActive || ! this.zoomCanvas ) {
			return;
		}

		var self = this;
		var token = ++this.zoomRenderToken;
		var pageNum = this.zoomPageNum;
		var viewerWidth = this.viewer.clientWidth || 960;
		var maxBackingPixels = 4200;

		this.pdf.getPage( pageNum ).then( function ( page ) {
			if ( token !== self.zoomRenderToken ) {
				return;
			}

			var dpr = window.devicePixelRatio || 1;
			var base = page.getViewport( { scale: 1 } );
			var cssWidth = viewerWidth * self.zoom;
			var backingScale = ( cssWidth / base.width ) * dpr;
			var viewport = page.getViewport( { scale: backingScale } );

			// Cap the backing size so very deep zooms do not exhaust memory.
			if ( viewport.width > maxBackingPixels ) {
				backingScale = backingScale * ( maxBackingPixels / viewport.width );
				viewport = page.getViewport( { scale: backingScale } );
			}

			var canvas = self.zoomCanvas;
			canvas.width = Math.floor( viewport.width );
			canvas.height = Math.floor( viewport.height );

			var cssHeight = cssWidth * ( base.height / base.width );
			canvas.style.width = cssWidth + 'px';
			canvas.style.height = cssHeight + 'px';
			self.zoomCanvasCss = { w: cssWidth, h: cssHeight };

			var context = canvas.getContext( '2d' );

			return page.render( { canvasContext: context, viewport: viewport } ).promise.then( function () {
				if ( token !== self.zoomRenderToken ) {
					return;
				}
				self.liveScale = 1;
				self.applyTransform();
			} );
		} ).catch( function ( error ) {
			if ( window.console && window.console.error ) {
				window.console.error( 'KDNA Flipbook zoom:', error );
			}
		} );
	};

	/**
	 * Apply the current pan and live scale to the zoom canvas.
	 */
	KdnaFlipbookViewer.prototype.applyTransform = function () {
		this.clampPan();
		var transform = 'translate(-50%, -50%) translate(' + this.pan.x + 'px, ' + this.pan.y + 'px)';
		if ( 1 !== this.liveScale ) {
			transform += ' scale(' + this.liveScale + ')';
		}
		this.zoomCanvas.style.transform = transform;
	};

	/**
	 * Keep the pan within the bounds of the viewer so the page cannot be lost.
	 */
	KdnaFlipbookViewer.prototype.clampPan = function () {
		if ( ! this.zoomCanvasCss ) {
			return;
		}
		var scale = this.liveScale || 1;
		var canvasWidth = this.zoomCanvasCss.w * scale;
		var canvasHeight = this.zoomCanvasCss.h * scale;
		var viewerWidth = this.viewer.clientWidth;
		var viewerHeight = this.viewer.clientHeight;

		var maxX = Math.max( 0, ( canvasWidth - viewerWidth ) / 2 );
		var maxY = Math.max( 0, ( canvasHeight - viewerHeight ) / 2 );

		this.pan.x = clamp( this.pan.x, -maxX, maxX );
		this.pan.y = clamp( this.pan.y, -maxY, maxY );
	};

	/**
	 * Update the zoom readout and button states.
	 */
	KdnaFlipbookViewer.prototype.updateZoomUI = function () {
		if ( this.zoomLevelEl ) {
			this.zoomLevelEl.textContent = Math.round( this.zoom * 100 ) + '%';
		}
		var out = this.root.querySelector( '.kdna-flipbook__btn--zoom-out' );
		var into = this.root.querySelector( '.kdna-flipbook__btn--zoom-in' );
		if ( out ) {
			out.disabled = this.zoom <= this.minZoom;
		}
		if ( into ) {
			into.disabled = this.zoom >= this.maxZoom;
		}
	};

	/**
	 * A point relative to the viewer centre, from a client coordinate.
	 *
	 * @param {number} clientX Client X.
	 * @param {number} clientY Client Y.
	 * @return {Object}
	 */
	KdnaFlipbookViewer.prototype.focalFromPoint = function ( clientX, clientY ) {
		var rect = this.viewer.getBoundingClientRect();
		return {
			x: clientX - rect.left - rect.width / 2,
			y: clientY - rect.top - rect.height / 2
		};
	};

	/**
	 * Distance between two touches.
	 *
	 * @param {TouchList} touches Touches.
	 * @return {number}
	 */
	function touchDistance( touches ) {
		var dx = touches[ 0 ].clientX - touches[ 1 ].clientX;
		var dy = touches[ 0 ].clientY - touches[ 1 ].clientY;
		return Math.sqrt( dx * dx + dy * dy );
	}

	/**
	 * Midpoint of two touches.
	 *
	 * @param {TouchList} touches Touches.
	 * @return {Object}
	 */
	function touchMidpoint( touches ) {
		return {
			x: ( touches[ 0 ].clientX + touches[ 1 ].clientX ) / 2,
			y: ( touches[ 0 ].clientY + touches[ 1 ].clientY ) / 2
		};
	}

	/**
	 * Bind wheel zoom, drag pan and pinch zoom.
	 */
	KdnaFlipbookViewer.prototype.setupZoom = function () {
		var self = this;

		if ( this.viewer ) {
			this.viewer.addEventListener( 'wheel', function ( event ) {
				var zoomingIn = event.deltaY < 0;

				// At fit, let the page scroll normally when scrolling down.
				if ( ! self.zoomActive && ! zoomingIn ) {
					return;
				}

				event.preventDefault();
				var focal = self.focalFromPoint( event.clientX, event.clientY );
				self.setZoom( self.zoom + ( zoomingIn ? self.zoomStep : -self.zoomStep ), focal );
			}, { passive: false } );
		}

		if ( ! this.zoomLayer ) {
			return;
		}

		// Desktop drag to pan.
		this.zoomLayer.addEventListener( 'mousedown', function ( event ) {
			if ( ! self.zoomActive ) {
				return;
			}
			event.preventDefault();
			self.drag = { x: event.clientX, y: event.clientY, panX: self.pan.x, panY: self.pan.y };
			self.zoomLayer.classList.add( 'is-grabbing' );
		} );

		window.addEventListener( 'mousemove', function ( event ) {
			if ( ! self.drag ) {
				return;
			}
			self.pan.x = self.drag.panX + ( event.clientX - self.drag.x );
			self.pan.y = self.drag.panY + ( event.clientY - self.drag.y );
			self.applyTransform();
		} );

		window.addEventListener( 'mouseup', function () {
			if ( self.drag ) {
				self.drag = null;
				self.zoomLayer.classList.remove( 'is-grabbing' );
			}
		} );

		// Touch: one finger pans, two fingers pinch to zoom.
		this.zoomLayer.addEventListener( 'touchstart', function ( event ) {
			if ( ! self.zoomActive ) {
				return;
			}
			self.beginTouch( event );
		}, { passive: false } );

		this.zoomLayer.addEventListener( 'touchmove', function ( event ) {
			if ( ! self.zoomActive || ! self.touch ) {
				return;
			}
			self.moveTouch( event );
		}, { passive: false } );

		this.zoomLayer.addEventListener( 'touchend', function ( event ) {
			self.endTouch( event );
		} );

		this.zoomLayer.addEventListener( 'touchcancel', function ( event ) {
			self.endTouch( event );
		} );
	};

	/**
	 * Start a touch gesture.
	 *
	 * @param {TouchEvent} event Touch event.
	 */
	KdnaFlipbookViewer.prototype.beginTouch = function ( event ) {
		if ( 2 === event.touches.length ) {
			var mid = touchMidpoint( event.touches );
			this.touch = {
				mode: 'pinch',
				startDist: touchDistance( event.touches ),
				startZoom: this.zoom,
				focal: this.focalFromPoint( mid.x, mid.y ),
				panX: this.pan.x,
				panY: this.pan.y
			};
		} else if ( 1 === event.touches.length ) {
			this.touch = {
				mode: 'pan',
				x: event.touches[ 0 ].clientX,
				y: event.touches[ 0 ].clientY,
				panX: this.pan.x,
				panY: this.pan.y
			};
		}
	};

	/**
	 * Update a touch gesture.
	 *
	 * @param {TouchEvent} event Touch event.
	 */
	KdnaFlipbookViewer.prototype.moveTouch = function ( event ) {
		if ( 'pan' === this.touch.mode && 1 === event.touches.length ) {
			event.preventDefault();
			this.pan.x = this.touch.panX + ( event.touches[ 0 ].clientX - this.touch.x );
			this.pan.y = this.touch.panY + ( event.touches[ 0 ].clientY - this.touch.y );
			this.applyTransform();
		} else if ( 'pinch' === this.touch.mode && 2 === event.touches.length ) {
			event.preventDefault();
			var ratio = touchDistance( event.touches ) / this.touch.startDist;
			var target = clamp( this.touch.startZoom * ratio, this.minZoom, this.maxZoom );
			this.pinchTarget = target;

			// Scale the already-rendered canvas live, then re-render crisp on end.
			this.liveScale = target / this.zoom;
			var f = target / this.touch.startZoom;
			this.pan.x = this.touch.focal.x * ( 1 - f ) + f * this.touch.panX;
			this.pan.y = this.touch.focal.y * ( 1 - f ) + f * this.touch.panY;
			this.applyTransform();
		}
	};

	/**
	 * Finish a touch gesture.
	 *
	 * @param {TouchEvent} event Touch event.
	 */
	KdnaFlipbookViewer.prototype.endTouch = function ( event ) {
		if ( ! this.touch ) {
			return;
		}

		if ( 'pinch' === this.touch.mode && event.touches.length < 2 ) {
			var finalZoom = this.pinchTarget || this.zoom;
			this.pinchTarget = null;
			this.touch = null;

			if ( finalZoom <= this.minZoom + 0.01 ) {
				this.resetZoom();
			} else {
				this.zoom = finalZoom;
				this.liveScale = 1;
				this.renderZoomPage();
				this.updateZoomUI();
			}

			// If one finger remains, carry on with a pan.
			if ( 1 === event.touches.length && this.zoomActive ) {
				this.touch = {
					mode: 'pan',
					x: event.touches[ 0 ].clientX,
					y: event.touches[ 0 ].clientY,
					panX: this.pan.x,
					panY: this.pan.y
				};
			}
			return;
		}

		if ( 0 === event.touches.length ) {
			this.touch = null;
		}
	};

	/**
	 * Set up the fullscreen control and state changes.
	 */
	KdnaFlipbookViewer.prototype.setupFullscreen = function () {
		var self = this;

		var handler = function () {
			self.onFullscreenChange();
		};

		document.addEventListener( 'fullscreenchange', handler );
		document.addEventListener( 'webkitfullscreenchange', handler );
	};

	/**
	 * The element used for fullscreen.
	 *
	 * @return {HTMLElement}
	 */
	KdnaFlipbookViewer.prototype.fullscreenTarget = function () {
		return this.viewer || this.root;
	};

	/**
	 * Is this viewer currently fullscreen.
	 *
	 * @return {boolean}
	 */
	KdnaFlipbookViewer.prototype.isFullscreen = function () {
		var current = document.fullscreenElement || document.webkitFullscreenElement;
		return current === this.fullscreenTarget();
	};

	/**
	 * Toggle fullscreen for this viewer.
	 */
	KdnaFlipbookViewer.prototype.toggleFullscreen = function () {
		var target = this.fullscreenTarget();

		if ( ! this.isFullscreen() ) {
			var request = target.requestFullscreen || target.webkitRequestFullscreen;
			if ( request ) {
				request.call( target );
			}
		} else {
			var exit = document.exitFullscreen || document.webkitExitFullscreen;
			if ( exit ) {
				exit.call( document );
			}
		}
	};

	/**
	 * React to entering or leaving fullscreen.
	 */
	KdnaFlipbookViewer.prototype.onFullscreenChange = function () {
		var full = this.isFullscreen();
		this.root.classList.toggle( 'is-fullscreen', full );

		var button = this.root.querySelector( '.kdna-flipbook__btn--fullscreen' );
		if ( button ) {
			button.classList.toggle( 'is-active', full );
			var label = full ? ( i18n.exitFullscreen || 'Exit fullscreen' ) : ( i18n.fullscreen || 'Fullscreen' );
			button.setAttribute( 'aria-label', label );
			button.setAttribute( 'title', label );
		}

		// The viewer has resized, so re-render for the new size after it settles.
		var self = this;
		window.setTimeout( function () {
			if ( self.zoomActive ) {
				self.renderZoomPage();
			} else if ( self.pdf ) {
				self.rendered = {};
				self.renderAround( self.currentIndex() );
			}
		}, 250 );
	};

	/* -------------------------------------------------------------------------
	 * Toolbar controls: arrows, thumbnails, contents, download, share, sound,
	 * deep-linking and toolbar behaviour.
	 * ---------------------------------------------------------------------- */

	/**
	 * Things to do after a flipbook has finished loading.
	 */
	KdnaFlipbookViewer.prototype.onFlipbookReady = function () {
		this.updatePageCount();
		this.updateDeepLink();
		this.updateThumbActive();
	};

	/**
	 * Central handler for a page turn.
	 *
	 * @param {number} index Zero-based current page index.
	 */
	KdnaFlipbookViewer.prototype.onFlip = function ( index ) {
		this.renderAround( index );
		this.updatePageCount();
		this.updateDeepLink();
		this.updateThumbActive();
		if ( this.soundOn ) {
			this.playFlipSound();
		}
	};

	/**
	 * Turn to the previous page.
	 */
	KdnaFlipbookViewer.prototype.flipPrev = function () {
		if ( this.pageFlip && typeof this.pageFlip.flipPrev === 'function' ) {
			this.pageFlip.flipPrev();
		}
	};

	/**
	 * Turn to the next page.
	 */
	KdnaFlipbookViewer.prototype.flipNext = function () {
		if ( this.pageFlip && typeof this.pageFlip.flipNext === 'function' ) {
			this.pageFlip.flipNext();
		}
	};

	/**
	 * Go to a one-based page number.
	 *
	 * @param {number}  pageNum One-based page number.
	 * @param {boolean} animate Whether to animate the turn.
	 */
	KdnaFlipbookViewer.prototype.goToPage = function ( pageNum, animate ) {
		if ( ! this.pageFlip ) {
			return;
		}
		var index = clamp( pageNum - 1, 0, this.numPages - 1 );
		if ( animate && typeof this.pageFlip.flip === 'function' ) {
			this.pageFlip.flip( index );
		} else if ( typeof this.pageFlip.turnToPage === 'function' ) {
			this.pageFlip.turnToPage( index );
			this.onFlip( index );
		}
	};

	/**
	 * Update the "page x of y" readout.
	 */
	KdnaFlipbookViewer.prototype.updatePageCount = function () {
		if ( ! this.pageCountEl ) {
			return;
		}
		var current = this.currentPageNum();
		this.pageCountEl.textContent = current + ' / ' + ( this.numPages || 1 );
	};

	/* --- Thumbnails ------------------------------------------------------- */

	/**
	 * Toggle the thumbnails panel.
	 */
	KdnaFlipbookViewer.prototype.toggleThumbs = function () {
		if ( ! this.thumbsPanel ) {
			return;
		}
		var open = this.thumbsPanel.hidden;
		this.closePanels();
		if ( open ) {
			this.thumbsPanel.hidden = false;
			this.setButtonActive( 'thumbnails', true );
			this.buildThumbs();
			this.updateThumbActive();
		}
	};

	/**
	 * Build the thumbnails lazily, rendering each as it scrolls into view.
	 */
	KdnaFlipbookViewer.prototype.buildThumbs = function () {
		if ( this.thumbsBuilt || ! this.thumbsTrack || ! this.pdf ) {
			return;
		}
		this.thumbsBuilt = true;

		var self = this;
		this.thumbObserver = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					self.renderThumb( entry.target );
					self.thumbObserver.unobserve( entry.target );
				}
			} );
		}, { root: this.thumbsTrack, rootMargin: '200px' } );

		for ( var i = 1; i <= this.numPages; i++ ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'kdna-flipbook__thumb';
			button.setAttribute( 'data-page', String( i ) );
			button.setAttribute( 'aria-label', 'Page ' + i );

			var number = document.createElement( 'span' );
			number.className = 'kdna-flipbook__thumb-num';
			number.textContent = String( i );
			button.appendChild( number );

			button.addEventListener( 'click', ( function ( pageNum ) {
				return function () {
					self.goToPage( pageNum, true );
				};
			} )( i ) );

			this.thumbsTrack.appendChild( button );
			this.thumbObserver.observe( button );
		}
	};

	/**
	 * Render one thumbnail canvas.
	 *
	 * @param {HTMLElement} button The thumbnail button.
	 */
	KdnaFlipbookViewer.prototype.renderThumb = function ( button ) {
		var self = this;
		var pageNum = parseInt( button.getAttribute( 'data-page' ), 10 );
		var pdf = this.pdf;

		pdf.getPage( pageNum ).then( function ( page ) {
			if ( self.pdf !== pdf ) {
				return;
			}
			var target = 120;
			var base = page.getViewport( { scale: 1 } );
			var scale = target / base.width;
			var viewport = page.getViewport( { scale: scale } );

			var canvas = document.createElement( 'canvas' );
			canvas.className = 'kdna-flipbook__thumb-canvas';
			canvas.width = Math.floor( viewport.width );
			canvas.height = Math.floor( viewport.height );

			return page.render( { canvasContext: canvas.getContext( '2d' ), viewport: viewport } ).promise.then( function () {
				if ( self.pdf !== pdf ) {
					return;
				}
				button.insertBefore( canvas, button.firstChild );
			} );
		} ).catch( function () {} );
	};

	/**
	 * Highlight the thumbnail for the current page.
	 */
	KdnaFlipbookViewer.prototype.updateThumbActive = function () {
		if ( ! this.thumbsTrack ) {
			return;
		}
		var current = this.currentPageNum();
		var thumbs = this.thumbsTrack.querySelectorAll( '.kdna-flipbook__thumb' );
		Array.prototype.forEach.call( thumbs, function ( thumb ) {
			var page = parseInt( thumb.getAttribute( 'data-page' ), 10 );
			thumb.classList.toggle( 'is-active', page === current );
		} );
	};

	/* --- Table of contents ------------------------------------------------ */

	/**
	 * Toggle the table of contents panel.
	 */
	KdnaFlipbookViewer.prototype.toggleToc = function () {
		if ( ! this.tocPanel ) {
			return;
		}
		var open = this.tocPanel.hidden;
		this.closePanels();
		if ( open ) {
			this.tocPanel.hidden = false;
			this.setButtonActive( 'toc', true );
			this.buildToc();
		}
	};

	/**
	 * Build the contents list from the PDF outline.
	 */
	KdnaFlipbookViewer.prototype.buildToc = function () {
		if ( this.tocBuilt || ! this.tocBody || ! this.pdf ) {
			return;
		}
		this.tocBuilt = true;

		var self = this;
		this.pdf.getOutline().then( function ( outline ) {
			if ( ! outline || ! outline.length ) {
				self.tocBody.innerHTML = '<p class="kdna-flipbook__toc-empty">' + ( i18n.noContents || 'No contents in this document.' ) + '</p>';
				return;
			}
			var list = self.buildTocList( outline );
			self.tocBody.innerHTML = '';
			self.tocBody.appendChild( list );
		} ).catch( function () {
			self.tocBody.innerHTML = '<p class="kdna-flipbook__toc-empty">' + ( i18n.noContents || 'No contents in this document.' ) + '</p>';
		} );
	};

	/**
	 * Build a nested list element from outline items.
	 *
	 * @param {Array} items Outline items.
	 * @return {HTMLElement}
	 */
	KdnaFlipbookViewer.prototype.buildTocList = function ( items ) {
		var self = this;
		var ul = document.createElement( 'ul' );
		ul.className = 'kdna-flipbook__toc-list';

		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			var link = document.createElement( 'button' );
			link.type = 'button';
			link.className = 'kdna-flipbook__toc-link';
			link.textContent = item.title || '';
			link.addEventListener( 'click', function () {
				self.resolveDest( item.dest ).then( function ( pageIndex ) {
					if ( null !== pageIndex ) {
						self.goToPage( pageIndex + 1, true );
						self.closePanels();
					}
				} );
			} );
			li.appendChild( link );

			if ( item.items && item.items.length ) {
				li.appendChild( self.buildTocList( item.items ) );
			}
			ul.appendChild( li );
		} );

		return ul;
	};

	/**
	 * Resolve an outline destination to a zero-based page index.
	 *
	 * @param {Array|string} dest Destination.
	 * @return {Promise<number|null>}
	 */
	KdnaFlipbookViewer.prototype.resolveDest = function ( dest ) {
		var pdf = this.pdf;
		var lookup = 'string' === typeof dest ? pdf.getDestination( dest ) : Promise.resolve( dest );

		return Promise.resolve( lookup ).then( function ( resolved ) {
			if ( ! resolved || ! resolved.length ) {
				return null;
			}
			var ref = resolved[ 0 ];
			if ( ref && 'object' === typeof ref ) {
				return pdf.getPageIndex( ref ).then( function ( index ) {
					return index;
				} ).catch( function () {
					return null;
				} );
			}
			if ( 'number' === typeof ref ) {
				return ref;
			}
			return null;
		} ).catch( function () {
			return null;
		} );
	};

	/* --- Download and share ---------------------------------------------- */

	/**
	 * Download the current flipbook's original PDF.
	 */
	KdnaFlipbookViewer.prototype.download = function () {
		var url = this.pdfUrlFor( this.activeIndex );
		if ( ! url ) {
			return;
		}
		var link = document.createElement( 'a' );
		link.href = url;
		link.download = '';
		link.rel = 'noopener';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
	};

	/**
	 * Build a deep-link URL for the current flipbook and page.
	 *
	 * @return {string}
	 */
	KdnaFlipbookViewer.prototype.deepLinkUrl = function () {
		var url = new URL( window.location.href );
		url.searchParams.set( 'kdnafb', String( this.activeIndex ) );
		url.searchParams.set( 'kdnapg', String( this.currentPageNum() ) );
		return url.toString();
	};

	/**
	 * Share the current view, or copy the link if sharing is unavailable.
	 */
	KdnaFlipbookViewer.prototype.share = function () {
		var self = this;
		var url = this.controls.deeplink ? this.deepLinkUrl() : window.location.href;
		var title = document.title || '';

		if ( navigator.share ) {
			navigator.share( { title: title, url: url } ).catch( function () {} );
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( function () {
				self.showToast( i18n.linkCopied || 'Link copied' );
			} ).catch( function () {
				self.showToast( url );
			} );
			return;
		}

		self.showToast( url );
	};

	/**
	 * Update the address bar with the current flipbook and page.
	 */
	KdnaFlipbookViewer.prototype.updateDeepLink = function () {
		if ( ! this.ownsDeepLink || ! window.history || ! window.history.replaceState ) {
			return;
		}
		try {
			window.history.replaceState( null, '', this.deepLinkUrl() );
		} catch ( e ) {
			// Ignore environments that block replaceState.
		}
	};

	/* --- Flip sound ------------------------------------------------------- */

	/**
	 * Toggle the flip sound on or off.
	 */
	KdnaFlipbookViewer.prototype.toggleSound = function () {
		this.soundOn = ! this.soundOn;
		this.setButtonActive( 'sound', this.soundOn );

		var button = this.root.querySelector( '.kdna-flipbook__btn--sound' );
		if ( button ) {
			var label = this.soundOn ? ( i18n.soundOn || 'Flip sound on' ) : ( i18n.soundOff || 'Flip sound off' );
			button.setAttribute( 'aria-label', label );
			button.setAttribute( 'title', label );
			button.classList.toggle( 'is-muted', ! this.soundOn );
		}

		// Prime the audio context on this user gesture.
		if ( this.soundOn && ! this.audioCtx ) {
			var Ctx = window.AudioContext || window.webkitAudioContext;
			if ( Ctx ) {
				this.audioCtx = new Ctx();
			}
		}
	};

	/**
	 * Play a short synthesised page-flip sound. No audio file needed.
	 */
	KdnaFlipbookViewer.prototype.playFlipSound = function () {
		if ( ! this.audioCtx ) {
			return;
		}
		var ctx = this.audioCtx;
		if ( 'suspended' === ctx.state && ctx.resume ) {
			ctx.resume();
		}

		var duration = 0.18;
		var frames = Math.floor( ctx.sampleRate * duration );
		var buffer = ctx.createBuffer( 1, frames, ctx.sampleRate );
		var data = buffer.getChannelData( 0 );

		// Noise with a quick decay, shaped like a paper riffle.
		for ( var i = 0; i < frames; i++ ) {
			var envelope = Math.pow( 1 - i / frames, 2.2 );
			data[ i ] = ( Math.random() * 2 - 1 ) * envelope;
		}

		var source = ctx.createBufferSource();
		source.buffer = buffer;

		var filter = ctx.createBiquadFilter();
		filter.type = 'bandpass';
		filter.frequency.value = 2200;
		filter.Q.value = 0.7;

		var gain = ctx.createGain();
		gain.gain.value = 0.25;

		source.connect( filter );
		filter.connect( gain );
		gain.connect( ctx.destination );
		source.start();
	};

	/* --- Panels, toast and toolbar behaviour ------------------------------ */

	/**
	 * Close any open panels and clear their active buttons.
	 */
	KdnaFlipbookViewer.prototype.closePanels = function () {
		if ( this.thumbsPanel ) {
			this.thumbsPanel.hidden = true;
			this.setButtonActive( 'thumbnails', false );
		}
		if ( this.tocPanel ) {
			this.tocPanel.hidden = true;
			this.setButtonActive( 'toc', false );
		}
	};

	/**
	 * Reset panel content when the flipbook changes.
	 */
	KdnaFlipbookViewer.prototype.resetPanels = function () {
		this.thumbsBuilt = false;
		this.tocBuilt = false;
		if ( this.thumbObserver ) {
			this.thumbObserver.disconnect();
			this.thumbObserver = null;
		}
		if ( this.thumbsTrack ) {
			this.thumbsTrack.innerHTML = '';
		}
		if ( this.tocBody ) {
			this.tocBody.innerHTML = '';
		}
	};

	/**
	 * Toggle the active state of a toolbar button.
	 *
	 * @param {string}  action Button action name.
	 * @param {boolean} active Whether it is active.
	 */
	KdnaFlipbookViewer.prototype.setButtonActive = function ( action, active ) {
		var button = this.root.querySelector( '.kdna-flipbook__btn--' + action );
		if ( button ) {
			button.classList.toggle( 'is-active', !! active );
		}
	};

	/**
	 * Briefly show a status toast.
	 *
	 * @param {string} text Text to show.
	 */
	KdnaFlipbookViewer.prototype.showToast = function ( text ) {
		if ( ! this.toast ) {
			return;
		}
		var self = this;
		this.toast.textContent = text;
		this.toast.hidden = false;
		this.toast.classList.add( 'is-visible' );

		window.clearTimeout( this.toastTimer );
		this.toastTimer = window.setTimeout( function () {
			self.toast.classList.remove( 'is-visible' );
			self.toast.hidden = true;
		}, 2600 );
	};

	/**
	 * Fade the toolbar while reading, reappearing on activity.
	 */
	KdnaFlipbookViewer.prototype.setupToolbarBehaviour = function () {
		if ( 'fade' !== this.behaviour || ! this.viewer ) {
			return;
		}

		var self = this;
		var timer;

		var show = function () {
			self.root.classList.remove( 'is-idle' );
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				self.root.classList.add( 'is-idle' );
			}, 2600 );
		};

		this.viewer.addEventListener( 'mousemove', show );
		this.viewer.addEventListener( 'touchstart', show, { passive: true } );
		this.viewer.addEventListener( 'mouseleave', function () {
			window.clearTimeout( timer );
			self.root.classList.add( 'is-idle' );
		} );

		show();
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
			// The gate box also carries theme classes, so skip anything without a book.
			if ( ! node.querySelector( '.kdna-flipbook__book' ) ) {
				return;
			}
			if ( node.getAttribute( 'data-kdna-initialised' ) ) {
				return;
			}
			node.setAttribute( 'data-kdna-initialised', '1' );
			new KdnaFlipbookViewer( node ).init();
		} );

		initGates( context );
	}

	/**
	 * Bind the access code boxes within a scope.
	 *
	 * @param {Document|HTMLElement} context Scope to search within.
	 */
	function initGates( context ) {
		var gates = context.querySelectorAll( '.kdna-flipbook-gate' );

		Array.prototype.forEach.call( gates, function ( gate ) {
			if ( gate.getAttribute( 'data-kdna-initialised' ) ) {
				return;
			}
			gate.setAttribute( 'data-kdna-initialised', '1' );
			bindGate( gate );
		} );
	}

	/**
	 * Wire a single access code box to verify over admin-ajax.
	 *
	 * @param {HTMLElement} gate The gate container.
	 */
	function bindGate( gate ) {
		var form = gate.querySelector( '.kdna-flipbook-gate__form' );
		if ( ! form ) {
			return;
		}

		var input = form.querySelector( '.kdna-flipbook-gate__input' );
		var submit = form.querySelector( '.kdna-flipbook-gate__submit' );
		var errorEl = form.querySelector( '.kdna-flipbook-gate__error' );
		var nonceEl = form.querySelector( '.kdna-flipbook-gate__nonce' );
		var postId = gate.getAttribute( 'data-post-id' );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var code = input ? input.value : '';
			if ( ! code ) {
				return;
			}

			if ( errorEl ) {
				errorEl.hidden = true;
			}
			if ( submit ) {
				submit.disabled = true;
				submit.textContent = i18n.gateChecking || 'Checking';
			}

			var body = new URLSearchParams();
			body.set( 'action', 'kdna_flipbook_verify' );
			body.set( 'nonce', nonceEl ? nonceEl.value : '' );
			body.set( 'post_id', postId || '' );
			body.set( 'code', code );

			window.fetch( settings.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( result ) {
				if ( result && result.success ) {
					// The cookie is set, reload so the flipbooks render in context.
					window.location.reload();
					return;
				}
				showGateError( errorEl, result );
				resetGateSubmit( submit );
			} ).catch( function () {
				showGateError( errorEl, null );
				resetGateSubmit( submit );
			} );
		} );
	}

	/**
	 * Show an error in the gate box.
	 *
	 * @param {HTMLElement} errorEl Error element.
	 * @param {Object}      result  AJAX result, if any.
	 */
	function showGateError( errorEl, result ) {
		if ( ! errorEl ) {
			return;
		}
		var message = i18n.gateError || 'That code is not correct. Please try again.';
		if ( result && result.data && result.data.message ) {
			message = result.data.message;
		}
		errorEl.textContent = message;
		errorEl.hidden = false;
	}

	/**
	 * Restore the gate submit button.
	 *
	 * @param {HTMLElement} submit Submit button.
	 */
	function resetGateSubmit( submit ) {
		if ( submit ) {
			submit.disabled = false;
			submit.textContent = i18n.gateView || 'View';
		}
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
