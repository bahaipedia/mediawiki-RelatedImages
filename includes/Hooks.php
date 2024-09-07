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

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ImagePageAfterImageLinksHook;
use WikitextContent;

/**
 * Hooks of Extension:RelatedImages.
 */
class Hooks implements ImagePageAfterImageLinksHook {

	/**
	 * When rendering the page File:Something, find several more images that are
	 * in the same categories and display them as "Related images" navigation box.
	 *
	 * @param ImagePage $imagePage
	 * @param string &$html
	 * @return void
	 */
	public function onImagePageAfterImageLinks( $imagePage, &$html ): void {
		global $wgRelatedImagesIgnoredCategories;

		$title = $imagePage->getTitle();

		// TODO:
		// set $html to a hidden <div> with the necessary code of RelatedImages,
		// add JavaScript module that would reposition and unhide this <div>.

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		// Find all non-hidden categories that contain the page $title.
		$categoryNames = $dbr->newSelectQueryBuilder()
			->select( [ 'cl_to' ] )
			->from( 'categorylinks' )
			->leftJoin( 'page', null, [
				'page_namespace' => NS_CATEGORY,
				'page_title=cl_to'
			] )
			->leftJoin( 'page_props', null, [
				'pp_propname' => 'hiddencat',
				'pp_page=page_id',
			] )
			->where( [
				'cl_from' => $title->getArticleID(),
				'pp_propname IS NULL'
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		// Exclude $wgRelatedImagesIgnoredCategories from the list.
		$categoryNames = array_diff( $categoryNames, $wgRelatedImagesIgnoredCategories );

		// Check wikitext of $title for "which categories were added directly on this page, not via the template".
		$directlyAdded = []; // [ 'category_name' => true ]

		$content = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromLinkTarget( $title )->getContent();
		if ( $content && $content instanceof WikitextContent ) {
			foreach ( $categoryNames as $category ) {
				// This will likely match [[Category:<name>]] or [[Category:<name>|sortkey]] syntax.
				// The word "Category" might be translated to another language, so we shouldn't match it.
				$regex = '/:\s*' . str_replace( ' ', '_', preg_quote( $category, '/' ) ) . '\s*(\||\]\])/';

				if ( preg_match( $regex, $content->getText() ) ) {
					$directlyAdded[] = $category;
				}
			}
		}

		// Move directly added categories to the beginning of the array of categories.
		$categoryNames = array_merge(
			$directlyAdded,
			array_diff( $categoryNames, $directlyAdded )
		);

		// Randomly choose up to $wgRelatedImagesCount titles (not equal to $title) from $categoryNames.


		$html = 'TODO: add RelatedImages';
	}

}
