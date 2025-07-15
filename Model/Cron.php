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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

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
        protected Data $helper,
        protected DateTime $dateTime,
        protected ResourceConnection $resourceConnection,
        protected State $state,
        protected ScopeConfigInterface $scopeConfig
    )
    {
        $this->bestSellingRange = $this->scopeConfig->getValue(
            'oe_category_filter/settings/best_selling_range',
            ScopeInterface::SCOPE_STORE
        ) ?? self::BEST_SELLING_RANGE;
        $this->state->setAreaCode('adminhtml');
    }

    private function getBestSellingProductsCollection()
    {
        $to = $this->dateTime->gmtDate('Y-m-d');
        $from = $this->dateTime->gmtDate('Y-m-d', strtotime("-{$this->bestSellingRange} days"));
      
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['main_table' => $this->resourceConnection->getTableName('sales_bestsellers_aggregated_monthly')],
                [
                    'product_id',
                    'rating_pos',
                    'qty_ordered' => new \Zend_Db_Expr('SUM(qty_ordered)')            
                ]
            )    
            ->where('period <= ?', $to)
            ->where('period >= ?', $from)
            ->group('main_table.product_id')
            ->limit(100);

        return $connection->fetchAll($select);
    }

    public function setQtyOrdered()
    {
        try {
            $productIdsToIndex = [];

            foreach ($this->getBestSellingProductsCollection() as $item) {
                
                $product = $this->productRepositoryInterface->getById($item['product_id']);
                $ratingScore = intval(round((9 - $item['rating_pos']) * (98 / 8) + 1));

                if ($product->getQtyOrdered() != $ratingScore) {
                    $this->action->updateAttributes(
                        [$product->getId()],
                        ['qty_ordered' => $ratingScore],
                        $product->getStoreId());
                    $productIdsToIndex[] = $product->getId();
                }
            }

            //Perform bulk re-indexing
            $productIdsToIndex = array_unique($productIdsToIndex);
            if (!empty($productIdsToIndex)) {
                foreach ($this->indexerIds as $indexerId) {
                    $indexer = $this->indexerFactory->create()->load($indexerId);
                    $indexer->reindexList($productIdsToIndex);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return;
    }

    public function setInStockSearch()
    {
        if (!$this->helper->isInStockFirstEnabled()) {
            return;
        }

        try {
            $productIdsToIndex = [];
            foreach ($this->getAllProductsCollection() as $product)
            {
                $isInStock = 0;

                $salable = $this->stockState->execute($product->getSku());
                if (!empty($salable) && isset($salable[0]['qty'])) {
                    $isInStock = ($salable[0]['qty']) >= 1 ? 1 : 0;
                }

                //var_dump($product->getSku().' : '.$product->getTypeId().' : '.$isInStock);

                $parentsIds = $this->configModel->getParentIdsByChild($product->getId());
                foreach ($parentsIds as $parentId) {
                    $productParent = $this->productRepositoryInterface->getById($parentId);
                    if ($productParent->isSaleable() || $productParent->isAvailable()) {
                        $isInStock = 1;
                    }

                    /*
                     * Force parent to update status
                     */
                    if ($productParent->getInStockSearch() != $isInStock) {
                        $this->action->updateAttributes(
                            [$productParent->getId()],
                            ['in_stock_search' => $isInStock],
                            $productParent->getStoreId());
                        $productIdsToIndex[] = $productParent->getId();
                    }

                    /*
                     * Force childs to have the same value on in_stock_search
                     * That way elasticsearch dont mess with value
                     */
                    $childrensConfig = $productParent->getTypeInstance()->getUsedProducts($productParent);
                    foreach ($childrensConfig as $child) {
                        if ($child->getInStockSearch() != $isInStock) {
                            $this->action->updateAttributes(
                                [$child->getId()],
                                ['in_stock_search' => $isInStock],
                                $child->getStoreId());
                            $productIdsToIndex[] = $child->getId();
                        }
                    }
                }

                if ($product->getInStockSearch() != $isInStock) {
                    $this->action->updateAttributes(
                        [$product->getId()],
                        ['in_stock_search' => $isInStock],
                        $product->getStoreId());
                    $productIdsToIndex[] = $product->getId();
                }
            }

            //Perform bulk re-indexing
            $productIdsToIndex = array_unique($productIdsToIndex);
            if (!empty($productIdsToIndex)) {
                foreach ($this->indexerIds as $indexerId) {
                    $indexer = $this->indexerFactory->create()->load($indexerId);
                    $indexer->reindexList($productIdsToIndex);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
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
            ->addAttributeToSelect(['entity_id', 'sku'])
           /*->addAttributeToFilter('sku',['in'=>
                ['use-sku-to-test-one-product']
                ]) */
            ->load();

       // echo $collection->getSelect(); die();
        return $collection;
    }

}
