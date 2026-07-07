<?php

class shopMystorePluginFrontendGetorderwebhooksController extends waJsonController
{
    public function execute()
    {
        /*
            обязательное указание параметра ?status=1 для CREATE вебхука
        */
        $get = waRequest::get();
        $create_order = isset($get['status']) ? $get['status'] : "";

        $plugin = wa('shop')->getPlugin('mystore');

        $response = json_decode(file_get_contents('php://input'));
        $link_mc = $response->events[0]->meta->href;
        $link_mc = explode('/', $link_mc);
        $id = end($link_mc);

        $order_audit = $plugin->get_customerorder_audit($id);
        $order = $plugin->get_order($id);
        $counterparty = $plugin->get_by_link($order['agent']['meta']['href']);
        $old_status = NULL;
        $new_status = NULL;
        $new_order = NULL;
        $send_email = true;

        #$contact_model = new waContactModel();
        #$user = $contact_model->getByField(array('ms_uid' => $counterparty['id']));
        $user_name = $counterparty['name'];
        $contact_email_model = new waContactEmailsModel();
        $query = $contact_email_model->getByField(array('email' => $counterparty['email']));
        if (count($query) > 0) {
            $contact_email = $query['email'];


            $new_status_array = array(
                'Принят' => 1,
                'Ожидание товара' => 2,
                'Сборка' => 3,
                'Готов к отгрузке' => 4,
                'Отправлен' => 5,
                'Выполнен' => 6,
                'Закрыт' => 7,
                'Возврат!' => 8,
                'Оплачен' => 9,
                
            );

            $status_array = array(
                'Не обработан' => 1,
                'Не согласован' => 1,
                'Согласован' => 1,
                'Ожидание товара' => 2,
                'Сборка' => 3,
                'ДоСборка' => 3,
                'ДоБор' => 3,
                'Натяжка' => 3,
                'ВРезерве' => 4,
                'Ожидание отправки' => 4,
                'Частично отгружен!' => 4,
                'Отправлен' => 5,
                'Выполнен' => 6,
                'Доставлено/деньги украдены' => 6,
                'Закрыт / не выполнен' => 7,
                'Закрыт без проводки - для документов' => 7,
                'ОтказРезрв' => 7,
                'Идет возврат!!!' => 8,
                'Оплачен' => 1,
            );

            #если пришел веб-хук о создании нового заказа
            if ($create_order) {
                if ($order_audit['rows'][0]['eventType'] == 'create') {
                    $new_status = $status_array['Не обработан'];
                    $new_status = array_search($new_status, $new_status_array);
                    $new_order = true;
                }
            } else {
                if ($order_audit['rows'][0]['eventType'] == 'update') {
                    $old_status = $order_audit['rows'][0]['diff']['state']['oldValue']['name'];
                    $new_status = $order_audit['rows'][0]['diff']['state']['newValue']['name'];
                    $moment = $order_audit['rows'][0]['moment'];
                    $d = date("Y-m-d H:i:s.v");
                    $d1 = strtotime($moment);
                    $d2 = strtotime($d);
                    $diff = $d2 - $d1;

                    if ($diff < 5) {
                        if ($new_status) {
                            $new_status = $status_array[$new_status];
                            $new_status = array_search($new_status, $new_status_array);
                            if (!$new_status) {
                                $new_status = 'Принят';
                            }

                            $old_status = $status_array[$old_status];
                            $old_status = array_search($old_status, $new_status_array);
                            if (!$old_status) {
                                $old_status = 'Принят';
                            }
                        } else {
                            $send_email = false;
                        }
                    } else {
                        $send_email = false;
                    }
                }
            }

            $order_position = $plugin->get_order_positions($order['id']);

            $order_positions_items = array();
            foreach ($order_position['rows'] as $position) {
                $quantity = $position['quantity'];
                $price = strval($position['price'] / 100);
                $discount = $position['discount'] ? $position['discount'] : 0;
                $get_position_name = $plugin->get_by_link($position['assortment']['meta']['href']);

                array_push($order_positions_items, array(
                    'name' => $get_position_name["name"],
                    'price' => $price,
                    'discount' => $discount,
                    'quantity' => $quantity,
                    'sum' => ($price * $quantity) - ($price * $quantity) / 100 * $discount,
                ));
            }

            $delivery = 'не указан';
            $employee = 'не указан';
            $track_number = false;
            

            if (array_key_exists('attributes', $order)) {
                foreach ($order['attributes'] as $attributes) {
                    if ($attributes['id'] == 'eac48209-b307-11eb-0a80-09ea0009c660') {
                        $track_number = $attributes['value'];
                    }
                    if ($attributes['id'] == '45179d88-8c83-11e6-7a69-8f55000baddf') {
                        $delivery = $attributes['value']['name'];
                    }
                    if ($attributes['id'] == '790202f6-d4da-11e4-8b2d-002590a28ec4') {
                        $employee = $attributes['value']['name'];
                    }
                }
            }

            $price_type = explode(' ', $counterparty['priceType']['name'])[0];
            $view = wa()->getView();
            $view->assign(array(
                'name' => $counterparty['name'],
                'counterparty_status' => $price_type,
                'order' => $order['name'],
                'order_id' => $order['id'],
                'delivery' => $delivery,
                'employee' => $employee,
                'track_number' => $track_number,
                'positions' => $order_positions_items,
                'status' => $new_status,
                'sum' => strval($order['sum'] / 100),
            ));
            $template = file_get_contents('wa-apps/shop/plugins/mystore/templates/mail/status_change.html');

            if ($new_order) {
                $template = file_get_contents('wa-apps/shop/plugins/mystore/templates/mail/new_order.html');
            }

            $result = $view->fetch('string:' . $template);
            $view->assign('action', $this);


            $mail_message = new waMailMessage('Статус вашего заказа №' . $order['name'] . ' на сайте badmintonist.com изменен на ' . strtolower($new_status));
            if ($new_order) {
                $mail_message = new waMailMessage('Ваш заказ №' . $order['name'] . ' на сайте badmintonist.com успешно оформлен');
            }

            if ($send_email) {
                if ($new_status != $old_status) {
                    $mail_message->setBody($result);
                    $mail_message->setFrom('info@badmintonist.com', 'Интернет магазин badmintonist.com');
                    $mail_message->setTo($contact_email, $user_name);
                    $mail_message->send();
                }
            }
        }


        $this->response = array('Status' => "OK", 'old_status' => $old_status, 'new_status' => $new_status, 'data' => $contact_email);
    }
}

