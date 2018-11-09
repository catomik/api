<?php
/**
 * Date: 26.10.17
 * Time: 13:32
 */

namespace backend\modules\api\controllers;



use backend\modules\api\exceptions\ApiException;
use backend\modules\api\modules\v1\utilities\ApiLogger;
use common\models\ApiKeys;
use common\models\ApiLog;
use function GuzzleHttp\Promise\is_fulfilled;
use Yii;
use yii\base\InvalidRouteException;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

class ApiBaseController extends Controller
{
    protected $response_body = [
        'status' => null,
        'error' => null,
        'data' => null,
    ];

    /**
     * @param $headers
     * @param $ip
     * @throws ApiException
     */
    private function auth($headers,$ip)
    {
        if(!isset($headers['x-api-key']))
            throw new ApiException('Missing header X-Api-Key! Access Denied!',403);

        $key = $headers['x-api-key'];
        if(isset( \Yii::$app->params['secret_api_key']))
            if( \Yii::$app->params['secret_api_key'] == $key)
                return;
        $api_key = ApiKeys::findOne(['key'=>$key]);

        if(is_null($api_key))
            throw new ApiException('Invalid value of X-Api-Key! Access Denied!',403);

        $ips = json_decode($api_key->ips,true);

        $ipr = [];
        if(isset($headers['x-forwarded-for']))
        {
            foreach ($headers['x-forwarded-for'] as $v)
            {
                $ipr[] = $v;
                if(in_array($v,$ips))
                    return;
            }
        }
        $ipr[] = $ip;

        if(!in_array($ip,$ips))
            throw new ApiException('Invalid IP['.implode(',',$ipr).']! Access Denied!',403);
    }

    /**
     *
     */
    protected function formatJson()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }

    /**
     * @param $code
     * @param array $body
     * @return Response
     */
    protected function getResponse($code, array $body)
    {
        $response = new Response;
        $response->format = Response::FORMAT_JSON;
        $response->setStatusCode($code);
        $response->content = json_encode($body);
        return $response;
    }

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     * @throws \backend\modules\api\exceptions\ApiException
     */
    public function beforeAction($action)
    {
        $headers = \Yii::$app->request->headers->toArray();
        $this->auth($headers,Yii::$app->request->getUserIP());
        return parent::beforeAction($action);
    }

    /**
     * @param string $message
     * @return Response
     */
    protected function getBadRequestResponse($message)
    {
        $this->response_body['status'] = 400;
        $this->response_body['error'] = [
            'code' => $this->response_body['status'],
            'message' => $message
        ];
        return $this->getResponse($this->response_body['status'], $this->response_body);
    }

    /**
     * @param string $message
     * @return Response
     */
    protected function getNotFoundResponse($message)
    {
        $this->response_body['status'] = 404;
        $this->response_body['error'] = [
            'code' => $this->response_body['status'],
            'message' => $message
        ];
        return $this->getResponse($this->response_body['status'], $this->response_body);
    }

    /**
     * @param array $data
     * @return Response
     */
    protected function getSuccessResponse(array $data)
    {
        $this->response_body['status'] = 200;
        $this->response_body['data'] = $data;
        return $this->getResponse($this->response_body['status'], $this->response_body);
    }

    /**
     * @param array $data
     * @return Response
     */
    protected function getCreatedResponse(array $data = null)
    {
        $this->response_body['status'] = 201;
        $this->response_body['data'] = $data;
        return $this->getResponse($this->response_body['status'], $this->response_body);
    }

    /**
     *
     */
    public function init()
    {
        parent::init();

        $this->enableCsrfValidation = false;
    }

    /**
     * @param string $id
     * @param array $params
     * @return mixed|Response
     */
    public function runAction($id, $params = [])
    {

        $logger = new ApiLogger();
        Yii::setLogger($logger);
        $timeLogStart = microtime(true);
        $timeLogEnd = null;
        try
        {
            $response = parent::runAction($id, $params);
            $timeLogEnd = microtime(true);
            if(YII_ENV_PROD && $response->format == Response::FORMAT_JSON) {
                $profiling = Yii::getLogger()->getDbProfiling();
                $body  = json_decode($response->content,true);
                $body['profiling'] = $profiling;
                $response->content = json_encode($body);
            }
            return $response;
        }
        catch (InvalidRouteException $e)
        {
            $data = [];
            $response = new Response();
            $response->format = \yii\web\Response::FORMAT_JSON;
            $data['error']['code'] = '404';
            $data['error']['message'] = 'Not Found';
            $response->setStatusCode(404);
            $response->content=json_encode($data);
            return $response;
        }
        catch (HttpException $e)
        {
            $data = [];
            $response = new Response();
            $response->format = \yii\web\Response::FORMAT_JSON;
            $data['error']['code'] = $e->statusCode.'';
            $data['error']['message'] = $e->getMessage().'';
            if(YII_ENV_DEV) {
                $data['error']['file'] = $e->getFile();
                $data['error']['line'] = $e->getLine();
                $data['error']['headers'] = \Yii::$app->request->headers->toArray();
                $data['error']['stacktrace'] = $e->getTraceAsString();
                $profiling = Yii::getLogger()->getDbProfiling();
                $data['profiling'] = $profiling;
            }
            $response->setStatusCode($e->statusCode);
            $response->content=json_encode($data);
            return $response;
        }
        catch (ApiException $e)
        {
            $data = [];
            $response = new Response();
            $response->format = \yii\web\Response::FORMAT_JSON;
            $data['error']['code'] = $e->getCode().'';
            $data['error']['message'] = $e->getMessage().'';
            if(YII_ENV_DEV) {
                $data['error']['headers'] = \Yii::$app->request->headers->toArray();
                $profiling = Yii::getLogger()->getDbProfiling();
                $data['profiling'] = $profiling;
            }
            $response->setStatusCode($e->getCode());
            $response->content=json_encode($data);
            return $response;
        }
        catch (\Exception $e)
        {
            $data = [];
            $response = new Response();
            $response->format = \yii\web\Response::FORMAT_JSON;
            $data['error']['code'] = $e->getCode().'';
            $data['error']['message'] = $e->getMessage().'';
            if(YII_ENV_DEV) {
                $data['error']['file'] = $e->getFile();
                $data['error']['line'] = $e->getLine();
                $data['error']['headers'] = \Yii::$app->request->headers->toArray();
                $data['error']['stacktrace'] = $e->getTraceAsString();
                $profiling = Yii::getLogger()->getDbProfiling();
                $data['profiling'] = $profiling;
            }
            $response->setStatusCode(500);
            $response->content=json_encode($data);
            return $response;
        }
        finally
        {
            if (!isset($timeLogEnd)) {
                $timeLogEnd = microtime(true);
            }
            $api_key = ApiKeys::findOne(['key' => Yii::$app->request->headers['x-api-key']]);

            $api_log = new ApiLog();
            $api_log->method = Yii::$app->request->method;
            $api_log->url = Yii::$app->request->url;
            $api_log->status = $response->getStatusCode();
            $api_log->api_key_id = is_null($api_key) ? null : $api_key->id;
            $api_log->ip_request = Yii::$app->request->userIP;
            $api_log->headers = json_encode(Yii::$app->request->headers->toArray());
            $api_log->data_request = Yii::$app->request->rawBody;
            $api_log->data_response = $response->content;
            $api_log->api_time = $timeLogEnd - $timeLogStart;

            $api_log->save();
        }
    }
}