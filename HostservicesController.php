<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Servicegroup;
use Icinga\Module\Icingadb\Model\HostservicesSummary;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\HostservicesTable;
use Icinga\Module\Icingadb\Web\Control\ViewModeSwitcher;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;

class HostservicesController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Host Services'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $hostservices = HostservicesSummary::on($db);

        $this->handleSearchRequest($hostservices);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hostservices);
        $viewModeSwitcher = $this->createViewModeSwitcher($paginationControl, $limitControl);

        $defaultSort = null;

        $sortControl = $this->createSortControl(
            $hostservices,
            [
                'name'                                 => t('Object Name'),
                'display_name'                         => t('Display Name'),
                'services_warning_unhandled desc'      => t('Srv Unhandled Warning'),
                'services_critical_unhandled desc'     => t('Srv Unhandled Critial'),
                'services_unknown_unhandled desc'      => t('Srv Unhandled Unknown'),
                'services_critical_unhandled desc,services_warning_unhandled desc'     => t('Srv Unhandled Critial,Warning'),
                'services_total desc'                  => t('Srv Total Services'),
                'services_ok desc'                     => t('Srv Ok'),
                'services_pending desc'                => t('Srv Pending'),
                'services_total desc'                  => t('Srv Total Services'),
                'services_warning_handled desc'        => t('Srv Handled Warning'),
                'services_unknown_handled desc'        => t('Srv Handled Unknown')
            ],
            $defaultSort
        );

        $searchBar = $this->createSearchBar($hostservices, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam(),
            $viewModeSwitcher->getViewModeParam()
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

        $this->filter($hostservices, $filter);

        $hostservices->peekAhead($compact);

        yield $this->export($hostservices);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($searchBar);

        $results = $hostservices->execute();

        $this->addContent(
            (new HostservicesTable($results))
                ->setBaseFilter($filter)
                ->setViewMode($viewModeSwitcher->getViewMode())
        );

        if ($compact) {
            $this->addContent(
                (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit', 'view'])))
                    ->setBaseTarget('_next')
                    ->setAttribute('title', sprintf(
                        t('Show all %d Hosts'),
                        $hostservices->count()
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
        $suggestions->setModel(servicegroup::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(HostservicesSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM,
            ViewModeSwitcher::DEFAULT_VIEW_MODE_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
