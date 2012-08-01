<?php
/**
 * @class  shopModel
 * @author Arnia (xe_dev@arnia.ro)
 * @brief  shop module Model class
 */
class shopModel extends shop
{

	/**
	 * Initialization
	 * @author Arnia (dev@xpressengine.org)
	 */
	public function init()
	{
	}


	/**
	 * Get member shop
	 * @author Arnia (dev@xpressengine.org)
	 */
	public function getMemberShop($member_srl = 0)
	{
		if(!$member_srl && !Context::get('is_logged'))
		{
			return new ShopInfo();
		}

		if(!$member_srl)
		{
			$logged_info = Context::get('logged_info');
			$args->member_srl = $logged_info->member_srl;
		}
		else
		{
			$args->member_srl = $member_srl;
		}

		$output = executeQueryArray('shop.getMemberShop', $args);
		if(!$output->toBool() || !$output->data)
		{
			return new ShopInfo();
		}

		$shop = $output->data[0];

		$oShop = new ShopInfo();
		$oShop->setAttribute($shop);

		return $oShop;
	}

	/**
	 * Shop return list
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $args array
	 */
	public function getShopList($args)
	{
		$output = executeQueryArray('shop.getShopList', $args);
		if(!$output->toBool())
		{
			return $output;
		}

		if(count($output->data))
		{
			foreach($output->data as $key => $val)
			{
				$oShop = NULL;
				$oShop = new ShopInfo();
				$oShop->setAttribute($val);
				$output->data[$key] = NULL;
				$output->data[$key] = $oShop;
			}
		}
		return $output;
	}

	/**
	 * Shop return
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $module_srl int
	 * @return ShopInfo
	 */
	public function getShop($module_srl = 0)
	{
		static $shops = array();
		if(!isset($shops[$module_srl]))
		{
			$shops[$module_srl] = new ShopInfo($module_srl);
		}
		return $shops[$module_srl];
	}

	/**
	 * Return shop count
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $member_srl int
	 * @return int
	 */
	public function getShopCount($member_srl = NULL)
	{
		if(!$member_srl)
		{
			$logged_info = Context::get('logged_info');
			$member_srl = $logged_info->member_srl;
		}
		if(!$member_srl)
		{
			return NULL;
		}

		$args->member_srl = $member_srl;
		$output = executeQuery('shop.getShopCount', $args);

		return $output->data->count;
	}

	/**
	 * Get shop path
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $module_srl int
	 * @return string
	 */
	public function getShopPath($module_srl)
	{
		return sprintf("./files/attach/shop/%s", getNumberingPath($module_srl));
	}

	/**
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $module_srl int
	 * @param $skin
	 * @return bool
	 */
	function checkShopPath($module_srl, $skin = NULL)
	{
		$path = $this->getShopPath($module_srl);
		if(!file_exists($path))
		{
			$oShopController = getController('shop');
			$oShopController->resetSkin($module_srl, $skin);
		}
		return TRUE;
	}

	/**
	 * Get shop user skin file list
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $module_srl
	 * @return string[]
	 */
	public function getShopUserSkinFileList($module_srl)
	{
		$skin_path = $this->getShopPath($module_srl);
		$skin_file_list = FileHandler::readDir($skin_path, '/(\.html|\.htm|\.css)$/');
		return $skin_file_list;
	}

	/**
	 * Get module part config
	 *
	 * @author Arnia (dev@xpressengine.org)
	 * @param $module_srl int
	 * @return mixed
	 */
	public function getModulePartConfig($module_srl = 0)
	{
		static $configs = array();

		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('shop');
		if(!$config || !$config->allow_service)
		{
			$config->allow_service = array('board' => 1, 'page' => 1);
		}

		if($module_srl)
		{
			$part_config = $oModuleModel->getModulePartConfig('shop', $module_srl);
			if(!$part_config)
			{
				$part_config = $config;
			}
			else
			{
				$vars = get_object_vars($part_config);
				if($vars)
				{
					foreach($vars as $k => $v)
					{
						$config->{$k} = $v;
					}
				}
			}
		}

		$configs[$module_srl] = $config;

		return $configs[$module_srl];
	}

    public function getAttributesModel() {
        static $objects = array();

        require_once $this->module_path . 'libs/model/Attributes.class.php';

        if (!isset($objects[$document_srl])) $objects[$document_srl] = new publishObject($module_srl, $document_srl);

        return $objects[$document_srl];
    }
	
    /**
     * Insert a new Product; returns the ID of the newly created record
     *
     * @author Dan Dragan (dev@xpressengine.org)
     * @param $args array
     * @return int
     */
    public function insertProduct($args)
    {
        $args->product_srl = getNextSequence();
        $output = executeQuery('shop.insertProduct', $args);
        if(!$output->toBool())
        {
            throw new Exception($output->getMessage(), $output->getError());
        }
        return $args->product_srl;
    }

	/**
	 * Returns an instance of the Product Category repository
	 *
	 * @author Corina Udrescu (dev@xpressengine.org)
	 */
	function getProductCategoryRepository()
	{
		require_once dirname(__FILE__) . '/libs/repositories/ProductCategoryRepository.php';
		return new ProductCategoryRepository();
	}

}

?>
