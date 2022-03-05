<?php

namespace App\Sources\BloomfieldSchoolJobs;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class BloomfieldSchoolJobs extends BaseSource
{
    private $base_details_view_url = 'https://www.applitrack.com/bloomfield/onlineapp/default.aspx';

    /**
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source): RssItemCollection
    {
        try {
            $javascript_response = $this->getUrl($source, $source->source_url);
        } catch (\Exception $e) {
            throw new SourceNotCrawlable(
                'Problem fetching source Javascript',
                0,
                $e,
                $source
            );
        }

        // Try to extract the job data from the ridiculous JS structure
        $shards = explode('document.write(\'', $javascript_response);
        if (! isset($shards[1])) {
            throw new SourceNotCrawlable('Cannot find expected javascript structure', 0, null, $source);
        }
        $more_shards = explode('\');', $shards[1]);
        $jobs_raw = $more_shards[0];

        $jobs_node = new Crawler(stripslashes($jobs_raw));
        $jobs = $jobs_node
            ->filterXPath('//div[@id="AppliTrackListContent"]/table[@class="AppliTrackPostingTable"]/tbody/tr');

        $items = array();
        foreach ($jobs as $i => $job_node) {
            // The page contains two <tr>s for each job, so we skip all odd numbered rows.
            if ($i % 2 !== 0) {
                continue;
            }

            $job = new Crawler($job_node);

            // The job ID is buried in the javascript that overrides the default link behavior
            $url_js = $job->filter('td .title a')->attr('href');
            $job_id = Str::of($url_js)->match('/AppliTrackJobId%3D(\d+)%26/');

            // Build the job details link
            $details_view_url = $this->base_details_view_url . '?' . Arr::query([
                'all' => 1,
                'AppliTrackZipRadius' => 5,
                'AppliTrackSort' => 'newest',
                'AppliTrackJobId' => (string) $job_id,
                'AppliTrackLayoutMode' => 'detail',
                'AppliTrackViewPosting' => 1,
            ]);

            // Add the job to the final array of items that will be turned into RSS
            $items[] = array(
                'title' => $job->filter('td .title')->innerText(),
                'url' => $details_view_url,
                'guid' => $job_id,
                'description' => null,
                'pubDate' => Carbon::createFromFormat('n/j/Y', $job->filter('td')->eq(1)->text()),
            );
        }

        return RssItemCollection::make($items);
    }
}
