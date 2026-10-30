<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Model\AttributeProvider\CatalogProduct;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchCatalog\Model\AttributeProvider\CatalogProduct\Eav;
use Walkwizus\MeilisearchCatalog\Model\Config;

class EavTest extends TestCase
{
    /**
     * @return array<string, array{bool, string[], string[]}>
     */
    public static function cases(): array
    {
        return [
            'after the name' => [true, ['sku', 'name', 'description'], ['sku', 'name', 'category_name', 'description']],
            'last without a name' => [true, ['sku', 'description'], ['sku', 'description', 'category_name']],
            'disabled' => [false, ['sku', 'name', 'description'], ['sku', 'name', 'description']],
        ];
    }

    #[DataProvider('cases')]
    public function testCategoryNameIsSearchableWhenEnabled(bool $enabled, array $codes, array $expected): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addIsSearchableFilter')->willReturnSelf();
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('getSelect')->willReturn($this->createMock(Select::class));
        $collection->method('getIterator')->willReturn(new \ArrayIterator(
            array_map(fn ($code) => new DataObject(['attribute_code' => $code]), $codes)
        ));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $config = $this->createMock(Config::class);
        $config->method('isCategoryNameSearchable')->willReturn($enabled);

        self::assertSame($expected, (new Eav($factory, $config))->getSearchableAttributes());
    }
}
