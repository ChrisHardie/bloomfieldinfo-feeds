<?php

namespace App\Sources\WnycNjpr;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Illuminate\Support\Carbon;

class WnycNjpr extends BaseSource
{
    /**
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source): RssItemCollection
    {
        try {
            $report_response = $this->getUrl($source, $source->source_url);
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

        if (! empty($results['included']) && (0 < count($results['included']))) {
            foreach ($results['included'] as $story) {
                if (! empty($story['attributes'])) {
                    $attrs = $story['attributes'];
                    if (! empty($attrs['title']) && ! empty($attrs['newsdate']) && ! empty($attrs['url'])) {
                        $items[] = array(
                            'title' => $attrs['title'],
                            'pubDate' => Carbon::create($attrs['newsdate']),
                            'url' => $attrs['url'],
                            'description' => $attrs['body'],
                        );
                    }

                }
            }
            if (0 < $items) {
                return RssItemCollection::make($items);
            }
        }
        throw new SourceNotCrawlable('No news stories', 0, null, $source);
    }
}
