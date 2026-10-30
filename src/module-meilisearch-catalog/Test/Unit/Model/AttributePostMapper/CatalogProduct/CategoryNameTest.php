<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Model\AttributePostMapper\CatalogProduct;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchCatalog\Model\AttributePostMapper\CatalogProduct\CategoryName;
use Walkwizus\MeilisearchCatalog\Model\Config;

class CategoryNameTest extends TestCase
{
    public function testProductsGetTheNamesAlongTheirCategoryPaths(): void
    {
        $collection = $this->createMock(Collection::class);
        foreach (['setStoreId', 'addAttributeToSelect', 'addIsActiveFilter', 'addAttributeToFilter'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator([
            $this->category(11, 'Men', '1/2/11'),
            $this->category(12, 'Bottoms', '1/2/11/12'),
            $this->category(13, 'Shorts', '1/2/11/12/13'),
            $this->category(21, 'Women', '1/2/21'),
            // inactive categories are filtered out of the collection, so their names are skipped in a path
            $this->category(22, 'Shorts', '1/2/21/99/22'),
        ]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);

        $mapper = new CategoryName($factory, $this->config(true));
        $documents = [
            1 => ['sku' => 'a', 'category_ids' => [13, 22]],
            2 => ['sku' => 'b', 'category_ids' => [404]],
            3 => ['sku' => 'c'],
        ];

        self::assertSame(
            [
                1 => ['sku' => 'a', 'category_ids' => [13, 22], 'category_name' => ['Men', 'Bottoms', 'Shorts', 'Women']],
                2 => ['sku' => 'b', 'category_ids' => [404], 'category_name' => []],
                3 => ['sku' => 'c', 'category_name' => []],
            ],
            $mapper->map($documents, 1)
        );
        // the paths are loaded once per store
        $mapper->map($documents, 1);
    }

    public function testDocumentsAreUntouchedWhenDisabled(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $documents = [1 => ['category_ids' => [13]]];

        self::assertSame($documents, (new CategoryName($factory, $this->config(false)))->map($documents, 1));
    }

    private function category(int $id, string $name, string $path): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn($name);
        $category->method('getPath')->willReturn($path);

        return $category;
    }

    private function config(bool $enabled): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('isCategoryNameSearchable')->willReturn($enabled);

        return $config;
    }
}
