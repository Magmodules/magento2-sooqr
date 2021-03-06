<?php
/**
 * Copyright © 2019 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Sooqr\Setup;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ValueInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magmodules\Sooqr\Helper\General;

/**
 * Class SetupData
 *
 * @package Magmodules\Sooqr\Setup
 */
class SetupData
{
    const CATEGORY_EXCLUDE_ATT = 'sooqr_cat_disable_export';
    const PRODUCT_EXCLUDE_ATT = 'sooqr_exclude';

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ValueInterface
     */
    private $configReader;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * UpgradeData constructor.
     *
     * @param EavSetupFactory          $eavSetupFactory
     * @param ProductMetadataInterface $productMetadata
     * @param ObjectManagerInterface   $objectManager
     * @param ValueInterface           $configReader
     * @param WriterInterface          $configWriter
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ProductMetadataInterface $productMetadata,
        ObjectManagerInterface $objectManager,
        ValueInterface $configReader,
        WriterInterface $configWriter
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->productMetadata = $productMetadata;
        $this->objectManager = $objectManager;
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
    }

    /**
     * Create unique token
     */
    public function generateAndSaveToken()
    {
        $token = '';
        $chars = str_split("abcdefghijklmnopqrstuvwxyz0123456789");
        for ($i = 0; $i < 16; $i++) {
            $token .= $chars[array_rand($chars)];
        }
        $this->configWriter->save(General::XPATH_TOKEN, $token, 'default', 0);
    }

    /**
     * Change config paths for fields due to changes in config options.
     */
    public function changeConfigPaths()
    {
        $collection = $this->configReader->getCollection()
            ->addFieldToFilter("path", General::XPATH_EXTENSION_ENABLED)
            ->addFieldToFilter("scope_id", ["neq" => 0]);

        foreach ($collection as $config) {
            $this->configWriter->delete(
                General::XPATH_EXTENSION_ENABLED,
                $config->getScope(),
                $config->getScopeId()
            );
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function addProductAtribute(ModuleDataSetupInterface $setup)
    {
        $groupName = 'Sooqr Search';

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $attributeSetIds = $eavSetup->getAllAttributeSetIds(Product::ENTITY);

        foreach ($attributeSetIds as $attributeSetId) {
            $eavSetup->addAttributeGroup(Product::ENTITY, $attributeSetId, $groupName, 1000);
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::PRODUCT_EXCLUDE_ATT,
            [
                'group'                   => $groupName,
                'type'                    => 'int',
                'label'                   => 'Exclude for Sooqr Search',
                'input'                   => 'boolean',
                'source'                  => Boolean::class,
                'global'                  => ScopedAttributeInterface::SCOPE_GLOBAL,
                'default'                 => '0',
                'user_defined'            => true,
                'required'                => false,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'unique'                  => false,
                'apply_to'                => 'simple,configurable,virtual,bundle,downloadable'
            ]
        );

        $attribute = $eavSetup->getAttribute(Product::ENTITY, self::PRODUCT_EXCLUDE_ATT);
        foreach ($attributeSetIds as $attributeSetId) {
            $eavSetup->addAttributeToGroup(
                Product::ENTITY,
                $attributeSetId,
                $groupName,
                $attribute['attribute_id'],
                110
            );
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    public function addExcludeCateroryAttribute(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            Category::ENTITY,
            self::CATEGORY_EXCLUDE_ATT,
            [
                'type'         => 'int',
                'label'        => 'Disable Category from export',
                'input'        => 'select',
                'source'       => Boolean::class,
                'global'       => 1,
                'visible'      => true,
                'required'     => false,
                'user_defined' => false,
                'sort_order'   => 100,
                'default'      => 0
            ]
        );
    }
}
