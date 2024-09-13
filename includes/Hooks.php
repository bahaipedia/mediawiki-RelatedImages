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

use ContentHandler;
use DeferredUpdates;
use FileRepo;
use FormatJson;
use ImagePage;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\Hook\ImagePageAfterImageLinksHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use ParserOptions;
use RepoGroup;
use RequestContext;
use Wikimedia\Rdbms\LoadBalancer;
use Xml;

/**
 * Hooks of Extension:RelatedImages.
 */
class Hooks implements ImagePageAfterImageLinksHook {
	/** @var ContentRenderer */
	protected $contentRenderer;

	/** @var LoadBalancer */
	protected $loadBalancer;

	/** @var RepoGroup */
	protected $repoGroup;

	/** @var WikiPageFactory */
	protected $wikiPageFactory;

	/**
	 * @param ContentRenderer $contentRenderer
	 * @param LoadBalancer $loadBalancer
	 * @param RepoGroup $repoGroup
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ContentRenderer $contentRenderer,
		LoadBalancer $loadBalancer,
		RepoGroup $repoGroup,
		WikiPageFactory $wikiPageFactory
	) {
		$this->contentRenderer = $contentRenderer;
		$this->loadBalancer = $loadBalancer;
		$this->repoGroup = $repoGroup;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * When rendering the page File:Something, find several more images that are
	 * in the same categories and display them as "Related images" navigation box.
	 *
	 * @param ImagePage $imagePage
	 * @param string &$html
	 * @return bool|void
	 */
	public function onImagePageAfterImageLinks( $imagePage, &$html ) {
		global $wgRelatedImagesIgnoredCategories,
			$wgRelatedImagesMaxCategories,
			$wgRelatedImagesMaxImagesPerCategory,
			$wgRelatedImagesBoxExtraCssClass,
			$wgRelatedImagesExperimentalPregenerateThumbnails,
			$wgRelatedImagesDisableForExtensions;

		if ( in_array( $imagePage->getFile()->getExtension(), $wgRelatedImagesDisableForExtensions ) ) {
			// Not needed for this kind of file.
			return;
		}

		$title = $imagePage->getTitle();
		$articleID = $title->getArticleID();

		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

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

		// Check wikitext of $title for "which categories were added directly on this page, not via the template".
		$directlyAdded = $this->getDirectlyAddedCategories( $title->toPageIdentity() );
		$directlyAdded = array_intersect( $directlyAdded, $categoryNames ); // Exclude hidden categories
		$categoryNames = array_merge( $directlyAdded, array_diff( $categoryNames, $directlyAdded ) );

		// Exclude $wgRelatedImagesIgnoredCategories from the list.
		$categoryNames = array_diff( $categoryNames, $wgRelatedImagesIgnoredCategories );
		if ( !$categoryNames ) {
			// No categories found.
			return;
		}

		// Randomly choose up to $wgRelatedImagesMaxImagesPerCategory titles (not equal to $title) from $categoryNames.
		$filenamesPerCategory = []; # [ 'Category_name' => [ 'filename', ... ], ... ]
		$seenFilenames = [];

		// We request more titles than needed, so that duplicates wouldn't result in less recommendations.
		$limit = $wgRelatedImagesMaxImagesPerCategory * count( $categoryNames );
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
				->limit( $limit )
				->orderBy( 'cl_sortkey' )
				->caller( __METHOD__ )
				->fetchFieldValues();

			// Eliminate duplicates (files that have already been found in previous categories).
			$filenames = array_diff( $filenames, $seenFilenames );
			$filenames = array_slice( $filenames, 0, $wgRelatedImagesMaxImagesPerCategory );

			array_push( $seenFilenames, ...$filenames );

			$filenamesPerCategory[$category] = $filenames;
		}

		$logger = LoggerFactory::getInstance( 'RelatedImages' );
		$logger->debug( 'RelatedImages: file={file}, filenamesPerCategory: {candidates}',
			[
				'file' => $title->getFullText(),
				'candidates' => FormatJson::encode( $filenamesPerCategory )
			]
		);

		// Generate HTML of RelatedImages widget.
		$widgetWikitext = '__NOTOC__' . wfMessage( 'relatedimages-header' )->plain();
		$thumbsize = $this->getThumbnailSize();

		$numFilesCount = 0;
		$numCategoriesCount = 0;
		foreach ( $filenamesPerCategory as $category => $filenames ) {
			$found = $this->repoGroup->findFiles( $filenames, FileRepo::NAME_AND_TIME_ONLY );
			if ( !$found ) {
				// No files found in this category (can happen even if File pages exist).
				continue;
			}

			$numFilesCount += count( $found );

			$categoryName = strtr( $category, '_', ' ' );
			$widgetWikitext .= "\n===== [[:Category:$categoryName|$categoryName]] =====\n";

			foreach ( $filenames as $filename ) {
				if ( isset( $found[$filename] ) ) {
					$widgetWikitext .= "[[File:$filename|$thumbsize]]";
				}
			}

			if ( ++$numCategoriesCount >= $wgRelatedImagesMaxCategories ) {
				break;
			}
		}
		if ( $numFilesCount === 0 ) {
			// No files found.
			return;
		}

		$wrapperClass = 'mw-related-images';
		if ( $wgRelatedImagesBoxExtraCssClass ) {
			$wrapperClass .= ' ' . $wgRelatedImagesBoxExtraCssClass;
		}

		// This wikitext will later be rendered by Javascript (using api.php?action=parse).
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		$widgetHtml = Xml::element( 'pre', null, $widgetWikitext );

		$html = Xml::tags( 'div', [ 'class' => $wrapperClass ], $widgetHtml );
		$out = RequestContext::getMain()->getOutput();
		$out->addModuleStyles( [ 'ext.relatedimages.css' ] );
		$out->addModules( [ 'ext.relatedimages' ] );

		if ( $wgRelatedImagesExperimentalPregenerateThumbnails ) {
			// Experimental: parse $widgetWikitext at least once, so that at least some of the thumbnails
			// would already be generated before JavaScript uses /api.php?action=parse.
			DeferredUpdates::addCallableUpdate( function () use( $title, $widgetWikitext ) {
				$content = ContentHandler::makeContent( $widgetWikitext, null, CONTENT_MODEL_WIKITEXT );
				$this->contentRenderer->getParserOutput( $content, $title );
			} );
		}
	}

	/**
	 * Get the list of categories that are added to $title directly (not via included templates).
	 * @param PageIdentity $title
	 * @return string[]
	 */
	protected function getDirectlyAddedCategories( PageIdentity $title ) {
		$content = $this->wikiPageFactory->newFromTitle( $title )->getContent();
		if ( !$content ) {
			return [];
		}

		// Parse the wikitext, but prohibit expansion of templates.
		$popts = ParserOptions::newFromAnon();
		$popts->setMaxTemplateDepth( 0 );

		$pout = $this->contentRenderer->getParserOutput( $content, $title, null, $popts, false );
		return $pout->getCategoryNames();
	}

	/**
	 * Get thumbnail size parameters (e.g. "100x50px") for wikitext that adds thumbnails.
	 * @return string
	 */
	protected function getThumbnailSize() {
		global $wgRelatedImagesThumbnailWidth,
			$wgRelatedImagesThumbnailHeight;

		$thumbsize = '';
		$width = intval( $wgRelatedImagesThumbnailWidth );
		if ( $width > 0 ) {
			$thumbsize .= $width;
		}

		$height = intval( $wgRelatedImagesThumbnailHeight );
		if ( $height > 0 ) {
			$thumbsize .= "x$height";
		}

		if ( $thumbsize ) {
			$thumbsize .= 'px';
		}

		return $thumbsize;
	}
}
