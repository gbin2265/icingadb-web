<?php

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HostgroupsprojecttacticalSummary;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
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

class HostgroupsprojecttacticalTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'hostgroupsprojecttactical-table'];

    /** @var ResultSet */
    protected $data;

    /** @var Connection */
    protected $db;

    /** @var Filter\Rule|null */
    protected $baseFilter;

    /** @var string|null */
    protected $emptyStateMessage;

    /** @var array|null Filter for service states to show (e.g. [2] for critical only) */
    protected $serviceStateFilter;

    /** @var array|null Filter for host states to show (e.g. [0] for OK, [1] for DOWN) */
    protected $hostStateFilter;

    /** @var bool Whether to filter hosts by having services to display */
    protected $filterHostsByServices = false;

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
     * States: 0 = OK, 1 = WARNING, 2 = CRITICAL, 3 = UNKNOWN, 99 = PENDING
     *
     * @param array|null $states Array of state integers to show (e.g. [2] for critical only)
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
     * @return array|null
     */
    public function getServiceStateFilter(): ?array
    {
        return $this->serviceStateFilter;
    }

    /**
     * Set the host state filter
     *
     * States: 0 = UP (OK), 1 = DOWN (Critical)
     * When null, all hosts are shown regardless of state
     *
     * @param array|null $states Array of state integers to show
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
     * @return array|null
     */
    public function getHostStateFilter(): ?array
    {
        return $this->hostStateFilter;
    }

    /**
     * Set whether to filter hosts by having services to display
     *
     * @param bool $filter
     *
     * @return $this
     */
    public function setFilterHostsByServices(bool $filter): self
    {
        $this->filterHostsByServices = $filter;

        return $this;
    }

    /**
     * Get whether to filter hosts by having services to display
     *
     * @return bool
     */
    public function getFilterHostsByServices(): bool
    {
        return $this->filterHostsByServices;
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

    protected function assemble()
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
        $hosts = $this->getHostsForHostgroup($item->name);

        $content = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-content']));
        $hasHosts = false;

        foreach ($hosts as $host) {
            // Check if host should be shown based on filters
            if (! $this->shouldShowHost($host)) {
                continue;
            }

            $hasHosts = true;
            $content->addHtml($this->createHostTacticalLine($host));
        }

        return $hasHosts ? $content : null;
    }

    /**
     * Check if a host should be shown based on the active filters
     *
     * @param Host $host
     *
     * @return bool
     */
    protected function shouldShowHost(Host $host): bool
    {
        // If no filters are active, show all hosts
        if ($this->hostStateFilter === null && ! $this->filterHostsByServices) {
            return true;
        }

        // Check if host matches the state filter (Ok or Critical)
        $matchesStateFilter = false;
        if ($this->hostStateFilter !== null) {
            $matchesStateFilter = in_array($host->state->soft_state, $this->hostStateFilter);
        }

        // Check if host has services to display
        $hasServices = false;
        if ($this->filterHostsByServices) {
            // Only UP hosts can show services
            if ($host->state->soft_state === 0) {
                $services = $this->getServicesForHost($host->name);
                foreach ($services as $service) {
                    $hasServices = true;
                    break;
                }
            }
        }

        // Logic:
        // - If only state filter is active: show hosts matching state
        // - If only services filter is active: show hosts with services
        // - If both are active: show hosts matching state OR hosts with services
        if ($this->hostStateFilter !== null && $this->filterHostsByServices) {
            return $matchesStateFilter || $hasServices;
        } elseif ($this->hostStateFilter !== null) {
            return $matchesStateFilter;
        } elseif ($this->filterHostsByServices) {
            return $hasServices;
        }

        return true;
    }

    /**
     * Create a tactical line for a single host with services underneath
     *
     * @param Host $host
     *
     * @return BaseHtmlElement
     */
    protected function createHostTacticalLine(Host $host): BaseHtmlElement
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

        // Get service statistics for this host
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

        // Add services list (3rd level) only if host is UP (soft_state = 0)
        if ($host->state->soft_state === 0) {
            $servicesContent = $this->createServicesContent($host);
            if ($servicesContent !== null) {
                $container->addHtml($servicesContent);
            }
        }

        return $container;
    }

    /**
     * Create services list for a host
     *
     * @param Host $host
     *
     * @return BaseHtmlElement|null
     */
    protected function createServicesContent(Host $host): ?BaseHtmlElement
    {
        $services = $this->getServicesForHost($host->name);

        $hasServices = false;
        $servicesArray = [];

        foreach ($services as $service) {
            $hasServices = true;
            $servicesArray[] = $service;
        }

        if (! $hasServices) {
            return null;
        }

        $serviceContainer = new HtmlElement('div', Attributes::create(['class' => 'host-services-container']));

        $serviceList = (new ObjectList($servicesArray))
            ->setViewMode('minimal')
            ->setDetailActionsDisabled();

        $serviceContainer->addHtml($serviceList);

        return $serviceContainer;
    }

    /**
     * Get hosts for a hostgroup
     *
     * @param string $hostgroupName
     *
     * @return ResultSet
     */
    protected function getHostsForHostgroup(string $hostgroupName): ResultSet
    {
        $query = Host::on($this->db)
            ->with(['state', 'hostgroup'])
            ->setResultSetClass(VolatileStateResults::class);

        $query->filter(Filter::equal('hostgroup.name', $hostgroupName));

        // When host state filter is active without services filter, apply it at query level
        // But when services filter is also active, we need to check both conditions in PHP
        if ($this->hostStateFilter !== null && ! $this->filterHostsByServices) {
            $stateFilters = [];
            foreach ($this->hostStateFilter as $state) {
                $stateFilters[] = Filter::equal('state.soft_state', $state);
            }
            $query->filter(Filter::any(...$stateFilters));

            // When filtering by state, exclude acknowledged, in downtime, and handled hosts
            $query->filter(Filter::equal('state.is_acknowledged', 'n'));
            $query->filter(Filter::equal('state.in_downtime', 'n'));
            $query->filter(Filter::equal('state.is_handled', 'n'));
        }

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        $query->orderBy('host.state.severity', 'desc')
            ->orderBy('host.display_name', 'asc');

        return $query->execute();
    }

    /**
     * Get services for a host
     *
     * @param string $hostName
     *
     * @return ResultSet
     */
    protected function getServicesForHost(string $hostName): ResultSet
    {
        $query = Service::on($this->db)
            ->with(['state', 'state.last_comment', 'icon_image', 'host', 'host.state'])
            ->setResultSetClass(VolatileStateResults::class);

        $query->filter(Filter::equal('host.name', $hostName));

        // Apply service state filter if set
        if ($this->serviceStateFilter !== null && ! empty($this->serviceStateFilter)) {
            $stateFilters = [];
            foreach ($this->serviceStateFilter as $state) {
                $stateFilters[] = Filter::equal('state.soft_state', $state);
            }
            $query->filter(Filter::any(...$stateFilters));

            // Also filter for unhandled services only
            $query->filter(Filter::equal('state.is_acknowledged', 'n'));
            $query->filter(Filter::equal('state.in_downtime', 'n'));
            $query->filter(Filter::equal('state.is_handled', 'n'));
        }

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        $query->orderBy('service.state.severity', 'desc')
            ->orderBy('service.display_name', 'asc');

        return $query->execute();
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

        // Check if result exists and has valid data
        if ($result === null || $result->services_total === null || $result->services_total === 0) {
            return null;
        }

        return $result;
    }
}
