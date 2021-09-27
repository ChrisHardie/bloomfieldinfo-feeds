<?php

namespace App\Sources\NjPublicNotices;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class NjPublicNotices extends BaseSource
{
    /**
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source): RssItemCollection
    {
        // For the initial request, we use a Goutte/Browserkit client because we will need to parse out the Viewstate info.
        $client = new \Goutte\Client();
        $crawler = $client->request('GET', 'https://www.njpublicnotices.com/Search.aspx');

        $viewstateData = $this->getViewstate($crawler);

        // Now that we have the Viewstate data, we build a new HTTP/Guzzle client because the second request
        // is for a special partial HTML chunk that won't be parsable as a DOM.
        $countyPostData = [
            'MIME Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'ctl00$ToolkitScriptManager1'
                => 'ctl00$ContentPlaceHolder1$as1$upSearch|ctl00$ContentPlaceHolder1$as1$lstCounty$6',
            'ctl00$ContentPlaceHolder1$as1$hdnLastScrollPos' => '103',
            'ctl00$ContentPlaceHolder1$as1$hdnCountyScrollPosition' => '-1',
            '__EVENTTARGET' => 'ctl00$ContentPlaceHolder1$as1$lstCounty$6',
            '__ASYNCPOST' => 'true',
            '__LASTFOCUS' => '',
        ];

        $postData = array_merge($countyPostData, $viewstateData, self::getStaticFormFields());

        $response = Http::asForm()
            ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15')
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-MicrosoftAjax' => 'Delta=true',
                'Origin' => 'https://www.njpublicnotices.com',
            ])
            ->post('https://www.njpublicnotices.com/Search.aspx', $postData);

        // In the result of this request, we want to get the new ViewState info.
        $viewstateData2 = $this->parseViewstate($response->body());

        $searchPostData = array(
            'ctl00$ToolkitScriptManager1' => 'ctl00$ContentPlaceHolder1$as1$upSearch|ctl00$ContentPlaceHolder1$as1$btnGo',
            'ctl00$ContentPlaceHolder1$as1$hdnLastScrollPos' => '103',
            'ctl00$ContentPlaceHolder1$as1$hdnCountyScrollPosition' => '#ctl00_ContentPlaceHolder1_as1_lstCounty_6',
            'ctl00$ContentPlaceHolder1$as1$btnGo' => '',
            '__EVENTTARGET' => '',
            '__EVENTARGUMENT' => '',
            '__ASYNCPOST' => true,
            '__LASTFOCUS' => '',
        );

        $postData2 = array_merge($searchPostData, $viewstateData2, self::getStaticFormFields());

        $response2 = Http::asForm()
            ->withoutRedirecting()
            ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15')
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-MicrosoftAjax' => 'Delta=true',
                'Origin' => 'https://www.njpublicnotices.com',
                'Referer' => 'https://www.njpublicnotices.com/Search.aspx',
                'dnt' => 1,
            ])
            ->post('https://www.njpublicnotices.com/Search.aspx', $postData2);


        $noticeNodes = $crawler->filter('.wsResultsGrid tbody td');

        echo "Found " . $noticeNodes->count() . ' nodes.';

        dd($noticeNodes->eq(0)->html());

//        $crawler->submit()
//
//        $county_client = new Client();
//        $county_client->request('POST', $source->source_url, )


//        $items = array();
//        $nodes = $crawler->filter( '.news-items' );
//        foreach ( $nodes as $node ) {
//            ...
//        }

//        return RssItemCollection::make( $items );
    }

    private function getViewstate(Crawler $crawler): array
    {
        $viewstate_array = array();
        $viewstate_array['__VIEWSTATE'] = $crawler->filter('#__VIEWSTATE')->attr('value');
        $viewstate_array['__VIEWSTATEGENERATOR'] = $crawler->filter('#__VIEWSTATEGENERATOR')->attr('value');
        return $viewstate_array;
    }

    private function parseViewstate(string $html): array
    {
        // TODO error checking
        // hiddenField|__VIEWSTATE|...|hiddenField|__VIEWSTATEGENERATOR|...|
        preg_match(
            '/.*hiddenField\|__VIEWSTATE\|(\S+)\|hiddenField\|__VIEWSTATEGENERATOR\|([a-zA-Z0-9]+)\|.*$/',
            $html,
            $matches,
        );

        return [
            '__VIEWSTATE' => $matches[1],
            '__VIEWSTATEGENERATOR' => $matches[2],
        ];
    }

    private static function getStaticFormFields()
    {
        return array(
            'ctl00_ToolkitScriptManager1_HiddenField' => '',
            'ctl00$ContentPlaceHolder1$as1$ddlPopularSearches' => '0',
            'ctl00$ContentPlaceHolder1$as1$txtSearch' => '',
            'ctl00$ContentPlaceHolder1$as1$rdoType' => 'AND',
            'ctl00$ContentPlaceHolder1$as1$txtExclude' => '',
            'ctl00$ContentPlaceHolder1$as1$hdnCityScrollPosition' => '-1',
            'ctl00$ContentPlaceHolder1$as1$hdnPubScrollPosition' => '-1',
            'ctl00$ContentPlaceHolder1$as1$hdnField' => '',
            'ctl00$ContentPlaceHolder1$as1$lstCounty$6' => 'on',
            'ctl00$ContentPlaceHolder1$as1$dateRange' => 'rbLastNumDays',
            'ctl00$ContentPlaceHolder1$as1$txtLastNumDays' => '60',
            'ctl00$ContentPlaceHolder1$as1$txtLastNumWeeks' => '52',
            'ctl00$ContentPlaceHolder1$as1$txtLastNumMonths' => '12',
            'ctl00$ContentPlaceHolder1$as1$txtDateFrom' => '9/13/2021',
            'ctl00$ContentPlaceHolder1$as1$txtDateTo' => '9/27/2021',
            'ctl00$ContentPlaceHolder1$as1$txtSSID' => '0',
        );
    }
}
