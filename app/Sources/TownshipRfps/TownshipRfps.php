<?php

namespace App\Sources\TownshipRfps;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use ChrisHardie\Feedmaker\Sources\ScraperTrait;
use Illuminate\Support\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class TownshipRfps extends BaseSource
{
    use ScraperTrait;

    /**
     * @param Crawler $crawler
     * @param Source  $source
     * @return RssItemCollection
     */
    public function parse(Crawler $crawler, Source $source): RssItemCollection
    {
        $items = array();
        $nodes = $crawler->filter('.pageContent .relatedDocuments .semanticList li');

        foreach ($nodes as $node) {
            $item = new Crawler($node);
            $items[] = array(
                'title' => $item->filter('a')->text(),
                'url' => $this->resolveUrl($source, $item->filter('a')->attr('href')),
                'description' => '',
                'pubDate' => Carbon::now(),
            );
        }

        // Sometimes this page is empty, so we do not throw an exception when there are no items.
        return RssItemCollection::make($items);
    }
}
