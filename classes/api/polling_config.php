<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Configuration for job polling per job type.
 *
 * Defines timing parameters for polling different job types with appropriate
 * delays and timeouts based on expected processing time.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\api;

/**
 * Configuration class for job polling parameters.
 */
class polling_config {
    // Fill module timing constants (fast operations, ~2-5 seconds).
    /** @var int Initial delay for fill operations (ms). */
    public const FILL_INITIAL_DELAY_MS = 2000;
    /** @var int Poll interval for fill operations (ms). */
    public const FILL_POLL_INTERVAL_MS = 2000;
    /** @var int Timeout for fill operations (ms) - 2 minutes. */
    public const FILL_TIMEOUT_MS = 120000;

    // Edit module timing constants (fast operations, ~2-5 seconds).
    /** @var int Initial delay for edit operations (ms). */
    public const EDIT_INITIAL_DELAY_MS = 2000;
    /** @var int Poll interval for edit operations (ms). */
    public const EDIT_POLL_INTERVAL_MS = 2000;
    /** @var int Timeout for edit operations (ms) - 2 minutes. */
    public const EDIT_TIMEOUT_MS = 120000;

    // Generate module timing constants (medium operations, ~5-15 seconds).
    /** @var int Initial delay for generation operations (ms). */
    public const GENERATE_INITIAL_DELAY_MS = 3000;
    /** @var int Poll interval for generation operations (ms). */
    public const GENERATE_POLL_INTERVAL_MS = 2000;
    /** @var int Timeout for generation operations (ms) - 1 minute. */
    public const GENERATE_TIMEOUT_MS = 60000;

    // Course generation timing constants (long operations, ~30-120 seconds).
    /** @var int Initial delay for course generation (ms). */
    public const COURSE_GEN_INITIAL_DELAY_MS = 5000;
    /** @var int Poll interval for course generation (ms). */
    public const COURSE_GEN_POLL_INTERVAL_MS = 3000;
    /** @var int Timeout for course generation (ms) - 5 minutes. */
    public const COURSE_GEN_TIMEOUT_MS = 300000;

    // Tutor message timing constants (~2-90 seconds).
    /** @var int Initial delay for tutor messages (ms). */
    public const TUTOR_INITIAL_DELAY_MS = 2000;
    /** @var int Poll interval for tutor messages (ms). */
    public const TUTOR_POLL_INTERVAL_MS = 2000;
    /** @var int Timeout for tutor messages (ms) - 90 seconds. */
    public const TUTOR_TIMEOUT_MS = 90000;

    // Assign UX timing constants (review/grade/authorship; may include file_search, ~3-180 seconds).
    /** @var int Initial delay for assign UX jobs (ms). */
    public const ASSIGN_UX_INITIAL_DELAY_MS = 3000;
    /** @var int Poll interval for assign UX jobs (ms). */
    public const ASSIGN_UX_POLL_INTERVAL_MS = 2000;
    /** @var int Timeout for assign UX jobs (ms) - 3 minutes. */
    public const ASSIGN_UX_TIMEOUT_MS = 180000;

    // Milliseconds to seconds conversion factor.
    /** @var float Conversion factor from milliseconds to seconds. */
    private const MS_TO_SECONDS = 1000.0;

    /** @var int Initial delay before first poll in milliseconds. */
    public readonly int $initialdelayms;

    /** @var int Interval between polls in milliseconds. */
    public readonly int $pollintervalms;

    /** @var int Total timeout in milliseconds. */
    public readonly int $timeoutms;

    /**
     * Constructor.
     *
     * @param int $initialdelayms Initial delay before first poll.
     * @param int $pollintervalms Interval between subsequent polls.
     * @param int $timeoutms Total timeout for polling.
     */
    public function __construct(int $initialdelayms, int $pollintervalms, int $timeoutms) {
        $this->initialdelayms = $initialdelayms;
        $this->pollintervalms = $pollintervalms;
        $this->timeoutms = $timeoutms;
    }

    /**
     * Get polling configuration for a specific job type.
     *
     * Job types have different expected processing times:
     * - fill_module: Fill content for existing or structure-based modules (~2-5 seconds)
     * - edit_module: Fast edits to existing content (~2-5 seconds)
     * - generate_module: Creating new modules with name/intro/content (~5-15 seconds)
     * - generate_course_structure: AI-driven course outline generation (~30-120 seconds)
     * - tutor: Tutor message responses (~2-90 seconds)
     * - assign_review / assign_grade / assign_authorship: Assign UX AI jobs (~3-180 seconds)
     *
     * @param string $jobtype The job type identifier.
     * @return self The polling configuration for the job type.
     */
    public static function for_job_type(string $jobtype): self {
        return match ($jobtype) {
            'fill_module' => new self(
                self::FILL_INITIAL_DELAY_MS,
                self::FILL_POLL_INTERVAL_MS,
                self::FILL_TIMEOUT_MS
            ),
            'edit_module' => new self(
                self::EDIT_INITIAL_DELAY_MS,
                self::EDIT_POLL_INTERVAL_MS,
                self::EDIT_TIMEOUT_MS
            ),
            'generate_module' => new self(
                self::GENERATE_INITIAL_DELAY_MS,
                self::GENERATE_POLL_INTERVAL_MS,
                self::GENERATE_TIMEOUT_MS
            ),
            'generate_course_structure' => new self(
                self::COURSE_GEN_INITIAL_DELAY_MS,
                self::COURSE_GEN_POLL_INTERVAL_MS,
                self::COURSE_GEN_TIMEOUT_MS
            ),
            'tutor' => new self(
                self::TUTOR_INITIAL_DELAY_MS,
                self::TUTOR_POLL_INTERVAL_MS,
                self::TUTOR_TIMEOUT_MS
            ),
            'assign_review', 'assign_grade', 'assign_authorship' => new self(
                self::ASSIGN_UX_INITIAL_DELAY_MS,
                self::ASSIGN_UX_POLL_INTERVAL_MS,
                self::ASSIGN_UX_TIMEOUT_MS
            ),
            // Default to generate_module timing for unknown types.
            default => new self(
                self::GENERATE_INITIAL_DELAY_MS,
                self::GENERATE_POLL_INTERVAL_MS,
                self::GENERATE_TIMEOUT_MS
            ),
        };
    }

    /**
     * Calculate the maximum number of poll attempts based on config.
     *
     * @return int The maximum number of poll attempts.
     */
    public function get_max_attempts(): int {
        $remainingtime = $this->timeoutms - $this->initialdelayms;

        if ($remainingtime <= 0) {
            return 1;
        }

        return (int) ceil($remainingtime / $this->pollintervalms) + 1;
    }

    /**
     * Convert initial delay to seconds for PHP sleep.
     *
     * @return float The initial delay in seconds.
     */
    public function get_initial_delay_seconds(): float {
        return $this->initialdelayms / self::MS_TO_SECONDS;
    }

    /**
     * Convert poll interval to seconds for PHP sleep.
     *
     * @return float The poll interval in seconds.
     */
    public function get_poll_interval_seconds(): float {
        return $this->pollintervalms / self::MS_TO_SECONDS;
    }

    /**
     * Convert timeout to seconds.
     *
     * @return float The timeout in seconds.
     */
    public function get_timeout_seconds(): float {
        return $this->timeoutms / self::MS_TO_SECONDS;
    }
}
