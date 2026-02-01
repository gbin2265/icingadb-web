<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HostgroupsprojecttacticalSummary;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Icingadb\Web\Control\ServiceStateToggle;
use Icinga\Module\Icingadb\Widget\Detail\HostStatistics;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use Icinga\Module\Icingadb\Widget\ItemList\ObjectList;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Orm\ResultSet;
use ipl\Sql\Connection;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\Link;
use ipl\Web\Widget\StateBall;

/**
 * Hostgroup project tactical table widget
 *
 * @since 1.3.0 GeBi custom view
 */
class HostgroupsprojecttacticalTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'hostgroupsprojecttactical-table'];

    /** @var ResultSet */
    protected ResultSet $data;

    /** @var Connection */
    protected Connection $db;

    /** @var Filter\Rule|null */
    protected ?Filter\Rule $baseFilter = null;

    /** @var string|null */
    protected ?string $emptyStateMessage = null;

    /** @var int[]|null Filter for service states (0=OK, 1=WARNING, 2=CRITICAL, 3=UNKNOWN) */
    protected ?array $serviceStateFilter = null;

    /** @var int[]|null Filter for host states (0=UP, 1=DOWN) */
    protected ?array $hostStateFilter = null;

    /**
     * Create a new HostgroupsprojecttacticalTable
     *
     * @param ResultSet $data
     * @param Connection $db
     */
    public function __construct(ResultSet $data, Connection $db)
    {
        $this->data = $data;
        $this->db = $db;
    }

    /**
     * Set the base filter
     *
     * @param Filter\Rule|null $filter
     *
     * @return $this
     */
    public function setBaseFilter(?Filter\Rule $filter): self
    {
        $this->baseFilter = $filter;

        return $this;
    }

    /**
     * Get the base filter
     *
     * @return Filter\Rule|null
     */
    public function getBaseFilter(): ?Filter\Rule
    {
        return $this->baseFilter;
    }

    /**
     * Set the service state filter
     *
     * @param int[]|null $states Array of state integers
     *
     * @return $this
     */
    public function setServiceStateFilter(?array $states): self
    {
        $this->serviceStateFilter = $states;

        return $this;
    }

    /**
     * Get the service state filter
     *
     * @return int[]|null
     */
    public function getServiceStateFilter(): ?array
    {
        return $this->serviceStateFilter;
    }

    /**
     * Set the host state filter
     *
     * @param int[]|null $states Array of state integers
     *
     * @return $this
     */
    public function setHostStateFilter(?array $states): self
    {
        $this->hostStateFilter = $states;

        return $this;
    }

    /**
     * Get the host state filter
     *
     * @return int[]|null
     */
    public function getHostStateFilter(): ?array
    {
        return $this->hostStateFilter;
    }

    /**
     * Set the empty state message
     *
     * @param string|null $message
     *
     * @return $this
     */
    public function setEmptyStateMessage(?string $message): self
    {
        $this->emptyStateMessage = $message;

        return $this;
    }

    protected function assemble(): void
    {
        $hasItems = false;

        foreach ($this->data as $item) {
            $hasItems = true;
            $this->addHtml($this->createHostgroupItem($item));
        }

        if (! $hasItems && $this->emptyStateMessage !== null) {
            $this->addHtml(new HtmlElement(
                'div',
                Attributes::create(['class' => 'empty-state']),
                Text::create($this->emptyStateMessage)
            ));
        }
    }

    /**
     * Create a hostgroup item with tactical lines per host
     *
     * @param HostgroupsprojecttacticalSummary $item
     *
     * @return BaseHtmlElement
     */
    protected function createHostgroupItem(HostgroupsprojecttacticalSummary $item): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-item']));

        // Header with hostgroup name and statistics
        $header = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-header']));

        $link = new Link(
            $item->display_name,
            Links::hostgroup($item),
            [
                'class' => 'subject',
                'data-base-target' => '_main',
                'title' => sprintf(
                    $this->translate('List all hosts in the group "%s"'),
                    $item->display_name
                )
            ]
        );

        if ($this->baseFilter !== null) {
            $link->getUrl()->setFilter($this->baseFilter);
        }

        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-name']));
        $nameContainer->addHtml($link);
        $nameContainer->addHtml(new HtmlElement(
            'span',
            Attributes::create(['class' => 'hostgroup-tactical-caption']),
            Text::create($item->name)
        ));

        $header->addHtml($nameContainer);

        // Add hostgroup statistics
        $statsContainer = new HtmlElement('div', Attributes::create([
            'class' => 'hostgroup-tactical-stats',
            'data-base-target' => '_next'
        ]));

        $hostStats = (new HostStatistics($item))
            ->setBaseFilter(Filter::equal('hostgroup.name', $item->name));

        $serviceStats = (new ServiceStatistics($item))
            ->setBaseFilter(Filter::equal('hostgroup.name', $item->name));

        if ($this->baseFilter !== null) {
            $hostStats->setBaseFilter(Filter::all($hostStats->getBaseFilter(), $this->baseFilter));
            $serviceStats->setBaseFilter(Filter::all($serviceStats->getBaseFilter(), $this->baseFilter));
        }

        $statsContainer->addHtml($hostStats, $serviceStats);
        $header->addHtml($statsContainer);

        $container->addHtml($header);

        // Add per-host tactical lines
        $hostsContent = $this->createHostsContent($item);
        if ($hostsContent !== null) {
            $container->addHtml($hostsContent);
        }

        return $container;
    }

    /**
     * Create tactical lines for each host in the hostgroup
     *
     * @param HostgroupsprojecttacticalSummary $item
     *
     * @return BaseHtmlElement|null
     */
    protected function createHostsContent(HostgroupsprojecttacticalSummary $item): ?BaseHtmlElement
    {
        // Early exit: check Summary data to avoid unnecessary queries
        if (! $this->hasMatchingItems($item)) {
            return null;
        }

        $content = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-content']));
        $hostsToShow = [];
        $servicesByHost = [];

        // 1. If host filter active: get hosts matching that filter
        if ($this->hostStateFilter !== null) {
            $hosts = $this->getHostsByStateFilter($item->name);
            foreach ($hosts as $host) {
                $hostsToShow[$host->name] = $host;
            }
        }

        // 2. If service filter active: get services matching that filter, group by host
        if ($this->serviceStateFilter !== null) {
            $services = $this->getServicesByStateFilter($item->name);
            foreach ($services as $service) {
                $hostName = $service->host->name;
                // Add host to show list if not already there
                if (! isset($hostsToShow[$hostName])) {
                    $hostsToShow[$hostName] = $service->host;
                }
                // Collect services per host
                if (! isset($servicesByHost[$hostName])) {
                    $servicesByHost[$hostName] = [];
                }
                $servicesByHost[$hostName][] = $service;
            }
        }

        if (empty($hostsToShow)) {
            return null;
        }

        // Sort by severity (DOWN first) then by name
        uasort($hostsToShow, function ($a, $b) {
            if ($a->state->severity !== $b->state->severity) {
                return $b->state->severity <=> $a->state->severity;
            }
            return $a->display_name <=> $b->display_name;
        });

        foreach ($hostsToShow as $hostName => $host) {
            // Pass services if available (from service filter), otherwise null
            $hostServices = $servicesByHost[$hostName] ?? null;
            $content->addHtml($this->createHostTacticalLine($host, $hostServices));
        }

        return $content;
    }

    /**
     * Check if the hostgroup has items that match the current filters
     *
     * Uses Summary data to avoid unnecessary database queries
     *
     * @param HostgroupsprojecttacticalSummary $item
     *
     * @return bool
     */
    protected function hasMatchingItems(HostgroupsprojecttacticalSummary $item): bool
    {
        // If no filters are active, nothing to display in detail view
        if ($this->hostStateFilter === null && $this->serviceStateFilter === null) {
            return false;
        }

        $hasMatchingHosts = false;
        $hasMatchingServices = false;

        // Check host state filter
        if ($this->hostStateFilter !== null) {
            $matchingHosts = 0;

            if (in_array(ServiceStateToggle::HOST_STATE_UP, $this->hostStateFilter, true)) {
                $matchingHosts += (int) $item->hosts_up;
            }

            if (in_array(ServiceStateToggle::HOST_STATE_DOWN, $this->hostStateFilter, true)) {
                // We filter on unhandled hosts only
                $matchingHosts += (int) $item->hosts_down_unhandled;
            }

            $hasMatchingHosts = $matchingHosts > 0;
        }

        // Check service state filter
        if ($this->serviceStateFilter !== null) {
            $matchingServices = 0;

            if (in_array(ServiceStateToggle::SERVICE_STATE_CRITICAL, $this->serviceStateFilter, true)) {
                $matchingServices += (int) $item->services_critical_unhandled;
            }

            if (in_array(ServiceStateToggle::SERVICE_STATE_WARNING, $this->serviceStateFilter, true)) {
                $matchingServices += (int) $item->services_warning_unhandled;
            }

            if (in_array(ServiceStateToggle::SERVICE_STATE_UNKNOWN, $this->serviceStateFilter, true)) {
                $matchingServices += (int) $item->services_unknown_unhandled;
            }

            $hasMatchingServices = $matchingServices > 0;
        }

        // OR logic: show if host filter matches OR service filter matches
        if ($this->hostStateFilter !== null && $this->serviceStateFilter !== null) {
            return $hasMatchingHosts || $hasMatchingServices;
        }

        // Only host filter active
        if ($this->hostStateFilter !== null) {
            return $hasMatchingHosts;
        }

        // Only service filter active
        return $hasMatchingServices;
    }

    /**
     * Get hosts for a hostgroup filtered by host state
     *
     * @param string $hostgroupName
     *
     * @return ResultSet
     */
    protected function getHostsByStateFilter(string $hostgroupName): ResultSet
    {
        $query = Host::on($this->db)
            ->with(['state', 'hostgroup'])
            ->setResultSetClass(VolatileStateResults::class);

        $query->filter(Filter::equal('hostgroup.name', $hostgroupName));

        // Filter by selected host states
        $stateFilters = [];
        foreach ($this->hostStateFilter as $state) {
            $stateFilters[] = Filter::equal('state.soft_state', $state);
        }
        $query->filter(Filter::any(...$stateFilters));

        // Only unhandled hosts
        $query->filter(Filter::equal('state.is_acknowledged', 'n'));
        $query->filter(Filter::equal('state.in_downtime', 'n'));
        $query->filter(Filter::equal('state.is_handled', 'n'));

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query->execute();
    }

    /**
     * Get services for a hostgroup filtered by service state
     *
     * @param string $hostgroupName
     *
     * @return ResultSet
     */
    protected function getServicesByStateFilter(string $hostgroupName): ResultSet
    {
        $query = Service::on($this->db)
            ->with(['state', 'host', 'host.state', 'host.hostgroup'])
            ->setResultSetClass(VolatileStateResults::class);

        $query->filter(Filter::equal('host.hostgroup.name', $hostgroupName));

        // Filter by selected service states
        $stateFilters = [];
        foreach ($this->serviceStateFilter as $state) {
            $stateFilters[] = Filter::equal('state.soft_state', $state);
        }
        $query->filter(Filter::any(...$stateFilters));

        // Only unhandled services
        $query->filter(Filter::equal('state.is_acknowledged', 'n'));
        $query->filter(Filter::equal('state.in_downtime', 'n'));
        $query->filter(Filter::equal('state.is_handled', 'n'));

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query->execute();
    }

    /**
     * Create a tactical line for a single host
     *
     * @param Host $host
     * @param array|null $services Pre-fetched services for this host (from service filter)
     *
     * @return BaseHtmlElement
     */
    protected function createHostTacticalLine(Host $host, ?array $services = null): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'host-tactical-container']));
        $line = new HtmlElement('div', Attributes::create(['class' => 'host-tactical-line']));

        // Host name with state ball
        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'host-tactical-name']));

        $stateBall = new StateBall($host->state->getStateText(), StateBall::SIZE_MEDIUM);
        if ($host->state->is_handled) {
            $stateBall->addHtml(new HtmlElement('i', Attributes::create(['class' => 'icon icon-ack'])));
        }

        $hostLink = new Link(
            $host->display_name,
            Links::host($host),
            [
                'class' => 'subject',
                'data-base-target' => '_next',
                'title' => sprintf($this->translate('Show host %s'), $host->display_name)
            ]
        );

        $nameContainer->addHtml($stateBall, $hostLink);
        $line->addHtml($nameContainer);

        // Service statistics
        $serviceStatsSummary = $this->getServiceStatsForHost($host->name);

        if ($serviceStatsSummary !== null) {
            $statsContainer = new HtmlElement('div', Attributes::create([
                'class' => 'host-tactical-stats',
                'data-base-target' => '_next'
            ]));

            $serviceStats = (new ServiceStatistics($serviceStatsSummary))
                ->setBaseFilter(Filter::equal('host.name', $host->name));

            if ($this->baseFilter !== null) {
                $serviceStats->setBaseFilter(Filter::all($serviceStats->getBaseFilter(), $this->baseFilter));
            }

            $statsContainer->addHtml($serviceStats);
            $line->addHtml($statsContainer);
        }

        $container->addHtml($line);

        // Services list (only if services were passed - means host came from service filter)
        if ($services !== null && ! empty($services)) {
            $serviceContainer = new HtmlElement('div', Attributes::create(['class' => 'host-services-container']));
            $serviceList = (new ObjectList($services))
                ->setViewMode('minimal')
                ->setDetailActionsDisabled();
            $serviceContainer->addHtml($serviceList);
            $container->addHtml($serviceContainer);
        }

        return $container;
    }

    /**
     * Get service state summary for a host
     *
     * @param string $hostName
     *
     * @return ServicestateSummary|null
     */
    protected function getServiceStatsForHost(string $hostName): ?ServicestateSummary
    {
        $query = ServicestateSummary::on($this->db);

        $query->filter(Filter::equal('host.name', $hostName));

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        $result = $query->first();

        if ($result === null || $result->services_total === null || $result->services_total === 0) {
            return null;
        }

        return $result;
    }
}
