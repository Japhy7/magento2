<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogInventory\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Spi\StockResolverInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Model\Spi\StockStateProviderInterface;

/**
 * Class StockRegistry
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class StockRegistry implements StockRegistryInterface
{
    /**
     * @var StockConfigurationInterface
     */
    protected $stockConfiguration;

    /**
     * @var StockRegistryProviderInterface
     */
    protected $stockRegistryProvider;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var StockItemRepositoryInterface
     */
    protected $stockItemRepository;

    /**
     * @var \Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory
     */
    protected $criteriaFactory;

    /**
     * @var \Magento\CatalogInventory\Model\Spi\StockResolverInterface
     */
    protected $stockResolver;

    /**
     * @var StockStateProviderInterface
     */
    protected $stockStateProvider;

    /**
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockRegistryProviderInterface $stockRegistryProvider
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param StockItemCriteriaInterfaceFactory $criteriaFactory
     * @param ProductFactory $productFactory
     * @param StockResolverInterface $stockResolver
     * @param StockStateProviderInterface $stockStateProvider
     */
    public function __construct(
        StockConfigurationInterface $stockConfiguration,
        StockRegistryProviderInterface $stockRegistryProvider,
        StockItemRepositoryInterface $stockItemRepository,
        StockItemCriteriaInterfaceFactory $criteriaFactory,
        ProductFactory $productFactory,
        StockResolverInterface $stockResolver,
        StockStateProviderInterface $stockStateProvider
    ) {
        $this->stockConfiguration = $stockConfiguration;
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->stockItemRepository = $stockItemRepository;
        $this->criteriaFactory = $criteriaFactory;
        $this->productFactory = $productFactory;
        $this->stockResolver = $stockResolver;
        $this->stockStateProvider = $stockStateProvider;
    }

    /**
     * @inheritdoc
     */
    public function getStock($stockId = null)
    {
        return $this->stockRegistryProvider->getStock($stockId);
    }

    /**
     * @param int $productId
     * @param int $scopeId
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    public function getStockItem($productId, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);

        return $this->stockRegistryProvider->getStockItem($productId, $stockId);
    }

    /**
     * @param string $productSku
     * @param int $scopeId
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStockItemBySku($productSku, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $productId = $this->resolveProductId($productSku);
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);
        return $this->stockRegistryProvider->getStockItem($productId, $stockId);
    }

    /**
     * @param int $productId
     * @param int $scopeId
     * @return \Magento\CatalogInventory\Api\Data\StockStatusInterface
     */
    public function getStockStatus($productId, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);
        return $this->stockRegistryProvider->getStockStatus($productId, $stockId);
    }

    /**
     * @param string $productSku
     * @param int $scopeId
     * @return \Magento\CatalogInventory\Api\Data\StockStatusInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStockStatusBySku($productSku, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $productId = $this->resolveProductId($productSku);
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);
        return $this->getStockStatus($productId, $stockId);
    }

    /**
     * Retrieve Product stock status
     * @param int $productId
     * @param int $scopeId
     * @return int
     */
    public function getProductStockStatus($productId, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);
        $stockStatus = $this->getStockStatus($productId, $stockId);
        return $stockStatus->getStockStatus();
    }

    /**
     * @param string $productSku
     * @param null $scopeId
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProductStockStatusBySku($productSku, $scopeId = null)
    {
        if (!$scopeId) {
            $scopeId = $this->stockConfiguration->getDefaultScopeId();
        }
        $productId = $this->resolveProductId($productSku);
        $stockId = $this->stockResolver->getStockId($productId, $scopeId);
        return $this->getProductStockStatus($productId, $stockId);
    }

    /**
     * @inheritdoc
     */
    public function getLowStockItems($scopeId, $qty, $currentPage = 1, $pageSize = 0)
    {
        $criteria = $this->criteriaFactory->create();
        $criteria->setLimit($currentPage, $pageSize);
        $criteria->setScopeFilter($scopeId);
        $criteria->setQtyFilter('<=', $qty);
        $criteria->addField('qty');
        return $this->stockItemRepository->getList($criteria);
    }

    /**
     * @inheritdoc
     */
    public function updateStockItemBySku($productSku, \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem)
    {
        $productId = $this->resolveProductId($productSku);
        $websiteId = $stockItem->getWebsiteId() ?: null;
        $origStockItem = $this->getStockItem($productId, $websiteId);
        $data = $stockItem->getData();
        if ($origStockItem->getItemId()) {
            unset($data['item_id']);
        }
        $origStockItem->addData($data);
        $origStockItem->setProductId($productId);
        return $this->stockItemRepository->save($origStockItem)->getItemId();
    }

    /**
     * @param string $productSku
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function resolveProductId($productSku)
    {
        $product = $this->productFactory->create();
        $productId = $product->getIdBySku($productSku);
        if (!$productId) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(
                __(
                    'Product with SKU "%1" does not exist',
                    $productSku
                )
            );
        }
        return $productId;
    }
}
