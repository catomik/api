<?php
/**
 * ApiLogger.php
 * @copyright ©loccalls-backend
 * @author Valentin Stepanenko catomik13@gmail.com
 */

namespace backend\modules\api\modules\v1\utilities;


use yii\log\Logger;

class ApiLogger extends Logger
{
    public function calculateTimings($messages)
    {
        $timings = [];
        $stack = [];

        foreach ($messages as $i => $log) {
            list($token, $level, $category, $timestamp, $traces) = $log;
            $memory = isset($log[5]) ? $log[5] : 0;
            $log[6] = $i;
            if(is_array($token))
                $token = json_encode($token);
            $hash = md5($token);
            if ($level == Logger::LEVEL_PROFILE_BEGIN) {
                $stack[$hash] = $log;
            } elseif ($level == Logger::LEVEL_PROFILE_END) {
                if (isset($stack[$hash])) {
                    $timings[$stack[$hash][6]] = [
                        'info' => $stack[$hash][0],
                        'category' => $stack[$hash][2],
                        'timestamp' => $stack[$hash][3],
                        'trace' => $stack[$hash][4],
                        'level' => count($stack) - 1,
                        'duration' => $timestamp - $stack[$hash][3],
                        'memory' => $memory,
                        'memoryDiff' => $memory - (isset($stack[$hash][5]) ? $stack[$hash][5] : 0),
                    ];
                    unset($stack[$hash]);
                }
            }
        }

        ksort($timings);

        return array_values($timings);
    }
}