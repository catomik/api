<?php

namespace backend\modules\api\modules\v1\factories;


use backend\modules\api\modules\v1\factories\interfaces\PlanServiceFactoryInterface;
use backend\modules\api\modules\v1\utilities\Duration;
use common\utilities\plan_services\Calls;
use common\utilities\units\Period;

class CallsFactory implements PlanServiceFactoryInterface
{
    /**
     * @deprecated
     * @param array $params
     * @return Calls|null
     */
    public function createFromRequest(array $params)
    {
        $calls = null;
        if (
            isset($params['local_calls']) && isset($params['local_calls']['for']) &&
            isset($params['date_start']) && isset($params['date_end'])
        ) {
            $date_start = new \DateTime($params['date_start']);
            $date_end = new \DateTime($params['date_end']);

            /** @var \DateInterval $interval */
            $interval = $date_start->diff($date_end);
            $days = $interval->days + 1;

            $calls =new Calls(
                (float) $params['local_calls']['value'] / $days,
                $params['local_calls']['for'],
                Period::DAY
            );
        }
        return $calls;
    }

    /**
     * @param float|int $value
     * @param string $unit
     * @param string $period
     * @return Calls
     */
    public function create($value, $unit, $period)
    {
        return new Calls($value, $unit, $period);
    }

    /**
     * @param int|float $value
     * @param string $unit
     * @param Duration $duration
     * @return Calls
     */
    public function createByDuration($value, $unit, Duration $duration)
    {
        return new Calls(
            (float) $value / $duration->getDays(),
            $unit,
            Period::DAY
        );
    }
}