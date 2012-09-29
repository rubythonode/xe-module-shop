<?php
    /**
     * @class  shop
     * @author Arnia (xe_dev@arnia.ro)
     * @brief  shop module main class
     **/

    require_once(_XE_PATH_.'modules/shop/shop.info.php');
    require_once(__DIR__ . '/libs/autoload/autoload.php');

    class shop extends ModuleObject {

        /**
         * @brief default mid
         **/
        public $shop_mid = 'shop';

        /**
         * @brief default skin
         **/
        public $skin = 'default';

        public $add_triggers = array(
            array('display', 'shop', 'controller', 'triggerMemberMenu', 'before'),
            array('moduleHandler.proc', 'shop', 'controller', 'triggerApplyLayout', 'after'),
            array('member.doLogin', 'shop', 'controller', 'triggerLoginBefore', 'before'),
            array('member.doLogin', 'shop', 'controller', 'triggerLoginAfter', 'after')
        );

        /**
         * @brief module install
         **/
        public function moduleInstall() {
            $oModuleController = getController('module');

            foreach($this->add_triggers as $trigger) {
                $oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
            }

        }

        /**
         * @brief check for update method
         **/
        public function checkUpdate() {
            $oDB = &DB::getInstance();
            $oModuleModel = getModel('module');

            foreach($this->add_triggers as $trigger) {
                if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) return true;
            }

            if(!$oDB->isColumnExists("shop_orders","transaction_id")) return true;
            if(!$oDB->isColumnExists("shop","currency_symbol")) return true;
            if(!$oDB->isColumnExists("shop_products","discount_price")) return true;
            if(!$oDB->isColumnExists("shop_products","is_featured")) return true;
            if(!$oDB->isColumnExists("shop","discount_min_amount")) return true;
            if(!$oDB->isColumnExists("shop","discount_type")) return true;
            if(!$oDB->isColumnExists("shop","discount_amount")) return true;
            if(!$oDB->isColumnExists("shop","discount_tax_phase")) return true;
            if(!$oDB->isColumnExists("shop","out_of_stock_products")) return true;
            if(!$oDB->isColumnExists("shop","minimum_order")) return true;
            if(!$oDB->isColumnExists("shop","show_VAT")) return true;
            if(!$oDB->isColumnExists("shop_order_products","member_srl")) return true;
            if(!$oDB->isColumnExists("shop_order_products","parent_product_srl")) return true;
            if(!$oDB->isColumnExists("shop_order_products","product_type")) return true;
            if(!$oDB->isColumnExists("shop_order_products","title")) return true;
            if(!$oDB->isColumnExists("shop_order_products","description")) return true;
            if(!$oDB->isColumnExists("shop_order_products","short_description")) return true;
            if(!$oDB->isColumnExists("shop_order_products","sku")) return true;
            if(!$oDB->isColumnExists("shop_order_products","weight")) return true;
            if(!$oDB->isColumnExists("shop_order_products","status")) return true;
            if(!$oDB->isColumnExists("shop_order_products","friendly_url")) return true;
            if(!$oDB->isColumnExists("shop_order_products","price")) return true;
            if(!$oDB->isColumnExists("shop_order_products","discount_price")) return true;
            if(!$oDB->isColumnExists("shop_order_products","qty")) return true;
            if(!$oDB->isColumnExists("shop_order_products","in_stock")) return true;
            if(!$oDB->isColumnExists("shop_order_products","primary_image_filename")) return true;
            if(!$oDB->isColumnExists("shop_order_products","related_products")) return true;
            if(!$oDB->isColumnExists("shop_order_products","regdate")) return true;
            if(!$oDB->isColumnExists("shop_order_products","last_update")) return true;
            if(!$oDB->isColumnExists("shop_cart_products","title")) return true;
            if(!$oDB->isColumnExists("shop_orders","discount_min_order")) return true;
            if(!$oDB->isColumnExists("shop_orders","discount_type")) return true;
            if(!$oDB->isColumnExists("shop_orders","discount_amount")) return true;
            if(!$oDB->isColumnExists("shop_orders","discount_tax_phase")) return true;
            if(!$oDB->isColumnExists("shop_orders","currency")) return true;

            if($oDB->isColumnExists("shop_categories","order")) return true;
            if(!$oDB->isColumnExists("shop_categories","list_order")) return true;

            if(!$oDB->isColumnExists("shop_addresses","firstname")) return true;
            if(!$oDB->isColumnExists("shop_addresses","lastname")) return true;

            if(!$oDB->isColumnExists("shop_payment_methods","module_srl")) return true;
            if(!$oDB->isColumnExists("shop_shipping_methods","module_srl")) return true;

            if($oDB->isIndexExists("shop_payment_methods","unique_name")) return true;
            if($oDB->isIndexExists("shop_shipping_methods","unique_name")) return true;

            if(!$oDB->isColumnExists("shop","menus")) return true;

            return false;
        }

        /**
         * @brief module update
         **/
        public function moduleUpdate() {
            $oDB = &DB::getInstance();
            $oModuleModel = getModel('module');
            $oModuleController = getController('module');

            foreach($this->add_triggers as $trigger) {
                if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) {
                    $oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
                }
            }

            $success = true;

            if(!$oDB->isColumnExists("shop_orders","transaction_id")) {
                $success &= $oDB->addColumn('shop_orders',"transaction_id","varchar",128);
            }

            if(!$oDB->isColumnExists("shop","currency_symbol")) {
                $success &= $oDB->addColumn('shop',"currency_symbol","varchar",5);
            }

            if(!$oDB->isColumnExists("shop_products","discount_price")) {
                $success &= $oDB->addColumn('shop_products',"discount_price","float",20);
            }

            if(!$oDB->isColumnExists("shop_products","is_featured")) {
                $success &= $oDB->addColumn('shop_products',"is_featured","char",1);
            }

            if(!$oDB->isColumnExists("shop","show_VAT")) {
                $success &= $oDB->addColumn('shop',"show_VAT","char",1);
            }

            if(!$oDB->isColumnExists("shop","discount_min_amount")) {
                $success &= $oDB->addColumn('shop',"discount_min_amount","number",20);
            }

            if(!$oDB->isColumnExists("shop","discount_type")) {
                $success &= $oDB->addColumn('shop',"discount_type","varchar",40);
            }

            if(!$oDB->isColumnExists("shop","discount_amount")) {
                $success &= $oDB->addColumn('shop',"discount_amount","number",20);
            }

            if(!$oDB->isColumnExists("shop","discount_tax_phase")) {
                $success &= $oDB->addColumn('shop',"discount_tax_phase","varchar",40);
            }

            if(!$oDB->isColumnExists("shop","out_of_stock_products")) {
                $success &= $oDB->addColumn('shop',"out_of_stock_products","char",1);
            }

            if(!$oDB->isColumnExists("shop","minimum_order")) {
                $success &= $oDB->addColumn('shop',"minimum_order","number",20);
            }

            if(!$oDB->isColumnExists("shop_order_products","member_srl")) {
                $success &= $oDB->addColumn('shop_order_products',"member_srl","number",11, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","parent_product_srl")) {
                $success &= $oDB->addColumn('shop_order_products',"parent_product_srl","number", 11);
            }

            if(!$oDB->isColumnExists("shop_order_products","product_type")) {
                $success &= $oDB->addColumn('shop_order_products',"product_type","varchar", 250, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","title")) {
                $success &= $oDB->addColumn('shop_order_products',"title","varchar", 250, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","description")) {
                $success &= $oDB->addColumn('shop_order_products',"description","bigtext");
            }

            if(!$oDB->isColumnExists("shop_order_products","short_description")) {
                $success &= $oDB->addColumn('shop_order_products',"short_description","varchar", 500);
            }

            if(!$oDB->isColumnExists("shop_order_products","sku")) {
                $success &= $oDB->addColumn('shop_order_products',"sku","varchar", 250, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","weight")) {
                $success &= $oDB->addColumn('shop_order_products',"weight","float", 10);
            }

            if(!$oDB->isColumnExists("shop_order_products","status")) {
                $success &= $oDB->addColumn('shop_order_products',"status","varchar", 50);
            }

            if(!$oDB->isColumnExists("shop_order_products","friendly_url")) {
                $success &= $oDB->addColumn('shop_order_products',"friendly_url","varchar", 50);
            }

            if(!$oDB->isColumnExists("shop_order_products","price")) {
                $success &= $oDB->addColumn('shop_order_products',"price","float", 20, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","discount_price")) {
                $success &= $oDB->addColumn('shop_order_products',"discount_price","float", 20, null, true);
            }

            if(!$oDB->isColumnExists("shop_order_products","qty")) {
                $success &= $oDB->addColumn('shop_order_products',"qty","float", 10);
            }

            if(!$oDB->isColumnExists("shop_order_products","in_stock")) {
                $success &= $oDB->addColumn('shop_order_products',"in_stock","char", 1, 'N');
            }

            if(!$oDB->isColumnExists("shop_order_products","primary_image_filename")) {
                $success &= $oDB->addColumn('shop_order_products',"primary_image_filename","varchar", 250);
            }

            if(!$oDB->isColumnExists("shop_order_products","related_products")) {
                $success &= $oDB->addColumn('shop_order_products',"related_products","varchar", 500);
            }

            if(!$oDB->isColumnExists("shop_cart_products","title")) {
                $success &= $oDB->addColumn('shop_cart_products',"title","varchar", 255);
            }

            if(!$oDB->isColumnExists("shop_order_products","regdate")) {
                $success &= $oDB->addColumn('shop_order_products',"regdate","date");
            }

            if(!$oDB->isColumnExists("shop_order_products","last_update")) {
                $success &= $oDB->addColumn('shop_order_products',"last_update","date");
            }

            if($oDB->isColumnExists("shop_categories","order")) {
                $success &= $oDB->dropColumn('shop_categories',"order");
            }

            if(!$oDB->isColumnExists("shop_categories","list_order")) {
                $success &= $oDB->addColumn('shop_categories',"list_order","number", 11, 0, true);
                executeQuery('shop.fixCategoriesOrder');
            }

            if(!$oDB->isColumnExists("shop_addresses","firstname")) {
                $success &= $oDB->addColumn('shop_addresses',"firstname","varchar", 45);
            }

            if(!$oDB->isColumnExists("shop_addresses","lastname")) {
                $success &= $oDB->addColumn('shop_addresses',"lastname","varchar", 45);
            }

            if(!$oDB->isColumnExists("shop_payment_methods","module_srl")) {
                $success &= $oDB->addColumn('shop_payment_methods',"module_srl","number", 11, 0, true);
            }

            if(!$oDB->isColumnExists("shop_shipping_methods","module_srl")) {
                $success &= $oDB->addColumn('shop_shipping_methods',"module_srl","number", 11, 0, true);
            }

            if($oDB->isIndexExists("shop_payment_methods","unique_name"))
            {
                $oDB->dropIndex("shop_payment_methods", "unique_name", true);
                $oDB->addIndex("shop_payment_methods", "unique_module_srl_name", array('module_srl', 'name'), true);
            }

            if($oDB->isIndexExists("shop_shipping_methods","unique_name"))
            {
                $oDB->dropIndex("shop_shipping_methods", "unique_name", true);
                $oDB->addIndex("shop_shipping_methods", "unique_module_srl_name", array('module_srl', 'name'), true);
            }

            if(!$oDB->isColumnExists("shop","menus")) {
                $success &= $oDB->addColumn('shop',"menus","varchar", 500);
            }

            if (!$oDB->isColumnExists("shop_orders","caca")) $success &= $oDB->addColumn('shop_orders',"discount_min_order","number", 11, 0, true);

            if (!$oDB->isColumnExists("shop_orders","discount_min_order")) $success &= $oDB->addColumn('shop_orders',"discount_min_order","number", 11, 0, true);
            if (!$oDB->isColumnExists("shop_orders","discount_type")) $success &= $oDB->addColumn('shop_orders',"discount_type","varchar", 45);
            if (!$oDB->isColumnExists("shop_orders","discount_amount")) $success &= $oDB->addColumn('shop_orders',"discount_amount","number", 11, 0, true);
            if (!$oDB->isColumnExists("shop_orders","discount_tax_phase")) $success &= $oDB->addColumn('shop_orders',"discount_tax_phase","varchar", 20);
            if (!$oDB->isColumnExists("shop_orders","currency")) $success &= $oDB->addColumn('shop_orders',"currency","varchar", 10);

            return $success ? new Object(0, 'success_updated') : new Object(-1, 'Error. Double check your update code.');
        }

        /**
         * @brief recompile cache
         **/
        public function recompileCache() {
        }


        public function checkXECoreVersion($requried_version){
			$result = version_compare(__XE_VERSION__, $requried_version, '>=');
			if ($result != 1) return false;
			return true;
        }
    }
?>
