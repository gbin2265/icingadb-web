<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view - per-host tactical line table */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\HostStates;
use Icinga\Module\Icingadb\Common\Icons;
use Icinga\Module\Icingadb\Common\Links;
use Icinga\Module\Icingadb\Model\ProjecttacticalSummary;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Orm\ResultSet;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;
use ipl\Web\Widget\StateBall;

/**
 * Project tactical table widget
 *
 * Renders a list of hosts, each as a row with:
 * - Host state ball (large, with ack/downtime/flapping icons) + host name (link)
 * - Tactical line: ServiceStatistics (donut + badges)
 *
 * Uses a single query - all host state data comes from the ProjecttacticalSummary model.
 *
 * @since 1.3.0 GeBi custom view
 */
class ProjecttacticalTable extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'projecttactical-table'];

    /** @var ResultSet */
    protected ResultSet $data;

    /** @var Filter\Rule|null */
    protected ?Filter\Rule $baseFilter = null;

    /** @var string|null */
    protected ?string $emptyStateMessage = null;

    /**
     * Create a new ProjecttacticalTable
     *
     * @param ResultSet $data
     */
    public function __construct(ResultSet $data)
    {
        $this->data = $data;
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
            $this->addHtml($this->createHostRow($item));
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
     * Create a single host row with tactical line
     *
     * Layout per row:
     *   [StateBall+icon] [HostName]  |  [ServiceStatistics donut+badges]
     *
     * @param ProjecttacticalSummary $item
     *
     * @return BaseHtmlElement
     */
    protected function createHostRow(ProjecttacticalSummary $item): BaseHtmlElement
    {
        $row = new HtmlElement('div', Attributes::create(['class' => 'projecttactical-row']));

        // Left side: host state ball + host name link
        $nameContainer = new HtmlElement('div', Attributes::create(['class' => 'projecttactical-name']));

        // Render state ball from summary data (same logic as BaseHostAndServiceRenderer + State::getIcon)
        $stateText = HostStates::text(
            $item->host_soft_state !== null ? (int) $item->host_soft_state : null
        );
        $stateBall = new StateBall($stateText, StateBall::SIZE_LARGE);

        // Add icon (same logic as State::getIcon)
        $icon = $this->getStateIcon($item);
        if ($icon !== null) {
            $stateBall->add($icon);
        }

        // Add handled class (same logic as BaseHostAndServiceRenderer)
        if (
            $item->host_is_problem === 'y'
            && ($item->host_is_handled === 'y' || $item->host_is_reachable === 'n')
        ) {
            $stateBall->getAttributes()->add('class', 'handled');
        }

        // Host link to detail page (icingadb/host?name=...)
        $hostLink = new Link(
            $item->display_name,
            Links::hostDetails($item),
            [
                'class' => 'subject',
                'data-base-target' => '_next',
                'title' => sprintf($this->translate('Show host %s'), $item->display_name)
            ]
        );

        $nameContainer->addHtml($stateBall, $hostLink);
        $row->addHtml($nameContainer);

        // Right side: tactical line with ServiceStatistics
        $statsContainer = new HtmlElement('div', Attributes::create([
            'class' => 'projecttactical-stats',
            'data-base-target' => '_next'
        ]));

        $hostNameFilter = Filter::equal('host.name', $item->name);

        $serviceStats = (new ServiceStatistics($item))
            ->setBaseFilter($hostNameFilter);

        if ($this->baseFilter !== null) {
            $serviceStats->setBaseFilter(Filter::all($hostNameFilter, $this->baseFilter));
        }

        $statsContainer->addHtml($serviceStats);
        $row->addHtml($statsContainer);

        return $row;
    }

    /**
     * Get the state icon for a host (same logic as State::getIcon)
     *
     * @param ProjecttacticalSummary $item
     *
     * @return Icon|null
     */
    protected function getStateIcon(ProjecttacticalSummary $item): ?Icon
    {
        switch (true) {
            case $item->host_is_acknowledged === 'y':
                return new Icon(Icons::IS_ACKNOWLEDGED);
            case $item->host_in_downtime === 'y':
                return new Icon(Icons::IN_DOWNTIME);
            case $item->host_is_flapping === 'y':
                return new Icon(Icons::IS_FLAPPING);
            case $item->host_is_reachable === 'n':
                return new Icon(Icons::HOST_DOWN);
            case $item->host_is_handled === 'y':
                return new Icon(Icons::HOST_DOWN);
            default:
                return null;
        }
    }
}
