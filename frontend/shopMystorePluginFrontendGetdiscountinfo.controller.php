<?php
class shopMystorePluginFrontendGetdiscountinfoController extends waJsonController {

    public function execute()
    {
        $ret = array();

        if(waRequest::getMethod() != "get") {
            $this->setError("Access denied!");
            return;
        }
        $cart_id = waRequest::cookie("shop_cart");

        $cart = new shopCart($cart_id);
        if ($cart == null) {
            $this->setError("Access denied!");
            return;
        }

        $wa_user = wa()->getUser();
        if ($wa_user != null) {
            $contact_model = new shopMystoreContactPluginModel();
            $contact = $contact_model->getByField("contact_id", $wa_user->getId());
            $ret["bonuses"] = $contact != null ? floatval($contact["bonus_points"]) : 0;
            $ret["limit"] = shopMystorePluginHelper::calculateMaxDiscount($cart->total(false), floatval($contact["bonus_points"]));
        }

        $cart_model = new shopMystoreCartDiscountModel();
        $cart_discount = $cart_model->getDiscount($cart_id);
        if ($cart_discount == null)
        {
            $ret["type"] = "";
            $ret["discount"] = 0;
        }
        elseif ($cart_discount["type"] == "certificate") {
            $ret["type"] = $cart_discount["type"];
            $ret["number"] = $cart_discount["cert"];
            $ret["discount"] = $cart_discount["value"];
        }
        elseif ($cart_discount["type"] == "bonus"){
            $ret["type"] = $cart_discount["type"];
            $ret["discount"] = intval($cart_discount["value"]);
            }
        $this->response =  $ret;
    }
}