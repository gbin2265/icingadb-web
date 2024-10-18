<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\ItemTable;

use Icinga\Module\Icingadb\Common\DetailActions;
use Icinga\Module\Icingadb\Common\ViewMode;
use ipl\Web\Common\BaseItemTable;
use ipl\Web\Url;

class ServiceservicesTable extends BaseItemTable
{
    use DetailActions;
    use ViewMode;

    protected $defaultAttributes = ['class' => 'servicegroup-table'];

    protected function init(): void
    {
        $this->initializeDetailActions();
        $this->setDetailUrl(Url::fromPath('icingadb/serviceservices'));
    }

    protected function getItemClass(): string
    {
        return ServiceservicesTableRow::class;
    }
}