<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServiceservicesSummary;
use Icinga\Module\Icingadb\View\ServiceservicesRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;

class ServiceservicesController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Service Services'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $services = ServiceservicesSummary::on($db);

        $this->handleSearchRequest($services);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($services);

        $sortControl = $this->createSortControl(
            $services,
            [
                'display_name'                                                      => t('Display Name'),
                'name'                                                              => t('Name'),
                'services_critical_unhandled desc'                                  => t('Unhandled Critical'),
                'services_warning_unhandled desc'                                   => t('Unhandled Warning'),
                'services_unknown_unhandled desc'                                   => t('Unhandled Unknown'),
                'services_critical_unhandled desc, services_warning_unhandled desc' => t('Unhandled Critical, Warning'),
                'services_total desc'                                               => t('Total Services'),
                'services_ok desc'                                                  => t('Ok'),
                'services_pending desc'                                             => t('Pending'),
                'services_warning_handled desc'                                     => t('Handled Warning'),
                'services_unknown_handled desc'                                     => t('Handled Unknown')
            ],
            ['services_critical_unhandled desc', 'services_warning_unhandled desc']
        );

        $searchBar = $this->createSearchBar($services, [
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

        $this->filter($services, $filter);

        $services->peekAhead($compact);

        yield $this->export($services);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($searchBar);

        $results = $services->execute();

        $content = new ObjectTable($results, (new ServiceservicesRenderer())->setBaseFilter($filter));
        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(30);
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Service::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(ServiceservicesSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
