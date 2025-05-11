<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Hostgroup;
use Icinga\Module\Icingadb\Model\HosthostSummary;
use Icinga\Module\Icingadb\View\ServicegroupGridRenderer;
use Icinga\Module\Icingadb\View\HosthostRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectGrid;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Html\Attributes;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;
use ipl\Web\Widget\ItemList;

class HosthostController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Host Services'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $hosthost = HosthostSummary::on($db);

        $this->handleSearchRequest($hosthost);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hosthost);

        $sortControl = $this->createSortControl(
            $hosthost,
            [
                'name'                                 => t('Object Name'),
                'display_name'                         => t('Display Name'),
                'services_warning_unhandled desc'      => t('Srv Unhandled Warning'),
                'services_critical_unhandled desc'     => t('Srv Unhandled Critial'),
                'services_unknown_unhandled desc'      => t('Srv Unhandled Unknown'),
                'services_critical_unhandled desc,services_warning_unhandled desc'     => t('Srv Unhandled Critial,Warning'),
                'services_total desc'                  => t('Srv Total Services'),
                'services_ok desc'                     => t('Srv Ok'),
                'services_pending desc'                => t('Srv Pending'),
                'services_total desc'                  => t('Srv Total Services'),
                'services_warning_handled desc'        => t('Srv Handled Warning'),
                'services_unknown_handled desc'        => t('Srv Handled Unknown')
            ],
            ['services_critical_unhandled desc', 'services_warning_unhandled desc']
        );

        $filter = $this->getFilter();

        $this->filter($hosthost, $filter);

        $hosthost->peekAhead($compact);

        yield $this->export($hosthost);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);

        $results = $hosthost->execute();

	$content = new ObjectTable($results, (new HosthostRenderer())->setBaseFilter($filter));

        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        $this->setAutorefreshInterval(30);
    }

}
