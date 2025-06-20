<?php

namespace OuterEdge\CategoryFilters\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Psr\Log\LoggerInterface;
use Magento\Indexer\Model\IndexerFactory;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use OuterEdge\CategoryFilters\Helper\Data;

class Cron
{
    CONST BEST_SELLING_RANGE = 30;

    protected $indexerIds = array(
        'catalog_category_product',
        'catalog_product_category',
        'catalog_product_price',
        'catalog_product_attribute',
        'cataloginventory_stock',
        'catalogrule_product',
        'catalogsearch_fulltext',
    );

    protected $bestSellingRange;

    public function __construct(
        protected CollectionFactory $productCollectionFactory,
        protected Action $action,
        protected LoggerInterface $logger,
        protected IndexerFactory $indexerFactory,
        protected GetSalableQuantityDataBySku $stockState,
        protected Configurable $configModel,
        protected StockRegistryInterface $stockRegistryInterface,
        protected ProductRepositoryInterface $productRepositoryInterface,
        protected StockItemRepository $stockItemRepository,
        protected Data $helper
    )
    {
        $this->bestSellingRange = self::BEST_SELLING_RANGE;
    }

    private function getBestSellingProductsCollection()
    {
        $subquery = new \Zend_Db_Expr("(
            SELECT soi.product_id, COUNT(soi.product_id) AS `times_ordered`, soi.store_id
            FROM `sales_order_item` as soi
            WHERE (soi.created_at BETWEEN DATE_SUB(NOW(), INTERVAL {$this->bestSellingRange} DAY) AND NOW())
            GROUP BY soi.product_id)");

        $collection = $this->productCollectionFactory->create();
        $collection->getSelect()->joinInner(
            ['soi' => $subquery],
            'e.entity_id = soi.product_id',
            ['soi.times_ordered', 'soi.store_id']
        )
        ->where("`e`.`type_id` IN('simple', 'virtual')")
        ->order('soi.times_ordered DESC');

        //echo $collection->getSelect(); die();
        return $collection;
    }

    public function setQtyOrdered()
    {
        if (!$this->helper->isPopularityEnabled()) {
            return;
        }

        foreach ($this->getBestSellingProductsCollection() as $product) {
            try {
                $qtyOrdered = $product->getTimesOrdered();

                //var_dump($product->getSku().' : '.$product->getTypeId().' : '.$qtyOrdered);

                $parentsIds = $this->configModel->getParentIdsByChild($product->getId());
                foreach ($parentsIds as $parentId) {
                    $productParent = $this->productRepositoryInterface->getById($parentId);

                    if ($productParent->getTimesOrdered() > 0) {
                        $qtyOrdered = $productParent->getTimesOrdered();
                    }

                    /*
                    * Force parent to update qty_ordered
                    */
                    $this->action->updateAttributes(
                        [$productParent->getId()],
                        ['qty_ordered' => $qtyOrdered],
                        $productParent->getStoreId());

                    /*
                    * Force childs to have the same value of parents
                    * That way elasticsearch dont mess with value
                    */
                    $childrensConfig = $productParent->getTypeInstance()->getUsedProducts($productParent);
                    foreach ($childrensConfig as $child) {
                        $this->action->updateAttributes(
                            [$child->getId()],
                            ['qty_ordered' => $qtyOrdered],
                            $child->getStoreId());
                    }
                }

                $this->action->updateAttributes(
                    [$product->getId()],
                    ['qty_ordered' => $qtyOrdered],
                    $product->getStoreId());

                $indexer = $this->indexerFactory->create();
                foreach ($this->indexerIds as $indexerId) {
                    $indexer->load($indexerId)->reindexRow($product->getId());
                }

            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        return;
    }

    public function setInStockSearch()
    {
        if (!$this->helper->isInStockFirstEnabled()) {
            return;
        }

        foreach ($this->getAllProductsCollection() as $product) {

            try {
                $isInStock = 0;

                $salable = $this->stockState->execute($product->getSku());
                $isInStock = ($salable[0]['qty']) >= 1 ? 1 : 0;

                var_dump($product->getSku().' : '.$product->getTypeId().' : '.$isInStock);

                $parentsIds = $this->configModel->getParentIdsByChild($product->getId());
                foreach ($parentsIds as $parentId) {
                    $productParent = $this->productRepositoryInterface->getById($parentId);
                    if ($productParent->isSaleable() || $productParent->isAvailable()) {
                        $isInStock = 1;
                    }

                    /*
                     * Force parent to update status
                     */
                    $this->action->updateAttributes(
                        [$productParent->getId()],
                        ['in_stock_search' => $isInStock],
                        $productParent->getStoreId());

                    /*
                     * Force childs to have the same value on in_stock_search
                     * That way elasticsearch dont mess with value
                     */
                    $childrensConfig = $productParent->getTypeInstance()->getUsedProducts($productParent);
                    foreach ($childrensConfig as $child) {
                        $this->action->updateAttributes(
                            [$child->getId()],
                            ['in_stock_search' => $isInStock],
                            $child->getStoreId());
                    }
                }

                $this->action->updateAttributes(
                    [$product->getId()],
                    ['in_stock_search' => $isInStock],
                    $product->getStoreId());

                $indexer = $this->indexerFactory->create();
                foreach ($this->indexerIds as $indexerId) {
                    $indexer->load($indexerId)->reindexRow($product->getId());
                }

            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        return;
    }

    private function getAllProductsCollection()
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['in'=> [
                Type::TYPE_SIMPLE,
                Type::TYPE_VIRTUAL
            ]])
            ->addAttributeToSelect('*')
           /*->addAttributeToFilter('sku',['in'=>
                ['vdc-smok-novo-2s-Abstract']
                ]) */
            ->load();

       // echo $collection->getSelect(); die();
        return $collection;
    }

}
