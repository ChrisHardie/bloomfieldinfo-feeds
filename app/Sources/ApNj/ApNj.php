<?php

namespace App\Sources\ApNj;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use ChrisHardie\Feedmaker\Sources\ScraperTrait;
use Illuminate\Support\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class ApNj extends BaseSource
{
    use ScraperTrait;

    /**
     * @throws SourceNotCrawlable
     */
    public function parse(Crawler $crawler, Source $source): RssItemCollection
    {
        $items = array();
        $nodes = $crawler->filter('.Body .Hub .feed .FeedCard');

        foreach ($nodes as $node) {
            $item = new Crawler($node);
            $items[] = array(
                'title' => $item->filter('.CardHeadline h3')->text(),
                'url' => $this->resolveUrl($source, $item->filter('a')->attr('href')),
                'description' => $item->filter('a .content p')->text(''),
                'pubDate' => Carbon::create($item->filter('.CardHeadline div .Timestamp')->attr('data-source'))
            );
        }

        if (0 < $items) {
            return RssItemCollection::make($items);
        }
        throw new SourceNotCrawlable('No news stories', 0, null, $source);
    }
}
