<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */
/* GeBi custom view - based on official ServicegroupsController */
/* Differences: uses ServicegroupprojectSummary (for customvar_flat), no grid view */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Servicegroup;
use Icinga\Module\Icingadb\Model\ServicegroupprojectSummary;
use Icinga\Module\Icingadb\View\ServicegroupprojectRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;

class ServicegroupsprojectController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Service Groups Project'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $servicegroups = ServicegroupprojectSummary::on($db);

        $this->handleSearchRequest($servicegroups);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($servicegroups);
        // No ViewModeSwitcher - only table view

        $sortControl = $this->createSortControl(
            $servicegroups,
            [
                'display_name'                         => t('Name'),
                'services_severity desc, display_name' => t('Severity'),
                'services_total desc'                  => t('Total Services')
            ],
            ['services_severity DESC', 'display_name']
        );

        $searchBar = $this->createSearchBar($servicegroups, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam(),
        ]);

        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        $this->filter($servicegroups, $filter);

        $servicegroups->peekAhead($compact);

        yield $this->export($servicegroups);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        // No $this->addControl($viewModeSwitcher);
        $this->addControl($searchBar);

        $results = $servicegroups->execute();

        // Only table view, no grid
        $content = new ObjectTable($results, (new ServicegroupprojectRenderer())->setBaseFilter($filter));

        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        if ($compact) {
            $this->addContent(
                (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit', 'view'])))
                    ->setBaseTarget('_next')
                    ->setAttribute('title', sprintf(
                        t('Show all %d servicegroups'),
                        $servicegroups->count()
                    ))
            );
        }

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(30);
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Servicegroup::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(ServicegroupprojectSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM,
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
