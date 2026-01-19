<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2025 Icinga GmbH | GPLv2 */
/* GeBi custom view */

namespace Icinga\Module\Icingadb\Web\Control;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

/**
 * Toggle control for filtering hosts and services by state
 *
 * Uses link-based toggles that update URL parameters
 */
class ServiceStateToggle extends BaseHtmlElement
{
    use Translation;

    /** @var int Host state UP */
    public const HOST_STATE_UP = 0;

    /** @var int Host state DOWN */
    public const HOST_STATE_DOWN = 1;

    /** @var int Service state OK */
    public const SERVICE_STATE_OK = 0;

    /** @var int Service state WARNING */
    public const SERVICE_STATE_WARNING = 1;

    /** @var int Service state CRITICAL */
    public const SERVICE_STATE_CRITICAL = 2;

    /** @var int Service state UNKNOWN */
    public const SERVICE_STATE_UNKNOWN = 3;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'service-state-toggle'
    ];

    /** @var string[] Checkbox parameter names */
    public const CHECKBOX_PARAMS = [
        'checkboxhostok',
        'checkboxhostcritical',
        'checkboxhostservices',
        'checkboxservicecritical',
        'checkboxservicewarning',
        'checkboxserviceunknown'
    ];

    /** @var bool */
    protected bool $hostOkValue = false;

    /** @var bool */
    protected bool $hostCriticalValue = false;

    /** @var bool */
    protected bool $hostServicesValue = false;

    /** @var bool */
    protected bool $criticalValue = false;

    /** @var bool */
    protected bool $warningValue = false;

    /** @var bool */
    protected bool $unknownValue = false;

    /** @var Url */
    protected Url $baseUrl;

    /**
     * Initialize from current request
     */
    public function __construct()
    {
        $url = Url::fromRequest();

        $this->hostOkValue = $url->getParam('checkboxhostok') === 'y';
        $this->hostCriticalValue = $url->getParam('checkboxhostcritical') === 'y';
        $this->hostServicesValue = $url->getParam('checkboxhostservices') === 'y';
        $this->criticalValue = $url->getParam('checkboxservicecritical') === 'y';
        $this->warningValue = $url->getParam('checkboxservicewarning') === 'y';
        $this->unknownValue = $url->getParam('checkboxserviceunknown') === 'y';

        // Build base URL without checkbox params
        $this->baseUrl = $this->buildBaseUrl();
    }

    /**
     * Get whether host OK is checked
     *
     * @return bool
     */
    public function isHostOkChecked(): bool
    {
        return $this->hostOkValue;
    }

    /**
     * Get whether host critical is checked
     *
     * @return bool
     */
    public function isHostCriticalChecked(): bool
    {
        return $this->hostCriticalValue;
    }

    /**
     * Get whether host services is checked
     *
     * @return bool
     */
    public function isHostServicesChecked(): bool
    {
        return $this->hostServicesValue;
    }

    /**
     * Get whether service critical is checked
     *
     * @return bool
     */
    public function isCriticalChecked(): bool
    {
        return $this->criticalValue;
    }

    /**
     * Get whether service warning is checked
     *
     * @return bool
     */
    public function isWarningChecked(): bool
    {
        return $this->warningValue;
    }

    /**
     * Get whether service unknown is checked
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
     * @return int[]|null Array of state integers or null if none selected
     */
    public function getSelectedStates(): ?array
    {
        $states = [];

        if ($this->criticalValue) {
            $states[] = self::SERVICE_STATE_CRITICAL;
        }

        if ($this->warningValue) {
            $states[] = self::SERVICE_STATE_WARNING;
        }

        if ($this->unknownValue) {
            $states[] = self::SERVICE_STATE_UNKNOWN;
        }

        return empty($states) ? null : $states;
    }

    /**
     * Get the selected host states as array
     *
     * @return int[]|null Array of state integers or null if none selected
     */
    public function getSelectedHostStates(): ?array
    {
        $states = [];

        if ($this->hostOkValue) {
            $states[] = self::HOST_STATE_UP;
        }

        if ($this->hostCriticalValue) {
            $states[] = self::HOST_STATE_DOWN;
        }

        return empty($states) ? null : $states;
    }

    /**
     * Build base URL without checkbox parameters
     *
     * @return Url
     */
    protected function buildBaseUrl(): Url
    {
        $url = Url::fromRequest();

        foreach (self::CHECKBOX_PARAMS as $param) {
            $url->getParams()->remove($param);
        }

        return $url;
    }

    /**
     * Build URL with toggled parameter
     *
     * @param string $toggleParam Parameter to toggle
     *
     * @return Url
     */
    protected function buildToggleUrl(string $toggleParam): Url
    {
        $url = clone $this->baseUrl;

        $currentValues = [
            'checkboxhostok' => $this->hostOkValue,
            'checkboxhostcritical' => $this->hostCriticalValue,
            'checkboxhostservices' => $this->hostServicesValue,
            'checkboxservicecritical' => $this->criticalValue,
            'checkboxservicewarning' => $this->warningValue,
            'checkboxserviceunknown' => $this->unknownValue
        ];

        foreach (self::CHECKBOX_PARAMS as $param) {
            if ($param === $toggleParam) {
                // Toggle: if currently on, don't add; if off, add it
                if (! $currentValues[$param]) {
                    $url->getParams()->set($param, 'y');
                }
            } else {
                // Keep current value
                if ($currentValues[$param]) {
                    $url->getParams()->set($param, 'y');
                }
            }
        }

        return $url;
    }

    /**
     * Create a toggle link styled as checkbox
     *
     * @param string $param Parameter name
     * @param string $label Display label
     * @param bool $isChecked Current state
     *
     * @return HtmlElement
     */
    protected function createToggleLink(string $param, string $label, bool $isChecked): HtmlElement
    {
        $url = $this->buildToggleUrl($param);

        $checkbox = new HtmlElement(
            'span',
            Attributes::create([
                'class' => $isChecked ? 'toggle-checkbox checked' : 'toggle-checkbox'
            ])
        );

        $link = new Link(
            [$label . ' ', $checkbox],
            $url,
            [
                'class' => 'toggle-link',
                'title' => $isChecked
                    ? sprintf($this->translate('Click to disable %s filter'), $label)
                    : sprintf($this->translate('Click to enable %s filter'), $label)
            ]
        );

        return new HtmlElement(
            'span',
            Attributes::create(['class' => 'toggle-item']),
            $link
        );
    }

    protected function assemble(): void
    {
        // Host group
        $hostGroup = new HtmlElement('div', Attributes::create(['class' => 'checkbox-group host-checkbox-group']));
        $hostGroup->addHtml(new HtmlElement(
            'span',
            Attributes::create(['class' => 'checkbox-group-label']),
            Text::create($this->translate('Host'))
        ));

        $hostGroup->addHtml($this->createToggleLink('checkboxhostok', $this->translate('Ok'), $this->hostOkValue));
        $hostGroup->addHtml($this->createToggleLink('checkboxhostcritical', $this->translate('Critical'), $this->hostCriticalValue));
        $hostGroup->addHtml($this->createToggleLink('checkboxhostservices', $this->translate('Services'), $this->hostServicesValue));

        // Service group
        $serviceGroup = new HtmlElement('div', Attributes::create(['class' => 'checkbox-group service-checkbox-group']));
        $serviceGroup->addHtml(new HtmlElement(
            'span',
            Attributes::create(['class' => 'checkbox-group-label']),
            Text::create($this->translate('Service'))
        ));

        $serviceGroup->addHtml($this->createToggleLink('checkboxservicecritical', $this->translate('Critical'), $this->criticalValue));
        $serviceGroup->addHtml($this->createToggleLink('checkboxservicewarning', $this->translate('Warning'), $this->warningValue));
        $serviceGroup->addHtml($this->createToggleLink('checkboxserviceunknown', $this->translate('Unknown'), $this->unknownValue));

        $this->addHtml($hostGroup);
        $this->addHtml($serviceGroup);
    }
}
