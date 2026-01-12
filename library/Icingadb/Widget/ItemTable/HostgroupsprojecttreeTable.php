<?php

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\HostgroupsprojecttreeSummary;
use Icinga\Module\Icingadb\Model\Service;
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

class HostgroupsprojecttreeTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'hostgroupsprojecttree-table'];

    /** @var ResultSet */
    protected $data;

    /** @var Connection */
    protected $db;

    /** @var Filter\Rule|null */
    protected $baseFilter;

    /** @var string|null */
    protected $emptyStateMessage;

    /**
     * Create a new HostgroupsprojecttreeTable
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
     * Create a hostgroup item with tree
     *
     * @param HostgroupsprojecttreeSummary $item
     *
     * @return BaseHtmlElement
     */
    protected function createHostgroupItem(HostgroupsprojecttreeSummary $item): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tree-item']));

        // Header with hostgroup name and statistics
        $header = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tree-header']));

        $link = new Link(
            $item->display_name,
            Links::hostgroup($item),
            [
                'class' => 'subject',
                'title' => sprintf(
                    $this->translate('List all hosts in the group "%s"'),
                    $item->display_name
                )
            ]
        );

        if ($this->baseFilter !== null) {
            $link->getUrl()->setFilter($this->baseFilter);
        }

        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tree-name']));
        $nameContainer->addHtml($link);
        $nameContainer->addHtml(new HtmlElement(
            'span',
            Attributes::create(['class' => 'hostgroup-tree-caption']),
            Text::create($item->name)
        ));

        $header->addHtml($nameContainer);

        // Add statistics
        $statsContainer = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tree-stats']));

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

        // Add tree content if there are problems
        $treeContent = $this->createTreeContent($item);
        if ($treeContent !== null) {
            $container->addHtml($treeContent);
        }

        return $container;
    }

    /**
     * Create tree content with problem hosts and services
     *
     * @param HostgroupsprojecttreeSummary $item
     *
     * @return BaseHtmlElement|null
     */
    protected function createTreeContent(HostgroupsprojecttreeSummary $item): ?BaseHtmlElement
    {
        $hasProblems = $item->hosts_down_unhandled > 0
            || $item->services_critical_unhandled > 0
            || $item->services_warning_unhandled > 0
            || $item->services_unknown_unhandled > 0;

        if (! $hasProblems) {
            return null;
        }

        $tree = new HtmlElement('div', Attributes::create(['class' => 'hostgroup-tree-content']));

        // Get problem hosts (DOWN)
        $hostsQuery = $this->getProblemHostsQuery($item->name);
        $problemHosts = $hostsQuery->execute();
        $processedHosts = [];

        foreach ($problemHosts as $host) {
            $processedHosts[$host->name] = true;

            // Create host list with ObjectList
            $hostContainer = new HtmlElement('div', Attributes::create(['class' => 'tree-host-container']));
            $hostList = (new ObjectList([$host]))
                ->setViewMode('minimal')
                ->setDetailActionsDisabled();
            $hostContainer->addHtml($hostList);
            $tree->addHtml($hostContainer);

            // Get problem services for this host
            $servicesQuery = $this->getProblemServicesQuery($host->name);
            $servicesCount = $servicesQuery->count();

            if ($servicesCount > 0) {
                $problemServices = $servicesQuery->execute();
                $serviceContainer = new HtmlElement('div', Attributes::create(['class' => 'tree-service-container']));
                $serviceList = (new ObjectList($problemServices))
                    ->setViewMode('minimal')
                    ->setDetailActionsDisabled();
                $serviceContainer->addHtml($serviceList);
                $tree->addHtml($serviceContainer);
            }
        }

        // Get hosts that are UP but have problem services
        $allHostsQuery = $this->getHostsWithProblemServicesQuery($item->name);
        $hostsWithProblemServices = $allHostsQuery->execute();

        foreach ($hostsWithProblemServices as $host) {
            // Skip hosts we already showed (DOWN hosts)
            if (isset($processedHosts[$host->name])) {
                continue;
            }

            $servicesQuery = $this->getProblemServicesQuery($host->name);
            $servicesCount = $servicesQuery->count();

            if ($servicesCount > 0) {
                $problemServices = $servicesQuery->execute();
                $processedHosts[$host->name] = true;

                // Create host list with ObjectList
                $hostContainer = new HtmlElement('div', Attributes::create(['class' => 'tree-host-container']));
                $hostList = (new ObjectList([$host]))
                    ->setViewMode('minimal')
                    ->setDetailActionsDisabled();
                $hostContainer->addHtml($hostList);
                $tree->addHtml($hostContainer);

                $serviceContainer = new HtmlElement('div', Attributes::create(['class' => 'tree-service-container']));
                $serviceList = (new ObjectList($problemServices))
                    ->setViewMode('minimal')
                    ->setDetailActionsDisabled();
                $serviceContainer->addHtml($serviceList);
                $tree->addHtml($serviceContainer);
            }
        }

        return $tree;
    }

    /**
     * Get query for problem hosts (DOWN, unhandled) for a hostgroup
     *
     * @param string $hostgroupName
     *
     * @return \ipl\Orm\Query
     */
    protected function getProblemHostsQuery(string $hostgroupName)
    {
        $query = Host::on($this->db)
            ->with(['state', 'state.last_comment', 'icon_image', 'hostgroup']);
        $query->setResultSetClass(VolatileStateResults::class);
        $query->filter(Filter::all(
                Filter::equal('hostgroup.name', $hostgroupName),
                Filter::equal('state.soft_state', 1),
                Filter::equal('state.is_handled', 'n'),
                Filter::equal('state.is_reachable', 'y')
            ))
            ->limit(100);

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query;
    }

    /**
     * Get query for hosts with problem services for a hostgroup
     *
     * @param string $hostgroupName
     *
     * @return \ipl\Orm\Query
     */
    protected function getHostsWithProblemServicesQuery(string $hostgroupName)
    {
        $query = Host::on($this->db)
            ->with(['state', 'state.last_comment', 'icon_image', 'hostgroup']);
        $query->setResultSetClass(VolatileStateResults::class);
        $query->filter(Filter::all(
                Filter::equal('hostgroup.name', $hostgroupName)
            ))
            ->limit(100);

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query;
    }

    /**
     * Get query for problem services (CRITICAL, WARNING, UNKNOWN, unhandled) for a host
     *
     * @param string $hostName
     *
     * @return \ipl\Orm\Query
     */
    protected function getProblemServicesQuery(string $hostName)
    {
        $query = Service::on($this->db)
            ->with(['state', 'state.last_comment', 'icon_image', 'host', 'host.state']);
        $query->setResultSetClass(VolatileStateResults::class);
        $query->filter(Filter::all(
                Filter::equal('host.name', $hostName),
                Filter::any(
                    Filter::equal('state.soft_state', 1), // WARNING
                    Filter::equal('state.soft_state', 2), // CRITICAL
                    Filter::equal('state.soft_state', 3)  // UNKNOWN
                ),
                Filter::equal('state.is_handled', 'n'),
                Filter::equal('state.is_reachable', 'y')
            ))
            ->limit(100);

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query;
    }
}
