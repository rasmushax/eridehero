<?php
/**
 * Interface for cron jobs.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

/**
 * Contract for cron job classes.
 */
interface CronJobInterface {

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Get the WordPress hook name for this job.
     *
     * @return string
     */
    public function get_hook_name(): string;

    /**
     * Get the cron schedule (e.g., 'daily', 'twicedaily', 'erh_six_hours').
     *
     * @return string
     */
    public function get_schedule(): string;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void;
}
