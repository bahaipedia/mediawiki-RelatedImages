<?php

/**
 * Implements RelatedImages extension for MediaWiki.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\RelatedImages;

use ImageGalleryBase;
use Title;
use UnlistedSpecialPage;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Implements hidden special page [[Special:SubcatImagesGallery/CategoryNameHere]],
 * which returns HTML of the gallery of images from subcategories of CategoryNameHere.
 * This can be used to add the link "Show images from subcategories" to category pages.
 */
class SpecialSubcatImagesGallery extends UnlistedSpecialPage {
	/** @var ILoadBalancer */
	protected $loadBalancer;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( ILoadBalancer $loadBalancer ) {
		parent::__construct( 'SubcatImagesGallery' );

		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param string|null $param
	 */
	public function execute( $param ) {
		$this->setHeaders();

		$target = $param ? Title::makeTitleSafe( NS_CATEGORY, $param ) : null;
		if ( !$target || !$target->exists() ) {
			$out = $this->getOutput();
			$out->setStatusCode( 404 );
			$out->addWikiMsg( 'badtitletext' );
			return;
		}

		$this->displayGallery( $target );
	}

	/**
	 * Find all images in subcategories of $categoryTitle and output HTML of the resulting gallery.
	 * @param Title $categoryTitle
	 */
	public function displayGallery( Title $categoryTitle ) {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$filenames = $dbr->newSelectQueryBuilder()
			->select( [
				'DISTINCT subcatpage.page_title'
			] )
			->from( 'categorylinks', 'topcat' )
			->join( 'page', 'topcatpage', [
				'topcatpage.page_id=topcat.cl_from',
				'topcatpage.page_namespace' => NS_CATEGORY
			] )
			->join( 'categorylinks', 'subcat', [
				'subcat.cl_to=topcatpage.page_title'
			] )
			->join( 'page', 'subcatpage', [
				'subcatpage.page_id=subcat.cl_from',
				'subcatpage.page_namespace' => NS_FILE
			] )
			->where( [
				'topcat.cl_to' => $categoryTitle->getDbKey()
			] )
			->orderBy( 'subcat.cl_sortkey' )
			->limit( $this->getConfig()->get( 'RelatedImagesMaxSubcatImages' ) )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$out = $this->getOutput();
		if ( !$filenames ) {
			$out->addWikiMsg( 'subcatimagesgallery-empty' );
			return;
		}

		$gallery = ImageGalleryBase::factory( false, $this->getContext() );
		$gallery->setHideBadImages( true );

		foreach ( $filenames as $filename ) {
			$title = Title::makeTitle( NS_FILE, $filename );
			$gallery->add( $title );
		}

		$out->addHTML( $gallery->toHTML() );
	}
}
