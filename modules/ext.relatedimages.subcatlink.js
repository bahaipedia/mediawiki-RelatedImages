/*
	Script for [[Category:Something]] pages.
*/

$( function () {
	if ( !$( '#mw-subcategories' ).length ) {
		// No subcategories.
		return;
	}

	var $gallery = $( '.gallery' );
	if ( !$gallery.length ) {
		// No images in this category,
		// it's very likely that subcategores won't have them either.
		return;
	}

	var $loading = $( '<div>' )
		.attr( 'class', 'mw-subcatimagesgallery-loading' )
		.append( mw.msg( 'subcatimagesgallery-link-loading' ) );

	function onfail() {
		$loading.html( mw.msg( 'subcatimagesgallery-empty' ) );
	}

	$gallery.after( $( '<a>' )
		.append( mw.msg( 'subcatimagesgallery-link' ) )
		.click( function () {
			$( this ).remove();
			$gallery.after( $loading );

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
