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
 * Base exception for Dixeo API errors.
 *
 * All API-related exceptions extend this class for consistent error handling.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\api\exception;

/**
 * Base exception for Dixeo API errors.
 */
class api_exception extends \moodle_exception {
    /** @var string RFC 7807 error type. */
    protected string $errortype;

    /** @var int HTTP status code. */
    protected int $httpstatus;

    /** @var array Additional error details. */
    protected array $details;

    /**
     * Constructor.
     *
     * @param string $errortype RFC 7807 error type identifier.
     * @param string $message Human-readable error message.
     * @param int $httpstatus HTTP status code.
     * @param array $details Additional error details from the API response.
     */
    public function __construct(
        string $errortype,
        string $message,
        int $httpstatus = 500,
        array $details = []
    ) {
        $this->errortype = $errortype;
        $this->httpstatus = $httpstatus;
        $this->details = $details;

        parent::__construct('api_error', 'local_dixeo', '', $message, null);
    }

    /**
     * Get the RFC 7807 error type.
     *
     * @return string The error type identifier.
     */
    public function get_error_type(): string {
        return $this->errortype;
    }

    /**
     * Get the error code (alias for error type).
     *
     * Provides a consistent interface for error handling across the plugin.
     *
     * @return string The error code identifier.
     */
    public function get_error_code(): string {
        return $this->errortype;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int The HTTP status code.
     */
    public function get_http_status(): int {
        return $this->httpstatus;
    }

    /**
     * Get additional error details.
     *
     * @return array The error details.
     */
    public function get_details(): array {
        return $this->details;
    }

    /**
     * Create exception from API error response.
     *
     * Factory method to create the appropriate exception subclass based on error type.
     * The API returns RFC 7807 format with extensions merged at root level.
     *
     * @param array $errordata The error data from the API response (RFC 7807 format).
     * @param int $httpstatus The HTTP status code.
     * @return api_exception The appropriate exception instance.
     */
    public static function from_response(array $errordata, int $httpstatus): api_exception {
        $type = $errordata['type'] ?? ($httpstatus === 429 ? 'rate_limit_exceeded' : 'unknown_error');
        $message = $errordata['detail'] ?? $errordata['title'] ?? "Unexpected API response (HTTP {$httpstatus})";
        $details = $errordata;

        // Map RFC 7807 error type identifiers to our exception classes.
        // Extensions (violations, currentBalance, retryAfter, ...) are merged at root level.
        return match ($type) {
            // Authentication errors.
            'authentication', 'authentication_error' => new authentication_exception($message, $details),

            // Payment/credit errors.
            'payment_required', 'insufficient_credits' => new payment_required_exception(
                $message,
                $errordata['currentBalance'] ?? $errordata['current_balance'] ?? null,
                $details
            ),

            // Rate limiting.
            'rate_limit_exceeded', 'too_many_requests', 'too_many_requests_http' => new rate_limit_exception(
                get_string('error:rate_limit', 'local_dixeo'),
                $errordata['retryAfter'] ?? $errordata['retry_after'] ?? null,
                $details
            ),

            // Validation errors - violations at root level.
            'validation_error' => new validation_exception(
                $message,
                $errordata['violations'] ?? [],
                $details
            ),

            // Job not found.
            'job_not_found' => new job_not_found_exception($message, $details),

            // AI service errors.
            'upstream_ai' => new upstream_ai_exception($message, $details),

            // Default fallback for unknown error types.
            default => new self($type, $message, $httpstatus, $details),
        };
    }
}
