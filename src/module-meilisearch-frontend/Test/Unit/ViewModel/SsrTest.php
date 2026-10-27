<?php

declare(strict_types=1);

namespace Walkwizus\MeilisearchFrontend\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Meilisearch\Contracts\HybridSearchOptions;
use Meilisearch\Contracts\MultiSearchQueryFactory;
use PHPUnit\Framework\TestCase;
use Walkwizus\MeilisearchBase\Service\SearchManager;
use Walkwizus\MeilisearchFrontend\Model\ConfigProvider;
use Walkwizus\MeilisearchFrontend\ViewModel\Ssr;

class SsrTest extends TestCase
{
    private const HASH = '0123456789abcdef0123456789abcdef';

    public function testProductImageUsesResizedCacheUrl(): void
    {
        self::assertSame(
            'https://example.com/media/catalog/product/cache/' . self::HASH . '/w/b/wb04.jpg',
            $this->createSsr(self::HASH)->getProductImage('/w/b/wb04.jpg')
        );
    }

    public function testPlaceholderIsNeverServedFromCache(): void
    {
        self::assertSame(
            'https://example.com/media/catalog/product/placeholder/stores/2/ph.jpg',
            $this->createSsr(self::HASH)->getProductImage('/placeholder/stores/2/ph.jpg')
        );
    }

    public function testProductImageFallsBackToOriginalWithoutHash(): void
    {
        self::assertSame(
            'https://example.com/media/catalog/product/w/b/wb04.jpg',
            $this->createSsr(null)->getProductImage('/w/b/wb04.jpg')
        );
    }

    private function createSsr(?string $hash): Ssr
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('get')->willReturn([
            'mediaBaseUrl' => 'https://example.com/media/catalog/product/',
            'images' => ['category_page_grid' => ['hash' => $hash]],
        ]);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnArgument(1);

        return new Ssr(
            $request,
            $this->createMock(MultiSearchQueryFactory::class),
            $this->createMock(HybridSearchOptions::class),
            $this->createMock(SearchManager::class),
            $configProvider,
            $this->createMock(PriceCurrencyInterface::class)
        );
    }
}
