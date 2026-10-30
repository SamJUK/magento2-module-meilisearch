<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\IndexesManager;
use Walkwizus\MeilisearchBase\Service\SettingsManager;

/**
 * Magento's search synonym groups (Marketing > SEO & Search > Search Synonyms) as Meilisearch synonyms, where
 * every word of a group is a synonym of the others. As when Magento searches, a word's groups come from the store
 * view if it has any there, otherwise from the website, otherwise from all store views.
 */
class Synonyms
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param SearchIndexNameResolver $searchIndexNameResolver
     * @param IndexesManager $indexesManager
     * @param SettingsManager $settingsManager
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly SearchIndexNameResolver $searchIndexNameResolver,
        private readonly IndexesManager $indexesManager,
        private readonly SettingsManager $settingsManager
    ) { }

    /**
     * @param int $storeId
     * @return array<string, string[]>
     */
    public function getForStore(int $storeId): array
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('search_synonyms'), ['synonyms', 'store_id', 'website_id'])
            ->where('store_id = ?', $storeId)
            ->orWhere('store_id = 0 AND website_id IN (?)', [0, $websiteId]);

        $byScope = ['store' => [], 'website' => [], 'default' => []];
        foreach ($connection->fetchAll($select) as $row) {
            $scope = (int)$row['store_id'] ? 'store' : ((int)$row['website_id'] ? 'website' : 'default');
            $words = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$row['synonyms'])))));
            foreach ($words as $word) {
                $byScope[$scope][$word] = array_values(array_unique([...($byScope[$scope][$word] ?? []), ...array_diff($words, [$word])]));
            }
        }

        // the most specific scope wins for each word
        return array_filter($byScope['store'] + $byScope['website'] + $byScope['default']);
    }

    /**
     * Pushes the synonyms to every store's product index.
     *
     * @return void
     */
    public function update(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $indexName = $this->searchIndexNameResolver->getIndexName($store->getId(), SearchIndexNameResolver::DEFAULT_INDEX);
            if ($this->indexesManager->indexExists($indexName)) {
                $this->settingsManager->updateSynonyms($indexName, $this->getForStore((int)$store->getId()));
            }
        }
    }
}
