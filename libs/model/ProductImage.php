<?php
class ProductImage extends BaseItem implements IThumbnailable
{
    public
        $image_srl,
        $product_srl,
        $module_srl,
        $member_srl,
        $filename,
        $is_primary,
        $file_size,
        $regdate,
		$source_filename;

    public function getFullPath()
    {
        $image_path = "./files/attach/images/shop/$this->module_srl/product-images/$this->product_srl/$this->filename";
        if(is_file($image_path))
        {
            return $image_path;
        }
        return "./files/attach/shop/".getNumberingPath($this->module_srl,3)."/img/missingProduct.png";
    }

    public function getThumbnailPath($width = 80, $height = 0, $thumbnail_type = '')
    {
        $thumbnail = new ShopThumbnail($this->image_srl, $this->getFullPath());
        return $thumbnail->getThumbnailPath($width, $height, $thumbnail_type);
    }

}