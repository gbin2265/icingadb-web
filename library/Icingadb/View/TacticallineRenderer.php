<?php

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\View;

use Icinga\Module\Icingadb\Model\TacticallineSummary;
use Icinga\Module\Icingadb\Widget\Detail\HostStatistics;
use Icinga\Module\Icingadb\Widget\Detail\ServiceStatistics;
use ipl\Html\Attributes;
use ipl\Html\HtmlDocument;
use ipl\I18n\Translation;
use ipl\Stdlib\BaseFilter;
use ipl\Stdlib\Filter;
use ipl\Web\Widget\ItemTable\ItemTableRenderer;

/** @implements ItemTableRenderer<TacticallineSummary> */
class TacticallineRenderer implements ItemTableRenderer
{
    use Translation;
    use BaseFilter;

    public function assembleAttributes($item, Attributes $attributes, string $layout): void
    {
        $attributes->get('class')->addValue('tacticalline-table-row');
    }

    public function assembleVisual($item, HtmlDocument $visual, string $layout): void
    {
    }

    public function assembleTitle($item, HtmlDocument $title, string $layout): void
    {
    }

    public function assembleCaption($item, HtmlDocument $caption, string $layout): void
    {
    }

    public function assembleExtendedInfo($item, HtmlDocument $info, string $layout): void
    {
        $info->addHtml(...$this->createStatistics($item));
    }

    public function assembleFooter($item, HtmlDocument $footer, string $layout): void
    {
    }

    public function assemble($item, string $name, HtmlDocument $element, string $layout): bool
    {
        return false;
    }

    public function assembleColumns($item, HtmlDocument $columns, string $layout): void
    {
        [$hostStats, $serviceStats] = $this->createStatistics($item);

        if ($this->hasBaseFilter()) {
            $hostStats->setBaseFilter(Filter::all($this->getBaseFilter()));
            $serviceStats->setBaseFilter(Filter::all($this->getBaseFilter()));
        }

        $columns->addHtml($hostStats, $serviceStats);
    }

    /**
     * Create statistics for the given item
     *
     * @param TacticallineSummary $item
     *
     * @return array{0: HostStatistics, 1: ServiceStatistics}
     */
    protected function createStatistics(TacticallineSummary $item): array
    {
        $hostStats = (new HostStatistics($item))
            ->addAttributes(['class' => 'tacticalline-table-row-host'])
            ->setBaseFilter($this->getBaseFilter());

        $serviceStats = (new ServiceStatistics($item))
            ->addAttributes(['class' => 'tacticalline-table-row-service'])
            ->setBaseFilter($this->getBaseFilter());

        return [$hostStats, $serviceStats];
    }
}

