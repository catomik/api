<?php

namespace backend\modules\api\modules\v1\filters\tariffs;


class PrepaidInternetFilter extends PlanServiceFilter
{
    /**
     * @return string
     */
    protected function getValueFieldName()
    {
        return 'total_prepaid_data_value';
    }

    /**
     * @return string
     */
    protected function getUnitFieldName()
    {
        return 'total_prepaid_data_storage_type';
    }

    /**
     * @return string
     */
    protected function getPeriodFieldName()
    {
        return 'total_prepaid_data_period';
    }
}