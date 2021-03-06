<?php
/**
 * @var \yii\web\View $this
 * @var string $content
 */

use yii\bootstrap\Html;
use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\widgets\Breadcrumbs;
use zhuravljov\yii\queue\monitor\assets\MainAsset;
use zhuravljov\yii\queue\monitor\filters\JobFilter;
use zhuravljov\yii\queue\monitor\filters\WorkerFilter;
use zhuravljov\yii\queue\monitor\Module;

MainAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
<div class="wrap">
    <?php
    NavBar::begin([
        'brandLabel' => Module::t('main', 'Queue Monitor'),
        'brandUrl' => ['/' . Module::getInstance()->id],
        'options' => ['class' => 'navbar-inverse navbar-fixed-top'],
    ]);
    echo Nav::widget([
        'options' => ['class' => 'nav navbar-nav'],
        'items' => [
            [
                'label' => Module::t('main', 'Jobs'),
                'url' => ['job/index'] + JobFilter::restoreParams(),
                'active' => Yii::$app->controller->id === 'job',
            ],
            [
                'label' => Module::t('main', 'Workers'),
                'url' => ['worker/index'] + WorkerFilter::restoreParams(),
                'active' => Yii::$app->controller->id === 'worker',
            ],
            [
                'label' => 'Test Actions',
                'items' => [
                    [
                        'label' => 'Push Simple Job',
                        'url' => ['/test/push-simple-job'],
                        'linkOptions' => ['data-method' => 'post'],
                    ],
                    [
                        'label' => 'Push Recursion Job',
                        'url' => ['/test/push-recursion-job'],
                        'linkOptions' => ['data-method' => 'post'],
                    ],
                    [
                        'label' => 'Push 10 Recursion Jobs',
                        'url' => ['/test/push-many-recursion-jobs', 'count' => 10],
                        'linkOptions' => ['data-method' => 'post'],
                    ],
                    [
                        'label' => 'Push Not Void Job',
                        'url' => ['/test/push-not-void-job'],
                        'linkOptions' => ['data-method' => 'post'],
                    ],
                    [
                        'label' => 'Push Wrong Job',
                        'url' => ['/test/push-wrong-job'],
                        'linkOptions' => ['data-method' => 'post'],
                    ],
                ],
            ],
        ],
    ]);
    NavBar::end();
    ?>
    <div class="container">
        <?= Breadcrumbs::widget([
            'homeLink' => false,
            'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
        ]) ?>
        <?= $this->render('_alerts') ?>
        <?= $content ?>
    </div><!-- .container -->
</div><!-- .wrap -->

<footer class="footer">
    <div class="container">
        <p class="pull-right">
            Powered by <a href="http://www.yiiframework.com/">Yii Framework</a>
        </p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
