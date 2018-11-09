<?php

namespace backend\modules\api\modules\v1\filters\tariffs;


use backend\modules\api\modules\v1\factories\interfaces\PlanServiceFactoryInterface;
use backend\modules\api\modules\v1\utilities\Duration;
use common\models\Tariffs;
use common\utilities\plan_services\Internet;
use common\utilities\plan_services\PlanServiceInterface;
use common\utilities\units\Period;

abstract class PlanServiceFilter implements TariffsFilterInterface
{
    /**
     * @var PlanServiceFactoryInterface
     */
    protected $factory;

    /**
     * @var TariffsFilterInterface
     */
    protected $parent_filter;

    /**
     * @var Duration
     */
    protected $duration;

    /**
     * @var PlanServiceInterface
     */
    protected $match_value;

    /**
     * @var PlanServiceInterface
     */
    protected $min_value;

    /**
     * @param PlanServiceFactoryInterface $factory
     * @param TariffsFilterInterface|null $parent_filter
     */
    public function __construct(PlanServiceFactoryInterface $factory, TariffsFilterInterface $parent_filter = null)
    {
        $this->factory = $factory;
        $this->parent_filter = $parent_filter;
    }

    /**
     * @param Duration $duration
     */
    public function setDuration(Duration $duration)
    {
        $this->duration = $duration;
        if (!is_null($this->parent_filter)) {
            $this->parent_filter->setDuration($duration);
        }
    }

    /**
     * @param PlanServiceInterface $match_value
     */
    public function setMatchValue(PlanServiceInterface $match_value)
    {
        $this->match_value = $match_value;
    }

    /**
     * @param PlanServiceInterface $min_value
     */
    public function setMinValue(PlanServiceInterface $min_value)
    {
        $this->min_value = $min_value;
    }

    /**
     * @param Tariffs[] $tariffs
     * @return Tariffs[]
     */
    public function filter(array $tariffs)
    {
        $data = [];
        foreach ($tariffs as $tariff) {
            $tariff_plan_service = $this->factory->create(
                $tariff->{$this->getValueFieldName()},
                $tariff->{$this->getUnitFieldName()},
                $tariff->{$this->getPeriodFieldName()}
            );
            if ($this->applyConditions($tariff_plan_service)) {
                $data[] = $tariff;
            }
        }

        if (!is_null($this->parent_filter)) {
            $data = $this->parent_filter->filter($data);
        }
        return $data;
    }

    /**
     * @param PlanServiceInterface $tariff_plan_service
     * @return bool
     */
    protected function applyConditions(PlanServiceInterface $tariff_plan_service)
    {
        return $this->applyMatchValue($tariff_plan_service) && $this->applyMinValue($tariff_plan_service);
    }

    /**
     * @param PlanServiceInterface $tariff_plan_service
     * @return bool
     */
    protected function applyMatchValue(PlanServiceInterface $tariff_plan_service)
    {
        if(is_null($this->match_value)) {
            return true;
        }

        return $this->match_value->compare($tariff_plan_service);
    }

    /**
     * @param PlanServiceInterface $tariff_plan_service
     * @return bool
     */
    protected function applyMinValue(PlanServiceInterface $tariff_plan_service)
    {
        if(is_null($this->min_value)) {
            return true;
        }

        if (is_null($this->duration)) {
            return $this->min_value->baseValue() <= $tariff_plan_service->baseValue();
        }

        $tariff_period_in_days = Period::convertFromTo(1, $tariff_plan_service->origPeriod(), Period::DAY);
        $number_of_tariff_periods = ceil($this->duration->getDays() / $tariff_period_in_days);

        $plan_service = $this->factory->createByDuration(
            $number_of_tariff_periods * $tariff_plan_service->origValue(),
            $tariff_plan_service->origUnit(),
            $this->duration
        );

        return $this->min_value->baseValue() <= $plan_service->baseValue();
    }

    /**
     * @return string
     */
    abstract protected function getValueFieldName();

    /**
     * @return string
     */
    abstract protected function getUnitFieldName();

    /**
     * @return string
     */
    abstract protected function getPeriodFieldName();
}