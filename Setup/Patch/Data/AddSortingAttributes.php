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

class AddSortingAttributes implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
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
                'price_desc',
                [
                    'label' => 'Price Desc for sorting',
                    'group' => 'General',
                    'type' => 'decimal',
                    'input' => 'price',
                    'required' => false,
                    'sort_order' => 50,
                    'backend_model' => 'Magento\Catalog\Model\Product\Attribute\Backend\Price',
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'default' => '',
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => false,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'user_defined' => true,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'used_for_sort_by' => true
                ]
            );

            $eavSetup->addAttribute(
                Product::ENTITY,
                'created_at_desc',
                [
                    'label' => 'Created At Desc for sorting',
                    'group' => 'General',
                    'type' => 'datetime',
                    'input' => 'date',
                    'required' => false,
                    'sort_order' => 51,
                    'backend_model' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                    'frontend_model' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'default' => '',
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'visible' => false,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'user_defined' => true,
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
}