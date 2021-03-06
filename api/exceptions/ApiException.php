<?php

namespace backend\modules\api\exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        $status_code = array("100","101","200","201","202","203","204","205","206","300","301","302","303","304","305","306","307","400","401","402","403","404","405","406","407","408","409","410","411","412","413","414","415","416","417","500","501","502","503","504","505");

        if(!in_array($code, $status_code)) {
            $message = 'Invalid status code! - '.$code;
            $code = 500;
        }
        parent::__construct($message, $code, $previous);
    }
}