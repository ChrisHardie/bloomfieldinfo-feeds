<?php

namespace App\Sources\PatchBloomfield;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use ChrisHardie\Feedmaker\Sources\ScraperTrait;
use Illuminate\Support\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class PatchBloomfield extends BaseSource
{
    use ScraperTrait;

    /**
     * @throws SourceNotCrawlable
     */
    public function parse(Crawler $crawler, Source $source): RssItemCollection
    {
        $items = array();
        $nodes = $crawler->filter('#articletop-content-section article');

        foreach ($nodes as $node) {
            $item = new Crawler($node);

            $items[] = array(
                'title' => $item->filter('h2 a')->text(),
                'url' => $this->resolveUrl($source, $item->filter('h2 a')->attr('href')),
                'description' => $item->filter('.styles_Card__Description__2bK90')->text(),
                'pubDate' => Carbon::create($item->filter('h6 time')->attr('datetime'))
            );
        }

        if (0 < $items) {
            return RssItemCollection::make($items);
        }
        throw new SourceNotCrawlable('No news stories', 0, null, $source);
    }
}
