/*
	Script for [[File:Something.png]] pages.
*/

( function () {
	var api = new mw.Api();

	function initCarousels( $container ) {
		$container.find( '.mw-relatedimages-strip' ).each( function () {
			var $strip = $( this ),
				queue = [];

			try {
				queue = JSON.parse( $strip.attr( 'data-queue' ) || '[]' );
			} catch ( e ) {
				queue = [];
			}
			$strip.removeAttr( 'data-queue' );

			var isFetching = false;

			var $wrapper = $( '<div>' ).addClass( 'mw-relatedimages-carousel' );
			var $btnLeft = $( '<button>' )
				.attr( 'type', 'button' )
				.addClass( 'mw-relatedimages-arrow mw-relatedimages-arrow-left' )
				.text( '‹' )
				.hide();
			var $btnRight = $( '<button>' )
				.attr( 'type', 'button' )
				.addClass( 'mw-relatedimages-arrow mw-relatedimages-arrow-right' )
				.text( '›' );

			$strip.wrap( $wrapper );
			$strip.before( $btnLeft );
			$strip.after( $btnRight );

			function updateArrows() {
				var el = $strip[ 0 ];
				if ( !el ) {
					return;
				}
				var maxScroll = el.scrollWidth - el.clientWidth;
				if ( el.scrollLeft > 5 ) {
					$btnLeft.show();
				} else {
					$btnLeft.hide();
				}

				if ( el.scrollLeft < maxScroll - 5 || queue.length > 0 || isFetching ) {
					$btnRight.show();
				} else {
					$btnRight.hide();
				}
			}

			function prefetchIfNeeded() {
				if ( isFetching || queue.length === 0 ) {
					return;
				}
				var el = $strip[ 0 ];
				var remainingScroll = el.scrollWidth - el.clientWidth - el.scrollLeft;

				// Prefetch next batch of 5 when remaining off-screen width is less than ~1.5x container width
				if ( remainingScroll < el.clientWidth * 1.5 ) {
					isFetching = true;
					var nextWikitext = queue.shift();

					api.post( {
						action: 'parse',
						contentmodel: 'wikitext',
						text: nextWikitext,
						prop: 'text',
						disableeditsection: '',
						disablelimitreport: '',
						disabletoc: ''
					} ).done( function ( ret ) {
						if ( ret.parse && ret.parse.text ) {
							var $newNodes = $( '<div>' ).html( ret.parse.text[ '*' ] );
							// Extract the links/images from the parsed <p> wrapper
							var $items = $newNodes.find( 'a.image, span.mw-default-size, figure, img' ).first().parent().contents();
							if ( $items.length ) {
								$strip.append( $items );
							} else {
								$strip.append( $newNodes.contents() );
							}
						}
					} ).always( function () {
						isFetching = false;
						updateArrows();
						setTimeout( updateArrows, 200 );
					} );
				}
			}

			$btnLeft.on( 'click', function () {
				var step = Math.max( 120, $strip[ 0 ].clientWidth * 0.75 );
				$strip[ 0 ].scrollBy( { left: -step, behavior: 'smooth' } );
			} );

			$btnRight.on( 'click', function () {
				var step = Math.max( 120, $strip[ 0 ].clientWidth * 0.75 );
				$strip[ 0 ].scrollBy( { left: step, behavior: 'smooth' } );
				prefetchIfNeeded();
			} );

			$strip.on( 'scroll', function () {
				updateArrows();
				prefetchIfNeeded();
			} );

			// Update arrows after images load so scrollWidth is accurate
			$strip.find( 'img' ).on( 'load', updateArrows );
			setTimeout( updateArrows, 100 );
		} );
	}

	// Asynchronously parse wikitext of RelatedImages widget (generating thumbnails),
	// display it after they are generated.
	function loadTab( $tab ) {
		if ( !$tab.length ) {
			return;
		}

		var wikitext = $tab.text(),
			$widget = $tab.parent(),
			$nextTab = $tab.next(),
			$loading = $( '<div>' )
				.attr( 'class', 'mw-relatedimages-loading' )
				.append( mw.msg( 'relatedimages-more-loading' ) );

		$tab.replaceWith( $loading );

		var q = {
			action: 'parse',
			contentmodel: 'wikitext',
			text: wikitext,
			prop: 'text',
			disableeditsection: '',
			disablelimitreport: '',
			disabletoc: ''
		};
		api.post( q ).done( function ( ret ) {
			$tab.empty();

			if ( !ret.parse || !ret.parse.text ) {
				console.log( 'RelatedImages: no HTML received from action=parse: ', JSON.stringify( ret ) );
				return;
			}

			// Unhide the widget (now that we have HTML to populate it).
			var $parsed = $( '<div>' ).append( ret.parse.text[ '*' ] );

			if ( $nextTab.length ) {
				// Add "More" link to load 1 more tab.
				$parsed.append( $( '<a>' )
					.attr( 'class', 'mw-relatedimages-more' )
					.append( mw.msg( 'relatedimages-more' ) )
					.click( function () {
						$( this ).remove();
						loadTab( $nextTab );
					} )
				);
			}

			$loading.replaceWith( $parsed );
			$widget.show();
			initCarousels( $parsed );
		} ).fail( function ( code, ret ) {
			console.log( 'RelatedImages: ajax error: ', JSON.stringify( ret ) );
		} );
	}

	$( function () {
		loadTab( $( '.mw-related-images pre' ).first() );
	} );

	// If the screen is large enough, move $( '.mw-related-images' ) to the right
	// from the main image.
	if ( matchMedia( '(min-width: 800px)' ).matches ) {
		$( function () {
			var $widget = $( '.mw-related-images' );
			if ( !$widget.length ) {
				return;
			}

			var $table = $( '<table>' )
				.attr( 'id', 'mw-related-images-table' )
				.append( $( '<tr>' ).append(
					$( '<td>' ).append( $( '#file' ) ),
					$( '<td>' ).attr( 'id', 'mw-related-images-wrapper' ).append( $widget )
				) );

			$( '#filetoc' ).after( $table );
		} );
	}

}() );
