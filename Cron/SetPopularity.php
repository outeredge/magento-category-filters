<?php

namespace OuterEdge\CategoryFilters\Cron;

use Magento\Catalog\Model\ResourceModel\Product\Action;
use Psr\Log\LoggerInterface;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use OuterEdge\CategoryFilters\Helper\Data;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SetPopularity
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
        protected Action $action,
        protected LoggerInterface $logger,
        protected IndexerFactory $indexerFactory,
        protected ProductRepositoryInterface $productRepositoryInterface,
        protected Data $helper,
        protected DateTime $dateTime,
        protected ResourceConnection $resourceConnection,
        protected ScopeConfigInterface $scopeConfig
    )
    {
        $this->bestSellingRange = $this->scopeConfig->getValue(
            'oe_category_filter/settings/best_selling_range',
            ScopeInterface::SCOPE_STORE
        ) ?? self::BEST_SELLING_RANGE;
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
                    'rating_pos'
                ]
            )
            ->where('period <= ?', $to)
            ->where('period >= ?', $from)
            ->group('main_table.product_id')
            ->limit(100);

        return $connection->fetchAll($select);
    }

    public function execute()
    {
        $this->logger->info('Running setPopularity cron job');

        if (!$this->helper->isPopularityEnabled()) {
            return;
        }
        try {
            $productIdsToIndex = [];

            foreach ($this->getBestSellingProductsCollection() as $item) {
                $product = $this->productRepositoryInterface->getById($item['product_id']);

                if ($product->getPopularity() != $item['rating_pos']) {
                    $this->action->updateAttributes(
                        [$product->getId()],
                        ['popularity' => $item['rating_pos']],
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
            $this->logger->critical('Error running setPopularity cron job: ' . $e->getMessage());
        }
        $this->logger->info('Finished setPopularity cron job');

        return;
    }
}
