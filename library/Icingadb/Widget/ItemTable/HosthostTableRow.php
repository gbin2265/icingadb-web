<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Widget\Detail\HostStatistics;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\BaseHtmlElement;
use ipl\Stdlib\Filter;

class HosthostTableRow extends BaseHostHostItem
{
    use TableRowLayout;

    protected $defaultAttributes = ['class' => 'host-table-row'];

    /**
     * Create Host and service statistics columns
     *
     * @return BaseHtmlElement[]
     */
    protected function createStatistics(): array
    {
        $hostStats = new HostStatistics($this->item);

        $hostStats->setBaseFilter(Filter::equal('host.name', $this->item->name));
        if (isset($this->table) && $this->table->hasBaseFilter()) {
            $hostStats->setBaseFilter(
                Filter::all($hostStats->getBaseFilter(), $this->table->getBaseFilter())
            );
        }

        $serviceStats = new ServiceStatistics($this->item);

        $serviceStats->setBaseFilter(Filter::equal('host.name', $this->item->name));
        if (isset($this->table) && $this->table->hasBaseFilter()) {
            $serviceStats->setBaseFilter(
                Filter::all($serviceStats->getBaseFilter(), $this->table->getBaseFilter())
            );
        }

        return [
            $this->createColumn($hostStats),
            $this->createColumn($serviceStats)
        ];
    }
}
