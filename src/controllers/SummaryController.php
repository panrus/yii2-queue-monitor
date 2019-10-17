<?php
/**
 * @link https://github.com/zhuravljov/yii2-queue-monitor
 * @copyright Copyright (c) 2017 Roman Zhuravlev
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace zhuravljov\yii\queue\monitor\controllers;

use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use zhuravljov\yii\queue\monitor\Module;
use zhuravljov\yii\queue\monitor\services\PushRecordService;

/**
 * Class SummaryController
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class SummaryController extends Controller
{
    /**
     * @var Module
     */
    public $module;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'push' => ['post'],
                    'stop' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Overview of pushed jobs
     *
     * @return mixed
     */
    public function actionIndex()
    {
        // Fetch from cached
        $pushedJobSummary = PushRecordService::generateSummary( ['delayed','success','buried','stopped','average'], Yii::$app->cache );

        // Fetch live data
        foreach ( PushRecordService::generateSummary( ['inProgress','backlogged']) as $summary )
        {
            $jobClass = $summary['job_class'];
            $pushedJobSummary[$jobClass]['inProgress'] = $summary['inProgress'];
            $pushedJobSummary[$jobClass]['backlogged'] = $summary['backlogged'];
        }

        foreach ( PushRecordService::generateEstimatedTimeToComplete( $pushedJobSummary ) as $jobClass => $estimated )
        {
            $pushedJobSummary[$jobClass]['estimated'] = $estimated;
        }

        $estimatedTotalBacklogSeconds = array_sum( ArrayHelper::getColumn($pushedJobSummary, 'estimated') );

        // Nicely format our floats
        foreach ( $pushedJobSummary as $key => $value )
        {
            $pushedJobSummary[$key]['average'] = Yii::$app->formatter->asDecimal( $pushedJobSummary[$key]['average'], 2 );
            $pushedJobSummary[$key]['estimated'] = Yii::$app->formatter->asDecimal( $pushedJobSummary[$key]['estimated'], 0 );
        }

        $pushedJobDataProvider = new ArrayDataProvider([
            'allModels' => $pushedJobSummary,
            // Make all columns sortable
            'sort' => [
                'attributes' => array_keys( reset($pushedJobSummary) ),
            ],
        ]);

        return $this->render( 'index', [
            'pushedJobDataProvider' => $pushedJobDataProvider,
            'totalEstimatedTimeRemaining' => Yii::$app->formatter->asDuration( $estimatedTotalBacklogSeconds )
        ]);
    }
}
