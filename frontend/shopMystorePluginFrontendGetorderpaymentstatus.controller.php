<?php

class shopMystorePluginFrontendGetorderpaymentstatusController extends waJsonController
{

    private $order_id;

    public function execute() {
        $get = waRequest::get();


        $this->$order_id = isset($get['mdOrder']) ? $get['mdOrder'] : "";

        $url = "https://pay.alfabank.ru/payment/rest/getOrderStatus.do";
        $params = array(
            'userName' => "badmintonist-api",
            'password' => "VfuYjP4!2-",
            'orderId' => $this->$order_id,
        );

        $request = $this->sendData($url, $params);


        if ($request['ErrorCode'] == 0 && $request['OrderStatus'] == 2) {
            $message = $request['ErrorMessage'];
            $app_payment_method = self::CALLBACK_PAYMENT;
            $transaction_data['state'] = self::STATE_CAPTURED;
            $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
            $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);

        } elseif ($request['ErrorCode'] == 0 && $request['OrderStatus'] == 1) {
            $message = $request['ErrorMessage'];
            $app_payment_method = self::CALLBACK_PAYMENT;
            $transaction_data['state'] = self::STATE_CAPTURED;
            $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
            $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);

        } else {
            $message = $request['ErrorMessage'];

            switch ($request['ErrorCode']) {
                case 2:
                    $message = 'Заказ отклонен по причине ошибки в реквизитах платежа.';
                    $app_payment_method = self::CALLBACK_DECLINE;
                    $transaction_data['state'] = self::STATE_DECLINED;
                    $transaction_data['type'] = self::OPERATION_CANCEL;
                    $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                    break;

                case 5:
                    $message = 'Ошибка значения параметра запроса.';
                    $app_payment_method = self::CALLBACK_DECLINE;
                    $transaction_data['state'] = self::STATE_DECLINED;
                    $transaction_data['type'] = self::OPERATION_CANCEL;
                    $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                    break;

                case 6:
                    $message = 'Незарегистрированный OrderId.';
                    $app_payment_method = self::CALLBACK_DECLINE;
                    $transaction_data['state'] = self::STATE_DECLINED;
                    $transaction_data['type'] = self::OPERATION_CANCEL;
                    $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                    break;

                default:
                    $message = $request['ErrorMessage'];
                    $app_payment_method = self::CALLBACK_DECLINE;
                    $transaction_data['state'] = self::STATE_DECLINED;
                    $transaction_data['type'] = self::OPERATION_CANCEL;
                    $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                    break;
            }
        }

        $transaction_data = $this->saveTransaction($transaction_data, $request);
        $result = $this->execAppCallback($app_payment_method, $transaction_data);
        $this->__printer($result);


        $this->response = array('data' => $result);
    }

    protected function __printer($v) {
        $path = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) . "/wa-plugins/payment/rbs/lib/testinfo.txt";
        echo '<pre>';
        print_r($v);
        echo '</pre>';
        if (is_array($v)) {
            $v = json_encode($v);
        }
        @file_put_contents($path, $v);
    }

    private function sendData($url, $data)
    {

        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new waException('PHP extension cURL not found');
        }
        if (!($ch = curl_init())) {
            throw new waException('cURL init error');
        }
        if (curl_errno($ch) != 0) {
            throw new waException('cURL error: ' . curl_errno($ch));
        }

        $rbsCurl = curl_init();
        curl_setopt_array($rbsCurl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                'CMS: Shop-script ' . wa()->getVersion('installer'),
                'Module-Version: ' . $this->version
            ),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,


        ));
        $response = curl_exec($rbsCurl);

        curl_close($rbsCurl);

        $app_error = null;

        if (curl_errno($ch) != 0) {
            $app_error = 'cURL error: ' . curl_errno($ch);
        }
        curl_close($ch);
        if ($app_error) {
            throw new waException($app_error);
        }
        if (empty($response)) {
            throw new waException('Empty server response');
        }

        $json = json_decode($response, true);

        if (!is_array($json)) {
            throw new waException("fdsfdsfdsfsd" . $response);
        }

        return $json;
    }

    private function formalizeData($transaction_raw_data)
    {
        $currency_id = $transaction_raw_data['currency'];

        $transaction_data = $this->formalizePluginData($transaction_raw_data);
        $transaction_data['native_id'] = $this->order_id;
        $order = explode('_', $transaction_raw_data['OrderNumber']);
        $transaction_data['order_id'] = $order[0];

        $arCurrency = unserialize($this->currency);
        $transaction_data['currency_id'] = $arCurrency[$currency_id];
        $transaction_data['amount'] = $transaction_raw_data['Amount'] / 100.0;

        return $transaction_data;
    }

    protected function formalizePluginData($transaction_raw_data)
    {
        $transaction_data = array(
            'plugin'          => $this->id,
            'merchant_id'     => $this->merchant_id,
            'date_time'       => date('Y-m-d H:i:s'),
            'update_datetime' => date('Y-m-d H:i:s'),
            'result'          => true,
        );
        return $transaction_data;
    }
    
}