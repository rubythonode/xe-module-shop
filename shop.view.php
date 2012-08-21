<?php

/**
 * @class  shopView
 * @author Arnia (xe_dev@arnia.ro)
 * @brief  shop module View class
 **/

class shopView extends shop {

	/**
	 * @brief Initialization
	 **/
	public function init() {
		$oShopModel = getModel('shop');
		if(preg_match("/ShopTool/",$this->act) ) {
			$this->initTool($this);

		} else {
			$this->initService($this);
		}
	}

	/**
	 * @brief Shop common init
	 **/
	public function initCommon($is_other_module = FALSE){
		if(!$this->checkXECoreVersion('1.4.3')) return $this->stop(sprintf(Context::getLang('msg_requried_version'),'1.4.3'));

		$oShopModel = getModel('shop');
		$oShopController = getController('shop');
		$oModuleModel = getModel('module');

		$site_module_info = Context::get('site_module_info');
		if(!$this->module_srl) {
			$site_module_info = Context::get('site_module_info');
			$site_srl = $site_module_info->site_srl;
			if($site_srl) {
				$this->module_srl = $site_module_info->index_module_srl;
				$this->module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
				if (!$is_other_module){
					Context::set('module_info',$this->module_info);
					Context::set('mid',$this->module_info->mid);
					Context::set('current_module_info',$this->module_info);
				}
			}
		}

		if(!$this->module_info->skin) $this->module_info->skin = $this->skin;

		$preview_skin = Context::get('preview_skin');
		if($oModuleModel->isSiteAdmin(Context::get('logged_info'))&&$preview_skin) {
			if(is_dir($this->module_path.'skins/'.$preview_skin)) {
				$shop_config->skin = $this->module_info->skin = $preview_skin;
			}
		}

		if (!$is_other_module){
			Context::set('module_info',$this->module_info);
			Context::set('current_module_info', $this->module_info);
		}

		$this->shop = $oShopModel->getShop($this->module_info->module_srl);
		$this->site_srl = $this->shop->site_srl;
		Context::set('shop',$this->shop);

		if($this->shop->timezone) $GLOBALS['_time_zone'] = $this->shop->timezone;



	}

	/**
	 * @brief Shop init tool
	 **/
	public function initTool(&$oModule, $is_other_module = FALSE){
		if (!$oModule) $oModule = $this;

		$this->initCommon($is_other_module);

		$oShopModel = getModel('shop');

		$site_module_info = Context::get('site_module_info');
		$shop = $oShopModel->getShop($site_module_info->index_module_srl);

		$info = Context::getDBInfo();




		if ($is_other_module){
			$oModule->setLayoutPath($this->module_path.'tpl');
			$oModule->setLayoutFile('_tool_layout');
		}else{
			$template_path = sprintf("%stpl",$this->module_path);
			$this->setTemplatePath($template_path);
			$this->setTemplateFile(str_replace('dispShopTool','',$this->act));
		}

		if($_COOKIE['tclnb']) Context::addBodyClass('lnbClose');
		else Context::addBodyClass('lnbToggleOpen');

		// set browser title
		Context::setBrowserTitle($shop->get('browser_title') . ' - admin');
	}

	/**
	 * @brief shop init service
	 **/
	public function initService(&$oModule, $is_other_module = FALSE, $isMobile = FALSE){
		if (!$oModule) $oModule = $this;

        /** @var $oShopModel shopModel */
		$oShopModel = getModel('shop');

		$this->initCommon($is_other_module);

		Context::addJsFile($this->module_path.'tpl/js/shop_service.js');

		$preview_skin = Context::get('preview_skin');
		if(!$isMobile)
		{
			if($is_other_module){
				$path_method = 'setLayoutPath';
				$file_method = 'setLayoutFile';
				$css_path_method = 'getLayoutPath';
				Context::set('shop_mode', 'module');
			}else{
				$path_method = 'setTemplatePath';
				$file_method = 'setTemplateFile';
				$css_path_method = 'getTemplatePath';
			}

			if(!$preview_skin){
				$oShopModel->checkShopPath($this->module_srl, $this->module_info->skin);
				$oModule->{$path_method}($oShopModel->getShopPath($this->module_srl));
			}else{
				$oModule->{$path_method}($this->module_path.'skins/'.$preview_skin);
			}

			$oModule->{$file_method}('shop');
			Context::addCssFile($oModule->{$css_path_method}().'shop.css',TRUE,'all','',100);
		}

		Context::set('root_url', Context::getRequestUri());
		Context::set('home_url', getFullSiteUrl($this->shop->domain));
		Context::set('profile_url', getSiteUrl($this->shop->domain,'','mid',$this->module_info->mid,'act','dispShopProfile'));
		if(Context::get('is_logged')) Context::set('admin_url', getSiteUrl($this->shop->domain,'','mid',$this->module_info->mid,'act','dispShopToolDashboard'));
		else Context::set('admin_url', getSiteUrl($shop->domain,'','mid','shop','act','dispShopToolLogin'));
		Context::set('shop_title', $this->shop->get('shop_title'));


		// set browser title
		Context::setBrowserTitle($this->shop->get('browser_title'));


        $cartRepo = $oShopModel->getCartRepository();
        $productsInCart = $cartRepo->countCartProductsBySessionId(session_id());
        Context::set('productsInCart', $productsInCart);
	}


	/**
	 * @brief Tool dashboard
	 **/
	public function dispShopToolDashboard(){
		set_include_path(_XE_PATH_."libs/PEAR");
		require_once('PEAR.php');
		require_once('HTTP/Request.php');

		$oCounterModel = getModel('counter');
		$oDocumentModel = getModel('document');
		$oCommentModel = getModel('comment');
		$oShopModel = getModel('shop');

		$url = sprintf("http://news.shop.kr/%s/news.php", Context::getLangType());
		$cache_file = sprintf("%sfiles/cache/shop/news/%s%s.cache.xml", _XE_PATH_,getNumberingPath($this->module_srl),Context::getLangType());
		if(!file_exists($cache_file) || filemtime($cache_file)+ 60*60 < time()) {
			FileHandler::writeFile($cache_file,'');

			if(__PROXY_SERVER__!==NULL) {
				$oRequest = new HTTP_Request(__PROXY_SERVER__);
				$oRequest->setMethod('POST');
				$oRequest->_timeout = $timeout;
				$oRequest->addPostData('arg', serialize(array('Destination'=>$url)));
			} else {
				$oRequest = new HTTP_Request($url);
				if(!$content_type) $oRequest->addHeader('Content-Type', 'text/html');
				else $oRequest->addHeader('Content-Type', $content_type);
				if(count($headers)) {
					foreach($headers as $key => $val) {
						$oRequest->addHeader($key, $val);
					}
				}
				$oRequest->_timeout = 2;
			}
			if(isSiteID($this->shop->domain)) $oRequest->addHeader('REQUESTURL', Context::getRequestUri().$this->shop->domain);
			else $oRequest->addHeader('REQUESTURL', $this->shop->domain);
			$oResponse = $oRequest->sendRequest();
			$body = $oRequest->getResponseBody();
			FileHandler::writeFile($cache_file, $body);
		}

		if(file_exists($cache_file)) {
			$oXml = new XmlParser();
			$buff = $oXml->parse(FileHandler::readFile($cache_file));

			$item = $buff->news->item;
			if($item) {
				if(!is_array($item)) $item = array($item);

				foreach($item as $key => $val) {
					$obj = NULL;
					$obj->title = $val->body;
					$obj->date = $val->attrs->date;
					$obj->url = $val->attrs->url;
					$news[] = $obj;
				}
				Context::set('news', $news);
			}
		}

		$time = time();
		$w = date("D");
		while(date("D",$time) != "Sun") {
			$time += 60*60*24;
		}
		$time -= 60*60*24;
		while(date("D",$time)!="Sun") {
			$thisWeek[] = date("Ymd",$time);
			$time -= 60*60*24;
		}
		$thisWeek[] = date("Ymd",$time);
		asort($thisWeek);
		$thisWeekCounter = $oCounterModel->getStatus($thisWeek, $this->site_srl);

		$time -= 60*60*24;
		while(date("D",$time)!="Sun") {
			$lastWeek[] = date("Ymd",$time);
			$time -= 60*60*24;
		}
		$lastWeek[] = date("Ymd",$time);
		asort($lastWeek);
		$lastWeekCounter = $oCounterModel->getStatus($lastWeek, $this->site_srl);

		$max = 0;
		foreach($thisWeek as $day) {
			$v = (int)$thisWeekCounter[$day]->unique_visitor;
			if($v && $v>$max) $max = $v;
			$status->week[date("D",strtotime($day))]->this = $v;
		}
		foreach($lastWeek as $day) {
			$v = (int)$lastWeekCounter[$day]->unique_visitor;
			if($v && $v>$max) $max = $v;
			$status->week[date("D",strtotime($day))]->last = $v;
		}
		$status->week_max = $max;
		$idx = 0;
		foreach($status->week as $key => $val) {
			$_item[] = sprintf("<item id=\"%d\" name=\"%s\" />", $idx, $thisWeek[$idx]);
			$_thisWeek[] = $val->this;
			$_lastWeek[] = $val->last;
			$idx++;
		}

		$buff = '<?xml version="1.0" encoding="utf-8" ?><Graph><gdata title="Shop Counter" id="data2"><fact>'.implode('',$_item).'</fact><subFact>';
		$buff .= '<item id="0"><data name="'.Context::getLang('this_week').'">'.implode('|',$_thisWeek).'</data></item>';
		$buff .= '<item id="1"><data name="'.Context::getLang('last_week').'">'.implode('|',$_lastWeek).'</data></item>';
		$buff .= '</subFact></gdata></Graph>';
		Context::set('xml', $buff);

		$counter = $oCounterModel->getStatus(array(0,date("Ymd")),$this->site_srl);
		$status->total_visitor = $counter[0]->unique_visitor;
		$status->visitor = $counter[date("Ymd")]->unique_visitor;


		Context::set('status', $status);

		unset($args);
		$args->module_srl = $this->module_srl;
		$args->page = 1;
		$args->list_count = 5;
	}

	/**
	 * @brief Login
	 **/
	public function dispShopToolLogin() {
		Context::addBodyClass('logOn');
	}


	public function dispShopToolLayoutConfigSkin() {
		$oModuleModel = getModel('module');

		$skins = $oModuleModel->getSkins($this->module_path);
		if(count($skins)) {
			foreach($skins as $skin_name => $info) {
				$large_screenshot = $this->module_path.'skins/'.$skin_name.'/screenshots/large.jpg';
				if(!file_exists($large_screenshot)) $large_screenshot = $this->module_path.'tpl/img/@large.jpg';
				$small_screenshot = $this->module_path.'skins/'.$skin_name.'/screenshots/small.jpg';
				if(!file_exists($small_screenshot)) $small_screenshot = $this->module_path.'tpl/img/@small.jpg';

				unset($obj);
				$obj->title = $info->title;
				$obj->description = $info->description;
				$_arr_author = array();
				for($i=0,$c=count($info->author);$i<$c;$i++) {
					$name =  $info->author[$i]->name;
					$homepage = $info->author[$i]->homepage;
					if($homepage) $_arr_author[] = '<a href="'.$homepage.'" onclick="window.open(this.href); return false;">'.$name.'</a>';
					else $_arr_author[] = $name;
				}
				$obj->author = implode(',',$_arr_author);
				$obj->large_screenshot = $large_screenshot;
				$obj->small_screenshot = $small_screenshot;
				$obj->date = $info->date;
				$output[$skin_name] = $obj;
			}
		}
		Context::set('skins', $output);
		Context::set('cur_skin', $output[$this->module_info->skin]);
	}

	public function dispShopToolLayoutConfigEdit() {
		$oShopModel = getModel('shop');
		$skin_path = $oShopModel->getShopPath($this->module_srl);

		$skin_file_list = $oShopModel->getShopUserSkinFileList($this->module_srl);
		$skin_file_content = array();
		foreach($skin_file_list as $file){
			if(preg_match('/^shop/',$file)){
				$skin_file_content[$file] = FileHandler::readFile($skin_path . $file);
			}
		}
		foreach($skin_file_list as $file){
			if(!in_array($file,$skin_file_content)){
				$skin_file_content[$file] = FileHandler::readFile($skin_path . $file);
			}
		}

		Context::set('skin_file_content',$skin_file_content);

		$user_image_path = sprintf("%suser_images/", $oShopModel->getShopPath($this->module_srl));
		$user_image_list = FileHandler::readDir($user_image_path);
		Context::set('user_image_path',$user_image_path);
		Context::set('user_image_list',$user_image_list);
	}

	public function dispShopToolManageAttributes()
	{
		$shopModel = getModel('shop');
		$repository = $shopModel->getAttributeRepository();
		$output = $repository->getAttributesList($this->module_info->module_srl);

		Context::set('attributes_list', $output->attributes);
		Context::set('page_navigation', $output->page_navigation);
	}

	/**
	 * @brief attribute add page
	 */
	public function dispShopToolAddAttribute()
	{
		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');
		$attributeRepository = $shopModel->getAttributeRepository();
		Context::set('types', $attributeRepository->getTypes(Context::get('lang')));

		// Retrieve existing categories
		$categoryRepository = $shopModel->getCategoryRepository();
		$tree = $categoryRepository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->showCheckbox = TRUE;
		$tree_config->selected = array();
		$tree_config->checkboxesName = 'category_scope';
		$HTML_tree = $tree->toHTML($tree_config);

		Context::set('HTML_tree', $HTML_tree);
	}

	public function dispShopToolEditAttribute()
	{
		/**
		 * @var shopModel #shopModel
		 */
		$shopModel = getModel('shop');
		$attributeRepository = $shopModel->getAttributeRepository();
		$srl = Context::get('attribute_srl');
		if (!$attribute = $attributeRepository->getAttributes(array($srl))) throw new Exception("Attribute doesn't exist");
		Context::set('attribute', $attribute);
		Context::set('types', $attributeRepository->getTypes(Context::get('lang')));

		// Retrieve existing categories
		$categoryRepository = $shopModel->getCategoryRepository();
		$tree = $categoryRepository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->showCheckbox = TRUE;
		$tree_config->selected = $attribute->category_scope;
		$tree_config->checkboxesName = 'category_scope';
		$HTML_tree = $tree->toHTML($tree_config);

		Context::set('HTML_tree', $HTML_tree);

		$this->setTemplateFile('AddAttribute');
	}

	/**
	 * @brief Shop display product tool page
	 */
	public function dispShopToolManageProducts(){
		$shopModel = getModel('shop');

		$product_repository = $shopModel->getProductRepository();
		$module_srl = $this->module_info->module_srl;

		$args = new stdClass();
		$args->module_srl = $module_srl;

		$page = Context::get('page');
		if($page) $args->page = $page;

		$output = $product_repository->getProductList($args);
		Context::set('product_list',$output->products);
		Context::set('page_navigation',$output->page_navigation);

		$category_repository = $shopModel->getCategoryRepository();
		$tree = $category_repository->getCategoriesTree($module_srl);
		$flat_tree = $tree->toFlatStructure();
		Context::set('category_list', $flat_tree);
	}

	/**
	 * @brief Shop display product tool page
	 */
	public function dispShopToolEditProduct(){
		$this->dispShopToolAddProduct();
		$this->setTemplateFile('AddProduct');
	}

	/**
	 * @brief Shop display simple product add page
	 */
	public function dispShopToolAddProduct(){
		$args = Context::getRequestVars();
		if(isset($args->configurable_attributes)) Context::set('configurable_attributes',$args->configurable_attributes);

		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');
		$productRepository = $shopModel->getProductRepository();

		// Retrieve product if exists
		$product_srl = Context::get('product_srl');
		if($product_srl)
		{
			$product = $productRepository->getProduct($product_srl);
			if($product->parent_product_srl) {
				$parent_product = $productRepository->getProduct($product->parent_product_srl);
				Context::set('parent_product',$parent_product);
			}

			// Display associated products for Configurable products
			if($product->isConfigurable())
			{
				Context::set('product_list',$product->associated_products);
			}
		}
		else
		{
			if($args->configurable_attributes)
			{
				$product = new ConfigurableProduct();
			}
			else
			{
				$product = new SimpleProduct();
			}
		}
		Context::set('product',$product);

		// Retrieve all attributes
		$attributeRepository = $shopModel->getAttributeRepository();
		$output = $attributeRepository->getAttributesList($this->module_info->module_srl);
		foreach($output->attributes as $attribute)
		{
			$attributeRepository->getAttributeScope($attribute);
		}
		Context::set('attributes_list', $output->attributes);

		// Retrieve existing categories
		$categoryRepository = $shopModel->getCategoryRepository();
		$tree = $categoryRepository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->showCheckbox = TRUE;
		$tree_config->selected = $product->categories;
		$tree_config->checkboxesName = 'categories';
		$HTML_tree = $tree->toHTML($tree_config);

		Context::set('HTML_tree', $HTML_tree);
	}

	/**
	 * @brief Shop display configurable product add page
	 */
	public function dispShopToolAddConfigurableProduct(){
		$shopModel = getModel('shop');
		$attributeRepository = $shopModel->getAttributeRepository();
		$output = $attributeRepository->getConfigurableAttributesList($this->module_info->module_srl);
		Context::set('attributes',$output->attributes);
	}


	/**
	 * @brief Shop display associated products
	 */
	public function dispShopToolAddAssociatedProducts(){
		$shopModel = getModel('shop');
		$product_srl = Context::get('product_srl');
		$productRepository = $shopModel->getProductRepository();
		$product = $productRepository->getProduct($product_srl);
		Context::set('product',$product);
		$attributeRepository = $shopModel->getAttributeRepository();
		$configurable_attributes = $attributeRepository->getAttributes(array_keys($product->configurable_attributes));
		if(count($product->configurable_attributes) == 1){
			$values_combinations = explode('|',$configurable_attributes->values);
		}else{
			foreach($configurable_attributes as $conf_att){
				$configurable_values[] = $conf_att->values;
			}
			$values_combinations = $attributeRepository->getValuesCombinations($configurable_values);
		}
		Context::set('values_combinations',$values_combinations);

	}

	/**
	 * @brief Shop home
	 **/
	public function dispShop() {
		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');

		// Categories left tree
		// Retrieve existing categories
		$category_srl = Context::get('category_srl');
		$category_repository = $shopModel->getCategoryRepository();
		$tree = $category_repository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->linkCategoryName = TRUE;
		$tree_config->linkGetUrlParams = array('vid', $this->mid, 'act', 'dispShop');
		if($category_srl) $tree_config->selected = array($category_srl);
		$HTML_tree = $tree->toHTML($tree_config);
		Context::set('HTML_tree', $HTML_tree);

		// Current category details
		if($category_srl)
		{
			$current_category = $category_repository->getCategory($category_srl);
			Context::set('current_category', $current_category);

			$breadcrumbs_items = $category_repository->getCategoryParents($current_category);
			Context::set('breadcrumbs_items', $breadcrumbs_items);
		}


		// Products list
		$product_repository = $shopModel->getProductRepository();
		try{
			$args = new stdClass();
			$args->module_srl = $this->module_srl;
			$page = Context::get('page');
			if($page) $args->page = $page;
			$category_srl = Context::get('category_srl');
			if($category_srl) $args->category_srls = array($category_srl);

			$output = $product_repository->getProductList($args, TRUE);
			Context::set('products', $output->products);
			Context::set('page_navigation', $output->page_navigation);

			$datasourceJS = $this->getAssociatedProductsAttributesAsJavascriptArray($output->products);
			Context::set('datasourceJS', $datasourceJS);
		}
		catch(Exception $e)
		{
			return new Object(-1, $e->getMessage());
		}
	}

	/**
	 * Frontend shop product page
	 */
	public function dispShopProduct()
	{
		$product_srl = Context::get('product_srl');

		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');
		$product_repository = $shopModel->getProductRepository();

		$product = $product_repository->getProduct($product_srl);
		Context::set('product', $product);

		// Setup Javscript datasource for linked dropdowns
		$datasourceJS = $this->getAssociatedProductsAttributesAsJavascriptArray(array($product));
		Context::set('datasourceJS', $datasourceJS);

		// Setup attributes names for display
		if(count($product->attributes))
		{
			$attribute_repository = $shopModel->getAttributeRepository();
			$attributes = $attribute_repository->getAttributes(array_keys($product->attributes));
			Context::set('attributes', $attributes);
		}

		// Categories left tree
		// Retrieve existing categories
		$category_repository = $shopModel->getCategoryRepository();
		$tree = $category_repository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->linkCategoryName = TRUE;
		$tree_config->linkGetUrlParams = array('vid', $this->mid, 'act', 'dispShop');
		$tree_config->selected = $product->categories;
		$HTML_tree = $tree->toHTML($tree_config);
		Context::set('HTML_tree', $HTML_tree);

		// Current category details
		$category_srl = Context::get('category_srl');
		if($category_srl)
		{
			$current_category = $category_repository->getCategory($category_srl);
			Context::set('current_category', $current_category);

			$breadcrumbs_items = $category_repository->getCategoryParents($current_category);
			Context::set('breadcrumbs_items', $breadcrumbs_items);
		}



		$this->setTemplateFile('product.html');
	}

	/**
	 * Returns the javascript code used as datasource for linked dropdowns
	 */
	private function getAssociatedProductsAttributesAsJavascriptArray($products, $reverse = NULL)
	{
		if(is_null($reverse))
		{
			return $this->getAssociatedProductsAttributesAsJavascriptArray($products, FALSE) . PHP_EOL .
						$this->getAssociatedProductsAttributesAsJavascriptArray($products, TRUE);
		}

		$datasource_name = 'associated_products';
		if($reverse) $datasource_name = 'reverse_' . $datasource_name;

		 $datasource = "var $datasource_name = new Object();" . PHP_EOL;

		 foreach($products as $product)
		 {
			 if($product->isSimple()) continue;

			 $datasource .= $datasource_name . "[$product->product_srl] = new Object();" . PHP_EOL;

			 $already_added = array();
			 foreach($product->associated_products as $asoc_product)
			 {
				 $attribute_values = array_values($asoc_product->attributes);

				 // Take just first two attributes
				 if(!$reverse)
				 {
					 $attribute1 = $attribute_values[0];
					 $attribute2 = $attribute_values[1];
				 }
				 else
				 {
					 $attribute2 = $attribute_values[0];
					 $attribute1 = $attribute_values[1];
				 }


				 if($attribute2)
				 {
					 if(!$already_added[$attribute1])
					 {
						 $datasource .= $datasource_name . "[$product->product_srl]['$attribute1'] = new Object();" . PHP_EOL;
						 $already_added[$attribute1] = TRUE;
					 }

					 $datasource .= $datasource_name . "[$product->product_srl]['$attribute1']['$attribute2'] = $asoc_product->product_srl;" . PHP_EOL;
				 }
				 else
				 {
					 $datasource .= $datasource_name . "[$product->product_srl]['$attribute1'] = $asoc_product->product_srl;" . PHP_EOL;
				 }
			 }
		 }
		return $datasource;
	}

	// region Product category
	/**
	 * Category management view (Admin)
	 */
	public function dispShopToolManageCategories()
	{
		// Retrieve existing categories
		$shopModel = getModel('shop');
		$repository = $shopModel->getCategoryRepository();
		$tree = $repository->getCategoriesTree($this->module_srl);

		// Prepare tree for display
		$tree_config = new HtmlCategoryTreeConfig();
		$tree_config->showManagingLinks = TRUE;
		$HTML_tree = $tree->toHTML($tree_config);

		Context::set('HTML_tree', $HTML_tree);

		// Initialize new empty Category object
		require_once('libs/model/Category.php');
		$category = new Category();
		$category->module_srl = $this->module_srl;
		Context::set('category', $category);
	}
	// endregion

	// startregion Payment Gateways

	/*
			 * Displays the PG management page
			 */
	public function dispShopToolManagePaymentGateways()
	{

		// get gateways
		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');
		$repository = $shopModel->getPaymentGatewayRepository();
		$gatewayManager = $shopModel->getPaymentGatewayManager();
		$gatewayManager->allGateways = $gatewayManager->getAllGateways();

		Context::set('gatewayManager',$gatewayManager);

	}

	/*
			 * Displays the payment method selection page
			 */
	public function dispShopToolSelectPaymentMethod() {

		$shopModel = getModel('shop');
		$repository = $shopModel->getPaymentGatewayRepository();
		//$repository->includeActiveGateways();

	}

	// endregion

	// region Extra menu
	/**
	 * Displays all extra menu elements
	 * @return object
	 */
	function dispShopToolExtraMenuList(){
		/**
		 * @var shopModel $shopModel
		 */
		$shopModel = getModel('shop');
		$shop_menu_srl = $shopModel->getShopMenuSrl($this->site_srl);

		/**
		 * @var menuAdminModel $menuModel
		 */
		$menuModel = getAdminModel('menu');
		$menu_items = $menuModel->getMenuItems($shop_menu_srl);

		Context::set('menu_list',$menu_items->data);

	}

	/**
	 * Edit menu item
	 *
	 * @return Object
	 */
	function dispShopToolExtraMenuEdit(){
		$menu_item_srl = Context::get('menu_item_srl');
		if(!$menu_item_srl)
			return new Object(-1, 'msg_invalid_request');

		/**
		 * @var menuAdminModel $menuModel
		 */
		$menuModel = getAdminModel('menu');
		$menu_item = $menuModel->getMenuItemInfo($menu_item_srl);
		Context::set('menu_item', $menu_item);
	}

	/**
	 * Add existing module to the the custom menu
	 *
	 * @return Object
	 */
	function dispShopToolExtraMenuModuleInsert(){
		$oModuleModel = getModel('module');

		// Retrieve just modules of type 'service' out of all installed modules
		$installed_module_list = $oModuleModel->getModulesXmlInfo();
		foreach($installed_module_list as $key => $val) {
			if($val->category != 'service') continue;
			if(!$val->default_index_act) continue;
			$service_modules[] = $val;
		}
		Context::set('service_modules', $service_modules);
	}

	/**
	 * Add new module (page) to custom menu
	 *
	 * @return Object
	 */
	function dispShopToolExtraMenuInsert(){
		// set filter
		$menu_mid = Context::get('menu_mid');
		if($menu_mid){
			$oModuleModel = &getModel('module');
			$module_info = $oModuleModel->getModuleInfoByMid($menu_mid,$this->site_srl);
			if(!$module_info) return new Object(-1,'msg_invalid_request');

			$oWidgetController = &getController('widget');
			$buff = trim($module_info->content);
			$oXmlParser = new XmlParser();
			$xml_doc = $oXmlParser->parse(trim($buff));
			$document_srl = $xml_doc->img->attrs->document_srl;
			$args->module_srl = $module_info->module_srl;
			$output = executeQuery('textyle.getExtraMenu',$args);
			if($output->data){
				$selected_extra_menu = $output->data;
			}
		}
		if($selected_extra_menu){
			Context::set('selected_extra_menu',$selected_extra_menu);
			Context::addJsFilter($this->module_path.'tpl/filter', 'modify_extra_menu.xml');
		}else{
			Context::addJsFilter($this->module_path.'tpl/filter', 'insert_extra_menu.xml');
		}


		$oDocumentModel = &getModel('document');
		$material_srl = Context::get('material_srl');

		if($document_srl){
			$oDocument = $oDocumentModel->getDocument($document_srl,FALSE,FALSE);
		}else{
			$document_srl=0;
			$oDocument = $oDocumentModel->getDocument(0);
			if($material_srl){
				$oMaterialModel = &getModel('material');
				$output = $oMaterialModel->getMaterial($material_srl);
				if($output->data){
					$material_content = $output->data[0]->content;
					Context::set('material_content',$material_content);
				}
			}

		}

//		$oEditorModel = &getModel('editor');
//		$option->skin = $this->textyle->getPostEditorSkin();
//		$option->primary_key_name = 'document_srl';
//		$option->content_key_name = 'content';
//		$option->allow_fileupload = TRUE;
//		$option->enable_autosave = TRUE;
//		$option->enable_default_component = TRUE;
//		$option->enable_component = $option->skin =='dreditor' ? FALSE : TRUE;
//		$option->resizable = TRUE;
//		$option->height = 500;
//		$option->content_font = $this->textyle->getFontFamily();
//		$option->content_font_size = $this->textyle->getFontSize();
//		$editor = $oEditorModel->getEditor($document_srl, $option);
//		Context::set('editor', $editor);
//		Context::set('editor_skin', $option->skin);

		if($oDocument->get('module_srl') != $this->module_srl && !$document_srl){
			Context::set('from_saved',TRUE);
		}

		Context::set('oDocument', $oDocument);
	}

	// endregion


}
?>
