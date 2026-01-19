<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi - Custom MetaInfo Links Widget */

namespace Icinga\Module\Icingadb\Widget\Detail;

use Icinga\Module\Icingadb\Model\Host;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Table;
use ipl\Html\Text;
use ipl\Web\Widget\CopyToClipboard;

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
        $cols[] = Table::td('HostName:', ['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostDisplayName, ['class' => 'object-meta-info-links-td-info']);
        $cols[] = Table::td(' - ', ['class' => 'object-meta-info-links-td-space']);
        $cols[] = Table::td('Address:', ['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostAddress, ['class' => 'object-meta-info-links-td-info']);
        $cols[] = Table::td(' - ', ['class' => 'object-meta-info-links-td-space']);
        $cols[] = Table::td('HostObj:', ['class' => 'object-meta-info-links-td-label']);
        $cols[] = Table::td($pluginOutputHostName, ['class' => 'object-meta-info-links-td-info']);

        $this->addHtml(Table::tr($cols));
    }
}
