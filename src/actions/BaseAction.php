<?php

namespace akhur0286\paykeeper\actions;

use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;

/**
 * Class BaseAction
 * @package robokassa
 */
class BaseAction extends Action
{
    public $callback;

    /**
     * @param mixed $orderID
     * @return mixed
     * @throws InvalidConfigException
     */
    protected function callback($orderID)
    {
        if (!is_callable($this->callback)) {
            throw new InvalidConfigException('"' . get_class($this) . '::callback" should be a valid callback.');
        }
        $response = call_user_func($this->callback, $orderID);
        return $response;
    }
    
    /**
     * Runs the action.
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function run()
    {
        if (!isset($_REQUEST['orderid'])) {
            throw new BadRequestHttpException;
        }

        return $this->callback($_REQUEST);
    }
}
