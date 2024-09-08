/*
	Script for [[File:Something.png]] pages:
	if the screen is large enough, move $( '.mw-related-images' )
	to the right from the main image.
*/

if ( matchMedia( '(min-width: 800px)' ).matches ) {
	$( function () {
		var $widget = $( '.mw-related-images' );
		if ( !$widget.length ) {
			return;
		}

		var $image = $( '#file' );

		var $table = $( '<table/>' )
			.attr( 'id', 'mw-related-images-table' )
			.append( $( '<tr/>' ).append(
				$( '<td/>' ).append( $image ),
				$( '<td/>' ).attr( 'id', 'mw-related-images-wrapper' ).append( $widget )
			) );

		$( '#filetoc' ).after( $table );
	} );
}
