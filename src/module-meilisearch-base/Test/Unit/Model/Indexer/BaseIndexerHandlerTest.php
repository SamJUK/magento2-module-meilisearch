<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchBase\Test\Unit\Model\Indexer;

use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Framework\Search\Request\Dimension;
use Magento\Search\Model\EngineResolver;
use Meilisearch\Contracts\Task;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchAi\Model\ResourceModel\Embedder\CollectionFactory as EmbedderCollectionFactory;
use Walkwizus\MeilisearchAi\Model\ResourceModel\Embedder\Link as EmbedderLinkResource;
use Walkwizus\MeilisearchBase\Model\AttributeMapper;
use Walkwizus\MeilisearchBase\Model\AttributeProvider;
use Walkwizus\MeilisearchBase\Model\Indexer\BaseIndexerHandler;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\DocumentsManager;
use Walkwizus\MeilisearchBase\Service\HealthManager;
use Walkwizus\MeilisearchBase\Service\IndexesManager;
use Walkwizus\MeilisearchBase\Service\SettingsManager;

class BaseIndexerHandlerTest extends TestCase
{
    private SettingsManager&MockObject $settingsManager;
    private BaseIndexerHandler $handler;
    private Dimension $dimension;

    protected function setUp(): void
    {
        $this->settingsManager = $this->createMock(SettingsManager::class);
        foreach (['updateFilterableAttributes', 'updateSortableAttributes', 'updateSearchableAttributes', 'resetEmbedders'] as $method) {
            $this->settingsManager->method($method)->willReturn($this->task());
        }
        $nameResolver = $this->createMock(SearchIndexNameResolver::class);
        $nameResolver->method('getIndexMapping')->willReturn('catalog_product');
        $nameResolver->method('getIndexName')->willReturn('products');
        $indexesManager = $this->createMock(IndexesManager::class);
        $indexesManager->method('indexExists')->willReturn(true);
        $indexesManager->method('deleteIndex')->willReturn($this->task());
        $batch = $this->createMock(Batch::class);
        $batch->method('getItems')->willReturn(new \ArrayIterator([]));

        $this->handler = new BaseIndexerHandler(
            $this->createMock(EngineResolver::class),
            $this->settingsManager,
            $indexesManager,
            $this->createMock(DocumentsManager::class),
            $this->createMock(HealthManager::class),
            $nameResolver,
            $batch,
            'catalogsearch_fulltext',
            $this->createMock(AttributeMapper::class),
            $this->createMock(AttributeProvider::class),
            $this->createMock(EmbedderLinkResource::class),
            $this->createMock(EmbedderCollectionFactory::class)
        );
        $this->dimension = new Dimension('scope', '1');
    }

    public function testFullReindexKeepsTheLiveIndexSettings(): void
    {
        $this->settingsManager->method('getSettings')->with('products')->willReturn([
            'synonyms' => ['tee' => ['t-shirt']],
            'stopWords' => ['the'],
            'searchableAttributes' => ['name'],
            'dictionary' => [],
        ]);
        $this->settingsManager->expects(self::once())
            ->method('updateSettings')
            ->with('products_tmp', ['synonyms' => ['tee' => ['t-shirt']], 'stopWords' => ['the']])
            ->willReturn($this->task());

        $this->handler->cleanIndex([$this->dimension]);
        $this->handler->saveIndex([$this->dimension], new \ArrayIterator([]));
    }

    public function testFirstFullReindexHasNothingToKeep(): void
    {
        $this->settingsManager->expects(self::once())->method('getSettings')
            ->willThrowException(new \RuntimeException('Index not found'));
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->handler->cleanIndex([$this->dimension]);
        $this->handler->saveIndex([$this->dimension], new \ArrayIterator([]));
    }

    public function testDefaultSettingsAreNotCopied(): void
    {
        $this->settingsManager->method('getSettings')->willReturn(['synonyms' => [], 'stopWords' => [], 'searchableAttributes' => ['*']]);
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->handler->cleanIndex([$this->dimension]);
        $this->handler->saveIndex([$this->dimension], new \ArrayIterator([]));
    }

    public function testAFailedCopyDoesNotStopTheReindex(): void
    {
        $this->settingsManager->method('getSettings')->willReturn(['stopWords' => ['the']]);
        $this->settingsManager->method('updateSettings')->willThrowException(new \RuntimeException('Bad Request'));
        $this->settingsManager->expects(self::once())->method('updateFilterableAttributes')
            ->with('products_tmp')
            ->willReturn($this->task());

        $this->handler->cleanIndex([$this->dimension]);
        $this->handler->saveIndex([$this->dimension], new \ArrayIterator([]));
    }

    public function testPartialReindexLeavesSettingsAlone(): void
    {
        $this->settingsManager->expects(self::never())->method('getSettings');
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->handler->saveIndex([$this->dimension], new \ArrayIterator([]));
    }

    private function task(): Task
    {
        return Task::fromArray(['taskUid' => 1, 'status' => 'enqueued', 'type' => 'settingsUpdate', 'enqueuedAt' => '2026-01-01T00:00:00Z']);
    }
}
