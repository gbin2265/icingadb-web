<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Hostgroup;
use Icinga\Module\Icingadb\Model\Hostgroupprojectsummary;
use Icinga\Module\Icingadb\View\HostgroupprojectRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;

class HostgroupsprojectController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Host Groups Project'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $hostgroupsproject = Hostgroupprojectsummary::on($db);

        $this->handleSearchRequest($hostgroupsproject);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($hostgroupsproject);

        $sortControl = $this->createSortControl(
            $hostgroupsproject,
            [
                'display_name'                      => t('Name'),
                'hosts_severity desc, display_name' => t('Severity'),
                'hosts_total desc'                  => t('Total Hosts'),
            ],
            ['hosts_severity DESC', 'display_name']
        );

        $filter = $this->getFilter();

        $this->filter($hostgroupsproject, $filter);

        $hostgroupsproject->peekAhead($compact);

        yield $this->export($hostgroupsproject);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);

        $results = $hostgroupsproject->execute();

	$content = new ObjectTable($results, (new HostgroupprojectRenderer())->setBaseFilter($filter));

        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        $this->setAutorefreshInterval(30);
    }

}
