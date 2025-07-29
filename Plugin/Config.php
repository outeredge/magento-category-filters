<?php

namespace OuterEdge\CategoryFilters\Plugin;

use Magento\Catalog\Model\Config as CoreConfig;
use OuterEdge\CategoryFilters\Helper\Data;

class Config
{
    public function __construct(
        protected Data $helper
    ) {
    }

    public function afterGetAttributeUsedForSortByArray(CoreConfig $catalogConfig, $options)
    {
        $options['price'] = __('Price - low to high');
        $options['price_desc'] = __('Price - high to low');

        $options['created_at'] = __('Oldest first');
        $options['created_at_desc'] = __('Newest first');

        if ($this->helper->isPopularityEnabled()) {
            $options['popularity'] = __('Popularity');
        } else {
            unset($options['popularity']);
        }

        krsort($options);

        return $options;
    }
}
