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

		this.setupControls();
		this.setupZoom();
		this.setupFullscreen();

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

		this.resetZoom();
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
