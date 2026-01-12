<?php
/**
 * YouTube video sync cron job.
 *
 * Fetches latest videos from YouTube channel and caches them.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\CacheKeys;

/**
 * Syncs YouTube videos from a channel.
 */
class YouTubeSyncJob implements CronJobInterface {

    /**
     * Transient key for cached videos.
     *
     * @deprecated Use CacheKeys::youtubeVideos() instead.
     */
    public const CACHE_KEY = 'erh_youtube_videos';

    /**
     * Cache duration in seconds (12 hours).
     */
    public const CACHE_DURATION = 12 * HOUR_IN_SECONDS;

    /**
     * Number of videos to fetch.
     */
    private const VIDEO_COUNT = 5;

    /**
     * YouTube API base URL.
     */
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    /**
     * Cron manager reference for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Constructor.
     *
     * @param CronManager $cron_manager Cron manager instance.
     */
    public function __construct(CronManager $cron_manager) {
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('YouTube Video Sync', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Fetches latest videos from the YouTube channel.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_youtube_sync';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'erh_twelve_hours';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock to prevent concurrent execution.
        if (!$this->cron_manager->lock_job('youtube-sync', 120)) {
            error_log('[ERH Cron] YouTube sync job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('youtube-sync');
            $this->cron_manager->record_run_time('youtube-sync');
        }
    }

    /**
     * Run the YouTube sync logic.
     *
     * @return void
     */
    private function run(): void {
        $api_key = $this->get_api_key();
        $channel_id = $this->get_channel_id();

        if (empty($api_key) || empty($channel_id)) {
            error_log('[ERH Cron] YouTube sync: Missing API key or channel ID.');
            return;
        }

        $videos = $this->fetch_videos($api_key, $channel_id);

        if ($videos === null) {
            error_log('[ERH Cron] YouTube sync: Failed to fetch videos.');
            return;
        }

        // Cache the videos using centralized cache key.
        set_transient(CacheKeys::youtubeVideos(), $videos, self::CACHE_DURATION);

        error_log(sprintf(
            '[ERH Cron] YouTube sync completed. Videos cached: %d',
            count($videos)
        ));
    }

    /**
     * Fetch videos from YouTube API.
     *
     * @param string $api_key The YouTube API key.
     * @param string $channel_id The YouTube channel ID.
     * @return array|null Array of videos or null on failure.
     */
    private function fetch_videos(string $api_key, string $channel_id): ?array {
        // First, get the uploads playlist ID.
        $channel_url = add_query_arg([
            'part' => 'contentDetails',
            'id'   => $channel_id,
            'key'  => $api_key,
        ], self::API_BASE . '/channels');

        $channel_response = wp_remote_get($channel_url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($channel_response)) {
            error_log('[ERH YouTube] Channel request failed: ' . $channel_response->get_error_message());
            return null;
        }

        $channel_data = json_decode(wp_remote_retrieve_body($channel_response), true);

        if (empty($channel_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
            error_log('[ERH YouTube] Could not find uploads playlist.');
            return null;
        }

        $uploads_playlist_id = $channel_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

        // Fetch videos from uploads playlist.
        $playlist_url = add_query_arg([
            'part'       => 'snippet',
            'playlistId' => $uploads_playlist_id,
            'maxResults' => self::VIDEO_COUNT,
            'key'        => $api_key,
        ], self::API_BASE . '/playlistItems');

        $playlist_response = wp_remote_get($playlist_url, [
            'timeout' => 15,
        ]);

        if (is_wp_error($playlist_response)) {
            error_log('[ERH YouTube] Playlist request failed: ' . $playlist_response->get_error_message());
            return null;
        }

        $playlist_data = json_decode(wp_remote_retrieve_body($playlist_response), true);

        if (empty($playlist_data['items'])) {
            error_log('[ERH YouTube] No videos found in playlist.');
            return [];
        }

        // Transform the data.
        $videos = [];
        foreach ($playlist_data['items'] as $item) {
            $snippet = $item['snippet'];
            $video_id = $snippet['resourceId']['videoId'] ?? '';

            if (empty($video_id)) {
                continue;
            }

            // Get the best available thumbnail.
            $thumbnails = $snippet['thumbnails'] ?? [];
            $thumbnail_url = $thumbnails['maxres']['url']
                ?? $thumbnails['high']['url']
                ?? $thumbnails['medium']['url']
                ?? $thumbnails['default']['url']
                ?? '';

            $videos[] = [
                'id'        => $video_id,
                'title'     => $snippet['title'] ?? '',
                'thumbnail' => $thumbnail_url,
                'url'       => 'https://www.youtube.com/watch?v=' . $video_id,
            ];
        }

        return $videos;
    }

    /**
     * Get the YouTube API key from options.
     *
     * @return string
     */
    private function get_api_key(): string {
        // Try ACF options first.
        if (function_exists('get_field')) {
            $key = get_field('youtube_api_key', 'option');
            if ($key) {
                return $key;
            }
        }

        // Fall back to wp_options or constant.
        return get_option('erh_youtube_api_key', defined('ERH_YOUTUBE_API_KEY') ? ERH_YOUTUBE_API_KEY : '');
    }

    /**
     * Get the YouTube channel ID from options.
     *
     * @return string
     */
    private function get_channel_id(): string {
        // Try ACF options first.
        if (function_exists('get_field')) {
            $id = get_field('youtube_channel_id', 'option');
            if ($id) {
                return $id;
            }
        }

        // Fall back to wp_options or constant.
        return get_option('erh_youtube_channel_id', defined('ERH_YOUTUBE_CHANNEL_ID') ? ERH_YOUTUBE_CHANNEL_ID : '');
    }

    /**
     * Get cached videos.
     *
     * @return array Array of videos (may be empty).
     */
    public static function get_cached_videos(): array {
        $videos = get_transient(CacheKeys::youtubeVideos());
        return is_array($videos) ? $videos : [];
    }

    /**
     * Force refresh the cache (for admin use).
     *
     * @return bool True on success, false on failure.
     */
    public function force_refresh(): bool {
        $api_key = $this->get_api_key();
        $channel_id = $this->get_channel_id();

        if (empty($api_key) || empty($channel_id)) {
            return false;
        }

        $videos = $this->fetch_videos($api_key, $channel_id);

        if ($videos === null) {
            return false;
        }

        set_transient(CacheKeys::youtubeVideos(), $videos, self::CACHE_DURATION);
        return true;
    }
}
