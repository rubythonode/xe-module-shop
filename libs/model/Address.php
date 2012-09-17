<?php
class Address extends BaseItem
{

    public
        $address_srl,
        $member_srl,
        $firstname,
        $lastname,
        $email,
        $address,
        $country,
        $region,
        $city,
        $postal_code,
        $telephone,
        $fax,
        $company,
        $default_shipping,
        $default_billing,
        $additional_info,
        $regdate,
        $last_update;

    /** @var AddressRepository */
    public $repo;


    public function save()
    {
        return $this->address_srl ? $this->repo->update($this) : $this->repo->insert($this);
    }

    public function __toString()
    {
        return <<<GATA
$this->address,
$this->country,
$this->region,
$this->city,
$this->company
GATA;
;
    }

    public function isDefaultBillingAddress()
    {
        return $this->default_billing == 'Y' ? true : false;
    }

    public function isDefaultShippingAddress()
    {
        return $this->default_shipping == 'Y' ? true : false;
    }
}