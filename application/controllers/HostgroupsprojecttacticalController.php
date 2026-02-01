<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Hostgroup;
use Icinga\Module\Icingadb\Model\HostgroupsprojecttacticalSummary;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Control\ServiceStateToggle;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\HostgroupsprojecttacticalTable;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;

/**
 * Controller for hostgroup project tactical view
 *
 * @since 1.3.0 GeBi custom view
 */
class HostgroupsprojecttacticalController extends Controller
{
    public function init(): void
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction(): \Generator
    {
        $this->addTitleTab(t('Host Group Tactical'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        // Remove checkbox params from URL before filter processing
        foreach (ServiceStateToggle::CHECKBOX_PARAMS as $param) {
            $this->params->shift($param);
        }

        $hostgroups = HostgroupsprojecttacticalSummary::on($db);

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

        $serviceStateToggle = new ServiceStateToggle();

        $searchBar = $this->createSearchBar($hostgroups, array_merge(
            [
                $limitControl->getLimitParam(),
                $sortControl->getSortParam()
            ],
            ServiceStateToggle::CHECKBOX_PARAMS
        ));

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
        $this->addControl($serviceStateToggle);
        $this->addControl($searchBar);

        $results = $hostgroups->execute();

        $content = new HostgroupsprojecttacticalTable($results, $db);
        $content->setBaseFilter($filter);
        $content->setServiceStateFilter($serviceStateToggle->getSelectedStates());
        $content->setHostStateFilter($serviceStateToggle->getSelectedHostStates());
        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(30);
    }

    public function completeAction(): void
    {
        foreach (ServiceStateToggle::CHECKBOX_PARAMS as $param) {
            $this->params->shift($param);
        }

        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Hostgroup::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction(): void
    {
        foreach (ServiceStateToggle::CHECKBOX_PARAMS as $param) {
            $this->params->shift($param);
        }

        $editor = $this->createSearchEditor(HostgroupsprojecttacticalSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
