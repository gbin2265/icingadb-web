<?php

declare(strict_types=1);

/* Icinga DB Web | (c) 2020 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Icingadb\Model;

use Icinga\Module\Icingadb\Common\Auth;
use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behaviors;
use ipl\Orm\Query;
use ipl\Orm\Relations;
use ipl\Orm\UnionModel;
use ipl\Sql\Connection;
use ipl\Sql\Expression;

class TacticallineSummary extends UnionModel
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

        return $q;
    }

    public function getTableName()
    {
        return 'host';
    }

    public function getKeyName()
    {
        return ['id' => new Expression('0')];
    }

    public function getColumns()
    {
        return [
            'name'                        => new Expression('0'),
            'hosts_down_handled'          => new Expression(
                'COALESCE(SUM(CASE WHEN host_state = 1'
                . ' AND (host_handled = \'y\' OR host_reachable = \'n\') THEN 1 ELSE 0 END),0)'
            ),
            'hosts_down_unhandled'        => new Expression(
                'COALESCE(SUM(CASE WHEN host_state = 1'
                . ' AND host_handled = \'n\' AND host_reachable = \'y\' THEN 1 ELSE 0 END),0)'
            ),
            'hosts_pending'               => new Expression(
                'COALESCE(SUM(CASE WHEN host_state = 99 THEN 1 ELSE 0 END),0)'
            ),
            'hosts_total'                 => new Expression(
                'COALESCE(SUM(CASE WHEN host_id IS NOT NULL THEN 1 ELSE 0 END),0)'
            ),
            'hosts_up'                    => new Expression(
                'COALESCE(SUM(CASE WHEN host_state = 0 THEN 1 ELSE 0 END),0)'
            ),
            'hosts_severity'              => new Expression('COALESCE(MAX(host_severity),0)'),
            'services_critical_handled'   => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 2'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END),0)'
            ),
            'services_critical_unhandled' => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 2'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END),0)'
            ),
            'services_ok'                 => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 0 THEN 1 ELSE 0 END),0)'
            ),
            'services_pending'            => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 99 THEN 1 ELSE 0 END),0)'
            ),
            'services_total'              => new Expression(
                'COALESCE(SUM(CASE WHEN service_id IS NOT NULL THEN 1 ELSE 0 END),0)'
            ),
            'services_unknown_handled'    => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 3'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END),0)'
            ),
            'services_unknown_unhandled'  => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 3'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END),0)'
            ),
            'services_warning_handled'    => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 1'
                . ' AND (service_handled = \'y\' OR service_reachable = \'n\') THEN 1 ELSE 0 END),0)'
            ),
            'services_warning_unhandled'  => new Expression(
                'COALESCE(SUM(CASE WHEN service_state = 1'
                . ' AND service_handled = \'n\' AND service_reachable = \'y\' THEN 1 ELSE 0 END),0)'
            )
        ];
    }

    public function getSearchColumns()
    {
        return ['name'];
    }

    public function getDefaultSort()
    {
        return null;
    }

    public function getUnions()
    {
        return [
            [
                Host::class,
                [
                    'state'
                ],
                [
                    'host_id'           => 'host.id',
                    'host_state'        => 'state.soft_state',
                    'host_handled'      => 'state.is_handled',
                    'host_reachable'    => 'state.is_reachable',
                    'host_severity'     => 'state.severity',
                    'service_id'        => new Expression('NULL'),
                    'service_state'     => new Expression('NULL'),
                    'service_handled'   => new Expression('NULL'),
                    'service_reachable' => new Expression('NULL')
                ]
            ],
            [
                Service::class,
                [
                    'state'
                ],
                [
                    'host_id'           => new Expression('NULL'),
                    'host_state'        => new Expression('NULL'),
                    'host_handled'      => new Expression('NULL'),
                    'host_reachable'    => new Expression('NULL'),
                    'host_severity'     => new Expression('0'),
                    'service_id'        => 'service.id',
                    'service_state'     => 'state.soft_state',
                    'service_handled'   => 'state.is_handled',
                    'service_reachable' => 'state.is_reachable'
                ]
            ]
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Binary([
            'id'
        ]));
    }

    public function createRelations(Relations $relations)
    {
        // No relations needed for tactical line summary
    }

    public function getColumnDefinitions()
    {
        return [];
    }
}
