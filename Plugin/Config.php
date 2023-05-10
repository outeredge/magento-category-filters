<?php

namespace OuterEdge\CategoryFilters\Plugin;

use Magento\Catalog\Model\Config as CoreConfig;

class Config
{

    public function afterGetAttributeUsedForSortByArray(CoreConfig $catalogConfig, $options)
    {
        $options['price'] = __('Price - low to high');
        $options['price_desc'] = __('Price - high to low');

        $options['created_at'] = __('Oldest first');
        $options['created_at_desc'] = __('Newest first');

        krsort($options);

        return $options;
    }
}
