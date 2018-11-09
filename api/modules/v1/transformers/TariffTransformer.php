<?php

namespace backend\modules\api\modules\v1\transformers;


use common\models\Countries;
use common\models\Currency;
use common\models\Languages;
use common\models\TariffFields;
use common\models\Tariffs;
use common\models\TariffsSocialCompany;
use common\models\TariffsTariffFields;
use common\utilities\exchange_rates\CurrencyExchanger;
use common\utilities\plan_services\Calls;
use common\utilities\plan_services\Internet;
use yii\db\ActiveRecord;

class TariffTransformer implements TransformerInterface
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var Calls
     */
    protected $calls_format;

    /**
     * @var Internet
     */
    protected $internet_format;

    /**
     * @var Languages
     */
    protected $language;

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * TariffTransformer constructor.
     * @param Languages $language
     * @param Currency $currency
     */
    public function __construct(Languages $language, Currency $currency)
    {
        $this->language = $language;
        $this->currency = $currency;
    }

    /**
     * @param ActiveRecord $tariff
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function transform(ActiveRecord $tariff, array $params = [])
    {
        if (!($tariff instanceof Tariffs)) {
            throw new \Exception("Entity has to be instance of " . Tariffs::class);
        }

        if (!isset($params['language']) || !($params['language'] instanceof Languages)) {
            $params['language'] = $this->language;
        }

        if (!isset($params['currency']) || !($params['currency'] instanceof Currency)) {
            $params['currency'] = $this->currency;
        }

        /** @var Languages $language */
        $language = $params['language'];

        /** @var Currency $currency */
        $currency = $params['currency'];

        /**
         * @var $countries_def Countries[]
         */
        $countries_def = isset($params['countries_def'])?$params['countries_def']:[];
        /**
         * @var $countries_lang Countries[]
         */
        $countries_lang = isset($params['countries_lang'])?$params['countries_lang']:[];

        $variables = isset($params['variables'])?$params['variables']:[];

        /**
         * @var $tariffs_tariff_fields TariffsTariffFields[]
         */
        $tariffs_tariff_fields = isset($params['tariffs_tariff_fields'])?
            \array_filter($params['tariffs_tariff_fields'],function($item)use($tariff) {
                return $item->tariffs_id == $tariff->id;
            })
            :[];
        /** @var CurrencyExchanger $exchanger */
//        $exchanger = \Yii::$app->get('currency_exchanger');
        $exchanger = \Yii::$container->get('currency_exchanger');

        $data = [
            "tariff_id" => $tariff->id,
            "tariff_name" => $tariff->tariff_name,
            "operator_id" => $tariff->operator_id,
            "title" => $tariff->title,
            "description" => $tariff->description,
            "addons" => array_column($tariff->addons, 'id'),
            "addons_relations" => array_column($tariff->addonsRelation, 'id'),
            "operator_name" => $tariff->operator->name,
            "balance" => $tariff->balance,
            "balance_conv" => $exchanger->exchangeFromTo($tariff->balance, $tariff->currency->iso, $currency->iso),
            "balance_description" => $tariff->balance_description,
            "local_sms_description" => $tariff->local_sms_description,
            "local_sms" => $tariff->local_sms,
            "local_sms_conv" => $exchanger->exchangeFromTo($tariff->local_sms, $tariff->currency->iso, $currency->iso),
            "promo_internet" => $tariff->promo_internet,
            "promo_calls" => $tariff->promo_calls,
            "promo_recommended" => $tariff->promo_recommended,
            "promo_popular" => $tariff->promo_popular,
            "for_social" => $tariff->for_social,
            "best" => $tariff->best,
            "currency" => $tariff->currency->iso,
            "currency_conv" => $currency->iso,
            "price" => $tariff->gross_price,
            "price_conv" => $exchanger->exchangeFromTo($tariff->gross_price, $tariff->currency->iso, $currency->iso),
            "country" => [
                'code' => $tariff->operator->countries->iso,
                'name' => (isset($countries_def[$tariff->operator->country]))?$countries_def[$tariff->operator->country]->name:'',
                'name_translation' => (isset($countries_lang[$tariff->operator->country]))?$countries_lang[$tariff->operator->country]->name:''
//                'name' => $tariff->operator->countries->translate(Languages::getDefault())->name,
//                'name_translation' => $tariff->operator->countries->translate($language->iso)->name

            ],
            "language" => $language->iso,
            "language_name" => $language->name,
            "language_name_original" => $language->original_name,
            "image" => \common\models\Files::getUrl($tariff->image),
            "calls_within_network_value" => [
                "type" => Tariffs::getType($tariff->calls_within_network_type),
                "value" => $tariff->calls_within_network_value,
                "for" => $tariff->calls_within_network_time_type,
                "per" => $tariff->calls_within_network_period
            ],
            "included_calls_to_local_numbers" => [
                "type" => Tariffs::getType($tariff->included_calls_to_local_type),
                "value" => $tariff->included_calls_to_local_numbers_value,
                "for" => $tariff->included_calls_to_local_numbers_time_type,
                "per" => $tariff->included_calls_to_local_numbers_period
            ],
            "total_prepaid_data" => [
                "type" => Tariffs::getType($tariff->total_prepaid_data_period_type),
                "value" => $tariff->total_prepaid_data_value,
                "for" => $tariff->total_prepaid_data_storage_type,
                "per" => $tariff->total_prepaid_data_period,
                "description" => $tariff->total_prepaid_data_description
            ],
            "out_of_plan_calls_charges" => [
                "cost" => $tariff->out_of_plan_calls_charges_value,
                "cost_conv" => $exchanger->exchangeFromTo($tariff->out_of_plan_calls_charges_value, $tariff->currency->iso, $currency->iso),
                "for" => $tariff->out_of_plan_calls_charges_time_type
            ],
            "out_of_plan_data_charges" => [
                "cost" => $tariff->out_of_plan_data_charges_currency_value,
                "cost_conv" => $exchanger->exchangeFromTo($tariff->out_of_plan_data_charges_currency_value, $tariff->currency->iso, $currency->iso),
                "for_value" => $tariff->out_of_plan_data_charges_storage_value,
                "for_type" => $tariff->out_of_plan_data_charges_storage_type
            ],
            "restrictions_for_data" => $tariff->restrictions_for_data,
        ];

        $data["social_unlim"] = [];
        foreach ($tariff->social as $socialCompany) {
            $data["social_unlim"][] = [
                "name" => $socialCompany->name,
                "logo" => \common\models\Files::getUrl($socialCompany->logo),
                "url" => $socialCompany->url,
                "code" => $socialCompany->code,
            ];
        }

        $data["restrictions_for_social"] = $tariff->restrictions_for_social;
        $data["sms_included"]["type"] = Tariffs::getType($tariff->sms_type);
        $data["sms_included"]["value"] = $tariff->sms_included_count;
        $data["sms_included"]["for"] = "sms";
        $data["sms_included"]["per"] = $tariff->sms_included_period;
        $data["date_from"] = date(self::DATETIME_FORMAT, $tariff->date_from);
        $data["date_to"] = date(self::DATETIME_FORMAT, $tariff->date_to);
        $data["last_update"] = date(self::DATETIME_FORMAT, $tariff->updated_at);

        $data['custom_fields'] = [];
        $constructor_field = $tariffs_tariff_fields;
//        $constructor_field = TariffsTariffFields::find()->translate($language->iso)->where(['tariffs_id' => $tariff->id])
//            ->andWhere(['isDeleted' => false])
//            ->orderBy('id')->all();
        foreach ($constructor_field as $key => $constructor_field_value) {
            $tariffField = $constructor_field_value->tariffFields;
            if ($tariffField->type == 3) {
                $tableName = json_decode( $tariffField->variants)->table;
                if(isset($variables[$tableName])) {
                    $currentTable = $variables[$tableName][$constructor_field_value->value];
                    $currentArrayTable = $currentTable->toArray();
                    $arrayKeys = array_keys($currentArrayTable);
                    $field_value = [];
                    foreach ($arrayKeys as $key) {
                        $field_value[$key] = $currentArrayTable[$key];
                    }
                }
//                $table = TariffFields::getLinksClass($tableName);
//                $currentTable = $table::findOne($constructor_field_value->value);
//                $currentArrayTable = $currentTable->toArray();
//                $arrayKeys = array_keys($currentArrayTable);
//                $field_value = [];
//                foreach ($arrayKeys as $key) {
//                    $field_value[$key] = $currentArrayTable[$key];
//                }
            } elseif ($tariffField->type == 4) {

                $field_value = \common\models\Files::getUrl($constructor_field_value->value);
            } else {
                $field_value = $constructor_field_value->value;
            }
            $data['custom_fields'][$tariffField->name] = $field_value;
        }

        $this->bindFormattedCalls($data, $tariff);
        $this->bindFormattedInternet($data, $tariff);

        return $data;
    }

    /**
     * @param Calls $calls
     */
    public function setCallsFormat(Calls $calls)
    {
        $this->calls_format = $calls;
    }

    /**
     * @param Internet $internet
     */
    public function setInternetFormat(Internet $internet)
    {
        $this->internet_format = $internet;
    }

    /**
     * @param array $data
     * @param Tariffs $tariff
     * @return bool
     */
    protected function bindFormattedCalls(array &$data, Tariffs $tariff)
    {
        if (is_null($this->calls_format)) {
            return false;
        }

        $calls = new Calls(
            (float) $tariff->included_calls_to_local_numbers_value,
            $tariff->included_calls_to_local_numbers_time_type,
            $tariff->included_calls_to_local_numbers_period
        );

        $data['included_calls_to_local_numbers_conv'] = [
            'value' => $calls->formatValue($this->calls_format->origUnit(), $this->calls_format->origPeriod()),
            'for' => $this->calls_format->origUnit(),
            'per' => $this->calls_format->origPeriod(),
        ];

        return true;
    }

    /**
     * @param array $data
     * @param Tariffs $tariff
     * @return bool
     */
    protected function bindFormattedInternet(array &$data, Tariffs $tariff)
    {
        if (is_null($this->internet_format)) {
            return false;
        }

        $internet = new Internet(
            (float) $tariff->total_prepaid_data_value,
            $tariff->total_prepaid_data_storage_type,
            $tariff->total_prepaid_data_period
        );

        $data['total_prepaid_data_conv'] = [
            'value' => $internet->formatValue($this->internet_format->origUnit(), $this->internet_format->origPeriod()),
            'for' => $this->internet_format->origUnit(),
            'per' => $this->internet_format->origPeriod(),
            'description' => $data['total_prepaid_data']['description']
        ];

        return true;
    }
}