<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use backend\modules\api\exceptions\RequestValidationException;
use backend\modules\api\modules\v1\factories\AddonTransformerFactory;
use backend\modules\api\modules\v1\factories\CallsFactory;
use backend\modules\api\modules\v1\factories\InternetFactory;
use backend\modules\api\modules\v1\factories\TariffsFilterFactory;
use backend\modules\api\modules\v1\factories\TariffTransformerFactory;
use backend\modules\api\modules\v1\transformers\AddonTransformer;
use backend\modules\api\modules\v1\transformers\CurrencyTransformer;
use backend\modules\api\modules\v1\transformers\TariffTransformer;
use backend\modules\api\modules\v1\utilities\Duration;
use common\models\Addons;
use common\models\Cities;
use common\models\Countries;
use common\models\Currency;
use common\models\Languages;
use common\models\TariffFields;
use common\models\Tariffs;
use common\models\TariffsAddons;
use common\models\TariffsTariffFields;
use common\utilities\units\InternetSize;
use common\utilities\units\Period;
use common\utilities\units\Time;
use yii\db\ActiveQuery;

class SearchController extends ApiBaseController
{
    /**
     * @return \yii\web\Response
     */
    public function actionCountry()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $language_param = \Yii::$app->request->get('language');
        if (is_null($language_param)) {
            return $this->getBadRequestResponse('Missing argument: language!');
        }

        $language = Languages::findOne(['iso' => strtolower($language_param), 'activated' => true]);
        if(is_null($language)){
            return $this->getBadRequestResponse("Language '".$language_param."' is not activated or doesn't exist!");
        }

        $activated_param = \Yii::$app->request->get('status');

        $countries = Countries::find();

        if ($activated_param == 1)
            $countries = $countries->where(['activated' => true]);

        $countries = $countries->translate(Languages::getDefault())->orderBy('id')->all();

        $data = [];

        foreach ($countries as $country) {
//            $country->translate();

            $data[] = [
                'id' => $country->id,
                'code' => $country->iso,
                'status' => $country->activated,
                'name' => $country->name,
                'name_translation' => $country->translate($language->iso)->name
            ];
        }

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionCity()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $language_param = \Yii::$app->request->get('language');
        if (is_null($language_param)) {
            return $this->getBadRequestResponse('Missing argument: language!');
        }

        $language = Languages::findOne(['iso' => strtolower($language_param), 'activated' => true]);
        if (is_null($language)) {
            return $this->getBadRequestResponse("Language '$language_param' is not activated or doesn't exist!");
        }

        $country_param = \Yii::$app->request->get('country');
        if (is_null($country_param)) {
            return $this->getBadRequestResponse('Missing argument: country!');
        }

        $country = Countries::findOne(['iso' => $country_param, 'activated' => true]);
        if (is_null($country)) {
            return $this->getBadRequestResponse("Country '$country_param' is not activated or doesn't exist!");
        }

        $activated_param = \Yii::$app->request->get('status');

        $cities = Cities::find()->where(['country_id' => $country->id]);

        if ($activated_param == 1)
            $cities = $cities->andWhere(['activated' => true]);

        $cities = $cities->translate(Languages::getDefault())->orderBy('id')->all();

        $data = [];

        foreach ($cities as $city) {
//            $city->translate(Languages::getDefault());

            $data[] = [
                'id' => $city->id,
                'code' => $city->code,
                'status' => $city->activated,
                'name' => $city->name,
                'name_translation' => $city->translate($language->iso)->name
            ];
        }

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionTariff()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        try {
            $currency = $this->getCurrency(\Yii::$app->request->get('currency'));
            $country = $this->getCountry(\Yii::$app->request->get('country'));
            $language = $this->getLanguage(\Yii::$app->request->get('language'));
        } catch (RequestValidationException $e) {
            return $this->getBadRequestResponse($e->getMessage());
        }

        $data = [];
        $operatorsId = [];

        $tariff_transformer = (new TariffTransformerFactory())->create([
            'language' => $language,
            'currency' => $currency,
        ]);
        $addon_transformer = (new AddonTransformerFactory())->create([
            'language' => $language,
            'currency' => $currency,
        ]);

        /** @var Tariffs[] $tariffs */
        $tariffs = $this->buildTariffsQuery([
            'country' => $country,
            'language' => $language,
        ])->all();

        $countries_ids = [];
        $tariff_ids = [];
        foreach ($tariffs as $tariff) {
            if($tariff->country_id) {
                $countries_ids[$tariff->country_id] = $tariff->country_id;
            }
            if($tariff->operator->country) {
                $countries_ids[$tariff->operator->country] = $tariff->operator->country;
            }
            $tariff_ids[$tariff->id] = $tariff->id;
        }

        $params['tariffs_tariff_fields'] = TariffsTariffFields::find()->translate($language->iso)
            ->with(['tariffFields'=>function($q)use($language){
                $q->translate($language->iso);
            }])
            ->where(['in','tariffs_id',$tariff_ids])
            ->andWhere(['isDeleted' => false])
            ->orderBy('id')->all();

        $variables = [];
        /**
         * @var $field TariffsTariffFields
         */
        foreach ($params['tariffs_tariff_fields'] as $field) {
            if($field->tariffFields->type == 3) {
                $tableName = json_decode($field->tariffFields->variants)->table;
                $variables[$tableName][] = $field->value;
            }
        }
        foreach ($variables as $tableName => $variable) {
            $table = TariffFields::getLinksClass($tableName);
            $values = $table::find()->where(['in','id',$variable])->indexBy('id')->all();
            $variables[$tableName] = $values;
        }
        $params['variables'] = $variables;

        $params['countries_def'] = Countries::find()->where(['in','id',$countries_ids])->translate(Languages::getDefault())->indexBy('id')->all();
        $params['countries_lang'] = Countries::find()->where(['in','id',$countries_ids])->translate($language->iso)->indexBy('id')->all();

        $data["tariffs"] = [];
        foreach ($tariffs as $tariff) {
            $data["tariffs"][$tariff->id] = $tariff_transformer->transform($tariff);

            if (!in_array($tariff->operator_id, $operatorsId)) {
                $operatorsId[] = $tariff->operator_id;
            }
        }

        /** @var Addons[] $addons */
        $addons = Addons::find()->with('currency', 'operator')->where(['operator_id' => $operatorsId])->all();

        $data["addons"] = [];
        foreach ($addons as $addon) {
            $data["addons"][$addon->operator_id][$addon->id] = $addon_transformer->transform($addon);
        }

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionTariffAdvanced()
    {
        if (!\Yii::$app->request->isPost) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $body = json_decode(\Yii::$app->request->getRawBody(), true);
        if (!$body) {
            return $this->getBadRequestResponse("Request body has to be valid json string. Error message: '".json_last_error_msg()."'");
        }

        try {
            $currency = $this->getCurrency($body['currency']);
            $country = $this->getCountry($body['country']);
            $language = $this->getLanguage($body['language']);
            $this->validateRequestBody($body);
        } catch (RequestValidationException $e) {
            return $this->getBadRequestResponse($e->getMessage());
        }

        $duration = new Duration($body['date_start'], $body['date_end']);

        $internet_factory = new InternetFactory();
        $calls_factory = new CallsFactory();

        $request_internet = isset($body['data']) ? $internet_factory->createByDuration(
            (float) $body['data']['value'], $body['data']['for'], $duration
        ) : null;

        $request_calls = isset($body['local_calls']) ? $calls_factory->createByDuration(
            (float) $body['local_calls']['value'], $body['local_calls']['for'], $duration
        ) : null;

        $tariff_transformer = (new TariffTransformerFactory())->create([
            'language' => $language,
            'currency' => $currency,
            'internet_format' => $request_internet,
            'calls_format' => $request_calls
        ]);
        $addon_transformer = (new AddonTransformerFactory())->create([
            'language' => $language,
            'currency' => $currency,
            'internet_format' => $request_internet,
            'calls_format' => $request_calls
        ]);

        /** @var Tariffs[] $tariffs */
        $tariffs = $this->buildTariffsQuery([
            'country' => $country,
            'language' => $language,
            'request_params' => $body,
        ])->all();

        $tariffs_filter = (new TariffsFilterFactory())->create([
            'internet' => $request_internet,
            'calls' => $request_calls,
            'duration' => $duration,
            'internet_factory' => $internet_factory,
            'calls_factory' => $calls_factory
        ]);
        if (!is_null($tariffs_filter)) {
            $tariffs = $tariffs_filter->filter($tariffs);
        }

        $countries_ids = [];
        $tariff_ids = [];
        foreach ($tariffs as $tariff) {
            if($tariff->country_id) {
                $countries_ids[$tariff->country_id] = $tariff->country_id;
            }
            if($tariff->operator->country) {
                $countries_ids[$tariff->operator->country] = $tariff->operator->country;
            }
            $tariff_ids[$tariff->id] = $tariff->id;
        }

        $params['tariffs_tariff_fields'] = TariffsTariffFields::find()->translate($language->iso)
            ->with(['tariffFields'=>function($q)use($language){
                $q->translate($language->iso);
            }])
            ->where(['in','tariffs_id',$tariff_ids])
            ->andWhere(['isDeleted' => false])
            ->orderBy('id')->all();

        $variables = [];
        /**
         * @var $field TariffsTariffFields
         */
        foreach ($params['tariffs_tariff_fields'] as $field) {
            if($field->tariffFields->type == 3) {
                $tableName = json_decode($field->tariffFields->variants)->table;
                $variables[$tableName][] = $field->value;
            }
        }
        foreach ($variables as $tableName => $variable) {
            $table = TariffFields::getLinksClass($tableName);
            $values = $table::find()->where(['in','id',$variable])->indexBy('id')->all();
            $variables[$tableName] = $values;
        }
        $params['variables'] = $variables;

        $params['countries_def'] = Countries::find()->where(['in','id',$countries_ids])->translate(Languages::getDefault())->indexBy('id')->all();
        $params['countries_lang'] = Countries::find()->where(['in','id',$countries_ids])->translate($language->iso)->indexBy('id')->all();

        $tariffs_array = [];
        $operatorsId = [];

        foreach ($tariffs as $tariff) {
            $tariffs_array[$tariff->id] = $tariff_transformer->transform($tariff,$params);
            if (!in_array($tariff->operator_id, $operatorsId)) {
                $operatorsId[] = $tariff->operator_id;
            }
        }

        /** @var Addons[] $addons */
        $addons = Addons::find()->with('currency', 'operator')
            ->where(['operator_id' => $operatorsId])
            ->andWhere(['isDeleted' => false])
            ->andWhere(['status' => true])->translate($language->iso)
            ->all();
        $addons_array = [];
        foreach ($addons as $addon) {
            $addons_array[$addon->operator_id][$addon->id] = $addon_transformer->transform($addon);
        }

        /** @var Currency[] $currencies */
        $currencies = Currency::find()->where(['activated' => true])->all();
        $currency_transformer = new CurrencyTransformer();
        $currencies_array = [];
        foreach ($currencies as $currency_actived) {
            $currencies_array[] = $currency_transformer->transform($currency_actived, [
                'language' => $language,
            ]);
        }

        return $this->getSuccessResponse([
            'tariffs' => $tariffs_array,
            'addons' => $addons_array,
            'currencies' => $currencies_array
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionTariffOffer()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        try {
            $language = $this->getLanguage(\Yii::$app->request->get('language'));
        } catch (RequestValidationException $e) {
            return $this->getBadRequestResponse($e->getMessage());
        }

        if (!\Yii::$app->request->get('type')) {
            return $this->getBadRequestResponse('Missing parameter: type.');
        }

        $params = [];

        if (\Yii::$app->request->get('currency')) {
            $currency = \Yii::$app->request->get('currency');
            $params['currency'] = $this->getCurrency($currency);
        }

        $type = \Yii::$app->request->get('type');

        if (!in_array($type, ['best', 'promo'])) {
            return $this->getBadRequestResponse("Parameter 'type' has to be equal 'best' or 'promo'. '$type' given");
        }

        $promo = $type == 'promo';
        $best = $type == 'best';

        /** @var Tariffs[] $tariffs */
        $tariffs = $this->buildTariffsQuery([
            'promo' => $promo ? true : null,
            'best' => $best ? true : null,
            'language' => $language,
        ])->all();

        if (empty($tariffs)) {
            return $this->getBadRequestResponse('For this request, not one tariff was found');
        }

        $tariff_transformer = (new TariffTransformerFactory())->create([
            'language' => $language
        ]);


        $countries_ids = [];
        $tariff_ids = [];
        foreach ($tariffs as $tariff) {
            if($tariff->country_id) {
                $countries_ids[$tariff->country_id] = $tariff->country_id;
            }
            if($tariff->operator->country) {
                $countries_ids[$tariff->operator->country] = $tariff->operator->country;
            }
            $tariff_ids[$tariff->id] = $tariff->id;
        }

        $params['tariffs_tariff_fields'] = TariffsTariffFields::find()->translate($language->iso)
            ->with(['tariffFields'=>function($q)use($language){
                $q->translate($language->iso);
            }])
            ->where(['in','tariffs_id',$tariff_ids])
            ->andWhere(['isDeleted' => false])
            ->orderBy('id')->all();

        $variables = [];
        /**
         * @var $field TariffsTariffFields
         */
        foreach ($params['tariffs_tariff_fields'] as $field) {
            if($field->tariffFields->type == 3) {
                $tableName = json_decode($field->tariffFields->variants)->table;
                $variables[$tableName][] = $field->value;
            }
        }
        foreach ($variables as $tableName => $variable) {
            $table = TariffFields::getLinksClass($tableName);
            $values = $table::find()->where(['in','id',$variable])->indexBy('id')->all();
            $variables[$tableName] = $values;
        }
        $params['variables'] = $variables;

        $params['countries_def'] = Countries::find()->where(['in','id',$countries_ids])->translate(Languages::getDefault())->indexBy('id')->all();
        $params['countries_lang'] = Countries::find()->where(['in','id',$countries_ids])->translate($language->iso)->indexBy('id')->all();

        $tariffs_array = [];
        foreach ($tariffs as $tariff) {
            $tariffs_array[$tariff->id] = $tariff_transformer->transform($tariff, $params);
        }

        return $this->getSuccessResponse(['tariffs' => $tariffs_array]);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionDetailTariff()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        try {
            $language = $this->getLanguage(\Yii::$app->request->get('language'));
        } catch (RequestValidationException $e) {
            return $this->getBadRequestResponse($e->getMessage());
        }

        if (!\Yii::$app->request->get('id')) {
            return $this->getBadRequestResponse('Missing parameter: id.');
        }

        $params = [];

        if (\Yii::$app->request->get('currency')) {
            $currency = \Yii::$app->request->get('currency');
            $params['currency'] = $this->getCurrency($currency);
        }
        
        $id = (int) \Yii::$app->request->get('id');

        $tariff = Tariffs::find()->where(['id' => $id])->one();

        if (!$tariff) {
            return $this->getNotFoundResponse("Tariff with id '$id' was not found");
        }

        if ($tariff->date_to <= \time()) {
            return $this->getNotFoundResponse("Tariff since ". date("Y-m-d",$tariff->date_to) ." is not actived");
        }

        $tariff_transformer = (new TariffTransformerFactory())->create([
            'language' => $language
        ]);

        $addon_transformer = (new AddonTransformerFactory())->create([
            'language' => $language
        ]);

        $countries_ids = [];
        $tariff_ids = [];
        if($tariff->country_id) {
            $countries_ids[$tariff->country_id] = $tariff->country_id;
        }
        if($tariff->operator->country) {
            $countries_ids[$tariff->operator->country] = $tariff->operator->country;
        }
        $tariff_ids[$tariff->id] = $tariff->id;

        $params['tariffs_tariff_fields'] = TariffsTariffFields::find()->translate($language->iso)
            ->with(['tariffFields'=>function($q)use($language){
                $q->translate($language->iso);
            }])
            ->where(['in','tariffs_id',$tariff_ids])
            ->andWhere(['isDeleted' => false])
            ->orderBy('id')->all();

        $variables = [];
        /**
         * @var $field TariffsTariffFields
         */
        foreach ($params['tariffs_tariff_fields'] as $field) {
            if($field->tariffFields->type == 3) {
                $tableName = json_decode($field->tariffFields->variants)->table;
                $variables[$tableName][] = $field->value;
            }
        }
        foreach ($variables as $tableName => $variable) {
            $table = TariffFields::getLinksClass($tableName);
            $values = $table::find()->where(['in','id',$variable])->indexBy('id')->all();
            $variables[$tableName] = $values;
        }
        $params['variables'] = $variables;

        $params['countries_def'] = Countries::find()->where(['in','id',$countries_ids])->translate(Languages::getDefault())->indexBy('id')->all();
        $params['countries_lang'] = Countries::find()->where(['in','id',$countries_ids])->translate($language->iso)->indexBy('id')->all();
        $tariffs_array = [
            $tariff_transformer->transform($tariff, $params)
        ];

        $addons_array = [];
        foreach ($tariff->addons as $addon) {
            $addons_array[$addon->id] = $addon_transformer->transform($addon, $params);
        }

        return $this->getSuccessResponse([
            'tariffs' => $tariffs_array,
            'addons' => $addons_array
        ]);
    }

    /**
     * @param string $param
     * @return Currency
     * @throws \Exception
     */
    protected function getCurrency($param)
    {
        if (is_null($param)) {
            throw new RequestValidationException('Missing argument: currency!');
        }
        $currency = Currency::findOne(['iso' => $param, 'activated' => true]);
        if (is_null($currency)) {
            throw new RequestValidationException("Currency '$param' is not activated");
        }
        return $currency;
    }

    /**
     * @param string $param
     * @return Countries
     * @throws \Exception
     */
    protected function getCountry($param)
    {
        if (is_null($param)) {
            throw new RequestValidationException('Missing argument: country!');
        }
        $country = Countries::findOne(['iso' => $param, 'activated' => true]);
        if (is_null($country)) {
            throw new RequestValidationException("Country '$param' is not activated");
        }
        return $country;
    }

    /**
     * @param string $param
     * @return Languages
     * @throws \Exception
     */
    protected function getLanguage($param)
    {
        if(is_null($param)) {
            return Languages::findOne(['iso' => strtolower(Languages::getDefault()), 'activated' => true]);
        }

        $language = Languages::findOne(['iso' => strtolower($param), 'activated' => true]);
        if (!$language) {
            throw new RequestValidationException("Language '$param' is not activated");
        }
        return $language;
    }

    /**
     * @param array $body
     * @return bool
     * @throws RequestValidationException
     */
    protected function validateRequestBody(array $body)
    {
        if (!isset($body['date_start'])) {
            throw new RequestValidationException("Parameter 'date_start' is required");
        }
        if (!isset($body['date_end'])) {
            throw new RequestValidationException("Parameter 'date_end' is required");
        }

        $date_start = strtotime($body['date_start']);
        $date_end = strtotime($body['date_end']);
        if (!$date_start || !$date_end) {
            throw new RequestValidationException("Date has to be in format 'YYYY-mm-dd HH:ii:ss'");
        }
        if ($date_end < $date_start) {
            throw new RequestValidationException("'date_start' has to be not bigger than 'date_end'");
        }


        if (isset($body['data'])) {
            if (!isset($body['data']['value']) || !isset($body['data']['for'])) {
                throw new RequestValidationException("Key 'data' has to contain keys 'value', 'for'");
            }
            if (!InternetSize::exists($body['data']['for'])) {
                throw new RequestValidationException("Internet size unit '{$body['data']['for']}' is invalid");
            }
        }

        if (isset($body['local_calls'])) {
            if (!isset($body['local_calls']['value']) || !isset($body['local_calls']['for'])) {
                throw new RequestValidationException("Key 'local_calls' has to contain keys 'value', 'for'");
            }
            if (!Time::exists($body['local_calls']['for'])) {
                throw new RequestValidationException("Time unit '{$body['local_calls']['for']}' is invalid");
            }
        }

        return true;
    }

    /**
     * @param array $params
     * @return ActiveQuery
     */
    protected function buildTariffsQuery(array $params = [])
    {
        $lang = $params['language']->iso;
        $query = Tariffs::find()
            ->where(['status' => true])
            ->andWhere(['isDeleted' => false])
            ->andWhere(['>=','date_to', \time()])
            ->with(['currency', 'operator.countries'=>function($q)use($lang){
                $q->translate($lang);
            }, 'addons'=>function($q)use($lang){
                $q->translate($lang);
            }, 'addonsRelation'=>function($q)use($lang){
                $q->translate($lang);
            }, 'operator'=>function($q)use($lang){
                $q->translate($lang);
            }])->translate($lang);

        if (isset($params['country']) && ($params['country'] instanceof Countries)) {
            $query = $query->andWhere(['country_id' => $params['country']->id]);
        }

        if (isset($params['best']) && $params['best'] === true) {
            $query = $query->andWhere(['best' => true]);
        }

        if (isset($params['promo']) && $params['promo'] === true) {
            $query = $query->andWhere(['or',
                'promo_internet=true', 'promo_calls=true', 'promo_recommended=true', 'promo_popular=true'
            ]);
        }

        if (!isset($params['request_params'])) {
            return $query;
        }

        $body = $params['request_params'];

        if (isset($body['calls_within_network_unlim']) && $body['calls_within_network_unlim']) {
            $query = $query->andWhere(['calls_within_network_type' => 1]);
        }

        if (isset($body['date_start'])) {
            $date_start = strtotime($body['date_start']);
            $query = $query->andWhere(['<=', 'date_from', $date_start])->andWhere(['>', 'date_to', $date_start]);
        }

        if (isset($body['date_end'])) {
            $date_end = strtotime($body['date_end']);
            $query = $query->andWhere(['<', 'date_from', $date_end])->andWhere(['>=', 'date_to', $date_end]);
        }

        return $query;
    }

    /**
     * @return array
     */
    protected function getFakeCountries()
    {
        return [
            [
                "id" => 123,
                "code" => "UA",
                "status" => true,
                "name" => "Ukraine",
                "name_translation" => "Украина"
            ], [
                "id" => 124,
                "code" => "GB",
                "status" => true,
                "name" => "United Kingdom",
                "name_translation" => "Великобритания"
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getFakeCities()
    {
        return [
            [
                "id" => 1233,
                "code" => "IEV",
                "status" => true,
                "name" => "Kiev",
                "name_translation" => "Киев"
            ], [
                "id" => 1244,
                "code" => "DNK",
                "status" => true,
                "name" => "Dnipropetrovsk",
                "name_translation" => "Днепр"
            ],
        ];
    }

    protected function getFakeTariffsAndAddons()
    {
        return [
            "tariffs" => [
                // массив тарифов
                [
                    "tariff_id" => 123,
                    "tariff_name" => "Some tariff",
                    "operator_id" => 123,
                    "operator_name" => "Some operator",
                    "promo_internet" => false,
                    "promo_calls" => false,
                    "promo_recommended" => false,
                    "promo_popular" => false,
                    "for_social" => false,
                    "currency" => "UAH",
                    "currency_conv" => "USD",
                    "price" => 123.12,
                    "price_conv" => 123.12,
                    "country" => [
                        'code' => 'UA',
                        'name' => 'Ukraine',
                        'name_translation' => 'Україна'
                    ],
                    "language" => "uk",
                    "language_name" => "Украинский",
                    "language_name_original" => "Українська",
                    "image" => "https://www.lifecell.ua/uploads/cache/b9/77/b9770144a9032fc9bb5146494e4d7d7b.jpg",
                    "calls_within_network_value" => [
                        "value" => 50,
                        "for" => "min",
                        "per" => "day"
                    ],
                    "included_calls_to_local_numbers" => [
                        "value" => 50,
                        "for" => "min",
                        "per" => "day"
                    ],
                    "total_prepaid_data" => [
                        "value" => 3000,
                        "for" => "MB",
                        "per" => "month",
                        "description" => "Prepaid data description"
                    ],
                    "out_of_plan_data_charges" => [
                        "value" => 0.5,
                        "for" => "UAH",
                        "per" => "min"
                    ],
                    "restrictions_for_data" => "Some text",
                    "social" => [
                        [
                            "name" => "Facebook",
                            "logo" => "http:\\some.logo",
                            "url" => "http:\\some.url",
                            "code" => "facebook"
                        ]
                    ],
                    "restrictions_for_social" => "Some text",
                    "sms_included" => [
                        "value" => 50,
                        "for" => "sms",
                        "per" => "week"
                    ],
                    "date_from" => "2018-01-02 00:00:00",
                    "date_to" => "2018-03-01 00:00:00",
                    "last_update" => "2017-12-04 15:24:34",
                    "some_field" => "some value"//тут и далее динамические поля, значение может быть числом/строкой/массивом
                ],
                [
                    "tariff_id" => 12,
                    "tariff_name" => "Some tariff 2",
                    "operator_id" => 123,
                    "operator_name" => "Some operator 2",
                    "promo_internet" => false,
                    "promo_calls" => false,
                    "promo_recommended" => false,
                    "promo_popular" => false,
                    "for_social" => false,
                    "currency" => "UAH",
                    "currency_conv" => "USD",
                    "price" => 123.12,
                    "price_conv" => 123.12,
                    "country" => [
                        'code' => 'UA',
                        'name' => 'Ukraine',
                        'name_translation' => 'Україна'
                    ],
                    "language" => "uk",
                    "language_name" => "Украинский",
                    "language_name_original" => "Українська",
                    "image" => null,
                    "calls_within_network_value" => [
                        "value" => 50,
                        "for" => "min",
                        "per" => "day"
                    ],
                    "included_calls_to_local_numbers" => [
                        "value" => 50,
                        "for" => "min",
                        "per" => "day"
                    ],
                    "total_prepaid_data" => [
                        "value" => 3000,
                        "for" => "MB",
                        "per" => "month",
                        "description" => "Prepaid data description"
                    ],
                    "out_of_plan_data_charges" => [
                        "value" => 0.5,
                        "for" => "UAH",
                        "per" => "min"
                    ],
                    "restrictions_for_data" => "Some text",
                    "social" => [
                        [
                            "name" => "Facebook",
                            "logo" => "http:\\some.logo",
                            "url" => "http:\\some.url",
                            "code" => "facebook"
                        ]
                    ],
                    "restrictions_for_social" => "Some text",
                    "sms_included" => [
                        "value" => 50,
                        "for" => "sms",
                        "per" => "week"
                    ],
                    "date_from" => "2018-01-02 00:00:00",
                    "date_to" => "2018-03-01 00:00:00",
                    "last_update" => "2017-12-04 15:24:34",
                    "some_field" => "some value"//тут и далее динамические поля, значение может быть числом/строкой/массивом
                ],
            ],
            "addons" => [
                "123" => [ // 123 - id оператора. Берутся только те операторы которые присутствуют в возвращаемых тарифах
                    // массив аддонов у оператора
                    [
                        "operator_name" => "Some name",
                        "addon_id" => 123,
                        "addon_name" => "Some addon name",
                        "addon_type" => "internet",
                        "addon_value" => [
                            "value" => 3000,
                            "for" => "MB",
                            "per" => "month",
                            "description" => "Some addon value description"
                        ],
                        "price" => 123.12,
                        "price_conv" => 123.12,
                        "currency" => "UAH",
                        "currency_conv" => "USD",
                        "language" => "uk",
                        "language_name" => "Украинский",
                        "language_name_original" => "Українська"
                    ],
                ],
            ]
        ];
    }
}