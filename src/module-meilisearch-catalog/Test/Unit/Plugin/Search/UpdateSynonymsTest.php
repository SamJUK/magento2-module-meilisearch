<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Plugin\Search;

use Magento\Search\Api\SynonymGroupRepositoryInterface;
use Magento\Search\Model\EngineResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Walkwizus\MeilisearchCatalog\Model\Config;
use Walkwizus\MeilisearchCatalog\Model\Synonyms;
use Walkwizus\MeilisearchCatalog\Plugin\Search\UpdateSynonyms;

class UpdateSynonymsTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function cases(): array
    {
        return [
            'meilisearch, enabled' => ['meilisearch', true, true],
            'meilisearch, disabled' => ['meilisearch', false, false],
            'other engine' => ['opensearch', true, false],
        ];
    }

    #[DataProvider('cases')]
    public function testSynonymsArePushedOnceAfterSavesAndDeletes(string $engine, bool $enabled, bool $pushed): void
    {
        $synonyms = $this->createMock(Synonyms::class);
        $synonyms->expects(self::exactly($pushed ? 1 : 0))->method('update');
        $plugin = $this->plugin($synonyms, $engine, $enabled, $this->createMock(LoggerInterface::class));
        $repository = $this->createMock(SynonymGroupRepositoryInterface::class);

        self::assertSame('saved', $plugin->afterSave($repository, 'saved'));
        self::assertTrue($plugin->afterDelete($repository, true));
        self::assertTrue($plugin->afterDelete($repository, true));
        $plugin->pushPending();
        $plugin->pushPending();
    }

    public function testAFailedPushDoesNotFailTheSave(): void
    {
        $synonyms = $this->createMock(Synonyms::class);
        $synonyms->method('update')->willThrowException(new \RuntimeException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $plugin = $this->plugin($synonyms, 'meilisearch', true, $logger);

        self::assertSame('saved', $plugin->afterSave($this->createMock(SynonymGroupRepositoryInterface::class), 'saved'));
        $plugin->pushPending();
    }

    private function plugin(Synonyms $synonyms, string $engine, bool $enabled, LoggerInterface $logger): UpdateSynonyms
    {
        $engineResolver = $this->createMock(EngineResolver::class);
        $engineResolver->method('getCurrentSearchEngine')->willReturn($engine);
        $config = $this->createMock(Config::class);
        $config->method('isMagentoSynonyms')->willReturn($enabled);

        return new UpdateSynonyms($synonyms, $config, $engineResolver, $logger);
    }
}
