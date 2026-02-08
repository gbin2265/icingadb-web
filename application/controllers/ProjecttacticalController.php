<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view - per-host tactical overview */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Model\ProjecttacticalSummary;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\HostStatistics;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use Icinga\Module\Icingadb\Widget\ItemTable\ProjecttacticalTable;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;

/**
 * Controller for project tactical view
 *
 * Shows a list of all hosts, each as a row with:
 * - Host state ball + host name
 * - Tactical line (HostStatistics + ServiceStatistics donuts and badges)
 *
 * @since 1.3.0 GeBi custom view
 */
class ProjecttacticalController extends Controller
{
    public function init(): void
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction(): \Generator
    {
        $this->addTitleTab(t('Project Tactical'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        // Shift projectname parameter before filter processing
        $projectName = $this->params->shift('projectname');

        $hosts = ProjecttacticalSummary::on($db);

        $this->handleSearchRequest($hosts);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hosts);

        $sortControl = $this->createSortControl(
            $hosts,
            [
                'display_name'                     => t('Name'),
                'services_critical_unhandled desc'  => t('Services Critical Unhandled'),
                'services_warning_unhandled desc'   => t('Services Warning Unhandled'),
                'services_total desc'               => t('Total Services')
            ],
            ['display_name']
        );

        $searchBar = $this->createSearchBar($hosts, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam(),
            'projectname'
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

        $this->filter($hosts, $filter);

        $hosts->peekAhead($compact);

        yield $this->export($hosts);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        if ($projectName === null || $projectName === '') {
            $this->addControl($searchBar);
        }

        $results = $hosts->execute();

        // Project title box (only visible if projectname parameter is set)
        if ($projectName !== null && $projectName !== '') {
            $projectTitle = new HtmlElement(
                'div',
                Attributes::create(['class' => 'projecttactical-title']),
                new HtmlElement(
                    'span',
                    Attributes::create(['class' => 'projecttactical-title-text']),
                    Text::create(t('Project') . ' ' . $projectName)
                )
            );

            $this->addContent($projectTitle);
        }

        // Tactical line summary (respects the same filter)
        $hoststateSummary = HoststateSummary::on($db);
        $servicestateSummary = ServicestateSummary::on($db);

        $this->filter($hoststateSummary, $filter);
        $this->filter($servicestateSummary, $filter);

        $hostSummary = $hoststateSummary->first();
        $serviceSummary = $servicestateSummary->first();

        // Show tactical line if we have any hosts OR services
        $hasHosts = $hostSummary !== null && $hostSummary->hosts_total !== null;
        $hasServices = $serviceSummary !== null && $serviceSummary->services_total !== null;

        if ($hasHosts || $hasServices) {
            if (! $hasHosts) {
                $hostSummary = (object) [
                    'hosts_total' => 0, 'hosts_up' => 0,
                    'hosts_down_handled' => 0, 'hosts_down_unhandled' => 0, 'hosts_pending' => 0
                ];
            }

            if (! $hasServices) {
                $serviceSummary = (object) [
                    'services_total' => 0, 'services_ok' => 0,
                    'services_warning_handled' => 0, 'services_warning_unhandled' => 0,
                    'services_critical_handled' => 0, 'services_critical_unhandled' => 0,
                    'services_unknown_handled' => 0, 'services_unknown_unhandled' => 0,
                    'services_pending' => 0
                ];
            }

            $tacticalLine = new HtmlElement(
                'div',
                Attributes::create([
                    'class' => 'projecttactical-summary',
                    'data-base-target' => '_next'
                ])
            );

            $tacticalLine->addHtml(
                (new HostStatistics($hostSummary))
                    ->setBaseFilter($filter),
                (new ServiceStatistics($serviceSummary))
                    ->setBaseFilter($filter)
            );

            $this->addContent($tacticalLine);
        }

        $content = new ProjecttacticalTable($results);
        $content->setBaseFilter($filter);
        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(30);
    }

    public function completeAction(): void
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Host::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction(): void
    {
        $editor = $this->createSearchEditor(ProjecttacticalSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
