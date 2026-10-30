<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_MAGENTO_SYNONYMS = 'catalog/search/meilisearch_magento_synonyms';

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
}
