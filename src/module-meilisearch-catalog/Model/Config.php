<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_MAGENTO_SYNONYMS = 'catalog/search/meilisearch_magento_synonyms';
    private const XML_PATH_CATEGORY_NAME_SEARCHABLE = 'catalog/search/meilisearch_category_name_searchable';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) { }

    /**
     * @return bool
     */
    public function isMagentoSynonyms(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_MAGENTO_SYNONYMS);
    }

    /**
     * @return bool
     */
    public function isCategoryNameSearchable(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CATEGORY_NAME_SEARCHABLE);
    }
}
