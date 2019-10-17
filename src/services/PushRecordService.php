<?php
/**
 * @link https://github.com/zhuravljov/yii2-queue-monitor
 * @copyright Copyright (c) 2017 Roman Zhuravlev
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace zhuravljov\yii\queue\monitor\services;

use Yii;
use yii\helpers\ArrayHelper;
use yii\caching\CacheInterface;
use zhuravljov\yii\queue\monitor\records\PushRecord;
use zhuravljov\yii\queue\monitor\records\WorkerRecord;

class PushRecordService
{
    public static $states = [
        'delayed',
        'success',
        'buried',
        'stopped',
        'inProgress',
        'backlogged',
        'average',
    ];

    /**
     * Generates an array of jobs processed through the queue
     *
     * @param array $states an array of defined job states (see self::$states)
     * @param CacheInterface $cache will attempt to pull from/save to this cache
     * (if provided)
     * @param boolean $forceRefresh when set to true bypasses the existing cache
     * @return array
     */
    public static function generateSummary( Array $states = [], CacheInterface $cache=null, $forceRefresh=false )
    {
        // If not given any states then default to all known states
        $states = empty($states) ? self::$states : $states ;

        $pushedJobs = [];

        // Find all pushed jobs
        $pushedJobClassQuery = PushRecord::find()
            ->select('DISTINCT {{push}}.[[job_class]]')
            ->asArray();

        foreach ( $pushedJobClassQuery->each() as $result )
        {
            $pushedJobs[$result['job_class']] = [ 'job_class' => $result['job_class'] ];
        }

        foreach ( $states as $state )
        {
            // If given a cache object try to pull the response from that first
            if ( $cache && $forceRefresh === false )
            {
                $cachedJobsByClass = $cache->get("zhuravljov:summary:{$state}");

                if ( $cachedJobsByClass !== null ) {
                    foreach ( $pushedJobs as $jobClass => $summaryData )
                    {
                        $pushedJobs[$jobClass][$state] = ArrayHelper::getValue( $cachedJobsByClass, "{$jobClass}.{$state}", 0);
                    }
                    continue;
                }
            }

            // We have a number of states that are fetched with a simple active
            // query param. For those we can execute a simple push record query.
            // For the The others we execute a manual query
            if ( in_array( $state, ['delayed','success','buried','stopped']) )
            {
                $jobsByClass = PushRecord::find()
                    ->select( "{{push}}.[[job_class]], count({{push}}.[[id]]) as `{$state}`" )
                    ->$state()
                    ->groupBy('job_class')
                    ->asArray()
                    ->all();

                $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );
            }
            elseif ( $state === 'inProgress' )
            {
                $jobsByClass = (new yii\db\Query)
                    ->select( "queue_push.job_class, count(queue.id) AS {$state}" )
                    ->from( Yii::$app->queue->tableName )
                    ->innerJoin( 'queue_push', 'queue_push.job_uid = queue.id' )
                    ->where( ['NOT', ['queue.reserved_at' => NULL]] )
                    ->groupBy( 'queue_push.job_class' )
                    ->all( Yii::$app->queue->db );

                $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );
            }
            elseif ( $state === 'backlogged')
            {
                $jobsByClass = (new yii\db\Query)
                    ->select( "queue_push.job_class, count(queue.id) AS {$state}" )
                    ->from( Yii::$app->queue->tableName )
                    ->innerJoin( 'queue_push', 'queue_push.job_uid = queue.id' )
                    ->where( 'queue_push.desired_execute_time < UNIX_TIMESTAMP()' )
                    ->andWhere( ['queue.reserved_at' => NULL] )
                    ->groupBy( 'queue_push.job_class' )
                    ->all( Yii::$app->queue->db );

                $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );
            }
            elseif ( $state === 'average' )
            {
                $jobsByClass = PushRecord::find()
                    ->select( '{{push}}.[[job_class]], AVG({{last_exec}}.[[finished_at]] - {{last_exec}}.[[started_at]]) AS `average`' )
                    ->innerJoinLastExec()
                    ->groupBy('job_class')
                    ->asArray()
                    ->all();

                $jobsByClass = ArrayHelper::index( $jobsByClass, 'job_class' );
            }

            // If given a cache object save our result for later
            if ( $cache ) { $cache->set("zhuravljov:summary:{$state}", $jobsByClass ); }

            foreach ( $pushedJobs as $jobClass => $summaryData )
            {
                // cast the result based on the class of data we're saving
                if ( $state === 'average' )
                {
                    $pushedJobs[$jobClass][$state] = (float)ArrayHelper::getValue( $jobsByClass, "{$jobClass}.{$state}", 0);
                }
                else
                {
                    $pushedJobs[$jobClass][$state] = (int)ArrayHelper::getValue( $jobsByClass, "{$jobClass}.{$state}", 0);
                }
            }
        }

        return $pushedJobs;
    }

    /**
     * Compute the estimated time to complete the currently executing and
     * backlogged jobs based on average completion times
     *
     * @param Array $pushedJobSummary an array of jobs by class with average,
     * backlogged, and in progress job counts
     * @return array
     */
    public static function generateEstimatedTimeToComplete( Array $pushedJobSummary )
    {
        $estimated = [];

        $activeWorkerCount = WorkerRecord::find()
            ->active()
            ->count();

        foreach ( $pushedJobSummary as $jobClass => $summaryData )
        {
            $average = ArrayHelper::getValue($summaryData, 'average', 0);
            $backloggedAndInProgress = ArrayHelper::getValue($summaryData, 'backlogged', 0) + ArrayHelper::getValue($summaryData, 'inProgress', 0);

            // If we have backlogged tasks then divide by the number of workers
            // (that's how many we can work on concurrently) to get a total
            // number of seconds until all queue items are worked down
            if ( $backloggedAndInProgress > 0 )
            {
                $backloggedAndInProgressPerWorker = ceil( $backloggedAndInProgress / $activeWorkerCount );
            }
            else
            {
                $backloggedAndInProgressPerWorker = 0;
            }

            $estimated[$jobClass] = $average * $backloggedAndInProgressPerWorker;
        }

        return $estimated;
    }
}
