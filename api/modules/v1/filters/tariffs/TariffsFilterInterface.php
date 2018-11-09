<?php

namespace backend\modules\api\modules\v1\filters\tariffs;


use backend\modules\api\modules\v1\factories\interfaces\PlanServiceFactoryInterface;
use backend\modules\api\modules\v1\utilities\Duration;
use common\models\Tariffs;

interface TariffsFilterInterface
{
    /**
     * @param PlanServiceFactoryInterface $factory
     * @param TariffsFilterInterface|null $parent_filter
     */
    public function __construct(PlanServiceFactoryInterface $factory, TariffsFilterInterface $parent_filter = null);

    /**
     * @param Tariffs[] $tariffs
     * @return Tariffs[]
     */
    public function filter(array $tariffs);

    /**
     * @param Duration $duration
     */
    public function setDuration(Duration $duration);
}