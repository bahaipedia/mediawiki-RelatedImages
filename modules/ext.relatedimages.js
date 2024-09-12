/*
	Script for [[File:Something.png]] pages.
*/

var api = new mw.Api();

// Asynchronously parse wikitext of RelatedImages widget (generating thumbnails),
// display it after they are generated.
$( function () {
	var $widget = $( '.mw-related-images' );
	if ( !$widget.length ) {
		return;
	}

	var $src = $widget.find( 'pre' );
	if ( !$src.length ) {
		return;
	}

	var wikitext = $src.text();
	var q = {
		action: 'parse',
		contentmodel: 'wikitext',
		text: wikitext,
		prop: 'text',
		disableeditsection: ''
	};

	api.post( q ).done( function ( ret ) {
		if ( !ret.parse || !ret.parse.text ) {
			console.log( 'RelatedImages: no HTML received from action=parse: ', JSON.stringify( ret ) );
			return;
		}

		// Unhide the widget (now that we have HTML to populate it).
		$src.replaceWith( ret.parse.text['*'] );
		$widget.show();
	} )
	.fail( function ( code, ret ) {
		console.log( 'RelatedImages: ajax error: ', JSON.stringify( ret ) );
	} );
} );

// If the screen is large enough, move $( '.mw-related-images' ) to the right from the main image.
if ( matchMedia( '(min-width: 800px)' ).matches ) {
	$( function () {
		var $widget = $( '.mw-related-images' );
		if ( !$widget.length ) {
			return;
		}

		var $table = $( '<table/>' )
			.attr( 'id', 'mw-related-images-table' )
			.append( $( '<tr/>' ).append(
				$( '<td/>' ).append( $( '#file' ) ),
				$( '<td/>' ).attr( 'id', 'mw-related-images-wrapper' ).append( $widget )
			) );

		$( '#filetoc' ).after( $table );
	} );
}
