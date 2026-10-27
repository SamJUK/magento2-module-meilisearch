<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchSearchAdapter\Plugin\Service;

use Meilisearch\Contracts\Task;
use Walkwizus\MeilisearchBase\Service\SettingsManager;

/**
 * Runs whenever the indexer pushes filterable attributes. Layered navigation needs exact totals and every
 * price value for its buckets, so the index limits are raised. Magento's sort orders are strict, so on the
 * product index the sort ranking rule moves ahead of the relevance rules, keeping the admin's order otherwise.
 */
class AdjustIndexSettings
{
    private const DEFAULT_RANKING_RULES = ['words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness'];

    /**
     * @param int $maxValuesPerFacet
     * @param int $maxTotalHits
     */
    public function __construct(
        private readonly int $maxValuesPerFacet = 10000,
        private readonly int $maxTotalHits = 100000
    ) { }

    /**
     * @param SettingsManager $subject
     * @param Task $result
     * @param $indexName
     * @return Task
     * @throws \Exception
     */
    public function afterUpdateFilterableAttributes(SettingsManager $subject, Task $result, $indexName): Task
    {
        $settings = [
            'faceting' => ['maxValuesPerFacet' => $this->maxValuesPerFacet],
            'pagination' => ['maxTotalHits' => $this->maxTotalHits],
        ];

        if (str_contains((string)$indexName, 'catalog_product')) {
            try {
                $rules = $subject->getSettings($indexName)['rankingRules'] ?? self::DEFAULT_RANKING_RULES;
            } catch (\Exception) {
                // a full reindex's temporary index is created by the task just queued, so it may not exist yet
                $rules = self::DEFAULT_RANKING_RULES;
            }
            $settings['rankingRules'] = array_values(array_unique(['sort', ...$rules]));
        }

        $subject->updateSettings($indexName, $settings);

        return $result;
    }
}
