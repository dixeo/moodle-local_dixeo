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

namespace local_dixeo\dto;

/**
 * Optional Moodle context metadata stored with a remote job binding.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_binding_metadata {
    /**
     * Constructor.
     *
     * @param string|null $moduletype Moodle/API activity type when known.
     * @param int $contextid Course or module context id.
     * @param int $cmid Course module id when known.
     */
    public function __construct(
        /** @var string|null Moodle/API activity type when known. */
        public readonly ?string $moduletype = null,
        /** @var int Course or module context id. */
        public readonly int $contextid = 0,
        /** @var int Course module id when known. */
        public readonly int $cmid = 0,
    ) {
    }

    /**
     * Build metadata from an API job payload.
     *
     * @param array $payload Request payload.
     * @return self
     */
    public static function from_payload(array $payload): self {
        $moduletype = $payload['moduleType'] ?? $payload['moduletype'] ?? null;
        if (is_string($moduletype)) {
            $moduletype = trim($moduletype);
            if ($moduletype === '') {
                $moduletype = null;
            }
        } else {
            $moduletype = null;
        }

        return new self(moduletype: $moduletype);
    }

    /**
     * Merge explicit metadata over payload-derived defaults.
     *
     * @param self $base Base metadata (typically from payload).
     * @param self|null $override Explicit overrides.
     * @return self
     */
    public static function merge(self $base, ?self $override): self {
        if ($override === null) {
            return $base;
        }

        return new self(
            moduletype: $override->moduletype ?? $base->moduletype,
            contextid: $override->contextid > 0 ? $override->contextid : $base->contextid,
            cmid: $override->cmid > 0 ? $override->cmid : $base->cmid,
        );
    }

    /**
     * Whether any metadata field is set.
     *
     * @return bool
     */
    public function has_data(): bool {
        return !empty($this->moduletype) || $this->contextid > 0 || $this->cmid > 0;
    }

    /**
     * Build metadata for a course-scoped job.
     *
     * @param int $courseid Course id.
     * @param string|null $moduletype Optional activity type code.
     * @return self
     */
    public static function for_course(int $courseid, ?string $moduletype = null): self {
        $context = \context_course::instance($courseid, IGNORE_MISSING);

        return new self(
            moduletype: $moduletype,
            contextid: $context ? (int) $context->id : 0,
        );
    }

    /**
     * Build metadata for a module-scoped job.
     *
     * @param string $moduletype Activity type code.
     * @param int $cmid Course module id.
     * @return self
     */
    public static function for_module(string $moduletype, int $cmid): self {
        $context = \context_module::instance($cmid, IGNORE_MISSING);

        return new self(
            moduletype: $moduletype,
            contextid: $context ? (int) $context->id : 0,
            cmid: $cmid,
        );
    }

    /**
     * Resolve metadata for a job submission from payload, course, and overrides.
     *
     * @param array $payload API request payload.
     * @param int|null $courseid Course id when known outside the payload.
     * @param self|null $override Explicit metadata overrides.
     * @return self|null Resolved metadata, or null when empty.
     */
    public static function resolve_for_submit(array $payload, ?int $courseid = null, ?self $override = null): ?self {
        $payloadcourseid = (int) ($payload['courseId'] ?? $payload['courseid'] ?? 0);
        $effectivecourseid = ($courseid !== null && $courseid > 0)
            ? $courseid
            : ($payloadcourseid > 0 ? $payloadcourseid : null);

        $merged = self::merge(self::from_payload($payload), $override);

        if ($merged->contextid <= 0 && $effectivecourseid !== null) {
            $merged = self::merge(
                $merged,
                self::for_course($effectivecourseid, $merged->moduletype)
            );
        }

        return $merged->has_data() ? $merged : null;
    }
}
