<?php
class shopMystorePluginFrontendGetorderitemsController extends waJsonController {
    public function execute(){

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->setError("Access denied!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $post = waRequest::post();

        $plugin = wa('shop')->getPlugin('mystore');

        $contact = wa()->getUser();
        $contact_model = new shopMystoreContactPluginModel();
        $user = $contact_model->getByField(array('contact_id' => $contact['id']));        

        $order_id = isset($post['order_id']) ? $post['order_id'] : "";
        
        $ms_orders = $plugin->get_orders($user['ms_uid'], $order_id);

        $orders = array();
        $curl = curl_init();

        foreach($ms_orders['rows'] as $item){

            $order_position = $plugin->get_order_positions($item['id']);

            $order_positions_items = array();

            foreach($order_position['rows'] as $position){
                $quantity = $position['quantity'];
                $get_position_name = $plugin->get_position_name($position['assortment']['meta']['href']);

                array_push($order_positions_items, array(
                    'positions' => $get_position_name["name"],
                    'quantity' => $quantity,
                ));
            }

            array_push($orders, array(
                'id' => $item['id'],
                'external_code' => $item['externalCode'],
                'name' => $item['name'],
                'sum' => strval($item['sum'] / 100),
                'moment' => $item['moment'],
                'updated' => $item['updated'],
                'position' => $order_positions_items,
            ));
        }

        $this->response = $orders;
    }
}

