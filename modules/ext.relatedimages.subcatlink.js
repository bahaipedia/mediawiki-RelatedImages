/*
	Script for [[Category:Something]] pages.
*/

$( function () {
	if ( !$( '#mw-subcategories' ).length ) {
		// No subcategories.
		return;
	}

	var $widget = $( '<div>' )
		.attr( 'class', 'mw-subcatimagesgallery' )
		.appendTo( '.mw-category-generated' );

	var $loading = $( '<div>' )
		.attr( 'class', 'mw-subcatimagesgallery-loading' )
		.append( mw.msg( 'subcatimagesgallery-link-loading' ) );

	function onfail() {
		$loading.text( mw.msg( 'subcatimagesgallery-empty' ) );
	}

	$( '<a>' ).appendTo( $widget )
		.attr( 'class', 'mw-subcatimagesgallery-load' )
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
		} );
} );
