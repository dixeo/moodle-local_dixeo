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

namespace local_dixeo;

/**
 * Custom admin setting to display credit balance.
 *
 * This is a read-only setting that fetches and displays the current credit balance
 * from the Dixeo API when the settings page is viewed.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_credit_balance extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name.
     * @param string $visiblename Localised label.
     * @param string $description Localised description.
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Always returns true - this setting cannot be written.
     *
     * @return bool Always true.
     */
    public function get_setting() {
        return true;
    }

    /**
     * Never writes anything.
     *
     * @param mixed $data Ignored.
     * @return string Always empty string.
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the credit balance display.
     *
     * @param mixed $data Ignored.
     * @param string $query Search query (unused).
     * @return string HTML output.
     */
    public function output_html($data, $query = '') {
        $html = $this->get_balance_html();
        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', null, $query);
    }

    /**
     * Get the HTML for displaying the credit balance.
     *
     * @return string HTML output.
     */
    protected function get_balance_html(): string {
        $apikey = get_config('local_dixeo', 'api_key');

        if (empty($apikey)) {
            return \html_writer::tag(
                'div',
                get_string('api_key_not_configured', 'local_dixeo'),
                ['class' => 'alert alert-warning']
            );
        }

        try {
            $service = new \local_dixeo\service\credit_service();
            $balance = $service->get_balance();

            $stateclass = match ($balance->state) {
                'active' => 'success',
                'frozen' => 'warning',
                'suspended' => 'danger',
                default => 'secondary',
            };

            $html = '';

            // Show warning if account is frozen or suspended.
            if ($balance->is_frozen()) {
                $html .= \html_writer::tag(
                    'div',
                    get_string('account_frozen_warning', 'local_dixeo'),
                    ['class' => 'alert alert-warning mb-2']
                );
            } else if ($balance->is_suspended()) {
                $html .= \html_writer::tag(
                    'div',
                    get_string('account_suspended_warning', 'local_dixeo'),
                    ['class' => 'alert alert-danger mb-2']
                );
            }

            // Balance display with badge.
            $html .= \html_writer::start_div('d-flex align-items-center', ['style' => 'gap: 0.75rem;']);

            // Balance amount.
            $html .= \html_writer::tag(
                'span',
                $balance->get_formatted_balance(),
                ['class' => 'font-weight-bold', 'style' => 'font-size: 1.1rem;']
            );

            // Account state badge.
            $html .= \html_writer::tag(
                'span',
                $balance->get_state_description(),
                ['class' => "badge bg-{$stateclass} dixeo-credit-balance-badge", 'style' => 'font-size: 0.75rem;']
            );

            $html .= \html_writer::end_div();

            return $html;
        } catch (\Throwable $e) {
            return $this->format_balance_error_html($e);
        }
    }

    /**
     * Render a safe admin-facing error when the balance API call fails.
     *
     * Remote problem details and debuginfo are logged for developers only.
     *
     * @param \Throwable $e The failure from the credit service or API client.
     * @return string Escaped HTML alert.
     */
    protected function format_balance_error_html(\Throwable $e): string {
        debugging('Failed to fetch credit balance: ' . $e->getMessage(), DEBUG_DEVELOPER);
        if ($e instanceof \moodle_exception && $e->debuginfo !== null && $e->debuginfo !== '') {
            debugging($e->debuginfo, DEBUG_DEVELOPER);
        }

        return \html_writer::tag(
            'div',
            s($e->getMessage()),
            ['class' => 'alert alert-danger']
        );
    }
}
