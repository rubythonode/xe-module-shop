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

    protected $addresses;

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
        if ($cart->cart_srl == $this->cart_srl) throw new Exception('Cannot merge with same cart');
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
            if ($have) { //if I have it update my quantity
                $this->repo->updateCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity + $cp2->quantity);
            }
            else { //else add it
                $this->repo->insertCartProduct($this->cart_srl, $cp->product_srl, $cp->quantity);
            }
        }
        //count again
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
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
        $product_srl = ( $product instanceof Product ? $product->product_srl : $product );
        if (!$product_srl) throw new Exception('Product is not persisted');
        //check if product already added
        $output = $this->repo->getCartProducts($this->cart_srl, array($product_srl));
        if (empty($output->data)) {
            $return = $this->repo->insertCartProduct($this->cart_srl, $product_srl, $quantity);
        }
        else {
            $cp = $output->data[0];
            $return = $this->setProductQuantity($product_srl, $relativeQuantity ? $cp->quantity + $quantity : $quantity);
        }

        //count with quantities
        $this->items = $this->count(true);
        $this->save();

        return $return;
    }

    /**
     * @param bool $sumQuantities take quantities into account or only count unique items?
     *
     * @return mixed number of items in cart
     */
    public function count($sumQuantities=false)
    {
        return $this->repo->countCartProducts($this->module_srl, $this->cart_srl, $this->member_srl, $this->session_id, $sumQuantities);
    }

    public function setProductQuantity($product_srl, $quantity)
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
        return $this->repo->updateCartProduct($this->cart_srl, $product_srl, $quantity);
    }

    public function getProducts()
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
        $output = $this->query('getCartAllProducts', array('cart_srl'=> $this->cart_srl), true);
        foreach ($output->data as $i=>&$data) {
            if ($data->product_srl) {
                $product = new SimpleProduct($data);
                $product->quantity = $data->quantity;
                $data = $product;
            }
            else unset($output->data[$i]);
        }
        return $output->data;
    }

    public function getItemTotal()
    {
        $output = $this->getProducts();
        $total = 0;
        /** @var $product Product */
        foreach ($output as $product) {
            $total += $product->price * $product->quantity;
        }
        return $total;
    }

    public function getShippingCost()
    {
        $shipping_method = $this->getExtra('shipping_method');
        if($shipping_method){
            $shipping_repository = new ShippingRepository();
            $shipping = $shipping_repository->getShippingMethod($shipping_method);
            return $shipping->calculateShipping($this, $this->getShippingAddress());
        }
        else
        {
            return 0;
        }
    }

    public function getTotal()
    {
        $itemTotal = $this->getItemTotal();
        $shippingCost = $this->getShippingCost();
        return $itemTotal + $shippingCost;
    }

    public function getProductsList(array $args=array())
    {
        if (!$this->cart_srl) throw new Exception('Cart is not persisted');
        $output = $this->query('getCartProductsList', array_merge(array('cart_srl'=>$this->cart_srl), $args));
        foreach ($output->data as $i=>&$data) {
            if ($data->product_srl) {
                $product = new SimpleProduct($data);
                $product->quantity = $data->quantity;
                $data = $product;
            }
            else unset($output->data[$i]);
        }
        return $output;
    }

    public function removeProducts(array $product_srls)
    {
        if (empty($product_srls)) throw new Exception('Empty array $products_srls');
        $output = $this->query('deleteCartProducts', array('cart_srl'=>$this->cart_srl, 'product_srls'=>$product_srls));
        //TODO: optimize queries here
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