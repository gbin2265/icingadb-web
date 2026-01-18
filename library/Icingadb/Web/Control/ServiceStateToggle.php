<?php

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Web\Control;

use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Web\Common\FormUid;
use ipl\Web\Compat\CompatForm;

class ServiceStateToggle extends CompatForm
{
    use FormUid;

    protected $protector;

    protected $defaultAttributes = [
        'name'    => 'service-state-toggle',
        'class'   => 'icinga-form icinga-controls inline service-state-toggle'
    ];

    /** @var bool */
    protected $criticalHostValue;

    /** @var bool */
    protected $hiddenHostValue;

    /** @var bool */
    protected $criticalValue;

    /** @var bool */
    protected $warningValue;

    /** @var bool */
    protected $unknownValue;

    /**
     * Create ServiceStateToggle with URL param values
     *
     * @param bool $criticalHost
     * @param bool $hiddenHost
     * @param bool $critical
     * @param bool $warning
     * @param bool $unknown
     */
    public function __construct(bool $criticalHost, bool $hiddenHost, bool $critical, bool $warning, bool $unknown)
    {
        $this->criticalHostValue = $criticalHost;
        $this->hiddenHostValue = $hiddenHost;
        $this->criticalValue = $critical;
        $this->warningValue = $warning;
        $this->unknownValue = $unknown;
    }

    /**
     * Set callback to protect ids with
     *
     * @param callable $protector
     *
     * @return $this
     */
    public function setIdProtector(callable $protector): self
    {
        $this->protector = $protector;

        return $this;
    }

    /**
     * Get whether critical host is checked
     *
     * @return bool
     */
    public function isCriticalHostChecked(): bool
    {
        return $this->criticalHostValue;
    }

    /**
     * Get whether hidden host is checked
     *
     * @return bool
     */
    public function isHiddenHostChecked(): bool
    {
        return $this->hiddenHostValue;
    }

    /**
     * Get whether critical is checked
     *
     * @return bool
     */
    public function isCriticalChecked(): bool
    {
        return $this->criticalValue;
    }

    /**
     * Get whether warning is checked
     *
     * @return bool
     */
    public function isWarningChecked(): bool
    {
        return $this->warningValue;
    }

    /**
     * Get whether unknown is checked
     *
     * @return bool
     */
    public function isUnknownChecked(): bool
    {
        return $this->unknownValue;
    }

    /**
     * Get the selected service states as array
     *
     * @return array|null Array of state integers or null if none selected
     */
    public function getSelectedStates(): ?array
    {
        $states = [];

        if ($this->isCriticalChecked()) {
            $states[] = 2;
        }

        if ($this->isWarningChecked()) {
            $states[] = 1;
        }

        if ($this->isUnknownChecked()) {
            $states[] = 3;
        }

        return empty($states) ? null : $states;
    }

    protected function assemble()
    {
        // Build base URL params (excluding our checkbox params)
        $currentUrl = Url::fromRequest();
        $baseParams = [];
        foreach ($currentUrl->getParams()->toArray() as $param) {
            if (! in_array($param[0], ['checkboxhostcritical', 'checkboxhosthidden', 'checkboxservicecritical', 'checkboxservicewarning', 'checkboxserviceunknown'])) {
                $baseParams[$param[0]] = $param[1];
            }
        }
        
        // Build base URL
        $baseQuery = http_build_query($baseParams);
        if ($baseQuery !== '') {
            $baseUrl = $currentUrl->getPath() . '?' . $baseQuery;
        } else {
            $baseUrl = $currentUrl->getPath();
        }

        // Helper to build URL with toggled param
        // If currently on -> remove it, if currently off -> add it
        $buildUrl = function($toggleParam, $params) use ($baseUrl) {
            $newParams = [];
            foreach (['checkboxhostcritical', 'checkboxhosthidden', 'checkboxservicecritical', 'checkboxservicewarning', 'checkboxserviceunknown'] as $p) {
                if ($p === $toggleParam) {
                    // Toggle this param
                    if (! $params[$p]) {
                        $newParams[$p] = 'y';
                    }
                    // If currently on, don't add it (removes it)
                } else {
                    // Keep current value
                    if ($params[$p]) {
                        $newParams[$p] = 'y';
                    }
                }
            }
            $query = http_build_query($newParams);
            if ($query !== '') {
                return $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . $query;
            }
            return $baseUrl;
        };

        $currentParams = [
            'checkboxhostcritical' => $this->criticalHostValue,
            'checkboxhosthidden' => $this->hiddenHostValue,
            'checkboxservicecritical' => $this->criticalValue,
            'checkboxservicewarning' => $this->warningValue,
            'checkboxserviceunknown' => $this->unknownValue
        ];

        // Host box
        $hostBox = Html::tag('div', ['class' => 'checkbox-group host-checkbox-group']);
        $hostBox->addHtml(Html::tag('span', ['class' => 'checkbox-group-label'], t('Host')));
        
        $hostLabel = Html::tag('label');
        $hostAttrs = [
            'type' => 'checkbox',
            'name' => 'checkboxhostcritical',
            'id' => $this->protectId('checkboxhostcritical'),
            'value' => 'y',
            'onclick' => sprintf("window.location.href='%s';", $buildUrl('checkboxhostcritical', $currentParams))
        ];
        if ($this->criticalHostValue) {
            $hostAttrs['checked'] = true;
        }
        $hostLabel->addHtml(Text::create(t('Critical') . ' '));
        $hostLabel->addHtml(Html::tag('input', $hostAttrs));
        $hostBox->addHtml($hostLabel);

        // Hidden checkbox
        $hiddenLabel = Html::tag('label');
        $hiddenAttrs = [
            'type' => 'checkbox',
            'name' => 'checkboxhosthidden',
            'id' => $this->protectId('checkboxhosthidden'),
            'value' => 'y',
            'onclick' => sprintf("window.location.href='%s';", $buildUrl('checkboxhosthidden', $currentParams))
        ];
        if ($this->hiddenHostValue) {
            $hiddenAttrs['checked'] = true;
        }
        $hiddenLabel->addHtml(Text::create(t('Hidden') . ' '));
        $hiddenLabel->addHtml(Html::tag('input', $hiddenAttrs));
        $hostBox->addHtml($hiddenLabel);

        // Service box
        $serviceBox = Html::tag('div', ['class' => 'checkbox-group service-checkbox-group']);
        $serviceBox->addHtml(Html::tag('span', ['class' => 'checkbox-group-label'], t('Service')));

        // Critical
        $criticalLabel = Html::tag('label');
        $criticalAttrs = [
            'type' => 'checkbox',
            'name' => 'checkboxservicecritical',
            'id' => $this->protectId('checkboxservicecritical'),
            'value' => 'y',
            'onclick' => sprintf("window.location.href='%s';", $buildUrl('checkboxservicecritical', $currentParams))
        ];
        if ($this->criticalValue) {
            $criticalAttrs['checked'] = true;
        }
        $criticalLabel->addHtml(Text::create(t('Critical') . ' '));
        $criticalLabel->addHtml(Html::tag('input', $criticalAttrs));
        $serviceBox->addHtml($criticalLabel);

        // Warning
        $warningLabel = Html::tag('label');
        $warningAttrs = [
            'type' => 'checkbox',
            'name' => 'checkboxservicewarning',
            'id' => $this->protectId('checkboxservicewarning'),
            'value' => 'y',
            'onclick' => sprintf("window.location.href='%s';", $buildUrl('checkboxservicewarning', $currentParams))
        ];
        if ($this->warningValue) {
            $warningAttrs['checked'] = true;
        }
        $warningLabel->addHtml(Text::create(t('Warning') . ' '));
        $warningLabel->addHtml(Html::tag('input', $warningAttrs));
        $serviceBox->addHtml($warningLabel);

        // Unknown
        $unknownLabel = Html::tag('label');
        $unknownAttrs = [
            'type' => 'checkbox',
            'name' => 'checkboxserviceunknown',
            'id' => $this->protectId('checkboxserviceunknown'),
            'value' => 'y',
            'onclick' => sprintf("window.location.href='%s';", $buildUrl('checkboxserviceunknown', $currentParams))
        ];
        if ($this->unknownValue) {
            $unknownAttrs['checked'] = true;
        }
        $unknownLabel->addHtml(Text::create(t('Unknown') . ' '));
        $unknownLabel->addHtml(Html::tag('input', $unknownAttrs));
        $serviceBox->addHtml($unknownLabel);

        $this->addHtml($hostBox);
        $this->addHtml($serviceBox);
    }

    private function protectId($id)
    {
        if (is_callable($this->protector)) {
            return call_user_func($this->protector, $id);
        }

        return $id;
    }
}
