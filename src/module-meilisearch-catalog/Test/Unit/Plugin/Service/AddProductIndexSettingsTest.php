<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Plugin\Service;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meilisearch\Contracts\Task;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\SettingsManager;
use Walkwizus\MeilisearchCatalog\Model\Config;
use Walkwizus\MeilisearchCatalog\Model\Synonyms;
use Walkwizus\MeilisearchCatalog\Plugin\Service\AddProductIndexSettings;

class AddProductIndexSettingsTest extends TestCase
{
    private SettingsManager&MockObject $settingsManager;
    private Config&MockObject $config;
    private Synonyms&MockObject $synonyms;
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
        $this->config = $this->createMock(Config::class);
        $this->synonyms = $this->createMock(Synonyms::class);
        $this->plugin = new AddProductIndexSettings($storeManager, $nameResolver, $this->config, $this->synonyms);
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

    /**
     * @return array<string, array{string, array, array, bool}>
     */
    public static function synonymCases(): array
    {
        $magento = ['tee' => ['t-shirt']];

        return [
            'live index, changed' => ['products_1', ['old' => ['older']], $magento, true],
            'live index, unchanged' => ['products_1', $magento, $magento, false],
            'live index, all removed' => ['products_1', ['old' => ['older']], [], true],
            'temporary index' => ['products_1_tmp', $magento, $magento, true],
            'temporary index, none anywhere' => ['products_1_tmp', [], [], false],
        ];
    }

    #[DataProvider('synonymCases')]
    public function testMagentoSynonymsArePushedWhenEnabled(string $indexName, array $current, array $magento, bool $pushed): void
    {
        $this->config->method('isMagentoSynonyms')->willReturn(true);
        $this->synonyms->method('getForStore')->with(1)->willReturn($magento);
        $this->settingsManager->method('getSettings')->willReturn(['separatorTokens' => ['|'], 'synonyms' => $current]);
        $this->settingsManager->method('updateSettings')->willReturn($this->task);
        $this->settingsManager->expects($pushed ? self::once() : self::never())
            ->method('updateSynonyms')
            ->with($indexName, $magento)
            ->willReturn($this->task);

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, $indexName);
    }

    public function testSynonymsAreLeftAloneWhenDisabled(): void
    {
        $this->settingsManager->method('getSettings')->willReturn(['separatorTokens' => ['|'], 'synonyms' => ['old' => ['older']]]);
        $this->synonyms->expects(self::never())->method('getForStore');
        $this->settingsManager->expects(self::never())->method('updateSynonyms');

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'products_1');
    }

    public function testOtherIndexesAreLeftAlone(): void
    {
        $this->settingsManager->expects(self::never())->method('getSettings');
        $this->settingsManager->expects(self::never())->method('updateSettings');

        $this->plugin->afterUpdateSearchableAttributes($this->settingsManager, $this->task, 'categories_1');
    }
}
