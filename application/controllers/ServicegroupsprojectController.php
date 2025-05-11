<?php

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Icingadb\Model\Servicegroup;
use Icinga\Module\Icingadb\Model\ServicegroupprojectSummary;
use Icinga\Module\Icingadb\View\ServicegroupprojectRenderer;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use Icinga\Module\Icingadb\Web\Controller;
use Icinga\Module\Icingadb\Widget\ItemTable\ObjectTable;
use Icinga\Module\Icingadb\Widget\ShowMore;
use ipl\Html\Attributes;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Url;
use ipl\Web\Widget\ItemList;

class ServicegroupsprojectController extends Controller
{
    public function init()
    {
        parent::init();

        $this->assertRouteAccess();
    }

    public function indexAction()
    {
        $this->addTitleTab(t('Service Groups'));
        $compact = $this->view->compact;

        $db = $this->getDb();

        $servicegroupsproject = ServicegroupprojectSummary::on($db);

        $this->handleSearchRequest($servicegroupsproject);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($servicegroupsproject);

        $sortControl = $this->createSortControl(
            $servicegroupsproject,
            [
                'display_name'                         => t('Name'),
                'services_severity desc, display_name' => t('Severity'),
                'services_total desc'                  => t('Total Services')
            ],
            ['services_severity DESC', 'display_name']
        );

        $filter = $this->getFilter();

        $this->filter($servicegroupsproject, $filter);

        $servicegroupsproject->peekAhead($compact);

        yield $this->export($servicegroupsproject);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);

        $results = $servicegroupsproject->execute();

        $content = new ObjectTable($results, (new ServicegroupprojectRenderer())->setBaseFilter($filter));
        $content->setEmptyStateMessage($paginationControl->getEmptyStateMessage());

        $this->addContent($content);

        $this->setAutorefreshInterval(30);
    }

}
