<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\api\exception;

/**
 * Exception for job processing failures.
 *
 * Thrown when a job completes with a 'failed' status.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_failed_exception extends api_exception {
    /** @var string The job ID that failed. */
    protected string $jobid;

    /**
     * Job-level error code from the Dixeo API (not Moodle's moodle_exception::$errorcode).
     *
     * @var string|null
     */
    protected ?string $joberrorcode;

    /**
     * Constructor.
     *
     * @param string $jobid The job ID that failed.
     * @param string $message Human-readable error message.
     * @param string|null $errorcode The error code from the job.
     * @param array $details Additional error details.
     */
    public function __construct(
        string $jobid,
        string $message = 'Job processing failed',
        ?string $errorcode = null,
        array $details = []
    ) {
        $this->jobid = $jobid;
        $this->joberrorcode = $errorcode;
        parent::__construct('job_processing_failed', $message, 500, $details);
    }

    /**
     * Get the job ID.
     *
     * @return string The job ID that failed.
     */
    public function get_jobid(): string {
        return $this->jobid;
    }

    /**
     * Get the job API error code when present, otherwise the RFC 7807 error type.
     *
     * @return string
     */
    public function get_error_code(): string {
        return $this->joberrorcode ?? parent::get_error_code();
    }

    /**
     * Get the raw job API error code.
     *
     * @return string|null
     */
    public function get_job_error_code(): ?string {
        return $this->joberrorcode;
    }
}
