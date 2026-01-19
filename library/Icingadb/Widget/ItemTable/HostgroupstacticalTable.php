<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HostgroupstacticalSummary;
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
 * Hostgroup tactical table widget
 *
 * @since 1.3.0 GeBi custom view
 */
class HostgroupstacticalTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'hostgroupstactical-table'];

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

    /** @var bool Whether to filter hosts by having services to display */
    protected bool $filterHostsByServices = false;

    /**
     * Create a new HostgroupstacticalTable
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
     * @param HostgroupstacticalSummary $item
     *
     * @return BaseHtmlElement
     */
    protected function createHostgroupItem(HostgroupstacticalSummary $item): BaseHtmlElement
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
     * @param HostgroupstacticalSummary $item
     *
     * @return BaseHtmlElement|null
     */
    protected function createHostsContent(HostgroupstacticalSummary $item): ?BaseHtmlElement
    {
        $hosts = $this->getHostsForHostgroup($item->name);

        $content = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tactical-content']));
        $hasHosts = false;

        foreach ($hosts as $host) {
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
        if ($this->hostStateFilter === null && ! $this->filterHostsByServices) {
            return true;
        }

        $matchesStateFilter = false;
        if ($this->hostStateFilter !== null) {
            $matchesStateFilter = in_array($host->state->soft_state, $this->hostStateFilter, true);
        }

        $hasServices = false;
        if ($this->filterHostsByServices && $host->state->soft_state === ServiceStateToggle::HOST_STATE_UP) {
            $services = $this->getServicesForHost($host->name);
            foreach ($services as $service) {
                $hasServices = true;
                break;
            }
        }

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
     * Create a tactical line for a single host
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

        // Services list (only for UP hosts)
        if ($host->state->soft_state === ServiceStateToggle::HOST_STATE_UP) {
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

        $servicesArray = [];
        foreach ($services as $service) {
            $servicesArray[] = $service;
        }

        if (empty($servicesArray)) {
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

        if ($this->hostStateFilter !== null && ! $this->filterHostsByServices) {
            $stateFilters = [];
            foreach ($this->hostStateFilter as $state) {
                $stateFilters[] = Filter::equal('state.soft_state', $state);
            }
            $query->filter(Filter::any(...$stateFilters));

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

        if ($this->serviceStateFilter !== null && ! empty($this->serviceStateFilter)) {
            $stateFilters = [];
            foreach ($this->serviceStateFilter as $state) {
                $stateFilters[] = Filter::equal('state.soft_state', $state);
            }
            $query->filter(Filter::any(...$stateFilters));

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

        if ($result === null || $result->services_total === null || $result->services_total === 0) {
            return null;
        }

        return $result;
    }
}
