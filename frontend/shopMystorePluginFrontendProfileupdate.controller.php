<?php
class shopMystorePluginFrontendProfileupdateController extends waJsonController {
    public function execute () {

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->setError("Access denied!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $post = waRequest::post();

        # Проверка полей
        $access_token = isset($post['access_token']) ? $post['access_token'] : "";
        $ms_id = isset($post['ms_uid']) ? $post['ms_uid'] : "";
        $ms_name = isset($post['name']) ? $post['name'] : "";
        $email = isset($post['email']) ? $post['email'] : "";
        $phone = isset($post['phone']) ? $post['phone'] : "";
        $wb_disable = isset($post['webhook_disabled']) ? $post['webhook_disabled'] : "";

        if (empty($access_token)) {
            $this->setError("Empty token!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $plugin = wa('shop')->getPlugin('mystore');
        $token = $plugin->get_api_token();

        if (strcmp($access_token, $token) != 0) {
            $this->setError("Wrong token!");
            $this->response = array('Status' => "BAD");
            return;
        }

        if (empty($ms_id)) {
            $this->setError("Empty ms_uid!");
            $this->response = array('Status' => "BAD");
            return;
        }

        waLog::dump("Пришли данные: ".$ms_name, 'shop/plugins/Mystore/counterparties.log');
        waLog::dump("ms_uid: ".$ms_id, 'shop/plugins/Mystore/counterparties.log');

        $contact_model = new waContactModel();
        $ms_contact_model = new shopMystoreContactPluginModel();
        $contact_emails_model = new waContactEmailsModel();
        $contact_data_model = new waContactDataModel();
        
        $ms_contact = $ms_contact_model->getByField('ms_uid', $ms_id);
        waLog::dump("ms_contact: " . json_encode($ms_contact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK), 'shop/plugins/Mystore/counterparties.log');
        
        $contact = $contact_model->getByField('id', $ms_contact['contact_id']);
        waLog::dump("contact: " . json_encode($contact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK), 'shop/plugins/Mystore/counterparties.log');



        if ($contact == null){
            $this->setError("Contact not found!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $update = array();
        $plugin->generate_fio($ms_name, $update);
        $update['name'] = $ms_name;

        $contact_model->updateById($contact['id'], $update);

        if (!empty($email))
            $contact_emails_model->updateByField(array('contact_id' => $contact['id']), array('email' => $email));

        if (!empty($phone)) {
            $phone = preg_replace('/\D+/', '', $phone);
            if ($phone[0] === 8) $phone[0] = 7;

            $contact_data_model->updateByField(array('contact_id' => $contact['id'], 'field' => "phone"),
                array('value' => $phone));
        }


        # Обновляем цены у контакта
        $ms_contact = $plugin->get_counterparty($ms_id);
        $plugin->set_prices($ms_contact);
        $bonust_points = floatval($ms_contact['bonusPoints']);
        $plugin_contact_model = new shopMystoreContactPluginModel();
        $plugin_contact = $plugin_contact_model->getByField('contact_id', $contact['id']);

        $update['bonus_points'] = $bonust_points;

        $plugin_contact_model->updateById($plugin_contact['id'], $update);

        waLog::dump("Пользователь с id: $ms_id обновлен. $bonust_points", 'shop/plugins/Mystore/counterparties.log');


        waLog::dump("Пользователь с id: $ms_id обновлен. $ms_name", 'shop/plugins/Mystore/counterparties.log');
        $this->response = array('Status' => "OK");
    }
}