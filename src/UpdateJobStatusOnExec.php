<?php

namespace zhuravljov\yii\queue\monitor;

use Yii;
use yii\base\Behavior;
use yii\queue\ExecEvent;
use yii\queue\Queue;
use yii\base\InvalidConfigException;
use zhuravljov\yii\queue\monitor\records\PushRecord;

class UpdateJobStatusOnExec extends Behavior
{
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Queue::EVENT_AFTER_EXEC => 'afterExec',
            Queue::EVENT_AFTER_ERROR => 'afterExec',
        ];
    }

    /**
     * In some rare cases (the root cause of which is not yet clear to me) the
     * afterExec handler in JobMonitor does not appear to fire to at least does
     * not reach the portion of the code that updates the job status. This leads
     * to incorrect reporting of waiting jobs.
     *
     * @param ExecEvent $event
     */
    public function afterExec( ExecEvent $event )
    {
        $push = PushRecord::find()
            ->byJob( $this->getSenderName($event), $event->id )
            ->one();

        if ( !$push )
        {
            Yii::error( "Executed queue event {$event->id} but unable to find push record" );
            return;
        }

        if ( !$event->error )
        {
            $push->status = 'success';
            $push->save();
        }
        elseif ( !$event->retry )
        {
            $push->status = 'buried';
            $push->save();
        }
	    elseif ( $event->retry )
        {
            $push->status = 'waiting';
            $push->save();
        }
    }

    /**
     * @see zhuravljov\yii\queue\monitor\JobMonitor
     * @param JobEvent $event
     * @throws InvalidConfigException
     * @return string
     */
    protected function getSenderName($event)
    {
        foreach (Yii::$app->getComponents(false) as $id => $component) {
            if ($component === $event->sender) {
                return $id;
            }
        }
        throw new InvalidConfigException('Queue must be an application component.');
    }
}
