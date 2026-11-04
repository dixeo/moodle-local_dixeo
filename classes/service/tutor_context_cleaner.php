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

namespace local_dixeo\service;

/**
 * Cleans the HTML fields of a tutor message context.
 *
 * Lesson and quiz review contexts carry HTML that the browser renders unescaped. They are
 * cleaned before they are sent to Dixeo and again when they are read back, so contexts stored
 * before cleaning, or altered on the way, never reach a page as raw HTML. The cleaning does not
 * depend on the context schema: any context holding these keys is cleaned.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_context_cleaner {
    /** @var string[] Context keys holding HTML. */
    public const HTML_FIELDS = ['introhtml', 'contenthtml'];

    /** @var string Key of the HTML feedback of each entry of the questions list. */
    private const QUESTION_FEEDBACK = 'feedbackHtml';

    /**
     * Clean every HTML field of a tutor message context with clean_text(FORMAT_HTML).
     *
     * The keys in HTML_FIELDS and questions[*].feedbackHtml are cleaned when present; a non-string
     * value there becomes ''. Other keys are returned unchanged.
     *
     * @param array $context Decoded message context.
     * @return array The context with its HTML fields cleaned.
     */
    public static function clean(array $context): array {
        foreach (self::HTML_FIELDS as $field) {
            if (array_key_exists($field, $context)) {
                $context[$field] = self::clean_value($context[$field]);
            }
        }

        if (isset($context['questions']) && is_array($context['questions'])) {
            foreach ($context['questions'] as $i => $question) {
                if (is_array($question) && array_key_exists(self::QUESTION_FEEDBACK, $question)) {
                    $context['questions'][$i][self::QUESTION_FEEDBACK] = self::clean_value($question[self::QUESTION_FEEDBACK]);
                }
            }
        }

        return $context;
    }

    /**
     * Clean one HTML value.
     *
     * @param mixed $value Stored value.
     * @return string Cleaned HTML, or '' for a non-string value.
     */
    private static function clean_value(mixed $value): string {
        return is_string($value) ? clean_text($value, FORMAT_HTML) : '';
    }
}
