<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchCatalog\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchBase\SearchAdapter\SearchIndexNameResolver;
use Walkwizus\MeilisearchBase\Service\IndexesManager;
use Walkwizus\MeilisearchBase\Service\SettingsManager;
use Walkwizus\MeilisearchCatalog\Model\Synonyms;

class SynonymsTest extends TestCase
{
    public function testEachWordTakesItsGroupsFromTheMostSpecificScope(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('orWhere')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([
            ['synonyms' => 'tee, t-shirt', 'store_id' => 0, 'website_id' => 0],
            ['synonyms' => 'jumper,sweater,jumper', 'store_id' => 0, 'website_id' => 0],
            ['synonyms' => 'jumper,pullover', 'store_id' => 0, 'website_id' => 2],
            ['synonyms' => 'pants,trousers', 'store_id' => 0, 'website_id' => 2],
            ['synonyms' => 'pants,underwear', 'store_id' => 5, 'website_id' => 0],
            ['synonyms' => 'lonely', 'store_id' => 5, 'website_id' => 0],
        ]);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(5)->willReturn($store);

        $synonyms = new Synonyms(
            $resourceConnection,
            $storeManager,
            $this->createMock(SearchIndexNameResolver::class),
            $this->createMock(IndexesManager::class),
            $this->createMock(SettingsManager::class)
        );

        self::assertEqualsCanonicalizing(
            [
                'pants' => ['underwear'],
                'underwear' => ['pants'],
                'jumper' => ['pullover'],
                'pullover' => ['jumper'],
                'trousers' => ['pants'],
                'tee' => ['t-shirt'],
                't-shirt' => ['tee'],
                'sweater' => ['jumper'],
            ],
            $synonyms->getForStore(5)
        );
    }
}
