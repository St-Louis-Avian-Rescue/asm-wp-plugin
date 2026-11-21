/**
 * ASM WP Plugin: page script for the adoptable animals listing.
 *
 * - Opens an animal's details in a dialog, loading them on demand.
 * - Keeps a shareable link (?animal=123) in the address bar.
 * - Replaces the listing when a full-page cache served an out-of-date copy.
 * - Runs the optional search, filter and sort bar.
 *
 * No dependencies and no build step.
 */
( function () {
	'use strict';

	var config = window.asmWpPluginConfig || {};
	var i18n = config.i18n || {};
	var FILTER_KEYS = [ 'q', 'species', 'sex', 'size', 'special', 'sort' ];
	var PARAM_PREFIX = 'asm_';
	var RECHECK_AFTER = 60 * 1000;
	var primaryClaimed = false;

	function restUrl( path, params ) {
		var url = new URL( ( config.restUrl || '/wp-json/asm-wp-plugin/v1/' ) + path, window.location.href );
		Object.keys( params || {} ).forEach( function ( key ) {
			if ( params[ key ] !== '' && params[ key ] !== null && params[ key ] !== undefined ) {
				url.searchParams.set( key, params[ key ] );
			}
		} );
		return url.toString();
	}

	function getJson( path, params ) {
		return fetch( restUrl( path, params ), { cache: 'no-store', credentials: 'omit' } ).then( function ( response ) {
			if ( ! response.ok ) {
				// The error body is not needed; say so, so the connection is freed.
				if ( response.body && response.body.cancel ) {
					response.body.cancel();
				}
				var error = new Error( 'HTTP ' + response.status );
				error.status = response.status;
				throw error;
			}
			return response.json();
		} );
	}

	function requestedAnimal() {
		var params = new URLSearchParams( window.location.search );
		var id = parseInt( params.get( 'animal' ) || '', 10 );
		return id > 0 ? id : 0;
	}

	/**
	 * One listing on the page.
	 *
	 * @param {HTMLElement} root The .asm-wp-plugin element.
	 */
	function Listing( root ) {
		this.root = root;
		this.grid = root.querySelector( '[data-asm-grid]' );
		this.modal = root.querySelector( '[data-asm-modal]' );
		this.modalBody = root.querySelector( '[data-asm-modal-body]' );
		this.filtersSlot = root.querySelector( '[data-asm-filters-slot]' );
		this.request = {};
		this.details = {};
		this.openId = 0;
		this.lastFocus = null;
		this.lastCheck = 0;
		this.searchTimer = 0;
		this.isPrimary = ! primaryClaimed;
		primaryClaimed = true;

		try {
			this.request = JSON.parse( root.getAttribute( 'data-request' ) || '{}' );
		} catch ( e ) {
			this.request = {};
		}

		if ( ! this.grid || ! this.modal || ! this.modalBody ) {
			return;
		}

		root.classList.add( 'asm-wp-plugin--js' );
		this.bind();
		this.readFiltersFromUrl();
		this.applyFilters( false );
		this.syncToUrl();
		this.checkFreshness();
	}

	Listing.prototype.bind = function () {
		var self = this;

		this.root.addEventListener( 'click', function ( event ) {
			var target = event.target;

			var opener = target.closest( '[data-asm-animal]' );
			if ( opener && self.root.contains( opener ) ) {
				// Let people open an animal in a new tab the usual ways.
				if ( event.ctrlKey || event.metaKey || event.shiftKey || event.button ) {
					return;
				}
				event.preventDefault();
				self.open( parseInt( opener.getAttribute( 'data-asm-animal' ), 10 ), true );
				return;
			}

			if ( target.closest( '[data-asm-close]' ) || target === self.modal ) {
				event.preventDefault();
				self.close( true );
				return;
			}

			var step = target.closest( '[data-asm-step]' );
			if ( step ) {
				event.preventDefault();
				self.stepSlide( parseInt( step.getAttribute( 'data-asm-step' ), 10 ) );
				return;
			}

			var copy = target.closest( '[data-asm-copy]' );
			if ( copy ) {
				event.preventDefault();
				self.copyLink( copy );
				return;
			}

			if ( target.closest( '[data-asm-clear]' ) ) {
				event.preventDefault();
				self.clearFilters();
			}
		} );

		this.root.addEventListener( 'input', function ( event ) {
			if ( event.target.matches( '[data-asm-filter="q"]' ) ) {
				window.clearTimeout( self.searchTimer );
				self.searchTimer = window.setTimeout( function () {
					self.applyFilters( true );
				}, 150 );
			}
		} );

		this.root.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '[data-asm-filter]' ) ) {
				self.applyFilters( true );
			}
		} );

		this.root.addEventListener( 'submit', function ( event ) {
			if ( event.target.matches( '[data-asm-filters]' ) ) {
				event.preventDefault();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! self.openId ) {
				return;
			}
			if ( event.key === 'Escape' ) {
				self.close( true );
			} else if ( event.key === 'ArrowLeft' ) {
				self.stepSlide( -1 );
			} else if ( event.key === 'ArrowRight' ) {
				self.stepSlide( 1 );
			} else if ( event.key === 'Tab' ) {
				self.trapFocus( event );
			}
		} );

		window.addEventListener( 'popstate', function () {
			self.syncToUrl();
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'visible' && Date.now() - self.lastCheck > RECHECK_AFTER ) {
				self.checkFreshness();
			}
		} );
	};

	/* Details dialog ---------------------------------------------------- */

	/**
	 * Makes the dialog match the animal named in the address bar.
	 *
	 * With several listings on one page, only the first one opens links.
	 */
	Listing.prototype.syncToUrl = function () {
		var id = this.isPrimary ? requestedAnimal() : 0;

		if ( id && id !== this.openId ) {
			// The server may already have rendered this animal into the dialog.
			var rendered = this.modalBody.querySelector( '.asm-wp-plugin-detail' );
			if ( rendered && parseInt( rendered.getAttribute( 'data-id' ), 10 ) === id && ! this.modal.hidden ) {
				this.details[ id ] = this.modalBody.innerHTML;
				this.showModal( id );
			} else {
				this.open( id, false );
			}
		} else if ( ! id && this.openId ) {
			this.close( false );
		}
	};

	Listing.prototype.open = function ( id, push ) {
		var self = this;
		if ( ! id ) {
			return;
		}

		if ( ! this.openId ) {
			this.lastFocus = document.activeElement;
		}
		this.showModal( id );

		if ( push ) {
			this.setUrl( id, 'push' );
		}

		if ( this.details[ id ] ) {
			this.modalBody.innerHTML = this.details[ id ];
			this.focusModal();
			return;
		}

		this.setStatus( i18n.loading || 'Loading…' );

		getJson( 'animals/' + id, { base: this.root.getAttribute( 'data-base' ) } )
			.then( function ( data ) {
				self.details[ id ] = data.html;
				if ( self.openId === id ) {
					self.modalBody.innerHTML = data.html;
					self.focusModal();
				}
			} )
			.catch( function ( error ) {
				if ( self.openId !== id ) {
					return;
				}
				if ( error.status === 404 ) {
					self.setStatus( i18n.gone || 'This animal is no longer listed.' );
					self.checkFreshness();
				} else {
					self.setStatus( i18n.error || 'Sorry, the details could not be loaded.' );
				}
			} );
	};

	Listing.prototype.showModal = function ( id ) {
		this.openId = id;
		this.modal.hidden = false;
		this.modal.classList.add( 'is-open' );
		this.modal.scrollTop = 0;
		document.documentElement.classList.add( 'asm-wp-plugin-modal-open' );
	};

	Listing.prototype.close = function ( updateUrl ) {
		if ( ! this.openId && this.modal.hidden ) {
			return;
		}
		this.openId = 0;
		this.modal.hidden = true;
		this.modal.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'asm-wp-plugin-modal-open' );

		if ( updateUrl ) {
			this.setUrl( 0, 'push' );
		}
		if ( this.lastFocus && document.contains( this.lastFocus ) ) {
			this.lastFocus.focus();
		}
		this.lastFocus = null;
	};

	Listing.prototype.setStatus = function ( text ) {
		var status = document.createElement( 'p' );
		status.className = 'asm-wp-plugin-modal__status';
		status.setAttribute( 'role', 'status' );
		status.textContent = text;
		this.modalBody.innerHTML = '';
		this.modalBody.appendChild( status );
	};

	Listing.prototype.focusModal = function () {
		var close = this.modal.querySelector( '[data-asm-close]' );
		if ( close ) {
			close.focus( { preventScroll: true } );
		}
	};

	Listing.prototype.trapFocus = function ( event ) {
		var focusable = this.modal.querySelectorAll( 'a[href], button:not([disabled])' );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( ! this.modal.contains( document.activeElement ) ) {
			event.preventDefault();
			first.focus();
		} else if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	/**
	 * Puts the open animal (or none) in the address bar.
	 *
	 * @param {number} id   Animal ID, or 0 for none.
	 * @param {string} mode 'push' adds a history entry, anything else replaces.
	 */
	Listing.prototype.setUrl = function ( id, mode ) {
		var url = new URL( window.location.href );
		if ( id ) {
			url.searchParams.set( 'animal', id );
		} else {
			url.searchParams.delete( 'animal' );
		}
		if ( url.toString() === window.location.href ) {
			return;
		}
		window.history[ mode === 'push' ? 'pushState' : 'replaceState' ]( {}, '', url.toString() );
	};

	Listing.prototype.stepSlide = function ( step ) {
		var slides = this.modalBody.querySelectorAll( '[data-asm-slide]' );
		if ( slides.length < 2 ) {
			return;
		}
		var current = 0;
		slides.forEach( function ( slide, index ) {
			if ( slide.classList.contains( 'is-active' ) ) {
				current = index;
			}
			slide.classList.remove( 'is-active' );
		} );
		slides[ ( current + step + slides.length ) % slides.length ].classList.add( 'is-active' );
	};

	Listing.prototype.copyLink = function ( link ) {
		var url = link.href;
		var tooltip = link.querySelector( '[data-asm-copied]' );
		var done = function () {
			if ( ! tooltip ) {
				return;
			}
			tooltip.textContent = i18n.copied || 'Copied!';
			window.setTimeout( function () {
				tooltip.textContent = '';
			}, 3000 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( done, function () {
				fallbackCopy( url );
				done();
			} );
		} else {
			fallbackCopy( url );
			done();
		}
	};

	function fallbackCopy( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.top = '0';
		area.style.left = '0';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			// Nothing more to try; the link is still in the address bar.
		}
		document.body.removeChild( area );
	}

	/* Freshness --------------------------------------------------------- */

	/**
	 * Asks the site whether this listing is still current, and replaces it
	 * if a page cache served an old copy.
	 */
	Listing.prototype.checkFreshness = function () {
		var self = this;
		this.lastCheck = Date.now();

		getJson( 'status' )
			.then( function ( status ) {
				if ( ! status.hash || status.hash === self.root.getAttribute( 'data-hash' ) ) {
					return null;
				}
				return getJson( 'grid', {
					species: self.request.species,
					sort: self.request.sort,
					limit: self.request.limit,
					filters: self.request.filters,
					base: self.root.getAttribute( 'data-base' )
				} );
			} )
			.then( function ( grid ) {
				if ( ! grid ) {
					return;
				}
				var state = self.readFilters();
				self.grid.innerHTML = grid.list;
				if ( self.filtersSlot ) {
					self.filtersSlot.innerHTML = grid.filters;
				}
				self.root.setAttribute( 'data-hash', grid.hash );
				self.details = {};
				self.writeFilters( state );
				self.applyFilters( false );
			} )
			.catch( function () {
				// The listing already on the page stays as it is.
			} );
	};

	/* Search, filters and sorting --------------------------------------- */

	Listing.prototype.control = function ( key ) {
		return this.root.querySelector( '[data-asm-filter="' + key + '"]' );
	};

	Listing.prototype.defaultSort = function () {
		var sort = this.control( 'sort' );
		return ( sort && sort.getAttribute( 'data-default' ) ) || this.request.sort || 'name';
	};

	Listing.prototype.readFilters = function () {
		var self = this;
		var state = {};
		FILTER_KEYS.forEach( function ( key ) {
			var control = self.control( key );
			if ( ! control ) {
				return;
			}
			if ( control.type === 'checkbox' ) {
				state[ key ] = control.checked ? '1' : '';
			} else {
				state[ key ] = control.value.trim();
			}
		} );
		return state;
	};

	Listing.prototype.writeFilters = function ( state ) {
		var self = this;
		FILTER_KEYS.forEach( function ( key ) {
			var control = self.control( key );
			if ( ! control || state[ key ] === undefined ) {
				return;
			}
			if ( control.type === 'checkbox' ) {
				control.checked = state[ key ] === '1';
			} else if ( control.tagName === 'SELECT' ) {
				var exists = Array.prototype.some.call( control.options, function ( option ) {
					return option.value === state[ key ];
				} );
				control.value = exists ? state[ key ] : ( key === 'sort' ? self.defaultSort() : '' );
			} else {
				control.value = state[ key ];
			}
		} );
	};

	Listing.prototype.readFiltersFromUrl = function () {
		var params = new URLSearchParams( window.location.search );
		var state = {};
		FILTER_KEYS.forEach( function ( key ) {
			if ( params.has( PARAM_PREFIX + key ) ) {
				state[ key ] = params.get( PARAM_PREFIX + key );
			}
		} );
		this.writeFilters( state );
	};

	Listing.prototype.clearFilters = function () {
		this.writeFilters( { q: '', species: '', sex: '', size: '', special: '', sort: this.defaultSort() } );
		this.applyFilters( true );
	};

	/**
	 * Shows the cards that match the bar, in the chosen order.
	 *
	 * @param {boolean} updateUrl Whether to record the state in the address bar.
	 */
	Listing.prototype.applyFilters = function ( updateUrl ) {
		var hasBar = !! this.root.querySelector( '[data-asm-filters]' );
		var cards = Array.prototype.slice.call( this.grid.querySelectorAll( '.asm-wp-plugin-card' ) );
		var empty = this.root.querySelector( '[data-asm-empty]' );
		var noMatches = this.root.querySelector( '[data-asm-no-matches]' );

		if ( empty ) {
			empty.hidden = cards.length > 0;
		}
		if ( ! hasBar ) {
			if ( noMatches ) {
				noMatches.hidden = true;
			}
			return;
		}

		var state = this.readFilters();
		var words = ( state.q || '' ).toLowerCase().split( /\s+/ ).filter( Boolean );
		var shown = 0;

		cards.forEach( function ( card ) {
			var text = card.getAttribute( 'data-search' ) || '';
			var match = words.every( function ( word ) {
				return text.indexOf( word ) !== -1;
			} );
			match = match && ( ! state.species || card.getAttribute( 'data-species' ) === state.species );
			match = match && ( ! state.sex || card.getAttribute( 'data-sex' ) === state.sex );
			match = match && ( ! state.size || card.getAttribute( 'data-size' ) === state.size );
			match = match && ( ! state.special || card.getAttribute( 'data-special' ) === '1' );
			card.hidden = ! match;
			shown += match ? 1 : 0;
		} );

		var sort = state.sort || this.defaultSort();
		var grid = this.grid;
		cards.sort( comparator( sort ) ).forEach( function ( card ) {
			grid.appendChild( card );
		} );

		var active = !! ( state.q || state.species || state.sex || state.size || state.special || sort !== this.defaultSort() );
		var count = this.root.querySelector( '[data-asm-count]' );
		if ( count ) {
			count.textContent = ( shown === 1 ? i18n.countOne || '%d animal' : i18n.countMany || '%d animals' ).replace( '%d', shown );
		}
		var clear = this.root.querySelector( '[data-asm-clear]' );
		if ( clear ) {
			clear.hidden = ! active;
		}
		if ( noMatches ) {
			noMatches.hidden = shown > 0 || cards.length === 0;
		}

		if ( updateUrl ) {
			var url = new URL( window.location.href );
			FILTER_KEYS.forEach( function ( key ) {
				var value = state[ key ];
				if ( key === 'sort' && value === this.defaultSort() ) {
					value = '';
				}
				if ( value ) {
					url.searchParams.set( PARAM_PREFIX + key, value );
				} else {
					url.searchParams.delete( PARAM_PREFIX + key );
				}
			}, this );
			window.history.replaceState( window.history.state, '', url.toString() );
		}
	};

	function comparator( sort ) {
		var byName = function ( a, b ) {
			return ( a.getAttribute( 'data-name' ) || '' ).localeCompare( b.getAttribute( 'data-name' ) || '', undefined, { sensitivity: 'base' } );
		};
		// ISO dates compare correctly as text. Animals without a date go last.
		var byDate = function ( attribute, newestFirst ) {
			return function ( a, b ) {
				var x = a.getAttribute( attribute ) || '';
				var y = b.getAttribute( attribute ) || '';
				if ( x === y ) {
					return byName( a, b );
				}
				if ( ! x ) {
					return 1;
				}
				if ( ! y ) {
					return -1;
				}
				return ( x < y ? -1 : 1 ) * ( newestFirst ? -1 : 1 );
			};
		};

		if ( sort === 'newest' ) {
			return byDate( 'data-date-in', true );
		}
		if ( sort === 'youngest' ) {
			return byDate( 'data-dob', true );
		}
		if ( sort === 'oldest' ) {
			return byDate( 'data-dob', false );
		}
		return byName;
	}

	function init() {
		document.querySelectorAll( '[data-asm-wp-plugin]' ).forEach( function ( root ) {
			if ( ! root.asmWpPlugin ) {
				root.asmWpPlugin = new Listing( root );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
