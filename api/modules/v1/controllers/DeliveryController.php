<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use common\models\Cities;
use common\models\DeliveryPoints;
use common\models\GalleryFiles;
use common\models\Languages;

const AIRPORT = 1;
const HOTEL = 2;
const STREET_PLACE = 3;

class DeliveryController extends ApiBaseController
{


    /**
     * @return \yii\web\Response
     */
    public function actionPointList()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $language_param = \Yii::$app->request->get('language');
        if(is_null($language_param)){
            return $this->getBadRequestResponse('Missing argument: language!');
        }

        $language = Languages::findOne(['iso' => strtolower($language_param), 'activated' => true]);
        if(is_null($language)){
            return $this->getBadRequestResponse("Language '$language_param' is not activated");
        }

        $city_param = \Yii::$app->request->get('city');
        if(is_null($city_param)){
            return $this->getBadRequestResponse('Missing argument: city!');
        }

        $city = Cities::findOne(['code' => $city_param, 'activated' => true]);
        if(is_null($city)){
            return $this->getBadRequestResponse("Country '$city_param' is not activated");
        }
        \Yii::$app->session->set('language',$language->iso);
        $deliveryPoints = DeliveryPoints::find()->with(['city'])->where(['city_id' => $city->id])->all();
        $data = [];
        foreach ($deliveryPoints as $key => $deliveryPoint) {
            $galleryFiles = GalleryFiles::findAll(['gallery_id' => $deliveryPoint->photo_point]);
            $galerryIds = array_column($galleryFiles, 'file_id');
            $urls = \common\models\Files::getUrls($galerryIds);
            switch ($deliveryPoint->type) {
                case AIRPORT:
                    $data['airport'][] = [
                        "item_id" => $deliveryPoint->id,
                        "airport_name" => $deliveryPoint->airport_name,
                        "airport_code" => $deliveryPoint->airport_code,
                        "airport_terminal" => $deliveryPoint->airport_terminal,
                        "gps" => $deliveryPoint->gps,
                        "city" => $deliveryPoint->city->name,
                        "photo_point" => $urls,
                        "phone_point" => [
                            $deliveryPoint->phone_staff,
                            $deliveryPoint->phone_director
                        ],

                    ];
                break;
                case HOTEL:
                        $data['hotel'][] = [
                        "item_id" => $deliveryPoint->id,
                        "hotel_name" => $deliveryPoint->hotel_name,
                        "gps" => $deliveryPoint->gps,
                        "city" => $deliveryPoint->city->name,
                        "photo_point" => $urls,
                        "phone_point" => [
                            $deliveryPoint->phone_staff,
                            $deliveryPoint->phone_director
                        ],
                    ];
                break;
                case STREET_PLACE:
                    $data['street_place'][] = [
                        "item_id" => $deliveryPoint->id,
                        "address" => $deliveryPoint->street_address,
                        "gps" => $deliveryPoint->gps,
                        "city" => $deliveryPoint->city->name,
                        "photo_point" => $urls,
                        "phone_point" => [
                            $deliveryPoint->phone_staff,
                            $deliveryPoint->phone_director
                        ],
                    ];
                break;
            }
        }

//        $data[] = [
//            "airport" => [ // точки выдачи с типом "airport"
//
//            ],
//            "hotel" => [ // точки выдачи с типом "hotel"
//
//            ],
//            "street_place" => [ // точки выдачи с типом "street_place"
//            ]
//        ];
        \Yii::$app->session->remove('language');
        if (empty($data)) {
            return $this->getBadRequestResponse
            ("Delivery point with city '$city->name' does not exist.");
        }
//        $data = $this->getFakePoints();

        return $this->getSuccessResponse($data);
    }

    /**
     * @return array
     */
    protected function getFakePoints()
    {
        return [
            "airport" => [ // точки выдачи с типом "airport"
              [
                "item_id" => 123,
                "airport_name" => "boryspil international airport",
                "airport_code" => "KBP",
                "airport_terminal" => "B",
                "gps" => "123",
                "city" => "Kiev",
                "photo_point" => [
                      "http://pipsum.com/435x310.jpg",
                      "http://pipsum.com/310x435.jpg",
                      "http://pipsum.com/435x310.jpg"
                  ],
                    "phone_point" => [
                      "+380551232323",
                      "+380961232323"
                  ]
              ],
            ],
            "hotel" => [ // точки выдачи с типом "hotel"
              [
                  "item_id" => 123,
                "hotel_name" => "Freedom Hostel",
                "gps" => 123,
                "city" => "Kiev",
                "photo_point" => [
                  "http://pipsum.com/435x310.jpg",
                  "http://pipsum.com/310x435.jpg",
                  "http://pipsum.com/435x310.jpg"
                ],
                "phone_point" => [
                  "+380551232323",
                  "+380961232323"
                ]
              ],
            ],
            "street_place" => [ // точки выдачи с типом "street_place"
               [
                   "item_id" => 123,
                "address" => "Freedom Hostel",
                "gps" => 123,
                "city" => "Kiev",
                "photo_point" => [
                   "http://pipsum.com/435x310.jpg",
                   "http://pipsum.com/310x435.jpg",
                   "http://pipsum.com/435x310.jpg"
               ],
                "phone_point" => [
                   "+380551232323",
                   "+380961232323"
               ]
              ],
            ]
        ];
    }
}