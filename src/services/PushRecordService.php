<?php
/**
 * @link https://github.com/zhuravljov/yii2-queue-monitor
 * @copyright Copyright (c) 2017 Roman Zhuravlev
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace zhuravljov\yii\queue\monitor\services;

use Yii;
use yii\helpers\ArrayHelper;
use zhuravljov\yii\queue\monitor\records\PushRecord;

class PushRecordService
{
    public static function generateSummary()
    {
        $pushedJobs = [];

        // Find all pushed jobs
        $pushedJobClassQuery = PushRecord::find()
            ->select('DISTINCT {{push}}.[[job_class]]')
            ->asArray();

        foreach ( $pushedJobClassQuery->each() as $result )
        {
            $pushedJobs[$result['job_class']] = [ 'job_class' => $result['job_class'] ];
        }

        // For each job determine the total number in each known state
        $states = [
            'delayed',
            'success',
            'buried',
            'stopped',
            'inProgress',
            'backlogged',
        ];

        foreach ( $states as $state )
        {
            $jobsByClass = PushRecord::find()
                ->select( "{{push}}.[[job_class]], count({{push}}.[[id]]) as `{$state}`" )
                ->$state()
                ->groupBy('job_class')
                ->asArray()
                ->all();

            $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );

            foreach ( $pushedJobs as $jobClass => $summaryData )
            {
                $pushedJobs[$jobClass][$state] = (int )ArrayHelper::getValue( $jobsByClass, "{$jobClass}.{$state}", 0);
            }
        }

        // Compute the average time to execute each job
        $averages = PushRecord::find()
            ->select( '{{push}}.[[job_class]], AVG({{last_exec}}.[[finished_at]] - {{last_exec}}.[[started_at]]) AS `average`' )
            ->innerJoinLastExec()
            ->groupBy('job_class')
            ->asArray();

        foreach ( $averages->each() as $result )
        {
            $pushedJobs[$result['job_class']]['average'] = (float) $result['average'];
        }

        // Finally compute the estimated time to complete the currently
        // executing and backlogged jobs based on average completion times
        foreach ( $pushedJobs as $jobClass => $summaryData )
        {
            $backloggedAndInProgress = ArrayHelper::getValue($summaryData, 'backlogged', 0) + ArrayHelper::getValue($summaryData, 'inProgress', 0);
            $pushedJobs[$jobClass]['estimated'] = ArrayHelper::getValue($summaryData, 'average', 0) * $backloggedAndInProgress;
        }

        return $pushedJobs;
    }
}
