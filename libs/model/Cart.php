<?php
/**
 * @author Florin Ercus (dev@xpressengine.org)
 */
class Cart extends BaseItem
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

    protected $addresses, $productsCache=array();

    /** @var CartRepository */
    public $repo;

    public function save()
    {
        return $this->cart_srl ? $this->repo->updateCart($this) : $this->repo->insertCart($this);
    }

    public function delete()
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted, can\'t delete.');
        $this->query('deleteCarts', array('cart_srls' => array($this->cart_srl)));
        $this->query('deleteCartProducts', array('cart_srl'=> $this->cart_srl));
    }

    public function merge(Cart $cart)
    {
        if (!$cart->cart_srl || !$this->cart_srl) throw new Exception('Missing srl(s) for carts merge');
        if ($cart->cart_srl == $this->cart_srl) {
            throw new Exception('Cannot merge with same cart');
        }
        $this->copyProductLinksFrom($cart);
        $cart->delete();
    }

    public function copyProductLinksFrom(Cart $cart)
    {
        if (!$cart->cart_srl || !$this->cart_srl) throw new Exception('Missing srl(s) for cart products copy');
        if ($cart->cart_srl == $this->cart_srl) throw new Exception('Cannot copy from same cart');
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
            //if I have it update my quantity
            if ($have) $this->repo->updateCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity + $cp2->quantity);
            //else add it
            else $this->repo->insertCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity);
        }
        //count again
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true);
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
        if (!$this->isPersisted()) throw new Exception('Cart is not persisted');

        if ($product instanceof SimpleProduct) {
            if (!$product_srl = $product->product_srl) {
                throw new Exception('Product is not persisted');
            }
        }
        elseif (is_numeric($product)) {
            $product = new SimpleProduct($product);
        }
        else {
            throw new Exception('Wrong $product input for addProduct to cart');
        }

        if (!$this->productStillAvailable($product)) {
            throw new Exception('Product is not available anymore, cannot add it to cart.');
        }

        if ($cp = $this->getCartProduct($product)) {
            $cartProductQuantity = ($relativeQuantity ? $cp->quantity + $quantity : $quantity);
            $return = $this->setProductQuantity($product->product_srl, $cartProductQuantity, array('title' => $product->title));
        }
        else {
            $cartProductQuantity = $quantity;
            $return = $this->repo->insertCartProduct($this->cart_srl, $product->product_srl, $cartProductQuantity, array('title' => $product->title));
        }
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true);

        $this->save();

        return $return;
    }

    public function productStillAvailable($product, $checkIfInStock=true)
    {
        if ($product instanceof SimpleProduct) {
            if (!$product->isPersisted()) {
                throw new Exception('Product not persisted');
            }
        }
        elseif (is_numeric($product)) {
            $pRepo = new ProductRepository();
            $product = $pRepo->getProduct($product);
        }
        else throw new Exception('Invalid input');
        return $product && $product->isAvailable($checkIfInStock);
    }

    public function hasProduct($product)
    {
        return $this->getCartProduct($product) ? true : false;
    }

    public function getCartProduct($product)
    {
        return $this->repo->getCartProduct($this->cart_srl, $product);
    }

    public function getPrice($refresh=false)
    {
        if (!$refresh && is_numeric($price = $this->getExtra('price'))) return $price;
        //calculate new price
        $price = 0;
        /** @var $product SimpleProduct */
        foreach ($this->getProducts() as $product) {
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
        return $this->repo->countCartProducts($this->module_srl, $this->cart_srl, $this->member_srl, $this->session_id, $sumQuantities);
    }

    public function countAvailableProducts()
    {
        return count($this->getAvailableProducts());
    }

    public function setProductQuantity($product_srl, $quantity, array $extraParams=array())
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
        return $this->repo->updateCartProduct($this->cart_srl, $product_srl, $quantity, $extraParams);
    }

    /**
     * @param null $n number of products to return
     *
     * @return mixed Cart products
     * @throws Exception
     */
    public function getProducts($n=null, $onlyValidForOrder=false)
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');

        //cache
        $cacheKey = ($n?$n:"NULL").(string)$onlyValidForOrder;
        if (isset($this->productsCache[$cacheKey])) return $this->productsCache[$cacheKey];
        //end cache

        $shopInfo = new ShopInfo($this->module_srl);
        $checkIfInStock = ($shopInfo->getOutOfStockProducts() == 'Y');

        $params = array('cart_srl'=> $this->cart_srl);
        if ($n) $params['list_count'] = $n;
        $output = $this->query('getCartAllProducts', $params, true);
        foreach ($output->data as $i=>&$data) {
            if ($data->product_srl) {
                $product = new SimpleProduct($data);
                $product->available = $this->productStillAvailable($product, $checkIfInStock);
                if (!$product->available && $onlyValidForOrder) {
                    unset($output->data[$i]);
                    continue;
                }
                $product->cart_product_srl = $data->cart_product_srl;
                $product->cart_product_title = $data->cart_product_title;
                $product->quantity = $data->quantity;
                $data = $product;
            }
            else unset($output->data[$i]);
        }
        return $this->productsCache[$cacheKey] = $output->data;
    }

    public function getProductsList(array $args=array())
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');

        $shopInfo = new ShopInfo($this->module_srl);
        $checkIfInStock = ($shopInfo->getOutOfStockProducts() == 'Y');

        $output = $this->query('getCartProductsList', array_merge(array('cart_srl'=>$this->cart_srl), $args), true);
        foreach ($output->data as $i=>&$data) {
            //if ($data->product_srl is missing) then product was deleted by shop admin
            if ($data->cart_product_srl) {
                $product = new SimpleProduct($data);
                $product->quantity = $data->quantity;
                $product->available = ( $data->product_srl ? $this->productStillAvailable($product, $checkIfInStock) : false );
                $product->cart_product_srl = $data->cart_product_srl;
                $product->cart_product_title = $data->cart_product_title;
                $data = $product;
            }
            else unset($output->data[$i]);
        }
        return $output;
    }

    public function getAvailableProducts($n=null)
    {
        return $this->getProducts($n, true);
    }

    public function check()
    {
        $shopInfo = new ShopInfo($this->module_srl);
        if ($minOrder = $shopInfo->getMinimumOrder()) {
            // TO DO getPrice() doesn't work without true
            if ($this->getPrice(true) < $minOrder) {
                throw new Exception("Minimum order amount of $minOrder not met (cart value: {$this->getPrice()})");
            }
        }
        return true;
    }

    public function getItemTotal($onlyAvailable=false)
    {
        $output = $onlyAvailable ? $this->getAvailableProducts() : $this->getProducts();
        $total = 0;
        /** @var $product Product */
        foreach ($output as $product) {
            $total += $product->price * $product->quantity;
        }
        return $total;
    }

    public function getTotal($onlyAvailable=false)
    {
        $itemTotal = $this->getItemTotal($onlyAvailable);
        $shippingCost = $this->getShippingCost();
        return $itemTotal + $shippingCost;
    }

    public function getShippingCost()
    {
        $shipping_method = $this->getExtra('shipping_method');
        if($shipping_method){
            $shipping_repository = new ShippingMethodRepository();
            $shipping = $shipping_repository->getShippingMethod($shipping_method, $this->module_srl);
            return $shipping->calculateShipping($this, $this->getShippingAddress());
        } else return 0;
    }

    public function removeProducts(array $product_srls)
    {
        if (empty($product_srls)) throw new Exception('Empty array $products_srls');
        $output = $this->query('deleteCartProducts', array('cart_srl'=>$this->cart_srl, 'product_srls'=>$product_srls));
        //TODO: optimize queries here
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true);
        $this->save();
    }


    public function updateProducts(array $quantities)
    {
        if (empty($quantities)) throw new Exception('Empty array $quantities');
        foreach ($quantities as $product_srl=>$quantity) {
            if (!is_numeric($product_srl) || !is_numeric($quantity)) throw new Exception('Problem with input $quantities array');
            if ($quantity == 0) $this->removeProducts(array($product_srl));
            else $this->query('updateCartProduct', array('cart_srl'=>$this->cart_srl, 'product_srl'=>$product_srl, 'quantity'=>$quantity));
        }
        //TODO: and here
        $this->setExtra('price', $this->getPrice(true));
        $this->items = $this->count(true);
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
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
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
                throw new Exception('No billing address');
            }
        }
        if (self::validateFormBlock($shipping = $input['shipping'])) {
            $data['extra']['shipping_method'] = $shipping['method'];
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
                throw new Exception('No shipping address');
            }
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

}