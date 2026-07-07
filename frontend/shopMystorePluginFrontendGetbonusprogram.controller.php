<?php
class shopMystorePluginFrontendGetbonusprogramController extends waJsonController {
    public function execute(){

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->setError("Access denied!");
            $this->response = array('Status' => "BAD");
            return;
        }

        $post = waRequest::post();

        $plugin = wa('shop')->getPlugin('mystore');

        $plugin = wa('shop')->getPlugin('mystore');
        $token = $plugin->get_api_token();
       
        $bonusprogram = $plugin->get_bonusprogram();

        $bonusprogram_data = array(
            'earn' => $bonusprogram['earnRateRoublesToPoint'],
            'spend' => $bonusprogram['spendRatePointsToRouble']
        );
                
        $this->response = $bonusprogram_data;
    }
}