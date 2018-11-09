<?php

namespace backend\modules\api\modules\v1\utilities;


class Duration
{
    /**
     * @var int
     */
    protected $days;

    /**
     * Duration constructor.
     * @param string $date_start (Y-m-d H:i:s)
     * @param string $date_end (Y-m-d H:i:s)
     */
    public function __construct($date_start, $date_end)
    {
        $start = new \DateTime($date_start);
        $end = new \DateTime($date_end);

        /** @var \DateInterval $interval */
        $interval = $start->diff($end);
        $this->days = $interval->days + 1;
    }

    /**
     * @return int
     */
    public function getDays()
    {
        return $this->days;
    }
}