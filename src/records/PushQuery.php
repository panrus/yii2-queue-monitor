<?php
/**
 * @link https://github.com/zhuravljov/yii2-queue-monitor
 * @copyright Copyright (c) 2017 Roman Zhuravlev
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace zhuravljov\yii\queue\monitor\records;

use DateInterval;
use DateTime;
use yii\db\ActiveQuery;
use yii\db\Query;

/**
 * Push Query
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class PushQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->alias('push');
    }

    /**
     * @param int $id
     * @return $this
     */
    public function byId($id)
    {
        return $this->andWhere(['push.id' => $id]);
    }

    /**
     * @param string $senderName
     * @param string $jobUid
     * @return $this
     */
    public function byJob($senderName, $jobUid)
    {
        return $this
            ->andWhere(['push.sender_name' => $senderName])
            ->andWhere(['push.job_uid' => $jobUid])
            ->orderBy(['push.id' => SORT_DESC])
            ->limit(1);
    }

    /**
     * Fetch all push records which are waiting and should have been run
     *
     * @return $this
     */
    public function backlogged()
    {
        return $this
            ->waiting()
            ->andWhere('{{push}}.[[desired_execute_time]] <= UNIX_TIMESTAMP()');
    }

    /**
     * Fetch all push records which are waiting because they have been
     * intentionally delayed
     *
     * @return $this
     */
    public function delayed()
    {
        return $this
            ->waiting()
            ->andWhere('{{push}}.[[desired_execute_time]] > UNIX_TIMESTAMP()');
    }

    /**
     * @return $this
     */
    public function waiting()
    {
        return $this->andWhere(['push.status' => 'waiting']);
    }

    /**
     * @return $this
     */
    public function inProgress()
    {
        return $this->andWhere(['push.status' => 'in_progress']);
    }

    /**
     * @return $this
     */
    public function done()
    {
        return $this->andWhere(['push.status' => ['buried','success']]);
    }

    /**
     * @return $this
     */
    public function success()
    {
        return $this->andWhere(['push.status' => 'success']);
    }

    /**
     * @return $this
     */
    public function buried()
    {
        return $this->andWhere(['push.status' => 'buried']);
    }

    /**
     * @return $this
     */
    public function hasFails()
    {
        return $this
            ->andWhere(['exists', new Query([
                'from' => ['exec' => ExecRecord::tableName()],
                'where' => '{{exec}}.[[push_id]] = {{push}}.[[id]] AND {{exec}}.[[error]] IS NOT NULL',
            ])]);
    }

    /**
     * @return $this
     */
    public function stopped()
    {
        return $this->andWhere(['is not', 'push.stopped_at', null]);
    }

    /**
     * @return $this
     */
    public function joinFirstExec()
    {
        return $this->leftJoin(
            ['first_exec' => ExecRecord::tableName()],
            '{{first_exec}}.[[id]] = {{push}}.[[first_exec_id]]'
        );
    }

    /**
     * @return $this
     */
    public function joinLastExec()
    {
        return $this->leftJoin(
            ['last_exec' => ExecRecord::tableName()],
            '{{last_exec}}.[[id]] = {{push}}.[[last_exec_id]]'
        );
    }

    /**
     * @return $this
     */
    public function innerJoinLastExec()
    {
        return $this->innerJoin(
            ['last_exec' => ExecRecord::tableName()],
            '{{last_exec}}.[[id]] = {{push}}.[[last_exec_id]]'
        );
    }

    /**
     * @param string $interval
     * @link https://www.php.net/manual/en/dateinterval.construct.php
     * @return $this
     */
    public function deprecated($interval)
    {
        $min = (new DateTime())->sub(new DateInterval($interval))->getTimestamp();
        return $this->andWhere(['<', 'push.pushed_at', $min]);
    }

    /**
     * @inheritdoc
     * @return PushRecord[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return PushRecord|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
