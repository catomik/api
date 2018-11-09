<?php

namespace backend\modules\api\modules\v1\transformers;


use common\models\Addons;
use common\models\Currency;
use common\models\Languages;
use common\utilities\exchange_rates\CurrencyExchanger;
use common\utilities\plan_services\Calls;
use common\utilities\plan_services\Internet;
use yii\db\ActiveRecord;

class AddonTransformer implements TransformerInterface
{

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
     * AddonTransformer constructor.
     * @param Languages $language
     * @param Currency $currency
     */
    public function __construct(Languages $language, Currency $currency)
    {
        $this->language = $language;
        $this->currency = $currency;
    }

    /**
     * @param ActiveRecord $addon
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function transform(ActiveRecord $addon, array $params = [])
    {
        if (!($addon instanceof Addons)) {
            throw new \Exception("Entity has to be instance of " . Addons::class);
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

        /** @var CurrencyExchanger $exchanger */
//        $exchanger = \Yii::$app->get('currency_exchanger');
        $exchanger = \Yii::$container->get('currency_exchanger');
        $addon = $addon->translate($language->iso);

        $addon_value = null;
        $addon_type = null;
        switch ($addon->type) {
            case Addons::INTERNET:
                $addon_type = 'internet';
                $addon_value = [
                    "type" => Addons::getType($addon->internet_type),
                    "value" => $addon->internet_storage_value,
                    "for" => $addon->internet_storage_type,
                    "per" => $addon->internet_period,
                ];
                break;
            case Addons::CALLS:
                $addon_type = 'calls';
                $addon_value = [
                    "type" => Addons::getType($addon->call_type),
                    "value" => $addon->calls_storage_value,
                    "for" => $addon->calls_storage_type,
                    "per" => $addon->calls_period,
                ];
                break;
            case Addons::SMS:
                $addon_type = 'sms';
                $addon_value = [
                    "type" => Addons::getType($addon->sms_type),
                    "value" => $addon->sms_count,
                    "per" => $addon->sms_period,
                ];
                break;
            case Addons::RECHARGET:
                $addon_type = 'recharget';
                $addon_value = [
                    "value" => $addon->recharget_count
                ];
                break;
        }
        $addon_value['description'] = $addon->addon_value_description;

        $data = [
            "operator_id" => $addon->operator->id,
            "operator_name" => $addon->operator->name,
            "addon_id" => $addon->id,
            "addon_name" => $addon->addon_name,
            "addon_type" => $addon_type,
            "addon_value" => $addon_value,
            "price" => $addon->gross_price,
            "price_conv" => $exchanger->exchangeFromTo($addon->gross_price, $addon->currency->iso, $currency->iso),
            "currency" => $addon->currency->iso,
            "currency_conv" => $currency->iso,
            "language" => $language->iso,
            "language_name" => $language->name,
            "language_name_original" => $language->original_name,
        ];

        $this->bindFormattedInternet($data, $addon);
        $this->bindFormattedCalls($data, $addon);

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
     * @param Addons $addon
     * @return bool
     */
    protected function bindFormattedCalls(array &$data, Addons $addon)
    {
        if ($addon->type != Addons::CALLS || is_null($this->calls_format)) {
            return false;
        }

        $calls = new Calls(
            (float) $addon->calls_storage_value,
            $addon->calls_storage_type,
            $addon->calls_period
        );

        $data['addon_value_conv'] = [
            'value' => $calls->formatValue($this->calls_format->origUnit(), $this->calls_format->origPeriod()),
            'for' => $this->calls_format->origUnit(),
            'per' => $this->calls_format->origPeriod(),
            'description' => $data['addon_value']['description'],
        ];

        return true;
    }

    /**
     * @param array $data
     * @param Addons $addon
     * @return bool
     */
    protected function bindFormattedInternet(array &$data, Addons $addon)
    {
        if ($addon->type != Addons::INTERNET || is_null($this->internet_format)) {
            return false;
        }

        $internet = new Internet(
            (float) $addon->internet_storage_value,
            $addon->internet_storage_type,
            $addon->internet_period
        );

        $data['addon_value_conv'] = [
            'value' => $internet->formatValue($this->internet_format->origUnit(), $this->internet_format->origPeriod()),
            'for' => $this->internet_format->origUnit(),
            'per' => $this->internet_format->origPeriod(),
            'description' => $data['addon_value']['description']
        ];

        return true;
    }
}