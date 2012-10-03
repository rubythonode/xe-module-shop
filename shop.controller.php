<?php
    /**
     * @class  shopController
     * @author NHN (developers@xpressengine.com)
     * @brief  shop module Controller class
     **/

    class shopController extends shop {

        /** @var shopModel */
        protected $model;
        /** @var shopInfo */
        protected $shop;
        /** @var Cart */
        protected $cartBeforeLogin;

        /**
         * @brief Initialization
         **/
        public function init() {
            $this->model = getModel('shop');

            $oModuleModel = getModel('module');

            $site_module_info = Context::get('site_module_info');
            $site_srl = $site_module_info->site_srl;
            if($site_srl) {
                $this->module_srl = $site_module_info->index_module_srl;
                $this->module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
                Context::set('module_info',$this->module_info);
                Context::set('mid',$this->module_info->mid);
                Context::set('current_module_info',$this->module_info);
            }

            $this->shop = $this->model->getShop($this->module_srl);
            $this->site_srl = $this->shop->site_srl;
            Context::set('shop',$this->shop);
        }

        public function procShopSort()
        {
            if (!in_array($sort = Context::get('sort'), array('price_asc', 'price_desc'))) {
                throw new Exception('Invalid sorting required');
            }
            $_SESSION['sort'] = $sort;
            $category_srl = Context::get('category_srl');
            $this->setRedirectUrl(getNotEncodedUrl('','act', 'dispShop','category_srl',$category_srl));
        }

        public function procShopToggleGridView()
        {
            $mode = Context::get('mode');
            if ($mode == 'grid') $sess = true;
            elseif ($mode == 'list') $sess = false;
            else throw new Exception("Invalid setting $mode");
            $_SESSION['grid_view'] = $sess;
            $this->setRedirectUrlIfNoReferer(getNotEncodedUrl('', 'act', 'dispShopHome'));
        }

        public function procShopLogin($user_id = null, $password = null, $keep_signed = null) {
            $oMemberController = getController('member');

            if(!$user_id) $user_id = Context::get('user_id');
            $user_id = trim($user_id);

            if(!$password) $password = Context::get('password');
            $password = trim($password);

            if(!$keep_signed) $keep_signed = Context::get('keep_signed');

            $stat = 0;

            if(!$user_id) {
                $stat = -1;
                $msg = Context::getLang('null_user_id');
            }
            if(!$password) {
                $stat = -1;
                $msg = Context::getLang('null_password');
            }

            if(!$stat) {
                $output = $oMemberController->doLogin($user_id, $password, $keep_signed=='Y'?TRUE:FALSE);
                if(!$output->toBool()) {
                    $stat = -1;
                    $msg = $output->getMessage();
                }
            }

			if($stat == -1) return new Object(-1, $msg);

			$vid = Context::get('vid');
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'act', 'dispShopToolDashboard', 'vid', $vid);
			$this->setRedirectUrl($returnUrl);
        }

        public function procShopSignToNewsletter(){
            $this->updateNewsletterExtraVar();
            $this->setMessage("Successfully signed to newsletter");
        }

        public function procShopUnsignToNewsletter(){
            $this->updateNewsletterExtraVar();
            $this->setMessage("Successfully unsubscribed from newsletter");
        }

        public function updateNewsletterExtraVar(){
            $memberAdminController = getAdminController('member');

            $memberAdminController->procMemberAdminInsert();
            $this->setMessage("Successfully signed to newsletter");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShop');
            $this->setRedirectUrl($returnUrl);
        }

        public function procShopAddAddress() {
            $shopModel = getModel('shop');
            $addressRepository = $shopModel->getAddressRepository();

            $args = Context::getRequestVars();
            $logged_info = Context::get('logged_info');
            $args->member_srl = $logged_info->member_srl;
            if($args->first_address == 'Y') {
                $args->default_billing = 'Y';
                $args->default_shipping = 'Y';
            }
            $address = new Address($args);
            if($address->default_billing == 'Y') {
                $addressRepository->unsetDefaultBillingAddress($args->member_srl);
            }
            if($address->default_shipping == 'Y') $addressRepository->unsetDefaultShippingAddress($args->member_srl);
            if($address->address_srl){
                $addressRepository->update($address);
                $this->setMessage("Address has been updated successfully");
            }else {
                $addressRepository->insert($address);
                $this->setMessage("Address has been saved successfully");
            }



            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopAddressBook');
            $this->setRedirectUrl($returnUrl);
        }

        public function procShopDeleteAddress(){
            $shopModel = $this->model;
            $addressRepository = $shopModel->getAddressRepository();

            $address_srl = Context::get('address_srl');
            $address = $addressRepository->getAddress($address_srl);
            $logged_info = Context::get('logged_info');
            if($logged_info->member_srl != $address->member_srl) return new Object(-1,'msg_invalid_request');

            $addressRepository->deleteAddress($address_srl);

            $this->setMessage("Address has been deleted succesfully");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopAddressBook');
            $this->setRedirectUrl($returnUrl);
        }


        public function updateShop($args){
            $output = executeQuery('shop.updateShop', $args);
            return $output;
        }

        public function updateShopInfo($module_srl,$args){
            $args->module_srl = $module_srl;
            $output = executeQuery('shop.updateShop', $args);
            return $output;
        }

        public function procShopInfoUpdate(){
            $oModuleController = getController('module');
            $oModuleModel = getModel('module');
            $oShopModel = $this->model;

            if(in_array(strtolower('dispShopToolConfigInfo'),$this->custom_menu->hidden_menu)) return new Object(-1,'msg_invalid_request');

            $args = Context::gets('shop_title','shop_content','timezone');
			$args->module_srl = $this->module_srl;
            $output = executeQuery('shop.updateShopInfo',$args);
            if(!$output->toBool()) return $output;

            $module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
            $module_info->browser_title = $args->shop_title;
            $output = $oModuleController->updateModule($module_info);
            if(!$output->toBool()) return $output;

			unset($args);
            $args = new stdClass();
            $args->index_module_srl = $this->module_srl;
            $args->default_language = Context::get('language');
            $args->site_srl = $this->site_srl;
            $output = $oModuleController->updateSite($args);
            if(!$output->toBool()) return $output;

            if(Context::get('delete_icon')=='Y') $this->deleteShopFavicon($this->module_srl);

            $favicon = Context::get('favicon');
            if(Context::isUploaded()&&is_uploaded_file($favicon['tmp_name'])) $this->insertShopFavicon($this->module_srl,$favicon['tmp_name']);

            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('move_myshop');
        }


        /**
         * @brief shop colorset modify
         **/
        public function procShopColorsetModify() {
            $oShopModel = $this->model;
            $myshop = $oShopModel->getMemberShop();
            if(!$myshop->isExists()) return new Object(-1, 'msg_not_permitted');

            $colorset = Context::get('colorset');
            if(!$colorset) return new Object(-1,'msg_invalid_request');

            $this->updateShopColorset($myshop->getModuleSrl(), $colorset);

            $this->setTemplatePath($this->module_path.'tpl');
            $this->setTemplateFile('move_myshop');
        }

        /*
         * brief function for product insert
         * @author Dan Dragan (dev@xpressengine.org)
         */
        public function procShopToolInsertProduct(){
            $shopModel = $this->model;
            $productRepository = $shopModel->getProductRepository();
			$imageRepository = $shopModel->getImageRepository();

            $args = Context::getRequestVars();
			if(is_array($args->filesToUpload)) $args->images = $imageRepository->createImagesUploadedFiles($args->filesToUpload);
			if(!isset($args->primary_image) && isset($args->images)) $args->images[0]->is_primary = 'Y';
			if(isset($args->primary_image) && isset($args->images[$args->primary_image]))	{
				$args->images[$args->primary_image]->is_primary = 'Y';
				unset($args->primary_image);
			}

            $logged_info = Context::get('logged_info');
            $args->member_srl = $logged_info->member_srl;
            $args->module_srl = $this->module_info->module_srl;

			if($args->product_type == 'simple')
			{
				$product = new SimpleProduct($args);
			}
			else
			{
				$product = new ConfigurableProduct($args);
			}

            try
            {
                if($product->product_srl === NULL)
                {
                    $product_srl = $productRepository->insertProduct($product);
					if($product->isSimple())
					{
						$this->setMessage("Saved simple product successfull");
						$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
					}
					else
					{
						$this->setMessage("Saved configurable product successfull");
						$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolAddAssociatedProducts','product_srl',$product->product_srl);
					}
                }
                else
                {
					$product->delete_images = $args->delete;
                    $productRepository->updateProduct($product);
					if($product->isSimple())
					{
						$this->setMessage("Updated simple product successfull");
					}
					else
					{
						$this->setMessage("Updated configurable product successfull");
					}

					if($product->isSimple() && $product->parent_product_srl)
					{
						$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolEditProduct', 'product_srl', $product->parent_product_srl);
					}
					else
					{
						$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
					}
                }
				$productRepository->updatePrimaryImageFilename($product);
            }
            catch(Exception $e)
            {
                return new Object(-1, $e->getMessage());
            }

			$this->setRedirectUrl($returnUrl);
        }

		/*
		* brief function for product insert duplicate
		* @author Dan Dragan (dev@xpressengine.org)
		*/
		public function procShopToolInsertDuplicate(){
			$shopModel = $this->model;
			$productRepository = $shopModel->getProductRepository();

			$product_srl = Context::get('product_srl');
			$product = $productRepository->getProduct($product_srl);
			$product->title = 'Copy of '.$product->title;
			$product->sku = 'Copy-'.$product->sku;
			foreach($product->images as $image){
				unset($image->image_srl);
				$path = sprintf('./files/attach/images/shop/%d/product-images/%d/', $image->module_srl , $image->product_srl);
				$image->source_filename = sprintf('%s%s', $path, $image->filename);
			}
			unset($product_srl);
			$productRepository->insertProduct($product);
            $productRepository->updatePrimaryImageFilename($product);
            $this->setMessage("A product has been successfully duplicated");
			$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
			$this->setRedirectUrl($returnUrl);
		}

		/*
		* brief function for export products to csv
		* @author Dan Dragan (dev@xpressengine.org)
		*/
		public function procShopToolExportProducts(){
			$shopModel = $this->model;
			$productRepository = $shopModel->getProductRepository();
            $categoryRepository = $shopModel->getCategoryRepository();
            $attributeRepository = $shopModel->getAttributeRepository();

			$args = new stdClass();
			$args->module_srl = $this->module_info->module_srl;

			$products = $productRepository->getAllProducts($args);
            $categories = $categoryRepository->getCategoriesTree($args->module_srl)->toFlatStructure();
            $attributes = $attributeRepository->getAttributesList($args->module_srl)->attributes;

            FileHandler::makeDir('./files/attach/shop/export-import/');
            if(count($products)) $productRepository->addProductsToExportFolder($products);
            if(count($categories)) $categoryRepository->addCategoriesToExportFolder($categories);
            if(count($attributes)) $attributeRepository->addAttributesToExportFolder($attributes);
            if(!count($products) && !count($categories) && !count($attributes)){
                $this->setMessage("No data to export");
                $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
                $this->setRedirectUrl($returnUrl);
                return;
            }
            $shopModel->includeZipHandler();

            ZipHandler::zip('./files/attach/shop/export-import/','./files/attach/shop/export.zip');

            header("Content-type: application/zip");
            header("Content-Disposition: attachment; filename=export.zip");
            header("Pragma: no-cache");
            header("Expires: 0");

            readfile('./files/attach/shop/export.zip');

            FileHandler::removeFile('./files/attach/shop/export.zip');
            FileHandler::removeDir('./files/attach/shop/export-import/');

            exit;
		}

        /*
          * brief function for import products to csv
          * @author Dan Dragan (dev@xpressengine.org)
          */
        public function procShopToolImportProducts(){
            $shopModel = $this->model;
            $args = new stdClass();
            $args->module_srl = $this->module_info->module_srl;
            $logged_info = Context::get('logged_info');
            $args->member_srl = $logged_info->member_srl;

            $productRepository = $shopModel->getProductRepository();
            $categoryRepository = $shopModel->getCategoryRepository();
            $attributeRepository = $shopModel->getAttributeRepository();

            $import_file = Context::get('import_file');
            $zip = new ZipArchive;
            $res = $zip->open($import_file['tmp_name']);
            if ($res === TRUE) {
                $zip->extractTo('./files/attach/shop/export-import/');
            }

            $category_ids = $categoryRepository->insertCategoriesFromImportFolder($args);
            if(isset($category_ids)){
                foreach($category_ids as $key => $id){
                    $args->category_ids[$key]=$id;
                }
            }
            $args->attribute_ids = $attributeRepository->insertAttributesFromImportFolder($args);
            $productRepository->insertProductsFromImportFolder($args);

            FileHandler::removeDir('./files/attach/shop/export-import/');
            $this->setMessage("Products have been successfully imported");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
            $this->setRedirectUrl($returnUrl);
        }

		/*
		* brief function for associated products insert
		* @author Dan Dragan (dev@xpressengine.org)
		*/
		public function procShopToolInsertAssociatedProducts(){
			/**
			 * @var shopModel $shopModel
			 */
			$shopModel = $this->model;
			$productRepository = $shopModel->getProductRepository();
			$args = Context::getRequestVars();
			$parent_product = $productRepository->getProduct($args->parent_product_srl);
			foreach($args->associated_combinations as $combination){
				$values = explode('_',$combination);
				try{
					$product = $productRepository->createProductFromParent($parent_product,$values);
					$product_srl = $productRepository->insertProduct($product);
				}
				catch(Exception $e)
				{
					return new Object(-1, $e->getMessage());
				}
			}
			$this->setMessage("Saved associated products successfull");
			$returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolEditProduct', 'product_srl', $parent_product->product_srl);
			$this->setRedirectUrl($returnUrl);
		}

        /*
         * @brief function for attribute insert
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopToolInsertAttribute() {
            $shopModel = $this->model;
            /** @var $repository AttributeRepository */
            $repository = $shopModel->getAttributeRepository();

            $args = Context::getRequestVars();
            $args->module_srl = $this->module_info->module_srl;
            $logged_info = Context::get('logged_info');
            $args->member_srl = $logged_info->member_srl;

            $attribute = new Attribute($args);
            $attribute->module_srl = $this->module_srl;
            try
            {
                if ($attribute->attribute_srl) {
                    $output = $repository->updateAttribute($attribute);
                    $this->setMessage("success_updated");
                }
                else {
                    $output = $repository->insertAttribute($attribute);
                    $this->setMessage("success_registed");
                }
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }

            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageAttributes');
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for address insert
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolInsertAddress() {
            $shopModel = $this->model;
            $addressRepository = $shopModel->getAddressRepository();

            $args = Context::getRequestVars();

            $address = new Address($args);
            try
            {
                if ($address->address_srl) {
                    $output = $addressRepository->update($address);
                    $this->setMessage("success_updated");
                }
                else {
                    $output = $addressRepository->insert($address);
                    $this->setMessage("success_registed");
                }
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }

            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageAddresses','member_srl',$args->member_srl);
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for admin account update
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolAccountUpdate(){
            $oMemberController = &getController('member');

            // nickname, email
            $args = new stdClass();
            $args->member_srl = $this->shop->member_srl;
            $args->nick_name = Context::get('nick_name');
            $args->email_address = Context::get('email_address');
            try{
                $output = $oMemberController->updateMember($args);
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }

            $this->setMessage("success_updated");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageAccount');
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for shop info update
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolInfoUpdate(){
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');
            $oShopModel = &getModel('shop');

            $args = Context::gets('shop_title','shop_content','timezone','telephone','address','currency','VAT','show_VAT','out_of_stock_products','minimum_order');
            $args->module_srl = $this->module_srl;
            $currencies = require_once(_XE_PATH_.'modules/shop/shop.currencies.php');
            $args->currency_symbol = $currencies[$args->currency]['symbol'];

            $output = executeQuery('shop.updateShopInfo',$args);
            if(!$output->toBool()) {
                $this->setMessage('failed_updated');
                return $output;
            }

            $module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
            $module_info->browser_title = $args->shop_title;
            $output = $oModuleController->updateModule($module_info);
            if(!$output->toBool()) return $output;

            unset($args);
            $args = new stdClass();
            $args->index_module_srl = $this->module_srl;
            $args->default_language = Context::get('language');
            $args->site_srl = $this->site_srl;
            $output = $oModuleController->updateSite($args);
            if(!$output->toBool()) {
                $this->setMessage('failed_updated');
                return $output;
            }

            if(Context::get('delete_icon')=='Y') $this->deleteShopFavicon($this->module_srl);

            $favicon = Context::get('favicon');
            if(Context::isUploaded()&&is_uploaded_file($favicon['tmp_name'])) $this->insertShopFavicon($this->module_srl,$favicon['tmp_name']);

            $this->setMessage("success_updated");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolConfigInfo');
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for discount info update
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDiscountUpdate(){
            $args = Context::gets('discount_min_amount','discount_type','discount_amount','discount_tax_phase');
            $args->module_srl = $this->module_srl;
            if($args->discount_amount >= $args->discount_min_amount){
                $this->setMessage("Discount amount ($args->discount_amount) is bigger than discount min amount ($args->discount_min_amount)");
                $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolDiscountInfo');
                $this->setRedirectUrl($returnUrl);
                return;
            }
            if($args->discount_type == 'percentage' && $args->discount_amount > 99) {
                $this->setMessage('Discount percentage is bigger than 99');
                $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolDiscountInfo');
                $this->setRedirectUrl($returnUrl);
                return;
            }
            $output = executeQuery('shop.updateDiscountInfo',$args);
            if(!$output->toBool()) {
                $this->setMessage('failed_updated');
                return $output;
            }
            $this->setMessage("success_updated");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolDiscountInfo');
            $this->setRedirectUrl($returnUrl);
        }

        public function insertShopFavicon($module_srl, $source) {
            $oShopModel = &getModel('shop');
            $path = $oShopModel->getShopFaviconPath($module_srl);
            if(!is_dir($path)) FileHandler::makeDir($path);
            $filename = sprintf('%sfavicon.ico', $path);
            move_uploaded_file($source, $filename);
        }

        public function deleteShopFavicon($module_srl){
            $oShopModel = &getModel('shop');
            $path = $oShopModel->getShopFaviconPath($module_srl);
            $filename = sprintf('%s/favicon.ico', $path);
            FileHandler::removeFile($filename);
        }

        /*
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopSearch()
        {
            if ($searchQuery = Context::get('search')) {
                $category_srl = Context::get('search_category_srl');
                $url = getNotEncodedUrl('', 'act', 'dispShopSearch', 'q', $searchQuery, 'search_category_srl', $category_srl);
            }
            else $url = getNotEncodedUrl('', 'act', 'dispShopHome');
            $this->setRedirectUrl($url);
        }

        public function procShopRenewOrder(){
            $shopModel = $this->model;
            $orderRepository = $shopModel->getOrderRepository();
            $cartRepository = $shopModel->getCartRepository();
            $productRepository = $shopModel->getProductRepository();

            $order_srl = Context::get('order_srl');
            $order = $orderRepository->getOrderBySrl($order_srl);
            $logged_info = Context::get('logged_info');
            if($logged_info->member_srl != $order->member_srl){
                return new Object(-1, 'This is not your order');
            }

            $order_items = $orderRepository->getOrderItems($order);
            $cart = $cartRepository->getCart($this->module_info->module_srl,null,$logged_info->member_srl, session_id(), true);
            $cartRepository->deleteCartProducts($cart->cart_srl);
            foreach($order_items as $item){
                $cart->addProduct($item,$item->quantity);
            }
            $this->setMessage("Ordered renewed");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopCart');
            $this->setRedirectUrl($returnUrl);
        }

        public function procShopToolManageProducts()
        {
            $params = array('', 'act', 'dispShopToolManageProducts');
            if ($search = Context::get('search_keyword')) {
                $params[] = 'search';
                $params[] = $search;
            }
            if ($category = Context::get('search_category_srl')) {
                $params[] = 'category_srl';
                $params[] = $category;
            }
            if ($col = Context::get('col_search')) {
                if ($col != 'title' && $search) {
                    $params[] = 'column';
                    $params[] = $col;
                }
            }
            $this->setRedirectUrl(call_user_func_array('getNotEncodedUrl', $params));
        }

        /*
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopToolLogin()
        {
            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopCheckout'));
            $login = Context::get('login');
            if ($user = $login['user']) {
                try {
                    if (Context::get('is_logged')) throw new Exception('Already logged in, this should not happen');
                    if (!$pass = $login['pass']) throw new Exception('No password');
                    /** @var $oMemberController memberController */
                    $oMemberController = getController('member');
                    return $oMemberController->procMemberLogin($user, $pass);
                }
                catch (Exception $e) {
                    return new Object(-1, $e->getMessage());
                }
            }
            else return new Object(-1, 'Username / password?');
        }

        /*
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopToolCheckout()
        {
            $cartRepo = new CartRepository();
            $logged_info = Context::get('logged_info');

            //get or create cart:
            if ($cart = $cartRepo->getCart($this->module_info->module_srl, null, $logged_info->member_srl, session_id(), true))
            {

                $haveShipping = (Context::get('different_shipping') == 'yes');
                $shipping = Context::get('shipping');
                if (!$haveShipping) unset($shipping['address_srl']);

                try {
                    $cart->checkout(array(
                        'billing'  => Context::get('billing'),
                        'new_billing_address' => Context::get('new_billing_address'),
                        'shipping' => $shipping, // MUST send shipping, otherwise shipping_method is lost
                        'new_shipping_address' => $haveShipping ? Context::get('new_shipping_address') : null,
                        'payment'  => Context::get('payment'),
                    ));
                }
                catch (Exception $e) {
                    return new Object(-1, $e->getMessage());
                }

                // Get selected payment method name
                $payment = Context::get('payment');
                $payment_method_name = $payment['method'];

                // Get payment class
                $payment_repository = new PaymentMethodRepository();
                try {
                    $payment_method = $payment_repository->getPaymentMethod($payment_method_name, $this->module_srl);
                }
                catch (Exception $e) {
                    return new Object(-1, $e->getMessage());
                }

                $error_message = '';
                if(!$payment_method->onCheckoutFormSubmit($cart, $error_message))
                {
                    $this->setMessage($error_message, 'error');
                    $vid = Context::get('vid');
                    $return_url = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopCheckout');
                    $this->setRedirectUrl($return_url);
                }
                else
                {
                    $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopPlaceOrder'));
                }
            }
            else throw new Exception('No cart');
        }

        /*
        * @brief function for holding order
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolHoldOrder(){
            $order_srl = Context::get('order_srl');
            $orderRepository = $this->model->getOrderRepository();
            $order = $orderRepository->getOrderBySrl($order_srl);
            $order->order_status = Order::ORDER_STATUS_HOLD;
            try{
                $order->save();
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }
            $this->setMessage("Ordered has been successfully put on Hold");
            $return_url = getNotEncodedUrl('', 'act','dispShopToolViewOrder','order_srl',$order_srl);
            $this->setRedirectUrl($return_url);
        }

        /*
        * @brief function for unholding order
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolUnholdOrder(){
            $order_srl = Context::get('order_srl');
            $orderRepository = $this->model->getOrderRepository();
            $order = $orderRepository->getOrderBySrl($order_srl);
            if(isset($order->invoice) || isset($order->shipment)) $order->order_status = Order::ORDER_STATUS_PROCESSING;
            else $order->order_status = Order::ORDER_STATUS_PENDING;
            try{
                $order->save();
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }
            $this->setMessage("Ordered has been successfully Unhold");
            $return_url = getNotEncodedUrl('', 'act','dispShopToolViewOrder','order_srl',$order_srl);
            $this->setRedirectUrl($return_url);
        }

        /*
        * @brief function for cancelling order
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolCancelOrder(){
            $order_srl = Context::get('order_srl');
            $orderRepository = $this->model->getOrderRepository();
            $order = $orderRepository->getOrderBySrl($order_srl);
            $order->order_status = Order::ORDER_STATUS_CANCELED;
            try{
                $order->save();
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }
            $this->setMessage("Ordered has been Canceled");
            $return_url = getNotEncodedUrl('', 'act','dispShopToolViewOrder','order_srl',$order_srl);
            $this->setRedirectUrl($return_url);
        }

        /*
        * @brief function for adding order invoice
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolInsertInvoice(){
            $order_srl = Context::get('order_srl');
            $orderRepository = $this->model->getOrderRepository();
            $invoiceRepository = $this->model->getInvoiceRepository();
            $order = $orderRepository->getOrderBySrl($order_srl);
            $args = Context::getRequestVars();
            $args->order_srl = $order_srl;
            $args->module_srl = $order->module_srl;
            $invoice = new Invoice($args);
            if(!isset($invoice->invoice_srl)) $insert=true;
            $invoice->save();
            if($invoice->invoice_srl){
                if(isset($order->shipment)) $order->order_status = Order::ORDER_STATUS_COMPLETED;
                else $order->order_status = Order::ORDER_STATUS_PROCESSING;
                try{
                    $order->save();
                }
                catch(Exception $e) {
                    return new Object(-1, $e->getMessage());
                }
                if($insert){
                    $this->setMessage("Invoice has been created");
                    $return_url = getNotEncodedUrl('', 'act','dispShopToolViewOrder','order_srl',$order_srl);
                    $this->setRedirectUrl($return_url);
                } else {
                    $this->setMessage("Invoice has been updated");
                    $return_url = getNotEncodedUrl('', 'act','dispShopToolManageInvoices','order_srl',$order_srl);
                    $this->setRedirectUrl($return_url);
                }

            } else {
                throw new Exception('Something whent wrong when adding invoice');
            }
        }

        /*
        * @brief function for adding order shipment
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolInsertShipment(){
            $order_srl = Context::get('order_srl');
            $orderRepository = $this->model->getOrderRepository();
            $shipmentRepository = $this->model->getShipmentRepository();
            $order = $orderRepository->getOrderBySrl($order_srl);
            $args = Context::getRequestVars();
            $args->order_srl = $order_srl;
            $args->module_srl = $order->module_srl;
            $shipment = new Shipment($args);
            if(!isset($shipment->shipment_srl)) $insert=true;
            $shipment->save();
            if($shipment->shipment_srl){
                if(isset($order->invoice)) $order->order_status = Order::ORDER_STATUS_COMPLETED;
                else $order->order_status = Order::ORDER_STATUS_PROCESSING;
                try{
                    $order->save();
                }
                catch(Exception $e) {
                    return new Object(-1, $e->getMessage());
                }
                if($insert){
                    $this->setMessage("Shipment has been created");
                    $return_url = getNotEncodedUrl('', 'act','dispShopToolViewOrder','order_srl',$order_srl);
                    $this->setRedirectUrl($return_url);
                } else {
                    $this->setMessage("Shipment has been updated");
                    $return_url = getNotEncodedUrl('', 'act','dispShopToolManageShipments','order_srl',$order_srl);
                    $this->setRedirectUrl($return_url);
                }

            } else {
                throw new Exception('Something whent wrong when adding shipment');
            }
        }

        public function procShopPlaceOrder()
        {
            $cartRepo = new CartRepository();
            $logged_info = Context::get('logged_info');
            $cart = $cartRepo->getCart($this->module_srl, null, $logged_info->member_srl, session_id());

            // Get payment class
            $payment_repository = new PaymentMethodRepository();
            $payment_method = $payment_repository->getPaymentMethod($cart->getPaymentMethodName(), $this->module_srl);

            $error_message = '';
            if(!$payment_method->processPayment($cart, $error_message))
            {
                return new Object(-1, $error_message);
            }

			try
			{
				$order = new Order($cart);
				$order->save(); //obtain srl
				$order->saveCartProducts($cart);
				$cart->delete();
			}
			catch(Exception $e)
			{
				return new Object(-1, 'msg_error_occured');
			}

            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopOrderConfirmation', 'order_srl', $order->order_srl));
        }

        /*
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopToolCartAddProduct()
        {
            $cartRepository = new CartRepository();
            if ($product_srl = Context::get('product_srl')) {
                $productsRepo = new ProductRepository();
                if ($product = $productsRepo->getProduct($product_srl))
                {
                    if (!($product instanceof SimpleProduct)) {
                        return new Object(-1, 'msg_invalid_request');
                    }
                    $logged_info = Context::get('logged_info');
                    $cart = $cartRepository->getCart($this->module_info->module_srl, null, $logged_info->member_srl, session_id(), true);
                    $quantity = (is_numeric(Context::get('quantity')) && Context::get('quantity') > 0 ? Context::get('quantity') : 1);
                    try {
                        $cart->addProduct($product, $quantity);
                    }
                    catch (Exception $e) {
                        return new Object(-1, $e->getMessage());
                    }
                }
                else return new Object(-1, 'msg_invalid_request');
            }
            else return new Object(-1, 'msg_invalid_request');
            $shop = $this->model->getShop($this->module_srl);
            $this->setRedirectUrlIfNoReferer(getSiteUrl($shop->domain));
        }

        /*
         * Remove products from cart
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopCartRemoveProducts() {
            $cart_srl = Context::get('cart_srl');
            if ($cart_srl && !is_numeric($cart_srl)) throw new Exception('Invalid cart_srl');
            if (!is_array($product_srls = Context::get('product_srls'))) {
                if (!is_numeric($product_srls)) throw new Exception('Invalid product_srl for single product delete');
                $product_srls = array($product_srls);
            }
            $cartRepo = new CartRepository();
            $logged_info = Context::get('logged_info');
            $cart = $cartRepo->getCart($this->module_srl, $cart_srl, $logged_info->member_srl, session_id());
            $cart->removeProducts($product_srls);
            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopCart'));
        }

        /*
         * Update product quantities in cart
         * Expects $quantities to be something like array('prod_srl'=>$newQuantity, ...)
         * @author Florin Ercus (dev@xpressengine.org)
         */
        public function procShopCartUpdateProducts() {
            $cart_srl = Context::get('cart_srl');
            if ($cart_srl && !is_numeric($cart_srl)) throw new Exception('Invalid cart_srl');
            if (!is_array($quantities = Context::get('quantity'))) {
                throw new Exception('Invalid products array input.');
            }
            $cartRepo = new CartRepository();
            $logged_info = Context::get('logged_info');
            if (!$cart = $cartRepo->getCart($this->module_srl, $cart_srl, $logged_info->member_srl, session_id())) {
                throw new Exception('No cart');
            }
            $cart->updateProducts($quantities);
            $this->setRedirectUrlIfNoReferer(getNotEncodedUrl('', 'act', 'dispShopCart'));
        }

        /**
         * Acts like setRedirectUrl, but it gives priority to the referer
         * @param string $url
         * @param null   $output
         */
        public function setRedirectUrlIfNoReferer($url='./', $output = NULL)
        {
            $url = ($_SERVER["HTTP_REFERER"] ? $_SERVER["HTTP_REFERER"] : $url);
            return $this->setRedirectUrl($url, $output);
        }

        /*
        * @brief function for product delete
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDeleteProduct() {
            $shopModel = $this->model;
            $repository = $shopModel->getProductRepository();

            $args = new stdClass();
            $args->product_srl = Context::get('product_srl');
			$args->product_type = Context::get('product_type');

            $repository->deleteProduct($args);
            $this->setMessage("success_deleted");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for multiple products delete
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDeleteProducts(){
            $shopModel = $this->model;
            $repository = $shopModel->getProductRepository();

			$args = new stdClass();
			$args->module_srl = $this->module_info->module_srl;
            $args->product_srls = explode(',',Context::get('product_srls'));
            $repository->deleteProducts($args);
            $this->setMessage("success_deleted");
            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageProducts');
            $this->setRedirectUrl($returnUrl);
        }

        /*
        * @brief function for multiple attributes delete
        * @author Florin Ercus (dev@xpressengine.org)
        */
        public function procShopToolDeleteAttributes()
        {
            $shopModel = $this->model;
            $repository = $shopModel->getAttributeRepository();
            $args = new stdClass();
            $args->attribute_srls = explode(',', Context::get('attribute_srls'));
            $repository->deleteAttributes($args);
            $this->setMessage("success_deleted");
            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopToolManageAttributes'));
        }

        public function procShopToolFilterAttributes()
        {
            $params = array('', 'act', 'dispShopToolManageAttributes');
            if ($search = Context::get('search_keyword')) {
                $params[] = 'search';
                $params[] = $search;
            }
            $this->setRedirectUrlFromArray($params);
        }

        public function procShopToolFilterOrders()
        {
            $params = array('', 'act', 'dispShopToolManageOrders');
            if ($search = Context::get('search_keyword')) {
                $params[] = 'search';
                $params[] = $search;
            }
            if ($col = Context::get('col_search')) {
                if ($col != 'billing_address' && $search) {
                    $params[] = 'column';
                    $params[] = $col;
                }
            }
            $this->setRedirectUrlFromArray($params);
        }

        public function procShopToolFilterInvoices()
        {
            $params = array('', 'act', 'dispShopToolManageInvoices');
            if ($search = Context::get('search_keyword')) {
                $params[] = 'search';
                $params[] = $search;
            }
            $this->setRedirectUrlFromArray($params);
        }

        public function procShopToolFilterCustomers()
        {
            $params = array('', 'act', 'dispShopToolManageCustomers');
            if ($search = Context::get('search_keyword')) {
                $params[] = 'search';
                $params[] = $search;
            }
            $this->setRedirectUrlFromArray($params);
        }

        public function setRedirectUrlFromArray(array $params)
        {
            $this->setRedirectUrl(call_user_func_array('getNotEncodedUrl', $params));
        }

        /*
        * @brief function for customer insertion
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolCustomerInsert(){
            $oMemberController = getController('member');
            $args = Context::getRequestVars();

            if ($args->reset_password)
                $args->password = $args->reset_password;
            else unset($args->password);

            // remove whitespace
            $checkInfos = array('user_id', 'nick_name', 'email_address');
            $replaceStr = array("\r\n", "\r", "\n", " ", "\t", "\xC2\xAD");
            foreach($checkInfos as $val){
                if(isset($args->{$val})){
                    $args->{$val} = str_replace($replaceStr, '', $args->{$val});
                }
            }

            if(!$args->member_srl) {
                $args->password = Context::get('password');
                $output = $oMemberController->insertMember($args);
                if(!$output->toBool()) {
                    $this->setMessage($output->message);
                    return $output;
                }
                $msg_code = 'success_registed';
            } else {
                $output = $oMemberController->updateMember($args);
                if(!$output->toBool()) {
                    $this->setMessage($output->message);
                    return $output;
                }
                $msg_code = 'success_updated';
            }


            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopToolManageCustomers'));
        }

        /*
        * @brief function for multiple customers delete
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDeleteCustomers()
        {
            $shopModel = $this->model;
            $target_member_srls = Context::get('target_member_srls');
            if(!$target_member_srls) return new Object(-1, 'msg_invalid_request');
            $member_srls = explode(',', $target_member_srls);
            $oMemberController = &getController('member');
            $oMemberController->memberInfo = null;

            foreach($member_srls as $member) {
                $output = $oMemberController->deleteMember($member);
                if(!$output->toBool()) {
                    $this->setMessage('failed_deleted');
                    return $output;
                }
            }

            $this->setMessage('success_deleted');
            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopToolManageCustomers'));
        }

        /*
        * @brief function for multiple customers unsubscription
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolUnsubscribeCustomers(){
            $shopModel = $this->model;
            $target_member_srls = Context::get('target_member_srls');
            if(!$target_member_srls) return new Object(-1, 'msg_invalid_request');
            $member_srls = explode(',', $target_member_srls);
            $customerRepository = $shopModel->getCustomerRepository();

            foreach($member_srls as $member) {
                $extra_vars = $customerRepository->getMemberExtraVars($member);
                $extra_vars = unserialize($extra_vars);
                $extra_vars->newsletter = 'N';
                $extra_vars = serialize($extra_vars);
                $customerRepository->updateMemberExtraVars($member,$extra_vars);
            }

            $this->setMessage('success_updated');
        }

        /*
        * @brief function for multiple newsletter delete
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDeleteNewsletters(){
            $shopModel = $this->model;
            $repository = $shopModel->getNewsletterRepository();
            $args = new stdClass();
            $args->newsletter_srls = explode(',', Context::get('newsletter_srls'));
            $repository->deleteNewsletters($args);
            $this->setMessage("success_deleted");
            $this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispShopToolManageNewsletters'));
        }

        /*
        * @brief function to send and add newsletter
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolSendNewsletter(){
            $shopModel = $this->model;
            $newsletterRepository = $shopModel->getNewsletterRepository();

            $args = Context::getRequestVars();
            $args->module_srl = $this->module_info->module_srl;

            $newsletter = new Newsletter($args);
            $newsletterRepository->sendEmailsToSubscribers($newsletter,$this->site_srl);
            try
            {
                if ($newsletter->newsletter_srl) {
                    $newsletter->save();
                    $this->setMessage("success_updated");
                }
                else {
                    $newsletter->save();
                    $this->setMessage("success_registed");
                }
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }

            $returnUrl = getNotEncodedUrl('', 'act', 'dispShopToolManageNewsletters');
            $this->setRedirectUrl($returnUrl);
        }


        /*
        * @brief function for multiple customers delete
        * @author Dan Dragan (dev@xpressengine.org)
        */
        public function procShopToolDeleteAddresses(){
            $shopModel = $this->model;
            $addressRepository = $shopModel->getAddressRepository();
            $address_srls = Context::get('address_srls');
            $member_srl = Context::get('member_srl');
            if(!$address_srls) return new Object(-1, 'msg_invalid_request');
            $address_srls = explode(',', $address_srls);

            foreach($address_srls as $address_srl) {
                $output = $addressRepository->deleteAddress($address_srl);
                if(!$output->toBool()) {
                    $this->setMessage('failed_deleted');
                    return $output;
                }
            }

            $this->setMessage('success_deleted');
            $returnUrl = getNotEncodedUrl('','act', 'dispShopToolManageAddresses','member_srl',$member_srl);
            $this->setRedirectUrl($returnUrl);
        }

        public function procShopToolLayoutConfigSkin() {
            $oModuleModel = getModel('module');
            $oModuleController = getController('module');
            $oShopModel = $this->model;

            if(in_array(strtolower('dispShopToolLayoutConfigSkin'),$this->custom_menu->hidden_menu)) return new Object(-1,'msg_invalid_request');

            $skin = Context::get('skin');
            if(!is_dir($this->module_path.'skins/'.$skin)) return new Object();

            $module_info  = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
            $module_info->skin = $skin;
            $output = $oModuleController->updateModule($module_info);
            if(!$output->toBool()) return $output;

            FileHandler::removeDir($oShopModel->getShopPath($this->module_srl));
            FileHandler::copyDir($this->module_path.'skins/'.$skin, $oShopModel->getShopPath($this->module_srl));
        }


        public function procShopToolLayoutResetConfigSkin() {
            /** @var $oModuleModel moduleModel */
            $oModuleModel = getModel('module');
            $module_info  = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
            $skin = $module_info->skin;
            $this->resetSkin($this->module_srl,$skin);
        }

        public function procShopToolResetSkin()
        {
            /** @var $oModuleModel moduleModel */
            $oModuleModel = getModel('module');
            $module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
            $skin = $module_info->skin;
            $this->resetSkin($this->module_srl, $skin);
        }

        public function resetSkin($module_srl,$skin=NULL){
            if(!$skin) $skin = $this->skin;
            if(!file_exists($this->module_path.'skins/'.$skin)) $skin = $this->skin;
            $oShopModel = $this->model;
            FileHandler::removeDir($oShopModel->getShopPath($module_srl));
            FileHandler::copyDir($this->module_path.'skins/'.$skin, $oShopModel->getShopPath($module_srl));
        }


        public function procShopToolLayoutConfigEdit() {
            if(in_array(strtolower('dispShopToolLayoutConfigEdit'),$this->custom_menu->hidden_menu)) return new Object(-1,'msg_invalid_request');

            $oShopModel = $this->model;
            $skin_path = $oShopModel->getShopPath($this->module_srl);

            $skin_file_list = $oShopModel->getShopUserSkinFileList($this->module_srl);
            foreach($skin_file_list as $file){
				// Replace . with _
				// Request variable names that contain . are modified by PHP to replace the . with _
				// see http://php.net/manual/en/language.variables.external.php
                $content = Context::get(str_replace('.', '_', $file));
                if($this->_checkDisabledFunction($content)) return new Object(-1,'msg_used_disabled_function');
                FileHandler::writeFile($skin_path.$file, $content);
            }

			$vid = Context::get('vid');
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolLayoutConfigEdit');
			$this->setRedirectUrl($returnUrl);
        }

        public function procShopToolUserSkinExport(){
            if(!$this->module_srl) return new Object('-1','msg_invalid_request');

            $oShopModel = $this->model;
            $skin_path = FileHandler::getRealPath($oShopModel->getShopPath($this->module_srl));

            $tar_list = FileHandler::readDir($skin_path,'/(\.css|\.html|\.htm|\.js)$/');

            $img_list = FileHandler::readDir($skin_path."img",'/(\.png|\.jpeg|\.jpg|\.gif|\.swf)$/');
            for($i=0,$c=count($img_list);$i<$c;$i++) $tar_list[] = 'img/' . $img_list[$i];

            $userimages_list = FileHandler::readDir($skin_path."user_images",'/(\.png|\.jpeg|\.jpg|\.gif|\.swf)$/');
            for($i=0,$c=count($userimages_list);$i<$c;$i++) $tar_list[] = 'user_images/' . $userimages_list[$i];

            require_once(_XE_PATH_.'libs/tar.class.php');
            chdir($skin_path);
            $tar = new tar();

            $replace_path = getNumberingPath($this->module_srl,3);
            foreach($tar_list as $key => $file) $tar->addFile($file,$replace_path,'__TEXTYLE_SKIN_PATH__');

            $stream = $tar->toTarStream();
            $filename = 'ShopUserSkin_' . date('YmdHis') . '.tar';
            header("Cache-Control: ");
            header("Pragma: ");
            header("Content-Type: application/x-compressed");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header('Content-Disposition: attachment; filename="'. $filename .'"');
            header("Content-Transfer-Encoding: binary\n");
            echo $stream;

            Context::close();
            exit();
         }

        public function procShopToolUserSkinImport(){
            if(!$this->module_srl) exit();

            // check upload
            if(!Context::isUploaded()) exit();
            $file = Context::get('file');
            if(!is_uploaded_file($file['tmp_name'])) exit();
            if(!preg_match('/\.(tar)$/i', $file['name'])) exit();

            $oShopModel = $this->model;
            $skin_path = FileHandler::getRealPath($oShopModel->getShopPath($this->module_srl));

            $tar_file = $skin_path . 'shop_skin.tar';

            FileHandler::removeDir($skin_path);
            FileHandler::makeDir($skin_path);

            if(!move_uploaded_file($file['tmp_name'], $tar_file)) exit();

            require_once(_XE_PATH_.'libs/tar.class.php');

            $tar = new tar();
            $tar->openTAR($tar_file);

            if(!$tar->getFile('shop.html')) return;

            $replace_path = getNumberingPath($this->module_srl,3);
            foreach($tar->files as $key => $info) {
                FileHandler::writeFile($skin_path . $info['name'],str_replace('__TEXTYLE_SKIN_PATH__',$replace_path,$info['file']));
            }

            FileHandler::removeFile($tar_file);
        }


        public function _checkDisabledFunction($str){
            if(preg_match('!<\?.*\?>!is',$str,$match)) return TRUE;

            $disabled = array(
                    // file
                    'fopen','link','unlink','popen','symlink','touch','readfile','rmdir','mkdir','rename','copy','delete','file_get_contents','file_put_contents','tmpname','parse_ini_file'
                    // dir
                    ,'dir'
                   // database
                   ,'mysql','sqlite','PDO','cubird','ibase','pg_','_pconnect','_connect','oci'
                   // network /etc
                   ,'fsockopen','pfsockopen','shmop_','shm_','sem_','dl','ini_','php','zend','pear','header','create_function','call_*','imap','openlog','socket','ob_','cookie','eval','exec','shell_exec','passthru'
                   // XE
                   ,'filehandler','displayhandler','xehttprequest','context','getmodel','getcontroller','getview','getadminmodel','getadmincontroller','getadminview','getdbinfo','executequery','executequeryarray'
            );
            unset($match);

            $disabled = '/('.implode($disabled, '|').')/i';
            preg_match_all('!<\!--@(.*?)-->!is', $str, $match1);
            preg_match_all('/ ([^(^ ]*) ?\(/i', ' '.join(' ',$match1[1]),$match_func1);
            preg_match_all('/{([^{]*)}/i',$str,$match2);
            preg_match_all('/ ([^(^ ]*) ?\(/i', ' '.join(' ',$match2[1]),$match_func2);
            $match1 = array_unique($match_func1[1]);
            $match2 = array_unique($match_func2[1]);
            preg_match($disabled, implode('|', $match1), $matches1);
            preg_match($disabled, implode('|', $match2), $matches2);

            if(count($matches1) || count($matches2)) return TRUE;

            return FALSE;
        }

        /**
         * @brief shop insert config
         **/
        public function insertShopConfig($shop) {
            $oModuleController = getController('module');
            $oModuleController->insertModuleConfig('shop', $shop);
        }

        /**
         * @brief shop update browser title
         **/
        public function updateShopBrowserTitle($module_srl, $browser_title) {
            $args = new stdClass();
            $args->module_srl = $module_srl;
            $args->browser_title = $browser_title;
            return executeQuery('shop.updateShopBrowserTitle', $args);
        }

        /**
         * @brief action forward apply layout
         **/
        public function triggerApplyLayout(&$oModule) {
            if(!$oModule || $oModule->getLayoutFile()=='popup_layout.html') return new Object();

            if(Context::get('module')=='admin') return new Object();

            if(in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) return new Object();

            if($oModule->act == 'dispMemberLogout') return new Object();

            $site_module_info = Context::get('site_module_info');
            if(!$site_module_info || !$site_module_info->site_srl || $site_module_info->mid != $this->shop_mid) return new Object();

            $oModuleModel = getModel('module');
            $xml_info = $oModuleModel->getModuleActionXml('shop');
            if($oModule->mid == $this->shop_mid && isset($xml_info->action->{$oModule->act})) return new Object();

            $oShopView = getView('shop');

            Context::set('layout',NULL);

            // When shop pages are accessed from other modules (a page, for instance)
            // Load the appropriate layout:
            //  - tool: backend
            //  - service: frontend
            if(strpos($oModule->act, "ShopTool") !== false || in_array($oModule->act, array('dispMenuAdminSiteMap'))) {
                $oShopView->initTool($oModule, true);
            } else {
                if(Mobile::isFromMobilePhone())
                {
                    $oShopView = &getMobile('shop');
                }
                $oShopView->initService($oModule, true);
            }

            return new Object();
        }

        public function triggerLoginBefore($obj)
        {
            $this->module_info = Context::get('site_module_info');
            $this->module_srl = $this->module_info->index_module_srl;
            $cartRepo = new CartRepository();
            $this->cartBeforeLogin = $cartRepo->getCart($this->module_srl, null, null, session_id(), true);
        }

        function triggerLoginAfter($logged_info)
        {
            $cartRepo = new CartRepository();
            if ($this->cartBeforeLogin instanceof Cart) {
                if ($memberCart = $cartRepo->getCart($this->module_info->module_srl, null, $logged_info->member_srl, session_id()))
                {
                    if ($memberCart->cart_srl != $this->cartBeforeLogin->cart_srl) {
                        $memberCart->merge($this->cartBeforeLogin);
                    }
                    Context::set('cart', $memberCart);
                } else {
                    $this->cartBeforeLogin->member_srl = $logged_info->member_srl;
                    $this->cartBeforeLogin->save();
                }
            }
        }

        public function procShopToolInit(){
            if(!$this->site_srl) return new Object(-1,'msg_invalid_request');

            $oShopAdminController = getAdminController('shop');
            $output = $oShopAdminController->initShop($this->site_srl);
            return $output;
        }

        public function procShopToolLive(){
			$_SESSION['live'] = time();
		}

		// region Product Category
		/**
		 * Inserts or updates a product category
		 *
		 * @author Corina Udrescu (dev@xpressengine.org)
		 * @return Object
		 */
		public function procShopToolInsertCategory()
		{
			$args = Context::gets('category_srl', 'module_srl', 'parent_srl', 'filename', 'title', 'description', 'friendly_url', 'include_in_navigation_menu');
			$file_info = Context::get('file_info');

			$delete_image = Context::get('delete_image');
			$vid = Context::get('vid');

			$shopModel = $this->model;
			$repository = $shopModel->getCategoryRepository();

			// Upload image
			if($file_info)
			{
				// If a previous picture exists, we delete it
				if($args->filename)
				{
					$repository->deleteCategoryImage($args->filename);
				}
				// Then we add the new one and update the filename
				$args->filename = $repository->saveCategoryImage(
					$args->module_srl
					, $file_info['name']
					, $file_info['tmp_name']
				);
			}
			else if($delete_image && $args->filename)
			{
				$repository->deleteCategoryImage($args->filename);
				$args->filename = '';
			}

			$category = new Category($args);
			try
			{
				if($category->category_srl === NULL)
				{
					$repository->insertCategory($category);
					$this->setMessage("success_registed");
				}
				else
				{
					$repository->updateCategory($category);
					$this->setMessage("success_updated");
				}
			}
			catch(Exception $e)
			{
				return new Object(-1, $e->getMessage());
			}

			$returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManageCategories');
			$this->setRedirectUrl($returnUrl);
		}

		/**
		 * Returns product category details
		 * Called through AJAX
		 *
		 * @author Corina Udrescu (dev@xpressengine.org)
		 * @return Object
		 */
		public function procShopServiceGetCategory()
		{
			$category_srl = Context::get('category_srl');
			if(!isset($category_srl)) return new Object(-1, 'msg_invalid_request');

			$shopModel = $this->model;
			$repository = $shopModel->getCategoryRepository();
			$category = $repository->getCategory($category_srl);
            $category->filename = $category->getThumbnailPath(50);

			$this->add('category', $category);
		}

		/**
		 * Deletes a product category
		 * Called through AJAX
		 *
		 * @author Corina Udrescu (dev@xpressengine.org)
		 * @return Object
		 */
		public function procShopServiceDeleteCategory()
		{
			$category_srl = Context::get('category_srl');
			if(!isset($category_srl)) return new Object(-1, 'msg_invalid_request');

			$shopModel = $this->model;
			$repository = $shopModel->getCategoryRepository();
			$args = new stdClass();
			$args->category_srl = $category_srl;

			try{
				$repository->deleteCategory($args);
				$this->setMessage('success_deleted');
			}
			catch(Exception $e)
			{
				$this->setError(-1);
				$this->setMessage($e->getMessage());
			}
		}

        /**
         * Moves a category - under another node
         * or simply updates its order
         */
        public function procShopServiceMoveCategory()
        {
            $source_category_srl = Context::get('source_srl');
            // If parent_srl exists, be the first child
            $parent_category_srl = Context::get('parent_srl');
            // If target_srl exists, be a sibling
            $target_category_srl = Context::get('target_srl');

            $category_repository = new CategoryRepository();
            $category_repository->moveCategory($source_category_srl, $parent_category_srl, $target_category_srl);
        }

		// endregion

        // region Payment methods

        /**
         * Activates a new payment method
         *
         * @author Daniel Ionescu (dev@xpressengine.org)
         */
        public function procUpdateShopActivatePaymentMethod() {

            $name = Context::get('name');

            if(!$name)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            $payment_repository = new PaymentMethodRepository();
            $payment_method = $payment_repository->getPaymentMethod($name, $this->module_srl);
            $payment_method->status = 1;
            $payment_repository->updatePaymentMethod($payment_method);

            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);
        }

        /**
         * Deactivates a payment method
         *
         * @author Daniel Ionescu (dev@xpressengine.org)
         */
        public function procUpdateShopDeactivatePaymentMethod() {

            $name = Context::get('name');

            if(!$name)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            $payment_repository = new PaymentMethodRepository();
            $payment_method = $payment_repository->getPaymentMethod($name, $this->module_srl);
            $payment_method->status = 0;
            $payment_repository->updatePaymentMethod($payment_method);

            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);

        }

		public function procShopToolSetPaymentMethodAsDefault()
		{
			$name = Context::get('name');
			if(!$name)
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$payment_repository = new PaymentMethodRepository();
			try
			{
				$payment_repository->setDefault($name, $this->module_srl);
				$this->setMessage('success_registed');
			}
			catch(ArgumentException $e)
			{
				$this->setError(-1);
				$this->setMessage($e->getMessage());
			}
			catch(DbQueryException $e)
			{
				$this->setError(-1);
				$this->setMessage('db_query_failed');
			}
			catch(Exception $e)
			{
				$this->setError(-1);
				$this->setMessage('fail_to_update');
			}

			$vid = Context::get('vid');
			$returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
			$this->setRedirectUrl($returnUrl);
		}

        /**
         * Deletes the payment plugins folder and database entry
         *
         * @author Daniel Ionescu (dev@xpressengine.org)
         */
        public function procUpdateShopDeletePaymentMethod() {
            global $lang;
            $name = Context::get('name');

            if ($name != '') {

                $baseDir = _XE_PATH_ . 'modules/shop/plugins_payment/';

                /**
                 * @var shopModel $shopModel
                 */
                $shopModel = $this->model;
                $repository = $shopModel->getPaymentMethodRepository();

                $repository->deletePaymentMethod($name, $this->module_srl);

                $fullPath = $baseDir . $name;
                if (!rmdir($fullPath)) {

                    $this->setError($lang->unable_to_delete);

                }

            }

            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);

        }

        public function procShopPaymentMethodUpdate()
        {
            $name = Context::get('name');
            if(!$name)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            /**
             * @var shopModel $shopModel
             */
            $shopModel = getModel('shop');
            $payment_repository = $shopModel->getPaymentMethodRepository();

            // Retrieve payment method from database
            $payment_method = $payment_repository->getPaymentMethod($name, $this->module_srl);

            // Update object with values submitted by user
            $data = Context::getRequestVars();
            $property_names = array_keys(get_object_vars($data));
            foreach($property_names as $property_name)
            {
                if(in_array($property_name, array('mid', 'vid','error_return_url', 'xe_form_id', 'act')))
                {
                    unset($data->$property_name);
                }
            }
            $payment_method->setProperties($data);

            // Save changes
            $payment_repository->updatePaymentMethod($payment_method);

            $this->setMessage('success_registed');
            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);
        }

        /**
         * Uploads and installs a new payment method
         *
         * @author Daniel Ionescu (dev@xpressengine.org)
         */
        public function procShopUploadPaymentPlugin() {

            $baseDir = _XE_PATH_ . 'modules/shop/plugins_payment/';
            $uploadedGateway = Context::get('uploadedPaymentGateway');
            $fullName = $uploadedGateway['name'];
            $name = explode('.',$uploadedGateway['name']);

            if ($uploadedGateway->error) {

                $this->setError('There was an error while uploading your file.');

            } else {

                $folderPath = $baseDir.$name[0];
                $filePath = $baseDir.$name[0].'/'.$fullName;

                if(is_dir($folderPath)) {

                    $this->setMessage('There is already a directory called "' . $name[0] . '" under ./modules/shop/plugins_payment/. Please delete the directory and try again.','error');

                } else {

                    if (mkdir($folderPath)) {

                        if (move_uploaded_file($uploadedGateway['tmp_name'], $filePath)) {

                            $zip = new ZipArchive();
                            $res = $zip->open($filePath);
                            if ($res === TRUE) {

                                $zip->extractTo($folderPath);
                                $zip->close();

                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }

                                /**
                                 * @var shopModel $shopModel
                                 */
                                $shopModel = $this->model;
                                $repository = $shopModel->getPaymentMethodRepository();

                                $pg = new stdClass();
                                $pg->name = $name[0];
                                $pg->status = 1;
                                $output = $repository->getPaymentMethod($pg, $this->module_srl);

                                if ($output) {

                                    $output = $repository->updatePaymentMethod($pg);

                                    if ($output) {

                                        $this->setMessage('An older installation of this payment method has been found. Reverting to old settings.','info');

                                    }

                                } else {

                                    $output = $repository->insertPaymentMethody($pg);

                                    if (!$output) {

                                        $this->setMessage('An error occurred when inserting the payment gateway in the Database.','error');

                                    }

                                }

                            } else {

                                $this->setMessage('The ZIP archive seems to be corrupt','error');

                            }

                        } else {

                            $this->setMessage('Unable to write in plugins_payment directory. Please set the appropriate permissions.','error');

                        }

                    } else {

                        $this->setMessage('Unable to create payment plugins directory at '.$folderPath,'error');

                    }

                }

            }

            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);

        }

        /**
         * Sanitizes the payment methods database
         */
        public function procSanitizePaymentMethods() {

            /**
             * @var shopModel $shopModel
             */
            $shopModel = $this->model;
            $repository = $shopModel->getPaymentMethodRepository();

            try {

                $repository->sanitizePaymentMethods($this->module_srl);
                $this->setMessage('Successfully sanitized payment methods database table','info');

            } catch (Exception $e) {

                $this->setMessage('Unable to sanitize payment methods table.','error');

            }

            $vid = Context::get('vid');
            $returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManagePaymentMethods');
            $this->setRedirectUrl($returnUrl);

        }

        /**
         * IPN Notification URL
         */
        public function procShopPaymentNotify()
        {
            $payment_method_name = Context::get('payment_method_name');
            $payment_repository = new PaymentMethodRepository();
            $payment_method = $payment_repository->getPaymentMethod($payment_method_name, $this->module_srl);
            $payment_method->notify();
        }

        // endregion

        /**
		 * Retrieves all module instances of a certain type
		 * Called through AJAX
		 *
		 * @author Corina Udrescu (dev@xpressengine.org)
		 * @return Object
		 */
		public function procShopServiceGetModulesByTypeAndSiteSrl()
		{
			$module_type = Context::get('module_type');
            $site_srl = Context::get('site_srl');
			if(!isset($module_type) || !isset($site_srl))
            {
                return new Object(-1, 'msg_invalid_request');
            }

			/**
			 * @var moduleModel $oModuleModel
			 */
			$oModuleModel = getModel('module');
			$args = new stdClass();
			$args->module = $module_type;
            $args->site_srl = $site_srl;
			$mid_list = $oModuleModel->getMidList($args, array("mid"));
            if(!$mid_list) $mid_list = array();

			$this->add('mid_list', $mid_list);
		}

        /**
         *
         */
        public function procShopToolSaveShopMenus()
        {
            $menus = Context::get('menus');
            $args = new stdClass();
            $args->module_srl = $this->module_srl;
            $args->menus = serialize($menus);
            $output = executeQuery('shop.updateShopInfo', $args);
            if(!$output->toBool())
            {
                return $output;
            }

            $this->setMessage('success_registed');
            $return_url = getNotEncodedUrl('', 'act', 'dispShopToolMenus', 'mid', $this->mid);
            $this->setRedirectUrl($return_url);
        }

		/**
		 * Delete menu item
		 */
		public function procShopToolDeletePage()
		{
            $page_module_srl = Context::get('module_srl');

			if(!$page_module_srl)
			{
				return new Object(-1, "msg_invalid_request");
			}

            // Delete module - this should also delete associated documents
            /**
             * @var moduleController $moduleController
             */
            $moduleController = &getController('module');
            $output = $moduleController->deleteModule($page_module_srl);
            if(!$output->toBool())
            {
                return $output;
            }

            $this->setMessage('success_deleted');
		}

        /**
         * Insert menu page
         *
         * @return object
         */
        function procShopToolInsertPage(){
            $page_mid = Context::get('page_mid');
            $page_title = Context::get('page_title');

            $args = Context::getRequestVars();

            $oModuleController = &getController('module');
            $oDocumentController = &getController('document');

            if(!$page_mid || !$page_title)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            // 1. Insert document
            $output = $oDocumentController->insertDocument($args);
            // 2. Insert page module
            $args->site_srl = $this->site_srl;
            $args->mid = $page_mid;
            $args->browser_title = $page_title;
            $args->module = 'page';
            $args->page_type = 'WIDGET';
            $args->content = '<img src="./common/tpl/images/widget_bg.jpg" class="zbxe_widget_output" widget="widgetContent" style="float: left; width: 100%;" body="" document_srl="'.$output->get('document_srl').'" widget_padding_left="0" widget_padding_right="0" widget_padding_top="0" widget_padding_bottom="0"  /> ';
            $output = $oModuleController->insertModule($args);
            if(!$output->toBool()) return $output;

            $this->setMessage('success_registed');
            $this->setRedirectUrl(getNotEncodedUrl('', 'vid', $this->mid, 'act', 'dispShopToolPages'));
        }

        /**
         * Update page menu entry
         *
         * @return Object
         */
        function procShopToolUpdatePage(){
            $args = Context::getRequestVars();
            $module_srl = Context::get('module_srl');
            $page_title = trim(Context::get('page_title'));

            if(!$module_srl || !$page_title)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            // Get current module info
            /**
             * @var moduleModel $oModuleModel
             */
            $oModuleModel = getModel('module');
            $page_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
            if(!$page_module_info)
            {
                ShopLogger::log("Error updating page module - getModuleInfoByDocumentSrl $document_srl returned null: ");
                return new Object(-1, 'fail_to_update');
            }

            // Get current document srl
            /**
             * @var shopModel $shopModel
             */
            $shopModel = getModel('shop');
            $document_srl = $shopModel->getPageDocumentSrl($page_module_info->content);

            // Update document info
            /**
             * @var documentModel $oDocumentModel
             */
            $oDocumentModel = getModel('document');
            $oDocument = $oDocumentModel->getDocument($document_srl);
            /**
             * @var documentController $oDocumentController
             */
            $oDocumentController = getController('document');
            $args->document_srl = $document_srl;
            $args->module_srl = $oDocument->module_srl;
            $args->category_srl = $oDocument->category_srl;
            $output = $oDocumentController->updateDocument($oDocument, $args);
            if(!$output->toBool())
            {
                ShopLogger::log("Error updating page document: " . print_r($args, true) . ' ' . print_r($output));
                return new Object(-1, 'fail_to_update');
            }

            // Update page title
            if($page_title != $page_module_info->browser_title)
            {
                /**
                 * @var moduleController $oModuleController
                 */
                $oModuleController = getController('module');
                $page_module_info->browser_title = $page_title;
                $output = $oModuleController->updateModule($page_module_info);
                if(!$output->toBool())
                {
                    ShopLogger::log("Error updating page module: " . print_r($page_module_info, true) . ' ' . print_r($output));
                    $this->setError($output->getError());
                    $this->setMessage($output->getMessage());
                    $error_return_url = Context::get('error_return_url');
                    $this->setRedirectUrl($error_return_url);
                    return;
                }
            }

            $this->setMessage('success_registed');
            $return_url = getNotEncodedUrl('', 'act', 'dispShopToolPages');
            $this->setRedirectUrl($return_url);
        }



        // endregion

        // region Shipping
        public function procShopToolShippingUpdate()
        {
            $name = Context::get('name');

            $shipping_repository = new ShippingMethodRepository();
            $shipping_method = $shipping_repository->getShippingMethod($name, $this->module_srl);

            // Update object with values submitted by user
            $data = Context::getRequestVars();
            $property_names = array_keys(get_object_vars($data));
            foreach($property_names as $property_name)
            {
                if(in_array($property_name, array('mid', 'vid','error_return_url', 'xe_form_id', 'act')))
                {
                    unset($data->$property_name);
                }
            }
            $shipping_method->setProperties($data);

            try
            {
                $shipping_repository->updateShippingMethod($shipping_method);
            }
            catch(Exception $e)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            $this->setMessage('success_updated');

            $vid = Context::get('vid');
            $mid = Context::get('mid');
            $this->setRedirectUrl(getNotEncodedUrl('', 'vid', $vid, 'mid', $mid, 'act', 'dispShopToolManageShippingMethods'));
        }

		private function updateShippingMethodStatus($status)
		{
			$name = Context::get('name');
			if(!isset($name))
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$shipping_repository = new ShippingMethodRepository();
			$shipping_method = $shipping_repository->getShippingMethod($name, $this->module_srl);
			$shipping_method->status = $status;

			try
			{
				$shipping_repository->updateShippingMethod($shipping_method);
			}
			catch(Exception $e)
			{
				$this->setError(-1);
				$this->setMessage('msg_invalid_request');
			}

			$this->setMessage('Shipping method successfully updated!');

			$vid = Context::get('vid');
			$mid = Context::get('mid');
			$this->setRedirectUrl(getNotEncodedUrl('', 'vid', $vid, 'mid', $mid, 'act', 'dispShopToolManageShippingMethods'));
		}


		public function procShopTollActivateShippingMethod()
		{
			$this->updateShippingMethodStatus(1);
		}

		public function procShopTollDeactivateShippingMethod()
		{
			$this->updateShippingMethodStatus(0);
		}

		public function procShopToolSetShippingMethodAsDefault()
		{
			$name = Context::get('name');
			if(!$name)
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$shipping_repository = new ShippingMethodRepository();
			try
			{
				$shipping_repository->setDefault($name, $this->module_srl);
				$this->setMessage('success_registed');
			}
			catch(ArgumentException $e)
			{
				$this->setError(-1);
				$this->setMessage($e->getMessage());
			}
			catch(DbQueryException $e)
			{
				$this->setError(-1);
				$this->setMessage('db_query_failed');
			}
			catch(Exception $e)
			{
				$this->setError(-1);
				$this->setMessage('fail_to_update');
			}

			$vid = Context::get('vid');
			$returnUrl = getNotEncodedUrl('', 'vid', $vid, 'act', 'dispShopToolManageShippingMethods');
			$this->setRedirectUrl($returnUrl);
		}

        public function procShopServiceActivateShippingMethod()
        {
            $name = Context::get('name');
            $status = Context::get('status');

            if(!isset($name) || !isset($status))
            {
                return new Object(-1, 'msg_invalid_request');
            }

            $shipping_repository = new ShippingMethodRepository();
            $shipping_method = $shipping_repository->getShippingMethod($name, $this->module_srl);
            $shipping_method->status = $status;

            try
            {
                $shipping_repository->updateShippingMethod($shipping_method);
            }
            catch(Exception $e)
            {
                return new Object(-1, 'msg_invalid_request');
            }

            $this->setMessage('Shipping method successfully updated!');
        }

        // endregion

		// moduleHandler.init after
		public function triggerDeleteOldLogs()
		{
			if(__DEBUG__)
			{
				FileHandler::writeFile(ShopLogger::LOG_FILE_PATH . '.bk', FileHandler::readFile(ShopLogger::LOG_FILE_PATH), 'a');
				FileHandler::writeFile(ShopLogger::XE_CORE_DEBUG_MESSAGE_PATH . '.bk', FileHandler::readFile(ShopLogger::XE_CORE_DEBUG_MESSAGE_PATH), 'a');
				FileHandler::writeFile(ShopLogger::XE_CORE_DEBUG_DB_QUERY_PATH . '.bk', FileHandler::readFile(ShopLogger::XE_CORE_DEBUG_DB_QUERY_PATH), 'a');
				FileHandler::removeFile(ShopLogger::LOG_FILE_PATH);
				FileHandler::removeFile(ShopLogger::XE_CORE_DEBUG_MESSAGE_PATH);
				FileHandler::removeFile(ShopLogger::XE_CORE_DEBUG_DB_QUERY_PATH);
			}
		}

		// display after
		public function triggerDisplayLogMessages()
		{
			if(__DEBUG__ && !in_array(Context::getResponseMethod(), array('XMLRPC')))
			{
				// Load XE Shop errors
				$shop_log_messages = FileHandler::readFile(ShopLogger::LOG_FILE_PATH);
				Context::set('shop_log_messages', $shop_log_messages);

				// Load XE Core query log
				$debug_messages = FileHandler::readFile(ShopLogger::XE_CORE_DEBUG_MESSAGE_PATH);
				Context::set('debug_messages', $debug_messages);

				// Load DB Query log
				$debug_db_query = FileHandler::readFile(ShopLogger::XE_CORE_DEBUG_DB_QUERY_PATH);
				Context::set('debug_db_query', $debug_db_query);

				$oTemplateHandler = TemplateHandler::getInstance();
				$view_logs = $oTemplateHandler->compile(_XE_PATH_ . '/modules/shop/tpl', 'log_viewer.html');
				print $view_logs;
			}
		}
    }
?>
