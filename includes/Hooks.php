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

use Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ImagePageAfterImageLinksHook;
use RequestContext;
use TitleValue;
use WikitextContent;
use Xml;

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
		global $wgRelatedImagesIgnoredCategories,
			$wgRelatedImagesMaxCategories,
			$wgRelatedImagesMaxImagesPerCategory,
			$wgRelatedImagesThumbnailWidth,
			$wgRelatedImagesThumbnailHeight;

		$title = $imagePage->getTitle();
		$articleID = $title->getArticleID();

		$services = MediaWikiServices::getInstance();
		$repoGroup = $services->getRepoGroup();
		$linkRenderer = $services->getLinkRenderer();

		$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );

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
				'cl_from' => $articleID,
				'pp_propname IS NULL'
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		// Exclude $wgRelatedImagesIgnoredCategories from the list.
		$categoryNames = array_diff( $categoryNames, $wgRelatedImagesIgnoredCategories );
		if ( !$categoryNames ) {
			// No categories found.
			return;
		}

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

		$categoryNames = array_merge( $directlyAdded, array_diff( $categoryNames, $directlyAdded ) );

		// Randomly choose up to $wgRelatedImagesMaxImagesPerCategory titles (not equal to $title) from $categoryNames.
		$filenamesPerCategory = []; # [ 'Category_name' => [ 'filename', ... ], ... ]
		$seenFilenames = [];
		foreach ( $categoryNames as $category ) {
			// Because the number of categories is low, and the number of images in them can very high,
			// it's preferable to do 1 SQL query per category (limited by $wgRelatedImagesMaxImagesPerCategory)
			// rather than do only 1 SQL query for all categories, but without the limit.
			$filenames = $dbr->newSelectQueryBuilder()
				->select( [ 'DISTINCT page_title' ] )
				->from( 'categorylinks' )
				->join( 'page', null, [
					'page_id=cl_from',
					'page_namespace' => NS_FILE
				] )
				->where( [
					'cl_to' => $category,
					'cl_from <> ' . $articleID
				] )
				->limit( $wgRelatedImagesMaxImagesPerCategory )
				->caller( __METHOD__ )
				->fetchFieldValues();

			// Eliminate duplicates (files that have already been found in previous categories).
			$filenames = array_diff( $filenames, $seenFilenames );
			array_push( $seenFilenames, ...$filenames );

			$filenamesPerCategory[$category] = $filenames;
		}

		// Generate HTML of RelatedImages widget.
		$parser = $services->getParser();
		$widgetHtml = wfMessage( 'relatedimages-header' )->escaped() . '<br>';

		$numFilesCount = 0;
		$numCategoriesCount = 0;
		foreach ( $filenamesPerCategory as $category => $filenames ) {
			$files = $repoGroup->findFiles( $filenames );
			if ( !$files ) {
				// No files found in this category (can happen even if File pages exist).
				continue;
			}

			$numFilesCount += count( $files );
			$numCategoriesCount++;

			$widgetHtml .= Xml::tags( 'h5', null,
				$linkRenderer->makeKnownLink( new TitleValue( NS_CATEGORY, $category ) )
			);
			foreach ( $files as $file ) {
				$widgetHtml .= Linker::makeImageLink(
					$parser,
					$file->getTitle(),
					$file,
					[ 'thumbnail' ],
					[
						'width' => $wgRelatedImagesThumbnailWidth,
						'height' => $wgRelatedImagesThumbnailHeight
					]
				);
			}

			if ( $numCategoriesCount >= $wgRelatedImagesMaxCategories ) {
				break;
			}
		}
		if ( $numFilesCount === 0 ) {
			// No files found.
			return;
		}

		$html = Xml::tags( 'div', [ 'class' => 'mw-related-images' ], $widgetHtml );

		$out = RequestContext::getMain()->getOutput();
		$out->addModuleStyles( [ 'ext.relatedimages.css' ] );
		$out->addModules( [ 'ext.relatedimages' ] );
	}

}
