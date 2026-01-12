<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Hostgroup;
use Icinga\Module\Icingadb\Model\HostgroupsprojecttreeSummary;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\HostgroupsprojecttreeTable;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;

class HostgroupsprojecttreeController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Host Group Tree'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $hostgroups = HostgroupsprojecttreeSummary::on($db);

        $this->handleSearchRequest($hostgroups);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hostgroups);

        $sortControl = $this->createSortControl(
            $hostgroups,
            [
                'display_name'                      => t('Name'),
                'hosts_severity desc, display_name' => t('Severity'),
                'hosts_down_unhandled desc'         => t('Hosts Down Unhandled'),
                'hosts_total desc'                  => t('Total Hosts'),
                'services_critical_unhandled desc'  => t('Services Critical Unhandled'),
                'services_total desc'               => t('Total Services')
            ],
            ['hosts_severity desc', 'display_name']
        );

        $searchBar = $this->createSearchBar($hostgroups, [
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

        $this->filter($hostgroups, $filter);

        $hostgroups->peekAhead($compact);

        yield $this->export($hostgroups);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($searchBar);

        $results = $hostgroups->execute();

        $content = new HostgroupsprojecttreeTable($results, $db);
        $content->setBaseFilter($filter);
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
        $suggestions->setModel(Hostgroup::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(HostgroupsprojecttreeSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
