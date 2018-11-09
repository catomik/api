<?php

namespace backend\modules\api\modules\v1\factories;


use backend\modules\api\modules\v1\factories\interfaces\PlanServiceFactoryInterface;
use backend\modules\api\modules\v1\utilities\Duration;
use common\utilities\plan_services\Internet;
use common\utilities\units\Period;

class InternetFactory implements PlanServiceFactoryInterface
{
    /**
     * @deprecated 2018-01-12
     * @param array $params
     * @return Internet|null
     */
    public function createFromRequest(array $params)
    {
        $internet = null;
        if (
            isset($params['data']) && isset($params['data']['for']) &&
            isset($params['date_start']) && isset($params['date_end'])
        ) {
            $date_start = new \DateTime($params['date_start']);
            $date_end = new \DateTime($params['date_end']);

            /** @var \DateInterval $interval */
            $interval = $date_start->diff($date_end);
            $days = $interval->days + 1;

            $internet =new Internet(
                (float) $params['data']['value'] / $days,
                $params['data']['for'],
                Period::DAY
            );
        }
        return $internet;
    }

    /**
     * @param float|int $value
     * @param string $unit
     * @param string $period
     * @return Internet
     */
    public function create($value, $unit, $period)
    {
        return new Internet($value, $unit, $period);
    }

    /**
     * @param int|float $value
     * @param string $unit
     * @param Duration $duration
     * @return Internet
     */
    public function createByDuration($value, $unit, Duration $duration)
    {
        return new Internet(
            (float) $value / $duration->getDays(),
            $unit,
            Period::DAY
        );
    }
}