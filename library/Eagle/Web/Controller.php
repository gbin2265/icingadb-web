<?php

namespace Icinga\Module\Eagle\Web;

use Generator;
use Icinga\Data\Filter\Filter;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Eagle\Widget\ViewModeSwitcher;
use ipl\Html\Html;
use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;
use ipl\Stdlib\Contract\PaginationInterface;
use ipl\Web\Compat\CompatController;
use ipl\Sql\Connection;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\PaginationControl;
use ipl\Web\Url;

class Controller extends CompatController
{
    /** @var Connection Connection to the Icinga database */
    private $db;

    /** @var string|null */
    private $format;

    /** @var \Redis Connection to the Icinga Redis */
    private $redis;

    /**
     * Get the connection to the Icinga database
     *
     * @return Connection
     *
     * @throws \Icinga\Exception\ConfigurationError If the related resource configuration does not exist
     */
    public function getDb()
    {
        if ($this->db === null) {
            $this->db = new Connection(
                ResourceFactory::getResourceConfig(
                    $this->Config()->get('icingadb', 'resource', 'icingadb')
                )->toArray()
            );
        }

        return $this->db;
    }

    /**
     * Get the connection to the Icinga Redis
     *
     * @return \Redis
     */
    public function getRedis()
    {
        if ($this->redis === null) {
            $config = $this->Config()->getSection('redis');

            $this->redis = new \Redis();
            $this->redis->connect(
                $config->get('host', 'redis'),
                $config->get('port', 6379)
            );
        }

        return $this->redis;
    }

    /**
     * Create and return the LimitControl
     *
     * This automatically shifts the limit URL parameter from {@link $params}.
     *
     * @return LimitControl
     */
    public function createLimitControl()
    {
        $limitControl = new LimitControl(Url::fromRequest());

        $this->params->shift($limitControl->getLimitParam());

        return $limitControl;
    }

    /**
     * Create and return the PaginationControl
     *
     * This automatically shifts the pagination URL parameters from {@link $params}.
     *
     * @return PaginationControl
     */
    public function createPaginationControl(PaginationInterface $paginatable)
    {
        $paginationControl = new PaginationControl($paginatable, Url::fromRequest());

        $this->params->shift($paginationControl->getPageParam());
        $this->params->shift($paginationControl->getPageSizeParam());

        return $paginationControl;
    }

    /**
     * Create and return the ViewModeSwitcher
     *
     * This automatically shifts the view mode URL parameter from {@link $params}.
     *
     * @return ViewModeSwitcher
     */
    public function createViewModeSwitcher()
    {
        $viewModeSwitcher = new ViewModeSwitcher(Url::fromRequest());

        $this->params->shift($viewModeSwitcher->getViewModeParam());

        return $viewModeSwitcher;
    }

    public function export(Query $query)
    {
        if ($this->format === 'sql') {
            list($sql, $values) = $query->getDb()->getQueryBuilder()->assembleSelect($query->assembleSelect());
            foreach ($values as $value) {
                $pos = strpos($sql, '?');
                $sql = substr_replace($sql, "\"{$value}\"", $pos, 1);
            }
            $this->content->setContent(Html::tag('pre', $sql));

            return true;
        }
    }

    public function dispatch($action)
    {
        // Notify helpers of action preDispatch state
        $this->_helper->notifyPreDispatch();

        $this->preDispatch();

        if ($this->getRequest()->isDispatched()) {
            // If pre-dispatch hooks introduced a redirect then stop dispatch
            // @see ZF-7496
            if (! $this->getResponse()->isRedirect()) {
                $interceptable = $this->$action();
                if ($interceptable instanceof Generator) {
                    foreach ($interceptable as $stopSignal) {
                        if ($stopSignal === true) {
                            break;
                        }
                    }
                }
            }
            $this->postDispatch();
        }

        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
        $this->_helper->notifyPostDispatch();
    }

    public function filter(Query $query)
    {
        FilterProcessor::apply(
            Filter::fromQueryString((string) $this->params),
            $query
        );

        return $this;
    }

    public function preDispatch()
    {
        $this->format = $this->params->shift('format');
    }
}
