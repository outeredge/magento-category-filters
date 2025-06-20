<?php
namespace OuterEdge\CategoryFilters\Plugin\Catalog\Block;

use Magento\Catalog\Block\Product\ProductList\Toolbar as CoreToolbar;

class Toolbar
{

    /**
    * @param CoreToolbar $subject
    * @param \Closure $proceed
    * @param \Magento\Framework\Data\Collection $collection
    * @return \Magento\Catalog\Block\Product\ProductList\Toolbar
    */
    public function aroundSetCollection(CoreToolbar $subject, \Closure $proceed, $collection)
    {
        $currentOrder = $subject->getCurrentOrder();
        $result = $proceed($collection);

        if ($currentOrder) {
            switch ($currentOrder) {
                case 'price_desc':
                    $subject->getCollection()->setOrder('price', 'desc');
                    break;
                case 'created_at_desc':
                    $subject->getCollection()->setOrder('created_at', 'desc');
                    break;
                case 'qty_ordered':
                    $subject->getCollection()->setOrder('qty_ordered', 'desc');
                    break;
            }
        }

        //ToDo if out of stock enabled
        $collection->setOrder('in_stock_search', 'desc');

        return $result;
    }

}
