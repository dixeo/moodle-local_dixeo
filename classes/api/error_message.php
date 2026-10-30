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

/**
 * User message for a Dixeo API error.
 *
 * The API reports errors two ways: an HTTP problem type on a refused request
 * (rate_limit_exceeded) and a code on a failed job (rate_limited). Both name the
 * same few situations, so both resolve here to one string per meaning.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\api;

/**
 * Maps API problem types and job error codes to local_dixeo strings.
 */
final class error_message {
    /** @var array<string, string> API problem type or job error code to string key. */
    private const STRING_KEYS = [
        'rate_limit_exceeded' => 'error:busy',
        'too_many_requests' => 'error:busy',
        'too_many_requests_http' => 'error:busy',
        'rate_limited' => 'error:busy',
        'upstream_ai' => 'error:unavailable',
        'upstream_ai_exception' => 'error:unavailable',
        'ai_unavailable' => 'error:unavailable',
        'connection_error' => 'error:unavailable',
        'content_rejected' => 'error:content_rejected',
        'payment_required' => 'error:payment_required',
        'insufficient_credits' => 'error:payment_required',
        'validation_error' => 'error:validation_failed',
        'job_not_found' => 'error:job_not_found',
        'authentication' => 'error:authentication',
        'authentication_error' => 'error:authentication',
        'authentication_failed' => 'error:authentication',
    ];

    /**
     * String key for an API problem type or job error code.
     *
     * @param string|null $code Problem type or job error code.
     * @return string|null local_dixeo string key, or null when the code is unknown.
     */
    public static function string_key(?string $code): ?string {
        if ($code === null) {
            return null;
        }

        return self::STRING_KEYS[$code] ?? null;
    }
}
