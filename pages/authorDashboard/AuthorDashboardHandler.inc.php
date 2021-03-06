<?php

/**
 * @file pages/authorDashboard/AuthorDashboardHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardHandler
 * @ingroup pages_authorDashboard
 *
 * @brief Handle requests for the author dashboard.
 */

// Import base class
import('lib.pkp.pages.authorDashboard.PKPAuthorDashboardHandler');

class AuthorDashboardHandler extends PKPAuthorDashboardHandler {

	/**
	 * Setup variables for the template
	 * @param $request Request
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);		

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$submissionContext = $request->getContext();
		if ($submission->getContextId() !== $submissionContext->getId()) {
			$submissionContext = Services::get('context')->get($submission->getContextId());
		}

		$supportedFormLocales = $submissionContext->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);		

		$latestPublication = $submission->getLatestPublication();
		$latestPublicationApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getPath(), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId());
		$relatePublicationApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getPath(), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId()) . '/relate';

		$publishUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'modals.publish.OPSPublishHandler',
			'publish',
			null,
			[
				'submissionId' => $submission->getId(),
				'publicationId' => '__publicationId__',
			]
		);

		$titleAbstractForm = new PKP\components\forms\publication\PKPTitleAbstractForm($latestPublicationApiUrl, $locales, $latestPublication);
		$relationForm = new APP\components\forms\publication\RelationForm($relatePublicationApiUrl, $locales, $latestPublication, $submissionContext, $baseUrl, $temporaryFileApiUrl);

		// Import constants
		import('classes.submission.Submission');
		import('classes.components.forms.publication.PublishForm');
		import('classes.components.forms.publication.RelationForm');

		$templateMgr->setConstants([
			'STATUS_QUEUED',
			'STATUS_PUBLISHED',
			'STATUS_DECLINED',
			'STATUS_SCHEDULED',
			'FORM_TITLE_ABSTRACT',
			'FORM_PUBLISH',
			'FORM_JOURNAL_ENTRY',
			'FORM_ID_RELATION',
		]);

		$workflowData = $templateMgr->getTemplateVars('workflowData');
		$workflowData['components'][FORM_TITLE_ABSTRACT] = $titleAbstractForm->getConfig();
		$workflowData['components'][FORM_ID_RELATION] = $relationForm->getConfig();
		$workflowData['i18n']['schedulePublication'] = __('editor.submission.schedulePublication');
		$workflowData['i18n']['publish'] = __('publication.publish');
		$workflowData['i18n']['setRelationSuccess'] = __('publication.relation.success');
		$workflowData['publishUrl'] = $publishUrl;
		$workflowData['components']['publicationFormIds'] = [FORM_PUBLISH,
				FORM_TITLE_ABSTRACT, FORM_JOURNAL_ENTRY];
		$templateMgr->assign('workflowData', $workflowData);
		
		// If authors can publish show publish buttons
		$canPublish = Services::get('publication')->canAuthorPublish($submission->getId()) ? true : false;
		$templateMgr->assign('canPublish', $canPublish);
	}

	/**
	 * @copydoc PKPAuthorDashboardHandler::_getRepresentationsGridUrl()
	 */
	protected function _getRepresentationsGridUrl($request, $submission) {
		return $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.articleGalleys.ArticleGalleyGridHandler',
			'fetchGrid',
			null,
			[
				'submissionId' => $submission->getId(),
				'publicationId' => '__publicationId__',
			]
		);
	}

	/**
	 * Translate the requested operation to a stage id.
	 * @param $request Request
	 * @param $args array
	 * @return integer One of the WORKFLOW_STAGE_* constants.
	 */
	protected function identifyStageId($request, $args) {
		if ($stageId = $request->getUserVar('stageId')) {
			return (int) $stageId;
		}
		// Maintain the old check for previous path urls
		$router = $request->getRouter();
		$workflowPath = $router->getRequestedOp($request);
		$stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
		if ($stageId) {
			return $stageId;
		}
		// Finally, retrieve the requested operation, if the stage id is
		// passed in via an argument in the URL, like index/submissionId/stageId
		$stageId = $args[1];
		// Translate the operation to a workflow stage identifier.
		assert(WorkflowStageDAO::getPathFromId($stageId) !== null);
		return $stageId;
	}

}


