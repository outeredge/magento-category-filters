<?php

namespace OuterEdge\CategoryFilters\Setup\Patch\Data;

use Exception;
use Psr\Log\LoggerInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class AddPopularityAttribute implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected EavSetupFactory $eavSetupFactory,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        try {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'popularity',
                [
                    'label' => 'Popularity',
                    'group' => 'General',
                    'type' => 'int',
                    'input' => 'text',
                    'required' => false,
                    'sort_order' => 50,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'default' => 1000,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => false,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'user_defined' => false,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'used_for_sort_by' => true
                ]
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    public static function getVersion()
    {
        return '2.0.1';
    }
}
