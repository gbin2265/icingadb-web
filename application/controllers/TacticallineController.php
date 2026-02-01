<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view - based on official TacticalController pattern */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\HoststateSummary;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\Detail\HostStatistics;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\HtmlString;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Control\ViewModeSwitcher;

class TacticallineController extends Controller
{
    public function indexAction()
    {
        $this->addTitleTab(t('Tactical Line'));

        $db = $this->getDb();

        // Use official Summary models (same as TacticalController)
        $hoststateSummary = HoststateSummary::on($db);
        $servicestateSummary = ServicestateSummary::on($db);

        // Handle search (same pattern as TacticalController)
        $this->handleSearchRequest($servicestateSummary, [
            'host.name_ci',
            'host.display_name',
            'host.address',
            'host.address6'
        ]);

        $searchBar = $this->createSearchBar($servicestateSummary);
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

        $this->filter($hoststateSummary, $filter);
        $this->filter($servicestateSummary, $filter);

        yield $this->export($hoststateSummary, $servicestateSummary);

        $this->addControl($searchBar);

        // Add inline CSS for tactical line layout
        $this->addContent(new HtmlString($this->getInlineCss()));

        // Create tactical line container with statistics (NOT donuts)
        $content = new HtmlElement(
            'div',
            Attributes::create([
                'class' => 'tacticalline-container',
                'data-base-target' => '_next'
            ])
        );

        // Get summary results (use empty fallback if null)
        $hostSummary = $hoststateSummary->first() ?? $this->getEmptyHostSummary();
        $serviceSummary = $servicestateSummary->first() ?? $this->getEmptyServiceSummary();

        // Fix null values to 0 (prevents crash in shortenAmount)
        if ($hostSummary->hosts_total === null) {
            $hostSummary = $this->getEmptyHostSummary();
        }
        if ($serviceSummary->services_total === null) {
            $serviceSummary = $this->getEmptyServiceSummary();
        }

        $content->addHtml(
            (new HostStatistics($hostSummary))
                ->setBaseFilter($filter),
            (new ServiceStatistics($serviceSummary))
                ->setBaseFilter($filter)
        );

        $this->addContent($content);

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }

        $this->setAutorefreshInterval(10);
    }

    /**
     * Get inline CSS for tactical line layout
     *
     * @return string
     */
    protected function getInlineCss(): string
    {
        return <<<'CSS'
<style>
.tacticalline-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    padding: 0.5em 1em;
    border: 1px solid #5c5c5c;
    border-radius: 0.25em;
    background: transparent;
    overflow: hidden;
}

.tacticalline-container .object-statistics {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
}

.tacticalline-container .object-statistics > li {
    margin: 0;
    padding: 0;
}

.tacticalline-container .object-statistics > li:not(:last-child) {
    margin-right: 1em;
}

.tacticalline-container .object-statistics .object-statistics-graph svg {
    width: 2em;
    height: 2em;
}
</style>
CSS;
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(ServicestateSummary::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(ServicestateSummary::on($this->getDb()), [
            LimitControl::DEFAULT_LIMIT_PARAM,
            SortControl::DEFAULT_SORT_PARAM,
            ViewModeSwitcher::DEFAULT_VIEW_MODE_PARAM
        ]);

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }

    /**
     * Get an empty host summary with all values set to 0
     *
     * @return object
     */
    protected function getEmptyHostSummary(): object
    {
        return (object) [
            'hosts_total' => 0,
            'hosts_up' => 0,
            'hosts_down_handled' => 0,
            'hosts_down_unhandled' => 0,
            'hosts_pending' => 0,
            'hosts_acknowledged' => 0,
            'hosts_problems_unacknowledged' => 0
        ];
    }

    /**
     * Get an empty service summary with all values set to 0
     *
     * @return object
     */
    protected function getEmptyServiceSummary(): object
    {
        return (object) [
            'services_total' => 0,
            'services_ok' => 0,
            'services_warning_handled' => 0,
            'services_warning_unhandled' => 0,
            'services_critical_handled' => 0,
            'services_critical_unhandled' => 0,
            'services_unknown_handled' => 0,
            'services_unknown_unhandled' => 0,
            'services_pending' => 0
        ];
    }
}

