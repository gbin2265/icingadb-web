<?php

/* Icinga DB Web | (c) 2021 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Widget\Detail;

use Icinga\Date\DateFormatter;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Widget\EmptyState;
use ipl\Web\Widget\HorizontalKeyValue;
use ipl\Web\Widget\VerticalKeyValue;
use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Text;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\CopyToClipboard;
use Icinga\Module\Icingadb\Util\PluginOutput;
use Icinga\Module\Icingadb\Widget\PluginOutputContainer;
use ipl\Html\Table;



class HostMetaInfoLinks extends BaseHtmlElement
{
    protected $tag = 'table';

    protected $defaultAttributes = ['class' => 'object-meta-info-links'];

    /** @var Host */
    protected $host;

    public function __construct(Host $host)
    {
        $this->host = $host;
    }

    protected function assemble()
    {

        $pluginOutputHostDisplayName = new HtmlElement('div', null, Text::create($this->host->display_name));
        CopyToClipboard::attachTo($pluginOutputHostDisplayName);

        $pluginOutputHostName = new HtmlElement('div', null, Text::create($this->host->name));
        CopyToClipboard::attachTo($pluginOutputHostName);

        $pluginOutputHostAddress = new HtmlElement('div', null, Text::create($this->host->address));
        CopyToClipboard::attachTo($pluginOutputHostAddress);


        $cols = [];
        $cols[] = Table::td('HostName:',['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostDisplayName,['class' => 'object-meta-info-links-td-info']);
        $cols[] = Table::td(' - ',['class' => 'object-meta-info-links-td-space']);
        $cols[] = Table::td('Address:',['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostAddress,['class' => 'object-meta-info-links-td-info']);
        $cols[] = Table::td(' - ',['class' => 'object-meta-info-links-td-space']);
        $cols[] = Table::td('HostObj:',['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostName,['class' => 'object-meta-info-links-td-info']);

        $this->addHtml(Table::tr($cols));

    }
}
