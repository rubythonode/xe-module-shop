<?php
/**
 * @author Florin Ercus (dev@xpressengine.org)
 */
class Cart extends BaseItem implements IProductItemsContainer
{

    public
        $cart_srl,
        $module_srl,
        $member_srl,
        $session_id,
        $billing_address_srl,
        $shipping_address_srl,
        $items = 0,
        $extra,
        $regdate,
        $last_update;

    protected $addresses;

    /** @var CartRepository */
    public $repo;

    public function save()
    {
        return $this->cart_srl ? $this->repo->updateCart($this) : $this->repo->insertCart($this);
    }

    public function delete()
    {
        if (!$this->cart_srl) throw new ShopException('Cart is not persisted, can\'t delete.');
        $this->query('deleteCarts', array('cart_srls' => array($this->cart_srl)));
        $this->query('deleteCartProducts', array('cart_srl'=> $this->cart_srl));
    }

    public function merge(Cart $cart, $withDelete=true)
    {
        if (!$cart->cart_srl || !$this->cart_srl) throw new ShopException('Missing srl(s) for carts merge');
        if ($cart->cart_srl == $this->cart_srl) {
            throw new ShopException('Cannot merge with same cart');
        }
        $this->copyProductLinksFrom($cart);
        if ($withDelete) $cart->delete();
    }

    public function copyProductLinksFrom(Cart $cart)
    {
        if (!$cart->cart_srl || !$this->cart_srl) throw new ShopException('Missing srl(s) for cart products copy');
        if ($cart->cart_srl == $this->cart_srl) throw new ShopException('Cannot copy from same cart');
        $myCps = $this->repo->getCartProducts($this->cart_srl)->data;
        $cps = $this->repo->getCartProducts($cart->cart_srl)->data;
        foreach ($cps as $cp) {
            $have = false; //presume I don't have it
            foreach ($myCps as $cp2) {
                if ($cp->product_srl == $cp2->product_srl) { //if I have it
                    $have = true;
                    break;
                }
            }
            if ($have) { //if I have it update my quantity
                $this->repo->updateCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity + $cp2->quantity);
            }
            else { //else add it
                $this->repo->insertCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity, array('title'=>$cp->title, 'price'=>$cp->price));
            }
        }
        //count again
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true, true);
        $this->save();
    }

    #region cart & stuff
    /**
     * @param      $product Product or product_srl
     * @param int  $quantity
     * @param bool $relativeQuantity
     *
     * @return mixed
     * @throws Exception
     */
    public function addProduct($product, $quantity=1, $relativeQuantity=true)
    {
        if (!$this->isPersisted()) throw new ShopException('Cart is not persisted');

        if ($product instanceof SimpleProduct) {
            if (!$product_srl = $product->product_srl) {
                throw new ShopException('Product is not persisted');
            }
        }
        elseif (is_numeric($product)) {
            $product = new SimpleProduct($product);
        }
        else {
            throw new ShopException('Wrong $product input for addProduct to cart');
        }

        if (!$this->productStillAvailable($product)) {
            throw new ShopException('Product is no longer available, cannot add it to cart.');
        }

        if ($cp = $this->getCartProduct($product)) {
            $cartProductQuantity = ($relativeQuantity ? $cp->quantity + $quantity : $quantity);
            $return = $this->setProductQuantity($product->product_srl, $cartProductQuantity, array('title' => $product->title));
        }
        else {
            $cartProductQuantity = $quantity;
            $return = $this->repo->insertCartProduct($this->cart_srl, $product->product_srl, $cartProductQuantity, array('title' => $product->title, 'price'=>$product->getPrice()));
        }
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true, true);

        $this->save();

        return $return;
    }

    public function productStillAvailable($product, $checkIfInStock=true)
    {
        if ($product instanceof SimpleProduct) {
            if (!$product->isPersisted()) {
                throw new ShopException('Product not persisted');
            }
        }
        elseif (is_numeric($product)) {
            $pRepo = new ProductRepository();
            $product = $pRepo->getProduct($product);
        }
        else throw new ShopException('Invalid input');
        return $product && $product->isAvailable($checkIfInStock);
    }

    public function hasProduct($product)
    {
        return $this->getCartProduct($product) ? true : false;
    }

    /**
     * @param $product
     *
     * @return null|CartProduct
     */
    public function getCartProduct($product)
    {
        return $this->repo->getCartProduct($this->cart_srl, $product);
    }

	// TODO See what's the deal with this - could be replaced with getTotal i think
    public function getPrice($refresh=false, $onlyAvailables=false)
    {
        if (!$refresh && is_numeric($price = $this->getExtra('price'))) return $price;
        //calculate new price
        $price = 0;
        /** @var $product SimpleProduct */
        foreach ($this->getProducts(null, $onlyAvailables) as $product) {
            $price += $product->price * $product->quantity;
        }
        return $price;
    }

    /**
     * @param bool $sumQuantities take quantities into account or only count unique items?
     *
     * @return mixed number of items in cart
     */
    public function count($sumQuantities=false, $onlyAvailables=false)
    {
        if ($onlyAvailables) {
            $products = $this->getProducts(null, $onlyAvailables);
            if (!$sumQuantities) return count($products);
            else {
                $count = 0;
                foreach ($products as $product) {
                    $count += $product->quantity;
                }
                return $count;
            }
        }
        return $this->repo->countCartProducts($this, $sumQuantities);
    }

    public function countAvailableProducts()
    {
        return count($this->getProducts(null, true));
    }

    public function setProductQuantity($product_srl, $quantity, array $extraParams=array())
    {
        if (!$this->cart_srl) throw new ShopException('Cart is not persisted');
        return $this->repo->updateCartProduct($this->cart_srl, $product_srl, $quantity, $extraParams);
    }

    /**
     * @param null $n number of products to return
     * @param false $onlyAvailables wether or not to return only available products
     *
     * @return mixed Cart products
     * @throws Exception
     */
    public function getProducts($n=null, $onlyAvailables=false, $ignoreCache=false)
    {
        if (!$this->cart_srl) throw new ShopException('Cart is not persisted');
        //an entity-unique cache key for the current method and parameters combination
        $cacheKey = 'getProducts|' . ($n?$n:"_").(string)($onlyAvailables?'av':'all');
        if ($ignoreCache || !$products = $this->cache[$cacheKey]) {
            $products = array();
            $shopInfo = new ShopInfo($this->module_srl);
            $checkIfInStock = ($shopInfo->getOutOfStockProducts() == 'Y');
            $params = array('cart_srl'=>$this->cart_srl);
            if ($n) $params['list_count'] = $n;
            $output = $this->query('getCartAllProducts', $params, true);
            $stds = $output->data;
            foreach ($stds as $i=>$data) {
                $cartProduct = new CartProduct($data);
                $simpleProduct = $cartProduct->getProduct();
                if ($simpleProduct->isPersisted()) {
                    if(!$simpleProduct->isAvailable($checkIfInStock) && $onlyAvailables) continue;
                }
                else {
                    if ($onlyAvailables || !$cartProduct->cart_product_srl) continue;
                }
                $products[$i] = $cartProduct;
            }
            $this->cache[$cacheKey] = $products;
        }
        //if limit did not work:
        if ($n && $n < count($products)) {
            return array_slice($products, 0, $n);
        }
        return $products;
    }

    public function emptyCart()
    {
        $this->repo->deleteCartProducts($this->cart_srl);
    }

    public function getProductsList(array $args=array())
    {
        if (!$this->cart_srl) throw new ShopException('Cart is not persisted');

        $output = $this->query('getCartProductsList', array_merge(array('cart_srl'=>$this->cart_srl), $args), true);
        foreach ($output->data as $i=>&$data) {
            //if ($data->product_srl is missing) then product was deleted by shop admin
            if ($data->cart_product_srl) {
                $product = new CartProduct($data);
                $data = $product;
            }
            else unset($output->data[$i]);
        }
        return $output;
    }

    public function check()
    {
        $shopInfo = new ShopInfo($this->module_srl);
        if ($minOrder = $shopInfo->getMinimumOrder()) {
            // TO DO getPrice() doesn't work without true
            if ($this->getPrice(true) < $minOrder) {
                throw new ShopException("Minimum order amount of $minOrder not met");
            }
        }
        return true;
    }

    private $discount; //discount short cache

    /**
     * @param null $forceDiscountType Forces a specific discount type
     *
     * @return Discount|null
     * @throws Exception
     */
    public function getDiscount()
    {
        if ($this->discount) return $this->discount;
        require_once __DIR__ . '/../classes/Discount.php';
        $shop = new ShopInfo($this->module_srl);
        $cartValue = $this->getTotalBeforeDiscount(true);
        $discountAmount = $shop->getShopDiscountAmount();
        $discountBeforeVAT = ($shop->getShopDiscountTaxPhase() == 'pre_taxes' ? true : false);
        $discountMinAmount = $shop->getShopDiscountMinAmount();
        $discountType = $shop->getShopDiscountType();
        $vat = $shop->getVAT();
        $currency = $shop->getCurrencySymbol();
        if ($discountAmount && $discountType && $discountMinAmount <= $cartValue) {
            if ($discountType == Discount::DISCOUNT_TYPE_FIXED_AMOUNT) {
                $discount = new FixedAmountDiscount($cartValue, $discountAmount, $discountMinAmount, $vat, $discountBeforeVAT, $currency);
            }
            elseif ($discountType == Discount::DISCOUNT_TYPE_PERCENTAGE) {
                $discount = new PercentageDiscount($cartValue, $discountAmount, $discountMinAmount, $vat, $discountBeforeVAT, $currency);
            }
            //elseif... add new discount types here after you create the classes
            else {
                throw new ShopException("Unknown discount type $discountType");
            }
            return $this->discount = $discount;
        }
        return null;
    }

    public function getItemTotal()
    {
        $output = $this->getProducts(null, true);
        $total = 0;
        /** @var $product CartProduct */
        foreach ($output as $product) {
            $price = ($product->price ? $product->price : $product->cart_product_price);
            $total += $price * $product->quantity;
        }
        return $total;
    }

	public function getTotalBeforeDiscountWithVAT()
	{
		return $this->getTotalBeforeDiscount();
	}

	public function getTotalBeforeDiscountWithoutVAT()
	{
		$shop = new ShopInfo($this->module_srl);
		return $this->getTotalBeforeDiscountWithVAT() / (1 + $shop->getVAT() / 100);
	}

	public function getTotalAfterDiscount()
	{
		return $this->getTotalAfterDiscountWithVAT();
	}

	public function getTotalAfterDiscountWithVAT()
	{
		return $this->getTotalAfterDiscountWithoutVAT()
			+ $this->getVATAfterDiscount();
	}

	public function getTotalAfterDiscountWithoutVAT()
	{
		$shop = new ShopInfo($this->module_srl);
		$discount = $this->getDiscount();
		if($discount && ($shop->getShopDiscountType() == Discount::DISCOUNT_TYPE_FIXED_AMOUNT
			|| $shop->getShopDiscountTaxPhase() == Discount::PHASE_AFTER_VAT))
		{
			$total = $this->getTotalBeforeDiscountWithVAT();
			$total -= $discount->getReductionValue();
			return $total / (1 + $shop->getVAT() / 100);
		}
		$total = $this->getTotalBeforeDiscountWithoutVAT();
		if($discount)
		{
			$total -= $discount->getReductionValue();
		}
		return $total;
	}

    public function getTotalBeforeDiscount()
    {
        $total = $this->getItemTotal();
        return $total;
    }

    public function getTotal()
    {
		$total = $this->getTotalAfterDiscount();
		$total += $this->getShippingCost();
        return $total;
    }

    public function getVAT()
    {
		return $this->getVATAfterDiscount();
    }

	public function getVATBeforeDiscount()
	{
		$shop = new ShopInfo($this->module_srl);
		return $this->getTotalBeforeDiscountWithoutVAT() * $shop->getVAT() / 100;
	}

	public function getVATAfterDiscount()
	{
		$shop = new ShopInfo($this->module_srl);
		return $this->getTotalAfterDiscountWithoutVAT() * $shop->getVAT() / 100;
	}

    public function getShippingCost()
    {
        $shipping_method = $this->getShippingMethodName();
        if($shipping_method){
            $shipping_repository = new ShippingMethodRepository();
            $shipping = $shipping_repository->getShippingMethod($shipping_method, $this->module_srl);
			$cacheKey = $this->cart_srl . '_shipping_cost';
			if(!$this->cache->has($cacheKey))
			{
				$this->cache[$cacheKey] = $shipping->calculateShipping($this, $this->getShippingMethodVariant());
			}
			$shipping_cost = $this->cache[$cacheKey];
            return $shipping_cost;
        } else return 0;
    }

    public function removeProducts(array $product_srls)
    {
        if (empty($product_srls)) throw new ShopException('Empty array $products_srls');
        $output = $this->query('deleteCartProducts', array('cart_srl'=>$this->cart_srl, 'product_srls'=>$product_srls));
        //TODO: optimize queries here
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true, true);
        $this->save();
    }


    public function updateProducts(array $quantities)
    {
        if (empty($quantities)) throw new ShopException('Empty array $quantities');
        foreach ($quantities as $product_srl=>$quantity) {
            if (!is_numeric($product_srl) || !is_numeric($quantity)) throw new ShopException('Problem with input $quantities array');
            if ($quantity == 0) $this->removeProducts(array($product_srl));
            else $this->query('updateCartProduct', array('cart_srl'=>$this->cart_srl, 'product_srl'=>$product_srl, 'quantity'=>$quantity));
        }
        //TODO: and here
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true, true);
        $this->save();
    }
    #endregion

    //region checkout
    /**
     * Checkout processor
     *
     * @param array $orderData
     *
     * @return Order
     * @throws Exception
     */
    public function checkout(array $orderData)
    {
        if (!$this->cart_srl) throw new ShopException('Cart is not persisted');
        $this->check();
        $orderData = $this->formTranslation($orderData);
        $this->setExtra($orderData['extra']);
        $this->billing_address_srl = $orderData['billing_address_srl'];
        $this->shipping_address_srl = (isset($orderData['shipping_address_srl']) ? $orderData['shipping_address_srl'] : $orderData['billing_address_srl']);
        $this->save();
    }

    private function formTranslation(array $input)
    {
        $data = array('extra'=> array());
        $addressRepo = new AddressRepository();
        if (self::validateFormBlock($billing = $input['billing'])) {
            if (is_numeric($billing['address'])) {
                $data['billing_address_srl'] = $billing['address'];
            } elseif (self::validateFormBlock($newAddress = $input['new_billing_address'])) {
                $newAddress = new Address($newAddress);
                if ($this->member_srl && !$addressRepo->hasDefaultAddress($this->member_srl, AddressRepository::TYPE_BILLING)) {
                    $newAddress->default_billing = 'Y';
                }
                $newAddress->save();
                $data['billing_address_srl'] = $newAddress->address_srl;
            }
            else {
                throw new ShopException('No billing address');
            }
        }
        if ($input['different_shipping'] == 'yes') {
            if (!self::validateFormBlock($shipping = $input['shipping'])) {
                throw new ShopException('Wrong shipping input');
            }
            $data['extra']['shipping_method'] = $shipping['method'];
			$data['extra']['shipping_variant'] = $shipping['variant'];
            if (is_numeric($shipping['address'])) {
                $data['shipping_address_srl'] = $shipping['address'];
            } elseif (self::validateFormBlock($newAddress = $input['new_shipping_address'])) {
                $newAddress = new Address($newAddress);
                if ($this->member_srl && !$addressRepo->hasDefaultAddress($this->member_srl, AddressRepository::TYPE_SHIPPING)) {
                    $newAddress->default_shipping = 'Y';
                }
                $newAddress->save();
                $data['shipping_address_srl'] = $newAddress->address_srl;
            }
            else {
                throw new ShopException('No shipping address');
            }
        }
		else
		{
			$shipping = $input['shipping'];
			$data['extra']['shipping_method'] = $shipping['method'];
			$data['extra']['shipping_variant'] = $shipping['variant'];
		}
        if (self::validateFormBlock($payment = $input['payment'])) {
            $data['extra']['payment_method'] = $payment['method'];
        }
        return empty($data) ? null : $data;
    }

    public function getAddresses($refresh=false)
    {
        if (!is_array($this->addresses) || $refresh = true) {
            if (!$this->member_srl) {
                $addresses = array();
                if ($this->billing_address_srl) {
                    $addresses[] = $this->getBillingAddress();
                }
                if ($this->shipping_address_srl && ($this->billing_address_srl != $this->shipping_address_srl)) {
                    $addresses[] = $this->getShippingAddress();
                }
                return $addresses;
            }
            $aRepo = new AddressRepository();
            $this->addresses = $aRepo->getAddresses($this->member_srl, true);
        }
        return $this->addresses;
    }

    public function getCurrency()
    {
        $shop = new ShopInfo($this->module_srl);
        return $shop->getCurrency();
    }

    /**
     * @return Address|null
     */
    public function getBillingAddress()
    {
        if (is_numeric($this->billing_address_srl)) {
            $aRepo = new AddressRepository();
            return $aRepo->getAddress($this->billing_address_srl);
        }
        else {
            $addresses = $this->getAddresses();
            if (!$addresses || empty($addresses)) return null;
            $defaultBillingAddress = null;
            /** @var $address Address */
            foreach ($addresses as $address) {
                if ($this->billing_address_srl == $address->address_srl) return $address;
                if ($address->isDefaultBillingAddress()) $defaultBillingAddress = $address;
            }
            return $defaultBillingAddress;
        }
    }

    /**
     * @return Address|null
     */
    public function getShippingAddress()
    {
        if (is_numeric($this->shipping_address_srl)) {
            $aRepo = new AddressRepository();
            return $aRepo->getAddress($this->shipping_address_srl);
        }
        else {
            $addresses = $this->getAddresses();
            if (!$addresses || empty($addresses)) return null;
            $defaultShippingAddress = null;
            /** @var $address Address */
            foreach ($addresses as $address) {
                if ($this->shipping_address_srl == $address->address_srl) return $address;
                if ($address->isDefaultShippingAddress()) $defaultShippingAddress = $address;
            }
            return $defaultShippingAddress;
        }
    }

    /**
     * retrieves Order attached to cart or null
     * @return null|Order
     */
    public function getOrder()
    {
        $output = $this->query('getCartOrder', array('cart_srl'=>$this->cart_srl));
        return empty($output->data) ? null : new Order((array) $output->data);
    }

    /**
     * @static Used to check if a form block has valid input (ex has any value)
     *
     * @param array $array
     *
     * @return bool
     */
    private static function validateFormBlock(array $array = null)
    {
        if ($array) foreach ($array as $val) if ($val) return true;
        return false;
    }
    //endregion

    //region extra
    public function setExtraArray(array $extra)
    {
        $this->extra = json_encode($extra);
        return $this;
    }

    /**
     * @return array|null
     */
    public function getExtraArray()
    {
        return (array) json_decode($this->extra);
    }

    public function setExtra($key, $value=null)
    {
        if (!$a = $this->getExtraArray()) $a = array();
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->setExtra($k, $v);
            }
        }
        else {
            $a[$key] = $value;
            $this->setExtraArray($a);
        }
        return $this;
    }

    public function getExtra($key)
    {
        $a = $this->getExtraArray();
        return isset($a[$key]) ? $a[$key] : null;
    }
    //endregion

    public function getTransactionId()
    {
        return $this->getExtra('transaction_id');
    }

    public function getTransactionErrorMessage()
    {
        return $this->getExtra('transaction_message');
    }

	public function getShippingMethod()
	{
		$shipping_repository = new ShippingMethodRepository();
		$shipping_method_name = $this->getExtra('shipping_method');
		if($shipping_method_name)
		{
			return $shipping_repository->getShippingMethod($shipping_method_name, $this->module_srl);
		}
		$default_shipping = $shipping_repository->getDefault($this->module_srl);
		return $default_shipping;
	}

    public function getShippingMethodName()
    {
        $shipping_method = $this->getExtra('shipping_method');
		if($shipping_method)
		{
			return $shipping_method;
		}

		$shipping_repository = new ShippingMethodRepository();
		$default_shipping = $shipping_repository->getDefault($this->module_srl);
		return $default_shipping->name;
    }

	public function getShippingMethodVariant()
	{
		return $this->getExtra('shipping_variant');
	}

    public function getPaymentMethodName()
    {
        $payment_method = $this->getExtra('payment_method');
		if($payment_method)
		{
			return $payment_method;
		}

		$payment_repository = new PaymentMethodRepository();
		$default_payment = $payment_repository->getDefault($this->module_srl);
		return $default_payment->name;
    }

    /**
     * Discount name
     */
    public function getDiscountName()
    {
		$discount = $this->getDiscount();
		return $discount ? $discount->getName() : null;
    }

    /**
     * Discount description
     */
    public function getDiscountDescription()
    {
		$discount = $this->getDiscount();
        return $discount ? $discount->getDescription() : null;
    }

    /**
     * Discount amount
     */
    public function getDiscountAmount()
    {
		$discount = $this->getDiscount();
        return $discount ? $discount->getReductionValue() : null;
    }

	public function getCustomerFirstname()
	{
		return $this->getBillingAddress()->firstname;
	}

	public function getCustomerLastname()
	{
		return $this->getBillingAddress()->lastname;
	}
}