<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Model\AttributePostMapper\CatalogProduct;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Walkwizus\MeilisearchBase\Api\AttributeMapperInterface;
use Walkwizus\MeilisearchCatalog\Model\Config;

/**
 * Indexes the names of a product's active categories and their parents, so a search can match them. Parents
 * count whether or not they are anchors: a product in Men > Bottoms is a men's product either way.
 */
class CategoryName implements AttributeMapperInterface
{
    /**
     * @var array<int, array<int, string[]>> by store and category id, the names along the category's path
     */
    private array $paths = [];

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Config $config
     */
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Config $config
    ) { }

    /**
     * @param array $documentData
     * @param $storeId
     * @param array $context
     * @return array
     */
    public function map(array $documentData, $storeId, array $context = []): array
    {
        if (!$this->config->isCategoryNameSearchable()) {
            return $documentData;
        }

        $paths = $this->paths[(int)$storeId] ??= $this->loadPaths((int)$storeId);
        foreach ($documentData as &$document) {
            $names = array_map(fn ($id) => $paths[(int)$id] ?? [], $document['category_ids'] ?? []);
            $document['category_name'] = array_values(array_unique(array_merge([], ...$names)));
        }
        unset($document);

        return $documentData;
    }

    /**
     * @param int $storeId
     * @return array<int, string[]>
     */
    private function loadPaths(int $storeId): array
    {
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIsActiveFilter()
            // levels 0 and 1 are the tree and store roots
            ->addAttributeToFilter('level', ['gt' => 1]);

        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = (string)$category->getName();
        }

        $paths = [];
        foreach ($collection as $category) {
            $path = array_slice(explode('/', (string)$category->getPath()), 2);
            $paths[(int)$category->getId()] = array_values(array_intersect_key($names, array_flip($path)));
        }

        return $paths;
    }
}
