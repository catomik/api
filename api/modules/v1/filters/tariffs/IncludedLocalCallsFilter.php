<?php

namespace backend\modules\api\modules\v1\filters\tariffs;


class IncludedLocalCallsFilter extends PlanServiceFilter
{
    /**
     * @return string
     */
    protected function getValueFieldName()
    {
        return 'included_calls_to_local_numbers_value';
    }

    /**
     * @return string
     */
    protected function getUnitFieldName()
    {
        return 'included_calls_to_local_numbers_time_type';
    }

    /**
     * @return string
     */
    protected function getPeriodFieldName()
    {
        return 'included_calls_to_local_numbers_period';
    }
}