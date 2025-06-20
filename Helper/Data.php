<?php
namespace OuterEdge\CategoryFilters\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED_IN_STOCK_FIRST = 'oe_category_filter/settings/enable_in_stock_first';
    const XML_PATH_ENABLED_POPULARITY = 'oe_category_filter/settings/enable_popularity';

    public function isInStockFirstEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED_IN_STOCK_FIRST,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isPopularityEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED_POPULARITY,
            ScopeInterface::SCOPE_STORE
        );
    }
}
?>
