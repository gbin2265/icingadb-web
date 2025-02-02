<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Model\Servicegroup;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\BaseHtmlElement;
use ipl\Stdlib\Filter;

/**
 * Servicegroup item of a servicegroup list. Represents one database row.
 *
 * @property Servicegroup $item
 * @property ServicegroupTable $table
 */
class ServicegroupTableRow extends BaseServiceGroupItem
{
    use TableRowLayout;

    protected $defaultAttributes = ['class' => 'servicegroup-table-row'];

    /**
     * Create Service statistics cell
     *
     * @return BaseHtmlElement[]
     */
    protected function createStatistics(): array
    {
        $serviceStats = new ServiceStatistics($this->item);

        $serviceStats->setBaseFilter(Filter::equal('servicegroup.name', $this->item->name));
        if (isset($this->table) && $this->table->hasBaseFilter()) {
            $serviceStats->setBaseFilter(
                Filter::all($serviceStats->getBaseFilter(), $this->table->getBaseFilter())
            );
        }

        return [$this->createColumn($serviceStats)];
    }
}
