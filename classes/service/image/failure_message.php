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

/**
 * Maps a Dixeo API image-job failure code to a translated user message.
 *
 * The API classifies a failed job with a stable code. The raw provider text
 * stays off the page. A known code is stored as itself and translated when a
 * status payload is built, so the sentence follows the current user's language.
 * Anything else is the generic failure sentence.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class failure_message {
    /** @var string Prompt or output refused by the model safety filter. */
    public const CODE_CONTENT_REJECTED = 'content_rejected';

    /** @var string Provider temporarily unreachable. */
    public const CODE_AI_UNAVAILABLE = 'ai_unavailable';

    /** @var string Provider asked the client to wait. */
    public const CODE_RATE_LIMITED = 'rate_limited';

    /** @var array<string, string> API code to local_dixeo string key. */
    private const STRING_KEYS = [
        self::CODE_CONTENT_REJECTED => 'dixeo_image_job_content_rejected',
        self::CODE_AI_UNAVAILABLE => 'dixeo_image_job_unavailable',
        self::CODE_RATE_LIMITED => 'dixeo_image_job_rate_limited',
    ];

    /**
     * Whether the value is a code this map translates.
     *
     * @param string|null $code API error code or a stored job message.
     * @return bool
     */
    public static function is_known(?string $code): bool {
        return $code !== null && isset(self::STRING_KEYS[$code]);
    }

    /**
     * Value to persist on the image job.
     *
     * A known code is stored unchanged. Any other code becomes the generic
     * failure sentence, which is what older rows and local timeouts already hold.
     *
     * @param string|null $errorcode API error code, or null when the job failed locally.
     * @return string
     */
    public static function for_storage(?string $errorcode): string {
        if (self::is_known($errorcode)) {
            return $errorcode;
        }

        return get_string('dixeo_image_job_failed', 'local_dixeo');
    }

    /**
     * Sentence shown to the current user.
     *
     * A stored code is translated now. A stored sentence is returned as-is.
     * An empty value uses the generic failure sentence.
     *
     * @param string|null $stored Code or sentence read from the image job.
     * @return string
     */
    public static function for_display(?string $stored): string {
        if (self::is_known($stored)) {
            return get_string(self::STRING_KEYS[$stored], 'local_dixeo');
        }

        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        return get_string('dixeo_image_job_failed', 'local_dixeo');
    }
}
