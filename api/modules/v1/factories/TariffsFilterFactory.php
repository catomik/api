<?php

namespace backend\modules\api\modules\v1\factories;


use backend\modules\api\modules\v1\filters\tariffs\IncludedLocalCallsFilter;
use backend\modules\api\modules\v1\filters\tariffs\PrepaidInternetFilter;
use backend\modules\api\modules\v1\filters\tariffs\TariffsFilterInterface;
use backend\modules\api\modules\v1\utilities\Duration;
use common\utilities\plan_services\Calls;
use common\utilities\plan_services\Internet;

class TariffsFilterFactory
{
    /**
     * @param array $params
     * @return TariffsFilterInterface|null
     */
    public function create(array $params = [])
    {
        $filter = null;

        if (
            isset($params['internet']) && ($params['internet'] instanceof Internet)
        ) {
            $filter = new PrepaidInternetFilter($params['internet_factory'], $filter);
            $filter->setMinValue($params['internet']);
        }

        if (
            isset($params['calls']) && ($params['calls'] instanceof Calls)
        ) {
            $filter = new IncludedLocalCallsFilter($params['calls_factory'], $filter);
            $filter->setMinValue($params['calls']);
        }

        if (
            $filter && isset($params['duration']) && ($params['duration'] instanceof Duration)
        ) {
            $filter->setDuration($params['duration']);
        }

        return $filter;
    }
}