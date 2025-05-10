<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Servicegroup;
use Icinga\Module\Icingadb\Model\HostservicesSummary;
use Icinga\Module\Icingadb\View\ServicegroupGridRenderer;
use Icinga\Module\Icingadb\View\HostservicesRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
#use Icinga\Module\Icingadb\Web\Control\ViewModeSwitcher;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectGrid;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Html\Attributes;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;
use ipl\Web\Widget\ItemList;

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
#        $viewModeSwitcher = $this->createViewModeSwitcher($paginationControl, $limitControl);

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
            ['services_critical_unhandled desc', 'services_warning_unhandled desc']
        );

#        $searchBar = $this->createSearchBar($hostservices, [
#            $limitControl->getLimitParam(),
#            $sortControl->getSortParam(),
#            $viewModeSwitcher->getViewModeParam()
#        ]);

#        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
#            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
#            } else {
#                $this->addControl($searchBar);
#                $this->sendMultipartUpdate();
#                return;
#            }
#        } else {
#            $filter = $searchBar->getFilter();
#        }

        $this->filter($hostservices, $filter);

        $hostservices->peekAhead($compact);

        yield $this->export($hostservices);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
#        $this->addControl($viewModeSwitcher);
#        $this->addControl($searchBar);

        $results = $hostservices->execute();

#       if ($viewModeSwitcher->getViewMode() === 'grid') {
#            $content = new ObjectGrid($results, (new ServicegroupGridRenderer())->setBaseFilter($filter));
#        } else {
            $content = new ObjectTable($results, (new HostservicesRenderer())->setBaseFilter($filter));
#        }
        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

#        if ($compact) {
#            $this->addContent(
#                (new ShowMore($results, Url::fromRequest()->without(['showCompact', 'limit', 'view'])))
#                    ->setBaseTarget('_next')
#                    ->setAttribute('title', sprintf(
#                        t('Show all %d hostservices'),
#                        $hostservices->count()
#                    ))
#            );
#        }

#        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
#            $this->sendMultipartUpdate();
#        }

        $this->setAutorefreshInterval(30);
    }

#    public function completeAction()
#    {
#        $suggestions = new ObjectSuggestions();
#        $suggestions->setModel(Servicegroup::class);
#        $suggestions->forRequest(ServerRequest::fromGlobals());
#        $this->getDocument()->add($suggestions);
#    }

#    public function searchEditorAction()
#    {
#        $editor = $this->createSearchEditor(HostservicesSummary::on($this->getDb()), [
#            LimitControl::DEFAULT_LIMIT_PARAM,
#            SortControl::DEFAULT_SORT_PARAM,
#            ViewModeSwitcher::DEFAULT_VIEW_MODE_PARAM
#        ]);
#
#        $this->getDocument()->add($editor);
#        $this->setTitle(t('Adjust Filter'));
#    }
}
