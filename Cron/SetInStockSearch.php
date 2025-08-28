<?php

namespace OuterEdge\CategoryFilters\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Psr\Log\LoggerInterface;
use Magento\Indexer\Model\IndexerFactory;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use OuterEdge\CategoryFilters\Helper\Data;

class SetInStockSearch
{
    protected $indexerIds = array(
        'catalog_category_product',
        'catalog_product_category',
        'catalog_product_price',
        'catalog_product_attribute',
        'cataloginventory_stock',
        'catalogrule_product',
        'catalogsearch_fulltext',
    );

    public function __construct(
        protected CollectionFactory $productCollectionFactory,
        protected Action $action,
        protected LoggerInterface $logger,
        protected IndexerFactory $indexerFactory,
        protected GetSalableQuantityDataBySku $stockState,
        protected Configurable $configModel,
        protected ProductRepositoryInterface $productRepositoryInterface,
        protected Data $helper
    )
    {
    }
    
    public function execute()
    {
        $this->logger->info('Running setInStockSearch cron job');
        if (!$this->helper->isInStockFirstEnabled()) {
            $this->logger->info('setInStockSearch cron job disabled');
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
            $this->logger->critical('Error running setInStockSearch cron job: ' . $e->getMessage());
        }
        $this->logger->info('Finished setInStockSearch cron job');

        return;
    }

    private function getAllProductsCollection()
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['in' => [
                Type::TYPE_SIMPLE,
                Type::TYPE_VIRTUAL
            ]])
            ->addAttributeToSelect(['entity_id', 'sku'])
           /*->addAttributeToFilter('sku',['in'=>
                ['use-sku-to-test-one-product']
                ]) */
            ->load();

        return $collection;
    }

}
