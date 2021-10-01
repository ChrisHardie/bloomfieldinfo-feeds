<?php

namespace App\Sources\NjPublicNotices;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Goutte\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

class NjPublicNotices extends BaseSource
{
    /**
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source): RssItemCollection
    {
        $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15';
        $base_url = 'https://www.njpublicnotices.com';

        $client = new Client(HttpClient::create(array(
            'headers' => array(
                'user-agent' => $user_agent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Referer' => $base_url . '/Search.aspx',
            ),
        )));
        $client->setServerParameter('HTTP_USER_AGENT', $user_agent);

        $crawler = $client->request('GET', $base_url . '/authenticate.aspx');

        $response = $client->getInternalResponse();
        if (200 !== $response->getStatusCode()) {
            throw new SourceNotCrawlable('Cannot get login form', 0, null, $source);
        }

        $form = $crawler->selectButton('Login')->form();
        $crawler = $client->submit(
            $form,
            array(
                'ctl00$ContentPlaceHolder1$AuthenticateIPA1$txtEmailAddress'
                    => config('bloomfieldfeeds.source_auth.njpublicnotices_username'),
                'ctl00$ContentPlaceHolder1$AuthenticateIPA1$txtPassword'
                    => config('bloomfieldfeeds.source_auth.njpublicnotices_password'),
            )
        );

        $response = $client->getInternalResponse();
        if (200 !== $response->getStatusCode()) {
            throw new SourceNotCrawlable('Cannot login', 0, null, $source);
        }

        $crawler = $client->request('GET', $base_url .'/Search.aspx?SSID=8028');

        $response = $client->getInternalResponse();
        if (200 !== $response->getStatusCode()) {
            throw new SourceNotCrawlable('Cannot get saved search', 0, null, $source);
        }

        $nodes = $crawler->filter('.wsResultsGrid tr');

        if (0 === $nodes->count()) {
            throw new SourceNotCrawlable('No public notice results', 0, null, $source);
        }

        $items = array();
        foreach ($nodes as $node) {
            $row = new Crawler($node);

            // See if we can fetch the table row that has the notice ID and date
            $link_row = $row->filterXPath('//td//table[@class="nested"]//tr')->first();
            if (! $link_row->count()) {
                continue;
            }

            // Get the notice ID and Date out
            $notice_id = $link_row->filterXPath('//td//input[contains(@name, "hdnPKValue")]')->attr('value');
            $pub_date = $link_row->filterXPath('//td')->last()->filterXpath('//div[@class="left"]//span')->text();
            $pub_date = preg_replace('/Posted: /', '', $pub_date);

            if (! empty($notice_id)) {
                $url = $base_url . '/Details.aspx?ID=' . $notice_id;
            } else {
                Log::debug('Cannot find notice ID on NJ Public Notice parsing');
                $url = $base_url .'/Search.aspx#searchResults';
            }

            // Get the row that has the description/title
            $description_row = $row->filterXPath('//td//table[@class="nested"]//tr')->last();
            $description = $description_row->filter('td')->text('No description available');

            $items[] = array(
                'title' => Str::limit($description, 55),
                'description' => $description,
                'pubDate' => Carbon::createFromFormat('!m/d/Y', $pub_date, 'America/New_York'),
                'url' => $url,
            );
        }

        return RssItemCollection::make($items);
    }
}
