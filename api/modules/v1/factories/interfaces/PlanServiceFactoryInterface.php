<?php

namespace backend\modules\api\modules\v1\factories\interfaces;


use backend\modules\api\modules\v1\utilities\Duration;
use common\utilities\plan_services\PlanServiceInterface;

interface PlanServiceFactoryInterface
{
    /**
     * @deprecated 2018-01-12
     * @param array $params
     * @return PlanServiceInterface|null
     */
    public function createFromRequest(array $params);

    /**
     * @param float|int $value
     * @param string $unit
     * @param string $period
     * @throws \Exception
     */
    public function create($value, $unit, $period);

    /**
     * @param int|float $value
     * @param string $unit
     * @param Duration $duration
     * @return PlanServiceInterface
     */
    public function createByDuration($value, $unit, Duration $duration);
}