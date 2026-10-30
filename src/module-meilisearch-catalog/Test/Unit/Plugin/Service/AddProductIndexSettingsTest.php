<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Plugin\Service;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meilisearch\Contracts\Task;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\SettingsManager;
use Walkwizus\MeilisearchCatalog\Plugin\Service\AddProductIndexSettings;

class AddProductIndexSettingsTest extends TestCase
{
    private SettingsManager&MockObject $settingsManager;
    private AddProductIndexSettings $plugin;
    private Task $task;

    protected function setUp(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $nameResolver = $this->createMock(SearchIndexNameResolver::class);
        $nameResolver->method('getIndexName')->willReturn('products_1');

        $this->settingsManager = $this->createMock(SettingsManager::class);
        $this->plugin = new AddProductIndexSettings($storeManager, $nameResolver);
        $this->task = Task::fromArray(['taskUid' => 1, 'status' => 'enqueued', 'type' => 'settingsUpdate', 'enqueuedAt' => '2026-01-01T00:00:00Z']);
    }

    public function testPipeIsAddedToTheSeparatorsOfTheLiveIndex(): void
    {
        $this->settingsManager->method('getSettings')->with('products_1')->willReturn(['separatorTokens' => ['&']]);
        $this->settingsManager->expects(self::once())
            ->method('updateSettings')
            ->with('products_1_tmp', ['separatorTokens' => ['&', '|']])
            ->willReturn($this->task);

        self::assertSame($this->task, $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'products_1_tmp'));
    }

    public function testSettingsAlreadyInPlaceAreNotPushedAgain(): void
    {
        $this->settingsManager->method('getSettings')->willReturn(['separatorTokens' => ['|']]);
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'products_1');
    }

    public function testTemporaryIndexAlwaysGetsTheSettings(): void
    {
        $this->settingsManager->method('getSettings')->willReturn(['separatorTokens' => ['|']]);
        $this->settingsManager->expects(self::once())
            ->method('updateSettings')
            ->with('products_1_tmp', ['separatorTokens' => ['|']])
            ->willReturn($this->task);

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'products_1_tmp');
    }

    public function testOtherIndexesAreLeftAlone(): void
    {
        $this->settingsManager->expects(self::never())->method('getSettings');
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'categories_1');
    }
}
