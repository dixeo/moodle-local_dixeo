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

namespace local_dixeo\service\image;

use local_dixeo\api\error_message;

/**
 * Stores an image job failure as a code and translates it for display.
 *
 * The code is translated when a status payload is built, so the sentence
 * follows the current user's language. A local failure stores CODE_FAILED.
 * Rows written before codes were stored still hold a sentence.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class failure_message {
    /** @var string Local failure, or an API error with no dedicated message. */
    public const CODE_FAILED = 'failed';

    /** @var string Prompt or output refused by the model safety filter. */
    public const CODE_CONTENT_REJECTED = 'content_rejected';

    /** @var string Provider temporarily unreachable. */
    public const CODE_AI_UNAVAILABLE = 'ai_unavailable';

    /** @var string Provider asked the client to wait. */
    public const CODE_RATE_LIMITED = 'rate_limited';

    /**
     * Whether the value is an API code with a dedicated message.
     *
     * @param string|null $code API error code or a stored job message.
     * @return bool
     */
    public static function is_known(?string $code): bool {
        return error_message::string_key($code) !== null;
    }

    /**
     * Code to persist on the image job.
     *
     * @param string|null $errorcode API error code, or null when the job failed locally.
     * @return string The code itself when it has a message, otherwise CODE_FAILED.
     */
    public static function for_storage(?string $errorcode): string {
        return self::is_known($errorcode) ? $errorcode : self::CODE_FAILED;
    }

    /**
     * Sentence shown to the current user.
     *
     * @param string|null $stored Code or legacy sentence read from the image job.
     * @return string
     */
    public static function for_display(?string $stored): string {
        $stringkey = error_message::string_key($stored);
        if ($stringkey !== null) {
            return get_string($stringkey, 'local_dixeo');
        }

        // Legacy rows hold a sentence. A bare token is a code.
        if ($stored !== null && preg_match('/\s/u', $stored) === 1) {
            return $stored;
        }

        return get_string('dixeo_image_job_failed', 'local_dixeo');
    }
}
