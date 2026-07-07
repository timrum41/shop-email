<?php
class shopMystorePluginFrontendGetbonuspointsController extends waJsonController {
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
        
        if ($user == null) {
            $this->setError("User not found");
            $this->response = array('Status' => "BAD");
            return;            
        }

        $counterparty_data = $plugin->get_counterparty($user['ms_uid']);
        if ($counterparty_data == null) {
            $this->setError("No counterparty data found");
            $this->response = array('Status' => "BAD");
            return;
        }

        $bonustransaction = $plugin->get_bonustransaction($counterparty_data['meta']['href']);
        if (!array_key_exists('bonusPoints', $counterparty_data))
        {
            $this->setError("User is not in the bonus group");
            $this->response = array('Status' => "BAD");
            return;
        }
        $contact_model->updateById($user['id'], array('bonus_points' => $counterparty_data['bonusPoints']));
        //$model->updateById($id, array('field_1' => $field_1, 'field_2' => $field_2));
        $bonuspoints = array(
            'points' => $counterparty_data['bonusPoints'],
            'transactions' => $bonustransaction['rows']
        );
        
        $this->response = $bonuspoints;
    }
}