<?php

interface IProductItemsContainer
{
    /**
     * Returns a list of all products
     * Products must implement IProductItem
     *
     * @return mixed
     */
    public function getProducts();

    /**
     * Shipping cost
     */
    public function getShippingCost();

    /**
     * Total before applying discount
     *
     * @return float
     */
    public function getTotalBeforeDiscount();

    /**
     * Discount name
     */
    public function getDiscountName();

    /**
     * Discount description
     */
    public function getDiscountDescription();

    /**
     * Discount amount
     */
    public function getDiscountAmount();

    /**
     * Returns global total
     */
    public function getTotal();

    /**
     * Returns amount of total that represents taxes
     */
    public function getVAT();


}