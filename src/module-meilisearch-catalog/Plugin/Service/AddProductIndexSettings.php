<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Plugin\Service;

use Magento\Store\Model\StoreManagerInterface;
use Meilisearch\Contracts\Task;
use Walkwizus\MeilisearchBase\Model\Indexer\BaseIndexerHandler;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\SettingsManager;

/**
 * Runs whenever the searchable attributes of an index are pushed. Swatch options are indexed as
 * "label|type|value", so "|" separates words: otherwise "Red|1|#ff0000" is one word that "red" only
 * matches as a prefix.
 */
class AddProductIndexSettings
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param SearchIndexNameResolver $searchIndexNameResolver
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SearchIndexNameResolver $searchIndexNameResolver
    ) { }

    /**
     * @param SettingsManager $subject
     * @param Task $result
     * @param string $indexName
     * @return Task
     * @throws \Exception
     */
    public function afterUpdateSearchableAttributes(SettingsManager $subject, Task $result, $indexName): Task
    {
        $storeId = $this->getProductIndexStoreId((string)$indexName);
        if ($storeId === null) {
            return $result;
        }
        $liveIndexName = $this->searchIndexNameResolver->getIndexName($storeId, SearchIndexNameResolver::DEFAULT_INDEX);

        try {
            // a full reindex's temporary index is created by a task still queued, so read the live index
            $current = $subject->getSettings($liveIndexName);
        } catch (\Exception) {
            $current = [];
        }

        $settings = [
            'separatorTokens' => array_values(array_unique([...($current['separatorTokens'] ?? []), '|'])),
        ];
        // the temporary index starts empty, the live one only needs what it lacks
        if ($liveIndexName !== $indexName || array_intersect_key($current, $settings) != $settings) {
            $subject->updateSettings($indexName, $settings);
        }

        return $result;
    }

    /**
     * @param string $indexName a product index, or the temporary index of a full reindex
     * @return int|null
     */
    private function getProductIndexStoreId(string $indexName): ?int
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeIndexName = $this->searchIndexNameResolver->getIndexName($store->getId(), SearchIndexNameResolver::DEFAULT_INDEX);
            if (in_array($indexName, [$storeIndexName, $storeIndexName . '_' . BaseIndexerHandler::INDEX_SWAP_SUFFIX], true)) {
                return (int)$store->getId();
            }
        }

        return null;
    }
}
