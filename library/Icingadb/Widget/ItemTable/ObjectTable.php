<?php

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Exception\NotImplementedError;
use Icinga\Module\Icingadb\Common\DetailActions;
use Icinga\Module\Icingadb\Model\Hostgroupsummary;
use Icinga\Module\Icingadb\Model\Hostgroupprojectsummary;
use Icinga\Module\Icingadb\Model\ServicegroupSummary;
use Icinga\Module\Icingadb\Model\Servicegroupprojectsummary;
use Icinga\Module\Icingadb\Model\Checkcommandsummary;
use Icinga\Module\Icingadb\Model\Hosthostsummary;
use Icinga\Module\Icingadb\Model\Hostservicessummary;
use Icinga\Module\Icingadb\Model\Serviceservicessummary;
use Icinga\Module\Icingadb\Model\TacticallineSummary;
use ipl\Html\ValidHtml;
use ipl\Stdlib\Filter;
use ipl\Web\Url;
use ipl\Web\Widget\ItemTable;

/**
 * ObjectTable
 *
 * @internal The only reason this class exists is due to the detail actions. In case those are part of the ipl
 * some time, this class is obsolete, and we must be able to safely drop it.
 *
 * @template Item of Hostgroupsummary|ServicegroupSummary
 *
 * @extends ItemTable<Item>
 */
class ObjectTable extends ItemTable
{
    use DetailActions;

    protected function init(): void
    {
        parent::init();

        $this->initializeDetailActions();
    }

    /**
     * @param Item $data
     *
     * @return ValidHtml
     *
     * @throws NotImplementedError When the data is not of the expected type
     */
    protected function createListItem(object $data): ValidHtml
    {
        $item = parent::createListItem($data);

        if ($this->getDetailActionsDisabled()) {
            return $item;
        }

        switch (true) {
            case $data instanceof Hostgroupsummary:
                $this->setDetailUrl(Url::fromPath('icingadb/hostgroup'));

                break;
            case $data instanceof Hostgroupprojectsummary:
                $this->setDetailUrl(Url::fromPath('icingadb/hostgroupsproject'));

                break;
            case $data instanceof ServicegroupSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/servicegroup'));

                break;
            case $data instanceof ServicegroupprojectSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/servicegroupsproject'));

                break;
            case $data instanceof CheckcommandSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/checkcommand'));

                break;
            case $data instanceof HosthostSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/hosthost'));

                break;
            case $data instanceof ServiceservicesSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/serviceservices'));

                break;
            case $data instanceof HostservicesSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/hostservices'));

		break;
            case $data instanceof TacticallineSummary:
                $this->setDetailUrl(Url::fromPath('icingadb/tacticalline'));

		break;
            default:
                throw new NotImplementedError('Not implemented 2');
        }

        $this->addDetailFilterAttribute($item, Filter::equal('name', $data->name));

        return $item;
    }
}
