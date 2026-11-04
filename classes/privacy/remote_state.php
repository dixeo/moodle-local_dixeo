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

namespace local_dixeo\privacy;

use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * Whether this site may hold data in the Dixeo API, and whether it can reach it.
 *
 * A missing API key alone cannot prove that the Dixeo API holds nothing: the key may have been
 * rotated out or removed after data was sent. The API client records the first write it makes
 * ({@see \local_dixeo\api\client}), so privacy requests on a site that transferred data but has
 * no key fail loudly instead of completing as if no remote data existed.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remote_state {
    /** @var string The API is configured: remote calls can be made. */
    public const READY = 'ready';

    /** @var string Data was sent at some point, but the API key is missing now. */
    public const UNREACHABLE = 'unreachable';

    /** @var string No key and no transfer recorded: there is nothing remote to find. */
    public const NEVER = 'never';

    /** @var string local_dixeo config name holding the time of the first recorded transfer. */
    public const CONFIG_TRANSFERRED = 'hastransferred';

    /** @var string Error code carried by the exception raised in the unreachable state. */
    public const ERROR_NOT_CONFIGURED = 'api_not_configured';

    /**
     * Current remote state.
     *
     * @return string One of READY, UNREACHABLE or NEVER.
     */
    public static function current(): string {
        if (service_factory::get_client()->is_configured()) {
            return self::READY;
        }
        return self::has_transferred() ? self::UNREACHABLE : self::NEVER;
    }

    /**
     * Whether this site is recorded as having sent data to the Dixeo API.
     *
     * @return bool
     */
    public static function has_transferred(): bool {
        return !empty(get_config('local_dixeo', self::CONFIG_TRANSFERRED));
    }

    /**
     * Record that data is about to be sent to the Dixeo API.
     *
     * Called before every remote write, so a call that fails after reaching the API is still
     * covered. Only the first call writes; later calls read the cached plugin config.
     *
     * @return void
     */
    public static function record_transfer(): void {
        if (!self::has_transferred()) {
            set_config(self::CONFIG_TRANSFERRED, time(), 'local_dixeo');
        }
    }

    /**
     * Exception describing a site that holds remote data but has no API key to reach it.
     *
     * @return api_exception
     */
    public static function not_configured_exception(): api_exception {
        return new api_exception(
            self::ERROR_NOT_CONFIGURED,
            'The Dixeo API key is missing, but data was sent to the Dixeo API from this site.',
            0
        );
    }
}
