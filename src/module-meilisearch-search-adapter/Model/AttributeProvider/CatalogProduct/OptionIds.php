<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchSearchAdapter\Model\AttributeProvider\CatalogProduct;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\Eav\Model\Entity\Attribute;
use Walkwizus\MeilisearchBase\Api\AttributeProviderInterface;

/**
 * Magento's search requests filter and bucket option attributes by option ID, so each one gets an
 * `<code>_ids` field next to the label field the catalog indexes.
 */
class OptionIds implements AttributeProviderInterface
{
    public const SUFFIX = '_ids';

    /**
     * @param DataProvider $dataProvider
     */
    public function __construct(
        private readonly DataProvider $dataProvider
    ) { }

    /**
     * @return Attribute[] keyed by attribute ID
     */
    public function getAttributes(): array
    {
        return array_filter(
            $this->dataProvider->getSearchableAttributes(),
            fn ($attribute) => $attribute->getAttributeCode() === 'visibility'
                || in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true)
        );
    }

    /**
     * @return array
     */
    public function getFilterableAttributes(): array
    {
        return array_values(array_map(
            fn ($attribute) => $attribute->getAttributeCode() . self::SUFFIX,
            $this->getAttributes()
        ));
    }

    /**
     * @return array
     */
    public function getSearchableAttributes(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getSortableAttributes(): array
    {
        return [];
    }
}
