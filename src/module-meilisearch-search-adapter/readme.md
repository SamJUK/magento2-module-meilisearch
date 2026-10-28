# Walkwizus_MeilisearchSearchAdapter

Magento search adapter for the `meilisearch` engine. Magento's own search requests (category and search pages with the theme's layered navigation, advanced search, REST `/V1/search`, GraphQL `products`) run against the `catalog_product` index, so Luma, Hyvä or any theme that uses core search works without the Meilisearch frontend. Disable `Walkwizus_MeilisearchFrontend` (and `Walkwizus_MeilisearchMerchandising`, which depends on it) to use the theme's own listing.

**Purpose**
- Registers `SearchAdapter\Adapter` for `meilisearch` in `Magento\Search\Model\AdapterFactory` and translates each request into one Meilisearch search: match queries on every field become the search text, term and range filters become the filter expression, buckets become facets.
- Text on named fields (advanced search, GraphQL `name: {match}`, wildcard filters) is a condition of its own, as in core: one multi-search per request resolves every such condition to product ids. A plain match needs any of its words, a phrase prefix match or a wildcard all of them, and a field the index cannot search matches nothing. Words split as in core (apostrophes, underscores and dots inside a word stay in it), a single letter matches only as a whole word (except as the last word of a phrase prefix), and only the first ten distinct words count.
- A fully quoted search term is a phrase; other quotes and leading `-` are dropped, so Meilisearch's syntax is not exposed.
- Checks each field against the index's own settings (cached for 60 seconds). Buckets and sorts on fields the index cannot facet or sort are left out; a filter on one matches nothing, as in OpenSearch. This keeps pages working when the request is ahead of the index, for example a customer group created since the last full reindex.
- Indexes `<code>_ids` option fields next to the catalog's label fields for every select, multiselect and boolean attribute in search requests, because Magento filters and buckets option attributes by ID. Sorting on an option attribute uses its label, as core does.
- Resolves `url_key` filters (the GraphQL product page lookup) to product IDs from the database, because the index holds the product's URL path rather than the attribute.
- Builds price buckets with Magento's auto and manual algorithms from the price facet of the same search.
- Registers `meilisearch` with the layered navigation, advanced search and suggestion providers from `Magento_Elasticsearch`, which it depends on.

**Index settings**
- Raises `faceting.maxValuesPerFacet` (10000) and `pagination.maxTotalHits` (100000) when filterable attributes are pushed, so totals and price buckets are exact. Change them through the `maxValuesPerFacet` and `maxTotalHits` arguments of `Walkwizus\MeilisearchSearchAdapter\Plugin\Service\AdjustIndexSettings`.
- Moves the `sort` ranking rule first on the product index, so an explicit sort (price, name) is strict on search results too. The rest of the order is kept. Sorting stops at relevance, so core's `entity_id` tiebreak never overrides it.

**Not supported**
- The "improved" price navigation algorithm falls back to auto.
- Decimal and date attributes are not indexed as filterable, so their layered navigation filters do not appear.
- Position sorting uses the category filtered on (the first one if several), or the store's root category.
- Ascending relevance sorts the same as descending.
- Wildcard (`like`) filters match whole words, not substrings.
- Results matched only on named fields (advanced search) are not ordered by relevance.
- Words on named fields match as in the main search: with typos and as prefixes, without stemming or word order. Meilisearch splits a joined word (`Hero.H`) again and matches its last part as a prefix.
- A request resolves at most 10,000 product ids for its named-field conditions, shared between their words; Meilisearch slows sharply on longer id lists. Products past that are left out and a warning is logged.
- Pages past `pagination.maxTotalHits` are empty.
