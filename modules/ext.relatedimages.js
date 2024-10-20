/*
	Script for [[File:Something.png]] pages.
*/

( function () {
	var api = new mw.Api();

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
					.append( mw.msg( 'relatedimages-more' ) )
					.click( function () {
						$( this ).remove();
						loadTab( $nextTab );
					} )
				);
			}

			$loading.replaceWith( $parsed );
			$widget.show();
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
