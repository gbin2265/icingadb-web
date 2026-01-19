<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Model\ServicegroupsprojecttreeSummary;
use Icinga\Module\Icingadb\Model\ServicestateSummary;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Orm\ResultSet;
use ipl\Sql\Connection;
use ipl\Stdlib\Filter;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;
use ipl\Web\Widget\Link;
use ipl\Web\Widget\StateBall;

class ServicegroupsprojecttreeTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'servicegroupsprojecttree-table'];

    /** @var ResultSet */
    protected $data;

    /** @var Connection */
    protected $db;

    /** @var Filter\Rule|null */
    protected $baseFilter;

    /** @var string|null */
    protected $emptyStateMessage;

    /**
     * Create a new ServicegroupsprojecttreeTable
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
            $this->addHtml($this->createServicegroupItem($item));
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
     * Create a servicegroup item with tree
     *
     * @param ServicegroupsprojecttreeSummary $item
     *
     * @return BaseHtmlElement
     */
    protected function createServicegroupItem(ServicegroupsprojecttreeSummary $item): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'servicegroup-tree-item']));

        // Header with servicegroup name and statistics
        $header = new HtmlElement('div', Attributes::create(['class' => 'servicegroup-tree-header']));

        $link = new Link(
            $item->display_name,
            Links::servicegroup($item),
            [
                'class' => 'subject',
                'data-base-target' => '_main',
                'title' => sprintf(
                    $this->translate('List all services in the group "%s"'),
                    $item->display_name
                )
            ]
        );

        if ($this->baseFilter !== null) {
            $link->getUrl()->setFilter($this->baseFilter);
        }

        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'servicegroup-tree-name']));
        $nameContainer->addHtml($link);
        $nameContainer->addHtml(new HtmlElement(
            'span',
            Attributes::create(['class' => 'servicegroup-tree-caption']),
            Text::create($item->name)
        ));

        $header->addHtml($nameContainer);

        // Add statistics
        $statsContainer = new HtmlElement('div', Attributes::create([
            'class' => 'servicegroup-tree-stats',
            'data-base-target' => '_next'
        ]));

        $serviceStats = (new ServiceStatistics($item))
            ->setBaseFilter(Filter::equal('servicegroup.name', $item->name));

        if ($this->baseFilter !== null) {
            $serviceStats->setBaseFilter(Filter::all($serviceStats->getBaseFilter(), $this->baseFilter));
        }

        $statsContainer->addHtml($serviceStats);
        $header->addHtml($statsContainer);

        $container->addHtml($header);

        // Add tree content with service summaries
        $treeContent = $this->createTreeContent($item);
        if ($treeContent !== null) {
            $container->addHtml($treeContent);
        }

        return $container;
    }

    /**
     * Create tree content with services and their hosts
     *
     * @param ServicegroupsprojecttreeSummary $item
     *
     * @return BaseHtmlElement|null
     */
    protected function createTreeContent(ServicegroupsprojecttreeSummary $item): ?BaseHtmlElement
    {
        // Get problem services grouped by name
        $serviceGroups = $this->getServiceGroups($item->name);

        if (empty($serviceGroups)) {
            return null;
        }

        $tree = new HtmlElement('div', Attributes::create(['class' => 'servicegroup-tree-content']));

        foreach ($serviceGroups as $serviceName => $serviceData) {
            // Service header line
            $serviceItem = $this->createServiceItem($serviceData, $item->name);
            $tree->addHtml($serviceItem);

            // Hosts underneath this service
            foreach ($serviceData['hosts'] as $host) {
                $hostItem = $this->createHostItem($host, $serviceData['name']);
                $tree->addHtml($hostItem);
            }
        }

        return $tree;
    }

    /**
     * Create a service item (like a servicegroup header with statistics)
     *
     * @param array $serviceData
     * @param string $servicegroupName
     *
     * @return BaseHtmlElement
     */
    protected function createServiceItem(array $serviceData, string $servicegroupName): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'tree-service-container']));

        $item = new HtmlElement('div', Attributes::create(['class' => 'service-tree-header']));

        // Service name link - include baseFilter
        $url = Url::fromPath('icingadb/services');
        
        // Build filter combining service filter with baseFilter
        $serviceFilter = Filter::all(
            Filter::equal('servicegroup.name', $servicegroupName),
            Filter::equal('service.name', $serviceData['name'])
        );

        if ($this->baseFilter !== null) {
            $serviceFilter = Filter::all($serviceFilter, $this->baseFilter);
        }

        $url = $url->setQueryString(QueryString::render($serviceFilter));

        $serviceLink = new Link(
            $serviceData['display_name'],
            $url,
            [
                'class' => 'subject',
                'data-base-target' => '_next',
                'title' => sprintf(
                    $this->translate('List all instances of service "%s"'),
                    $serviceData['display_name']
                )
            ]
        );

        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'service-tree-name']));
        $nameContainer->addHtml($serviceLink);

        $item->addHtml($nameContainer);

        // Add statistics using ServiceStatistics widget with correct data
        $statsContainer = new HtmlElement('div', Attributes::create([
            'class' => 'service-tree-stats',
            'data-base-target' => '_next'
        ]));

        // Get correct stats for this service name in this servicegroup
        $serviceStats = $this->getServiceStatsByName($servicegroupName, $serviceData['name']);
        
        if ($serviceStats !== null) {
            $statsWidget = (new ServiceStatistics($serviceStats))
                ->setBaseFilter($serviceFilter);

            $statsContainer->addHtml($statsWidget);
            $item->addHtml($statsContainer);
        }

        $container->addHtml($item);

        return $container;
    }

    /**
     * Get service statistics for a specific service name in a servicegroup
     *
     * @param string $servicegroupName
     * @param string $serviceName
     *
     * @return object|null
     */
    protected function getServiceStatsByName(string $servicegroupName, string $serviceName): ?object
    {
        $query = ServicestateSummary::on($this->db);
        
        $query->filter(Filter::all(
            Filter::equal('servicegroup.name', $servicegroupName),
            Filter::equal('service.name', $serviceName)
        ));

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        $result = $query->first();

        if ($result === null || $result->services_total === null || $result->services_total === 0) {
            return null;
        }

        return $result;
    }

    /**
     * Create a host item (links to service on this host)
     *
     * @param object $host
     * @param string $serviceName
     *
     * @return BaseHtmlElement
     */
    protected function createHostItem(object $host, string $serviceName): BaseHtmlElement
    {
        $container = new HtmlElement('div', Attributes::create(['class' => 'tree-host-container']));

        $item = new HtmlElement('div', Attributes::create(['class' => 'host-item']));

        // State ball based on host state
        $hostState = $host->state->soft_state == 1 ? 'down' : 'up';
        $stateBall = new StateBall($hostState, StateBall::SIZE_MEDIUM);

        // Host name link - goes to service on this host
        $url = Url::fromPath('icingadb/service', [
            'name' => $serviceName,
            'host.name' => $host->name
        ]);

        $hostLink = new Link(
            $host->display_name,
            $url,
            [
                'class' => 'subject',
                'data-base-target' => '_next',
                'title' => sprintf(
                    $this->translate('Show service "%s" on host "%s"'),
                    $serviceName,
                    $host->display_name
                )
            ]
        );

        $item->addHtml($stateBall);
        $item->addHtml($hostLink);

        $container->addHtml($item);

        return $container;
    }

    /**
     * Get services grouped by name with their hosts
     *
     * @param string $servicegroupName
     *
     * @return array
     */
    protected function getServiceGroups(string $servicegroupName): array
    {
        $query = $this->getProblemServicesQuery($servicegroupName);
        $problemServices = $query->execute();

        $serviceGroups = [];
        foreach ($problemServices as $service) {
            $serviceName = $service->name;

            if (! isset($serviceGroups[$serviceName])) {
                $serviceGroups[$serviceName] = [
                    'name'                        => $service->name,
                    'display_name'                => $service->display_name,
                    'worst_state'                 => 'ok',
                    'hosts'                       => [],
                    'services_critical_unhandled' => 0,
                    'services_warning_unhandled'  => 0,
                    'services_unknown_unhandled'  => 0,
                    'services_ok'                 => 0,
                    'services_total'              => 0
                ];
            }

            $serviceGroups[$serviceName]['services_total']++;

            // Track state counts
            $state = $service->state->soft_state;
            if ($state == 2) {
                $serviceGroups[$serviceName]['services_critical_unhandled']++;
                $serviceGroups[$serviceName]['worst_state'] = 'critical';
            } elseif ($state == 1) {
                $serviceGroups[$serviceName]['services_warning_unhandled']++;
                if ($serviceGroups[$serviceName]['worst_state'] != 'critical') {
                    $serviceGroups[$serviceName]['worst_state'] = 'warning';
                }
            } elseif ($state == 3) {
                $serviceGroups[$serviceName]['services_unknown_unhandled']++;
                if (! in_array($serviceGroups[$serviceName]['worst_state'], ['critical', 'warning'])) {
                    $serviceGroups[$serviceName]['worst_state'] = 'unknown';
                }
            } elseif ($state == 0) {
                $serviceGroups[$serviceName]['services_ok']++;
            }

            // Add host if not already added
            $hostName = $service->host->name;
            if (! isset($serviceGroups[$serviceName]['hosts'][$hostName])) {
                $serviceGroups[$serviceName]['hosts'][$hostName] = $service->host;
            }
        }

        // Sort by display_name
        uasort($serviceGroups, function ($a, $b) {
            return strcasecmp($a['display_name'], $b['display_name']);
        });

        return $serviceGroups;
    }

    /**
     * Get query for problem services (CRITICAL, WARNING, UNKNOWN, unhandled) for a servicegroup
     *
     * @param string $servicegroupName
     *
     * @return \ipl\Orm\Query
     */
    protected function getProblemServicesQuery(string $servicegroupName)
    {
        $query = Service::on($this->db)
            ->with(['state', 'state.last_comment', 'icon_image', 'host', 'host.state', 'servicegroup']);
        $query->setResultSetClass(VolatileStateResults::class);
        $query->filter(Filter::all(
                Filter::equal('servicegroup.name', $servicegroupName),
                Filter::any(
                    Filter::equal('state.soft_state', 1), // WARNING
                    Filter::equal('state.soft_state', 2), // CRITICAL
                    Filter::equal('state.soft_state', 3)  // UNKNOWN
                ),
                Filter::equal('state.is_handled', 'n'),
                Filter::equal('state.is_reachable', 'y')
            ))
            ->limit(1000);

        if ($this->baseFilter !== null) {
            $query->filter($this->baseFilter);
        }

        return $query;
    }
}
