<?php

namespace App\Sources\NjcomEssex;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;


class NjcomEssex extends BaseSource
{
    /**
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source): RssItemCollection
    {
        $params = array(
            'd' => 644,
            '_website' => 'nj',
            'query' => '{"limit":12,"offset":0,"section":"essex"}',
        );

        $query_url = $source->source_url . '?' . Arr::query($params);

        try {
            $report_response = $this->getUrl($source, $query_url);
            return $this->apiResultsToRssItems($report_response->json(), $source);
        } catch (\Exception $e) {
            throw new SourceNotCrawlable(
                'Problem parsing source HTML',
                0,
                $e,
                $source
            );
        }
    }

    /**
     * @param array  $results
     * @param Source $source
     * @return RssItemCollection
     * @throws SourceNotCrawlable
     */
    private function apiResultsToRssItems(array $results, Source $source): RssItemCollection
    {
        $items = array();

        foreach ($results as $story) {
            $items[] = array(
                'pubDate' => Carbon::create($story['display_date']),
                'title' => $story['headlines']['basic'],
                'url' => $this->resolveUrl($source, $story['website_url']),
                'description' => $story['description']['basic'],
            );
        }

        if (0 < $items) {
                return RssItemCollection::make($items);
        }
        throw new SourceNotCrawlable('No news stories', 0, null, $source);
    }
}
