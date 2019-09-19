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
        $pushedJobSummary = PushRecordService::generateSummary();

        $pushedJobDataProvider = new ArrayDataProvider([
            'allModels' => $pushedJobSummary,
            // Make all columns sortable
            'sort' => [
                'attributes' => array_keys( reset($pushedJobSummary) ),
            ],
        ]);

        $eta = array_sum( ArrayHelper::getColumn($pushedJobSummary, 'estimated') );

        return $this->render( 'index', [
            'pushedJobDataProvider' => $pushedJobDataProvider,
            'totalEstimatedTimeRemaining' => Yii::$app->formatter->asDuration( $eta )
        ]);
    }
}
