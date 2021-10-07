<?php

namespace App\Sources\WbmaTv;

use ChrisHardie\Feedmaker\Exceptions\SourceNotCrawlable;
use ChrisHardie\Feedmaker\Sources\BaseSource;
use ChrisHardie\Feedmaker\Sources\RssItemCollection;
use ChrisHardie\Feedmaker\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class WbmaTv extends BaseSource
{
    public array $feedItems = [];

    /**
     * @param Source $source
     * @return RssItemCollection
     * @throws SourceNotCrawlable
     */
    public function generateRssItems(Source $source) : RssItemCollection
    {
        $playlists = self::getPlaylistData();

        foreach ($playlists as $playlistId => $playlistName) {
            if (! is_int($playlistId)) {
                continue;
            }

            // https://connect.telvue.com/api/vod/playlists/6621.json?player_access_key=...
            $playlistUrl = sprintf(
                '%s/playlists/%d.json?player_access_key=%s',
                $source->source_url,
                $playlistId,
                config('bloomfieldfeeds.source_misc.wbmatv_access_key')
            );

            try {
                $response = HTTP::withHeaders([
                        'Referer' => 'https://videoplayer.telvue.com/',
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15', // phpcs:ignore
                        'Connect-Preshared-Key' => config('bloomfieldfeeds.source_misc.wbmatv_preshared_key'),
                    ])
                    ->get($playlistUrl);
                $this->reportResultsToArray($response->json(), $source);
            } catch (\Exception $e) {
                throw new SourceNotCrawlable(
                    'Problem parsing source JSON',
                    0,
                    $e,
                    $source
                );
            }
        }

        return RssItemCollection::make($this->feedItems);
    }

    /**
     * Take the playlist results and convert them into an array of videos, getting some detail along the way
     * @param array  $results
     * @param Source $source
     * @return void
     * @throws SourceNotCrawlable
     */
    private function reportResultsToArray(array $results, Source $source): void
    {
        if (! empty($results['collection_items_count']) && (0 < $results['collection_items_count'])) {
            $i = 0;
            foreach ($results['collection_items'] as $result) {
                // Get only the X most recent videos from the playlist
                if (config('bloomfieldfeeds.source_misc.wbmatv_vids_per_playlist') < $i) {
                    break;
                }
                if (null === $result) {
                    continue;
                }

                $i++;

                $description = $result['short_summary']
                    . $this->getDescriptionForVideo($source, $result['id']);

                $this->feedItems[] = array(
                    'pubDate' => Carbon::parse($result['created_at'])
                        ->setTimezone('UTC'),
                    'title' => $result['title'],
                    'url' => sprintf(
                        'https://videoplayer.telvue.com/player/%s/media/%d?autostart=true&showtabssearch=true',
                        config('bloomfieldfeeds.source_misc.wbmatv_access_key'),
                        $result['id'],
                    ),
                    'description' => $description,
                );
            }
            return;
        }

        throw new SourceNotCrawlable('No videos in playlist', 0, null, $source);
    }

    /**
     * Generate additional RSS item description with the video direct link if available
     * @throws SourceNotCrawlable
     * @returns string
     */
    private function getDescriptionForVideo(Source $source, int $videoId): string
    {
        // Collect some details about the video by making another fetch
        $videoDetailsUrl = sprintf(
            'https://connect.telvue.com/api/vod/media/%s.json?player_access_key=%s',
            $videoId,
            config('bloomfieldfeeds.source_misc.wbmatv_access_key')
        );
        try {
            $detailsResponse = HTTP::withHeaders([
                    'Referer' => 'https://videoplayer.telvue.com/',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15', // phpcs:ignore
                    'Connect-Preshared-Key' => config('bloomfieldfeeds.source_misc.wbmatv_preshared_key'),
                ])
                ->get($videoDetailsUrl)
                ->json();
        } catch (\Exception $e) {
            throw new SourceNotCrawlable(
                'Problem getting video details API result',
                0,
                $e,
                $source
            );
        }

        if (! empty($detailsResponse['sources'][1]['url']) && ! empty($detailsResponse['download_filename'])) {
            return sprintf(
                '<p>Direct link to video file: <a href="%s">%s</a></p>',
                $detailsResponse['sources'][1]['url'],
                $detailsResponse['download_filename']
            );
        }

        return '';
    }

    /**
     * Decide which playlists to access
     * @return array
     */
    private static function getPlaylistData(): array
    {
        return [
            '6625' => 'Zoning Board Meetings',
            '6626' => 'Planning Board',
            '6621' => 'Township Council',
            '6629' => 'Board of Education',
            '8219' => 'Board of Health',
        ];
    }

}
