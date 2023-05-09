<?php

namespace OuterEdge\CategoryFilters\Plugin;

use Magento\Catalog\Model\Config as CoreConfig;

class Config
{

    public function afterGetAttributeUsedForSortByArray(CoreConfig $catalogConfig, $options)
    {
        //unset($options['position']);
        //unset($options['name']);

        $newOptions['price'] = __('Price - low to high');
        $newOptions['price_desc'] = __('Price - high to low');

        $newOptions['created_at'] = __('Oldest first');
        $newOptions['created_at_desc'] = __('Newest first');

        return $newOptions;
    }
}

