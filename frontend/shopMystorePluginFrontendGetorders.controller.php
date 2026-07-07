<?php
class shopMystorePluginFrontendGetordersController extends waJsonController {
    public function execute(){

        $orders = array();
        $curl = curl_init();

        function get_status($status) {
            $new_status_array = array(
            'Принят' => 1,
            'Сборка' => 2,
            'Отправлен' => 3,
            'Выполнен' => 4,
            'Закрыт' => 5,
            'Ожидание товара' => 6,
        );

        $status_array = array(
            'Не обработан' => 1,
            'Не согласован' => 1,
            'Согласован' => 1,
            'Ожидание товара' => 6,
            'Согласован / ожидание оплаты' => 1,
            'Ожидание отправки' => 2,
            'Отправлен' => 3,
            'Выполнен' => 4,
            'Закрыт / не выполнен' => 5,
            'Закрыт без проводки - для документов' => 5,
            'Доставлено/деньги украдены' => 5,
            'Сборка' => 2,
            'Подтвержден' => 1,
            'Удален' => 5,
            'Новый' => 1,
            'ВРезерве' => 2,
            'ОтказРезрв' => 5,
            'Оплачено' => 1,
            );

            $new_status = $status_array[$status];
            $new_status = array_search($new_status, $new_status_array);

            if (!$new_status) {
                return 'Принят';
            }

            return $new_status;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->setError("Access denied!!!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $post = waRequest::post();
        $plugin = wa('shop')->getPlugin('mystore');

        $contact = wa()->getUser();
        $contact_model = new shopMystoreContactPluginModel();
        $user = $contact_model->getByField(array('contact_id' => $contact['id']));

        $order_id = isset($post['order_id']) ? $post['order_id'] : "";
        $link = isset($post['next']) ? $post['next'] : "";

        if ($user == null) {
            $this->setError("User not found");
            $this->response = array('Status' => "BAD");
            return;            
        }

        $counterparty_id = $user['ms_uid'];

        //wa_dump("counterparty_id: ".$counterparty_id, 'shop/plugins/Mystore/get_orders.log');

        if (empty($link)){
            $link = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder?limit=10&order=created,desc&filter=agent=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/$counterparty_id";
        }

        //wa_dump("link: ".$link, 'shop/plugins/Mystore/get_orders.log');

        $data = new StdClass;

        $ms_orders = $plugin->get_orders($user['ms_uid'], $order_id, $link);
        if ($ms_orders == null) $ms_orders = array();

        //wa_dump("Заказы: ".implode("<br>\r\n", $ms_orders), 'shop/plugins/Mystore/get_orders.log');

        if (is_array($ms_orders['meta']) && array_key_exists('nextHref', $ms_orders['meta'])) {
            $data->next = $ms_orders['meta']['nextHref'];
            $data->size = $ms_orders['meta']['size'];
            $data->limit = $ms_orders['meta']['limit'];
        }

        foreach($ms_orders['rows'] as $item){
            if (!empty($order_id)){

                $order_position = $plugin->get_order_positions($item['id']);


                $order_positions_items = array();
                foreach($order_position['rows'] as $position){
                    $quantity = $position['quantity'];
                    $price = strval($position['price'] / 100);
                    $discount = $position['discount'] ? $position['discount'] : 0;
                    $get_position_name = $plugin->get_by_link($position['assortment']['meta']['href']);


                    array_push($order_positions_items, array(
                        'name' => $get_position_name["name"],
                        'sale_price' => strval($get_position_name['salePrices'][0]['value'] / 100),
                        'price' => $price,
                        'discount' => round($discount),
                        'quantity' => $quantity
                    ));
                }

            } else {
                $order_positions_items = '';
            }


            $status = $plugin->get_by_link($item['state']['meta']['href']);
            $delivery = 'не указан';
            $employee = 'не указан';


            if (array_key_exists('attributes', $item)) {
                foreach($item['attributes'] as $attributes){
                    if ($attributes['id'] == '45179d88-8c83-11e6-7a69-8f55000baddf'){
                        $delivery = explode(' ', $attributes['value']['name'])[0];
                    }
                    if ($attributes['id'] == '790202f6-d4da-11e4-8b2d-002590a28ec4') {
                        $employee = $attributes['value']['name'];
                    }
                }
            }


            array_push($orders, array(
                'id' => $item['id'],
                'external_code' => $item['externalCode'],
                'name' => $item['name'],
                'sum' => strval($item['sum'] / 100),
                'moment' => $item['moment'],
                'updated' => $item['created'],
                'position' => $order_positions_items,
                'status' => get_status($status['name']),
                'delivery' => $delivery,
                'employee' => $employee,
            ));
        }

        $data->orders = $orders;

        $this->response = $data;
    }
}