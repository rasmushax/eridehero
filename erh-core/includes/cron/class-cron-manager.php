<?php
/**
 * Cron manager for registering and managing scheduled jobs.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

/**
 * Central manager for all cron jobs.
 */
class CronManager {

    /**
     * Option key for storing last run times.
     */
    public const OPTION_LAST_RUN = 'erh_cron_last_run';

    /**
     * Registered job instances.
     *
     * @var array<string, CronJobInterface>
     */
    private array $jobs = [];

    /**
     * Register the cron manager.
     *
     * @return void
     */
    public function register(): void {
        // Register custom cron schedules.
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Schedule jobs on init (after all jobs are added).
        add_action('init', [$this, 'schedule_jobs'], 20);

        // Register WP-CLI commands.
        if (defined('WP_CLI') && \WP_CLI) {
            $this->register_cli_commands();
        }
    }

    /**
     * Add a job to the manager.
     *
     * @param string $key Unique job identifier.
     * @param CronJobInterface $job The job instance.
     * @return self
     */
    public function add_job(string $key, CronJobInterface $job): self {
        $this->jobs[$key] = $job;

        // Register the job's action hook.
        add_action($job->get_hook_name(), [$job, 'execute']);

        return $this;
    }

    /**
     * Get all registered jobs.
     *
     * @return array<string, CronJobInterface>
     */
    public function get_jobs(): array {
        return $this->jobs;
    }

    /**
     * Get a specific job by key.
     *
     * @param string $key The job key.
     * @return CronJobInterface|null
     */
    public function get_job(string $key): ?CronJobInterface {
        return $this->jobs[$key] ?? null;
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_cron_schedules(array $schedules): array {
        // Every 2 hours (for JSON generation).
        $schedules['erh_two_hours'] = [
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => __('Every 2 Hours', 'erh-core'),
        ];

        // Every 6 hours (for notifications).
        $schedules['erh_six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __('Every 6 Hours', 'erh-core'),
        ];

        // Every 12 hours.
        $schedules['erh_twelve_hours'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Twice Daily (12 Hours)', 'erh-core'),
        ];

        return $schedules;
    }

    /**
     * Schedule all registered jobs.
     *
     * @return void
     */
    public function schedule_jobs(): void {
        foreach ($this->jobs as $key => $job) {
            $hook = $job->get_hook_name();
            $schedule = $job->get_schedule();

            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $schedule, $hook);
            }
        }
    }

    /**
     * Unschedule all jobs (for plugin deactivation).
     *
     * @return void
     */
    public function unschedule_jobs(): void {
        foreach ($this->jobs as $key => $job) {
            $hook = $job->get_hook_name();
            $timestamp = wp_next_scheduled($hook);

            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Run a specific job manually.
     *
     * @param string $key The job key.
     * @return bool True if job was run, false if not found.
     */
    public function run_job(string $key): bool {
        $job = $this->get_job($key);

        if (!$job) {
            return false;
        }

        // Execute the job.
        $job->execute();

        // Record the run time.
        $this->record_run_time($key);

        return true;
    }

    /**
     * Get the last run time for a job.
     *
     * @param string $key The job key.
     * @return string|null The datetime string or null if never run.
     */
    public function get_last_run(string $key): ?string {
        $times = get_option(self::OPTION_LAST_RUN, []);
        return $times[$key] ?? null;
    }

    /**
     * Get the next scheduled run time for a job.
     *
     * @param string $key The job key.
     * @return int|null The timestamp or null if not scheduled.
     */
    public function get_next_run(string $key): ?int {
        $job = $this->get_job($key);

        if (!$job) {
            return null;
        }

        $timestamp = wp_next_scheduled($job->get_hook_name());

        return $timestamp ?: null;
    }

    /**
     * Get status information for all jobs.
     *
     * @return array Array of job status information.
     */
    public function get_all_status(): array {
        $status = [];

        foreach ($this->jobs as $key => $job) {
            $last_run = $this->get_last_run($key);
            $next_run = $this->get_next_run($key);

            $status[$key] = [
                'name'        => $job->get_name(),
                'description' => $job->get_description(),
                'schedule'    => $job->get_schedule(),
                'hook'        => $job->get_hook_name(),
                'last_run'    => $last_run,
                'next_run'    => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
                'is_running'  => $this->is_job_running($key),
            ];
        }

        return $status;
    }

    /**
     * Record the run time for a job.
     *
     * @param string $key The job key.
     * @return void
     */
    public function record_run_time(string $key): void {
        $times = get_option(self::OPTION_LAST_RUN, []);
        $times[$key] = current_time('mysql');
        update_option(self::OPTION_LAST_RUN, $times);
    }

    /**
     * Check if a job is currently running.
     *
     * @param string $key The job key.
     * @return bool
     */
    public function is_job_running(string $key): bool {
        return (bool) get_transient("erh_cron_running_{$key}");
    }

    /**
     * Mark a job as running (prevents concurrent execution).
     *
     * @param string $key The job key.
     * @param int $timeout Lock timeout in seconds.
     * @return bool True if lock acquired, false if already running.
     */
    public function lock_job(string $key, int $timeout = 300): bool {
        $lock_key = "erh_cron_running_{$key}";

        if (get_transient($lock_key)) {
            return false;
        }

        set_transient($lock_key, true, $timeout);
        return true;
    }

    /**
     * Release the lock for a job.
     *
     * @param string $key The job key.
     * @return void
     */
    public function unlock_job(string $key): void {
        delete_transient("erh_cron_running_{$key}");
    }

    /**
     * Register WP-CLI commands.
     *
     * @return void
     */
    private function register_cli_commands(): void {
        \WP_CLI::add_command('erh cron run', function ($args, $assoc_args) {
            if (empty($args[0])) {
                \WP_CLI::error('Please specify a job key. Available jobs: ' . implode(', ', array_keys($this->jobs)));
                return;
            }

            $key = $args[0];

            if (!$this->get_job($key)) {
                \WP_CLI::error("Job '{$key}' not found. Available jobs: " . implode(', ', array_keys($this->jobs)));
                return;
            }

            \WP_CLI::log("Running job: {$key}...");

            $start = microtime(true);
            $this->run_job($key);
            $elapsed = round(microtime(true) - $start, 2);

            \WP_CLI::success("Job '{$key}' completed in {$elapsed}s");
        });

        \WP_CLI::add_command('erh cron list', function () {
            $status = $this->get_all_status();

            if (empty($status)) {
                \WP_CLI::warning('No cron jobs registered.');
                return;
            }

            $table = [];
            foreach ($status as $key => $info) {
                $table[] = [
                    'Key'       => $key,
                    'Name'      => $info['name'],
                    'Schedule'  => $info['schedule'],
                    'Last Run'  => $info['last_run'] ?? 'Never',
                    'Next Run'  => $info['next_run'] ?? 'Not scheduled',
                ];
            }

            \WP_CLI\Utils\format_items('table', $table, ['Key', 'Name', 'Schedule', 'Last Run', 'Next Run']);
        });

        \WP_CLI::add_command('erh cron status', function ($args) {
            if (empty($args[0])) {
                // Show all jobs status.
                $status = $this->get_all_status();

                foreach ($status as $key => $info) {
                    \WP_CLI::log("=== {$info['name']} ({$key}) ===");
                    \WP_CLI::log("  Schedule: {$info['schedule']}");
                    \WP_CLI::log("  Hook: {$info['hook']}");
                    \WP_CLI::log("  Last Run: " . ($info['last_run'] ?? 'Never'));
                    \WP_CLI::log("  Next Run: " . ($info['next_run'] ?? 'Not scheduled'));
                    \WP_CLI::log("  Running: " . ($info['is_running'] ? 'Yes' : 'No'));
                    \WP_CLI::log('');
                }
            } else {
                $key = $args[0];
                $job = $this->get_job($key);

                if (!$job) {
                    \WP_CLI::error("Job '{$key}' not found.");
                    return;
                }

                $info = $this->get_all_status()[$key];
                \WP_CLI::log("Job: {$info['name']}");
                \WP_CLI::log("Description: {$info['description']}");
                \WP_CLI::log("Schedule: {$info['schedule']}");
                \WP_CLI::log("Hook: {$info['hook']}");
                \WP_CLI::log("Last Run: " . ($info['last_run'] ?? 'Never'));
                \WP_CLI::log("Next Run: " . ($info['next_run'] ?? 'Not scheduled'));
                \WP_CLI::log("Running: " . ($info['is_running'] ? 'Yes' : 'No'));
            }
        });
    }
}
