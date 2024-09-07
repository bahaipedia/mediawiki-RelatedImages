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

use MediaWiki\Page\Hook\ImagePageAfterImageLinksHook;

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
		$title = $imagePage->getTitle();

		// TODO: find all non-hidden categories that have $title in them,
		// exclude $wgRelatedImagesIgnoredCategories from that array,
		// fetch wikitext of the page $title,
		// check wikitext for "which categories were added directly from that page, not via the template",
		// move directly added categories to the beginning of the array,
		// randomly choose $wgRelatedImagesCount titles (that are not equal to $title) from the first category,
		// if less than $wgRelatedImagesCount were found: continue searching in the following categories,
		// set $html to a hidden <div> with the necessary code of RelatedImages,
		// add JavaScript module that would reposition and unhide this <div>.


		$html = 'TODO: add RelatedImages';
	}

}
