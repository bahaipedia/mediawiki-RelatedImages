/*
	Script for [[Category:Something]] pages.
*/

$( function () {
	if ( !$( '#mw-subcategories' ).length ) {
		// No subcategories.
		return;
	}

	var $contents = $( '.mw-category' );

	var $loading = $( '<div>' )
		.attr( 'class', 'mw-subcatimagesgallery-loading' )
		.append( mw.msg( 'subcatimagesgallery-link-loading' ) );

	function onfail() {
		$loading.html( mw.msg( 'subcatimagesgallery-empty' ) );
	}

	$contents.append( $( '<a>' )
		.append( mw.msg( 'subcatimagesgallery-link' ) )
		.click( function () {
			$( this ).replaceWith( $loading );

			var url = new mw.Title( 'Special:SubcatImagesGallery/' + mw.config.get( 'wgTitle' ) ).getUrl();
			$.get( url ).done( function ( res ) {
				var $newgallery = $( '<div>' ).append( res ).find( '.gallery' );
				if ( !$newgallery.length ) {
					return onfail();
				}

				$loading.replaceWith( $newgallery );
				$newgallery[ 0 ].scrollIntoView( { behavior: 'smooth' } );
			} ).fail( onfail );
		} )
	);
} );
