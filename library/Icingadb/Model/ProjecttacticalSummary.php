<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */
/* GeBi custom view - per-host summary with host state + service aggregates */

namespace Icinga\Module\Icingadb\Model;

use Icinga\Module\Icingadb\Common\Auth;
use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behaviors;
use ipl\Orm\Query;
use ipl\Orm\Relations;
use ipl\Orm\UnionModel;
use ipl\Sql\Adapter\Pgsql;
use ipl\Sql\Connection;
use ipl\Sql\Expression;
use ipl\Sql\Select;

/**
 * Per-host summary model for project tactical view
 *
 * Groups by host and provides both host state and service state aggregates.
 * Each row represents one host with its service statistics.
 *
 * @property string $id
 * @property string $display_name
 * @property string $name
 * @property string $name_ci
 * @property int $services_critical_handled
 * @property int $services_critical_unhandled
 * @property int $services_ok
 * @property int $services_pending
 * @property int $services_total
 * @property int $services_unknown_handled
 * @property int $services_unknown_unhandled
 * @property int $services_warning_handled
 * @property int $services_warning_unhandled
 * @property int $host_soft_state
 * @property string $host_is_handled
 * @property string $host_is_reachable
 * @property string $host_is_problem
 * @property string $host_is_acknowledged
 * @property string $host_in_downtime
 * @property string $host_is_flapping
 */
class ProjecttacticalSummary extends UnionModel
{
    public static function on(Connection $db)
    {
        $q = parent::on($db);

        $q->on(
            Query::ON_SELECT_ASSEMBLED,
            function () use ($q) {
                $auth = new class () {
                    use Auth;
                };

                $auth->assertColumnRestrictions($q->getFilter());
            }
        );

        $q->on($q::ON_SELECT_ASSEMBLED, function (Select $select) use ($q) {
            $model = $q->getModel();

            $groupBy = $q->getResolver()->qualifyColumnsAndAliases((array) $model->getKeyName(), $model, false);

            // For PostgreSQL, ALL non-aggregate SELECT columns must appear in the GROUP BY clause:
            if ($q->getDb()->getAdapter() instanceof Pgsql) {
                /**
                 * Ignore Expressions, i.e. aggregate functions {@see getColumns()},
                 * which do not need to be added to the GROUP BY.
                 */
                $candidates = array_filter($select->getColumns(), 'is_string');
                // Remove already considered columns for the GROUP BY, i.e. the primary key.
                $candidates = array_diff_assoc($candidates, $groupBy);
                $groupBy = array_merge($groupBy, $candidates);
            }

            $select->groupBy($groupBy);
        });

        return $q;
    }

    public function getTableName()
    {
        return 'host';
    }

    public function getKeyName()
    {
        return ['id' => 'host_id'];
    }

    public function getColumns()
    {
        return [
            'display_name'                => 'host_display_name',
            'name'                        => 'host_name',
            'name_ci'                     => 'host_name_ci',
            'services_critical_handled'   => new Expression(
                'SUM(CASE WHEN service_state = 2'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END)'
            ),
            'services_critical_unhandled' => new Expression(
                'SUM(CASE WHEN service_state = 2'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END)'
            ),
            'services_ok'                 => new Expression(
                'SUM(CASE WHEN service_state = 0 THEN 1 ELSE 0 END)'
            ),
            'services_pending'            => new Expression(
                'SUM(CASE WHEN service_state = 99 THEN 1 ELSE 0 END)'
            ),
            'services_total'              => new Expression(
                'SUM(CASE WHEN service_id IS NOT NULL THEN 1 ELSE 0 END)'
            ),
            'services_unknown_handled'    => new Expression(
                'SUM(CASE WHEN service_state = 3'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END)'
            ),
            'services_unknown_unhandled'  => new Expression(
                'SUM(CASE WHEN service_state = 3'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END)'
            ),
            'services_warning_handled'    => new Expression(
                'SUM(CASE WHEN service_state = 1'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END)'
            ),
            'services_warning_unhandled'  => new Expression(
                'SUM(CASE WHEN service_state = 1'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END)'
            ),
            'host_soft_state'             => new Expression(
                'MAX(CASE WHEN host_soft_state IS NOT NULL THEN host_soft_state ELSE NULL END)'
            ),
            'host_is_handled'             => new Expression(
                'MAX(CASE WHEN host_is_handled = \'y\' THEN \'y\' ELSE \'n\' END)'
            ),
            'host_is_reachable'           => new Expression(
                'MAX(CASE WHEN host_is_reachable = \'y\' THEN \'y\' ELSE \'n\' END)'
            ),
            'host_is_problem'             => new Expression(
                'MAX(CASE WHEN host_is_problem = \'y\' THEN \'y\' ELSE \'n\' END)'
            ),
            'host_is_acknowledged'        => new Expression(
                'MAX(CASE WHEN host_is_acknowledged = \'y\' THEN \'y\' ELSE \'n\' END)'
            ),
            'host_in_downtime'            => new Expression(
                'MAX(CASE WHEN host_in_downtime = \'y\' THEN \'y\' ELSE \'n\' END)'
            ),
            'host_is_flapping'            => new Expression(
                'MAX(CASE WHEN host_is_flapping = \'y\' THEN \'y\' ELSE \'n\' END)'
            )
        ];
    }

    public function getSearchColumns()
    {
        return ['display_name', 'name'];
    }

    public function getDefaultSort()
    {
        return 'display_name';
    }

    public function getUnions()
    {
        $unions = [
            [
                Host::class,
                [
                    'state'
                ],
                [
                    'host_id'           => 'host.id',
                    'host_name'         => 'host.name',
                    'host_name_ci'      => 'host.name_ci',
                    'host_display_name' => 'host.display_name',
                    'host_soft_state'   => 'state.soft_state',
                    'host_is_handled'   => 'state.is_handled',
                    'host_is_reachable' => 'state.is_reachable',
                    'host_is_problem'   => 'state.is_problem',
                    'host_is_acknowledged' => 'state.is_acknowledged',
                    'host_in_downtime'  => 'state.in_downtime',
                    'host_is_flapping'  => 'state.is_flapping',
                    'service_id'        => new Expression('NULL'),
                    'service_state'     => new Expression('NULL'),
                    'service_handled'   => new Expression('NULL'),
                    'service_reachable' => new Expression('NULL')
                ]
            ],
            [
                Service::class,
                [
                    'host',
                    'state'
                ],
                [
                    'host_id'           => 'host.id',
                    'host_name'         => 'host.name',
                    'host_name_ci'      => 'host.name_ci',
                    'host_display_name' => 'host.display_name',
                    'host_soft_state'   => new Expression('NULL'),
                    'host_is_handled'   => new Expression('NULL'),
                    'host_is_reachable' => new Expression('NULL'),
                    'host_is_problem'   => new Expression('NULL'),
                    'host_is_acknowledged' => new Expression('NULL'),
                    'host_in_downtime'  => new Expression('NULL'),
                    'host_is_flapping'  => new Expression('NULL'),
                    'service_id'        => 'service.id',
                    'service_state'     => 'state.soft_state',
                    'service_handled'   => 'state.is_handled',
                    'service_reachable' => 'state.is_reachable'
                ]
            ]
        ];

        return $unions;
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Binary([
            'id'
        ]));

        (new Host())->createBehaviors($behaviors);
    }

    public function createRelations(Relations $relations)
    {
        (new Host())->createRelations($relations);
    }

    public function getColumnDefinitions()
    {
        return (new Host())->getColumnDefinitions();
    }
}
