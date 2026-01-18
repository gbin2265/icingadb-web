<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

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

class HostgroupsprojecttacticalController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Host Group Tactical'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        // Read and remove checkbox values from URL params BEFORE filter processing
        // All defaults are OFF - only ON if param exists in URL
        $criticalHostValue = $this->params->shift('checkboxhostcritical') === 'y';
        $hiddenHostValue = $this->params->shift('checkboxhosthidden') === 'y';
        $criticalValue = $this->params->shift('checkboxservicecritical') === 'y';
        $warningValue = $this->params->shift('checkboxservicewarning') === 'y';
        $unknownValue = $this->params->shift('checkboxserviceunknown') === 'y';

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

        // Create toggle with values
        $serviceStateToggle = new ServiceStateToggle(
            $criticalHostValue,
            $hiddenHostValue,
            $criticalValue,
            $warningValue,
            $unknownValue
        );
        $serviceStateToggle->setIdProtector([$this->getRequest(), 'protectId']);
        $serviceStateToggle->handleRequest(ServerRequest::fromGlobals());

        $searchBar = $this->createSearchBar($hostgroups, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam()
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

        // Add controls
        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($serviceStateToggle);
        $this->addControl($searchBar);

        $results = $hostgroups->execute();

        $content = new HostgroupsprojecttacticalTable($results, $db);
        $content->setBaseFilter($filter);

        // Set service state filter based on checkboxes
        $selectedStates = $serviceStateToggle->getSelectedStates();
        $content->setServiceStateFilter($selectedStates);

        // Set whether to show critical hosts
        $content->setShowCriticalHosts($serviceStateToggle->isCriticalHostChecked());

        // Set whether to hide hosts without services
        $content->setHideHostsWithoutServices($serviceStateToggle->isHiddenHostChecked());

        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(30);
    }

    public function completeAction()
    {
        // Remove checkbox params before filter processing
        $this->params->shift('checkboxhostcritical');
        $this->params->shift('checkboxhosthidden');
        $this->params->shift('checkboxservicecritical');
        $this->params->shift('checkboxservicewarning');
        $this->params->shift('checkboxserviceunknown');

        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Hostgroup::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        // Remove checkbox params before filter processing
        $this->params->shift('checkboxhostcritical');
        $this->params->shift('checkboxhosthidden');
        $this->params->shift('checkboxservicecritical');
        $this->params->shift('checkboxservicewarning');
        $this->params->shift('checkboxserviceunknown');

        $editor = $this->createSearchEditor(HostgroupsprojecttacticalSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
