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
            'waiting',
            'inProgress',
            'done',
            'success',
            'buried',
            'hasFails',
            'stopped'
        ];

        foreach ( $states as $state )
        {
            $jobsByClass = PushRecord::find()
                ->select( "{{push}}.[[job_class]], count({{push}}.[[id]]) as {$state}" )
                ->$state()
                ->groupBy('job_class')
                ->asArray()
                ->all();

            $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );

            foreach ( $pushedJobs as $jobClass => $summaryData )
            {
                $pushedJobs[$jobClass][$state] = ArrayHelper::getValue( $jobsByClass, "{$jobClass}.{$state}", 0);
            }
        }

        // Compute the average time to execute each job
        $averages = PushRecord::find()
            ->select( '{{push}}.[[job_class]], AVG({{last_exec}}.[[finished_at]] - {{last_exec}}.[[started_at]]) as average' )
            ->innerJoinLastExec()
            ->groupBy('job_class')
            ->asArray();

        foreach ( $averages->each() as $result )
        {
            $pushedJobs[$result['job_class']]['average'] = Yii::$app->formatter->asDecimal( $result['average'], 2 );
        }

        // Finally compute the estimated time to complete the remaining
        // (pending) jobs based on average completion times
        foreach ( $pushedJobs as $jobClass => $summaryData )
        {
            $waitingAndInProgress = ArrayHelper::getValue($summaryData, 'waiting', 0) + ArrayHelper::getValue($summaryData, 'inProgress', 0);
            $pushedJobs[$jobClass]['estimated'] = Yii::$app->formatter->asDecimal( $summaryData['average'] * $waitingAndInProgress, 0 );
        }

        return $pushedJobs;
    }
}
