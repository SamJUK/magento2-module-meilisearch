<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Plugin\Search;

use Magento\Search\Api\SynonymGroupRepositoryInterface;
use Magento\Search\Model\EngineResolver;
use Psr\Log\LoggerInterface;
use Walkwizus\MeilisearchBase\Model\ResourceModel\Engine;
use Walkwizus\MeilisearchCatalog\Model\Config;
use Walkwizus\MeilisearchCatalog\Model\Synonyms;

/**
 * Other engines read synonyms when searching, so a change applies at once. Meilisearch keeps them on the index,
 * so they are pushed once at the end of the request: a mass delete removes the groups one at a time.
 */
class UpdateSynonyms
{
    /**
     * @var bool
     */
    private bool $pending = false;

    /**
     * @param Synonyms $synonyms
     * @param Config $config
     * @param EngineResolver $engineResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Synonyms $synonyms,
        private readonly Config $config,
        private readonly EngineResolver $engineResolver,
        private readonly LoggerInterface $logger
    ) { }

    /**
     * @param SynonymGroupRepositoryInterface $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterSave(SynonymGroupRepositoryInterface $subject, mixed $result): mixed
    {
        $this->update();

        return $result;
    }

    /**
     * @param SynonymGroupRepositoryInterface $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterDelete(SynonymGroupRepositoryInterface $subject, mixed $result): mixed
    {
        $this->update();

        return $result;
    }

    /**
     * @return void
     */
    public function pushPending(): void
    {
        if (!$this->pending) {
            return;
        }
        $this->pending = false;
        try {
            $this->synonyms->update();
        } catch (\Exception $e) {
            // the synonym group is saved either way, and the next reindex pushes it
            $this->logger->error($e);
        }
    }

    /**
     * @return void
     */
    private function update(): void
    {
        if ($this->pending
            || $this->engineResolver->getCurrentSearchEngine() !== Engine::SEARCH_ENGINE
            || !$this->config->isMagentoSynonyms()
        ) {
            return;
        }
        $this->pending = true;
        register_shutdown_function([$this, 'pushPending']);
    }
}
