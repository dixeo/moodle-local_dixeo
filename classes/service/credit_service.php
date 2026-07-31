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

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\credit_balance;
use local_dixeo\dto\credit_transaction;

/**
 * Service for credit balance and usage operations.
 *
 * Provides methods for querying credit balance, transaction history,
 * and usage statistics from the Dixeo API.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_service {
    /** @var client The API client. */
    protected client $client;

    /**
     * Constructor.
     *
     * @param client|null $client Optional API client (creates new if not provided).
     */
    public function __construct(?client $client = null) {
        $this->client = $client ?? new client();
    }

    /**
     * Get the current credit balance.
     *
     * @return credit_balance The credit balance DTO.
     * @throws api_exception If an API error occurs.
     */
    public function get_balance(): credit_balance {
        $response = $this->client->get('/v1/credit-balance');
        return credit_balance::from_array($response);
    }

    /**
     * Get transaction history with optional filters.
     *
     * Wraps the flat transactions response with pagination metadata for backward compatibility.
     *
     * @param string|null $type Filter by transaction type (deduction, purchase, refund).
     * @param int $limit Maximum number of results.
     * @param int $offset Pagination offset.
     * @return array{transactions: array, pagination: array} Transactions and pagination info.
     * @throws api_exception If an API error occurs.
     */
    public function get_transactions(?string $type = null, int $limit = 50, int $offset = 0): array {
        $params = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($type !== null) {
            $params['type'] = $type;
        }

        $response = $this->client->get('/v1/credits/transactions', $params);

        $rawtransactions = is_array($response) ? array_values($response) : [];
        $transactions = [];
        foreach ($rawtransactions as $raw) {
            $transactions[] = credit_transaction::from_array($raw)->to_array();
        }
        $count = count($transactions);

        return [
            'transactions' => $transactions,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => $count >= $limit,
            ],
        ];
    }

    /**
     * Get usage statistics aggregated by period.
     *
     * The API returns an array of { period: string, creditsUsed: int } objects.
     * This method normalizes the response to our internal format.
     *
     * @param string $period Aggregation period (day, week, month).
     * @param string|null $startdate Start date in Y-m-d format.
     * @param string|null $enddate End date in Y-m-d format.
     * @return array The usage statistics with keys: period, start_date, end_date, stats.
     * @throws api_exception If an API error occurs.
     */
    public function get_usage_stats(string $period = 'day', ?string $startdate = null, ?string $enddate = null): array {
        $params = [
            'period' => $period,
        ];

        if ($startdate !== null) {
            $params['startDate'] = $startdate;
        }

        if ($enddate !== null) {
            $params['endDate'] = $enddate;
        }

        $response = $this->client->get('/v1/usage-stats', $params);

        // API returns array of { period: string, creditsUsed: int }.
        $stats = [];
        if (is_array($response)) {
            foreach ($response as $item) {
                $stats[] = [
                    'period' => $item['period'] ?? '',
                    'creditsUsed' => $item['creditsUsed'] ?? 0,
                ];
            }
        }

        return [
            'period' => $period,
            'start_date' => $startdate,
            'end_date' => $enddate,
            'stats' => $stats,
        ];
    }

    /**
     * Get recent deductions for display.
     *
     * Convenience method to get the most recent credit deductions.
     *
     * @param int $limit Maximum number of results.
     * @return array The deduction transactions.
     * @throws api_exception If an API error occurs.
     */
    public function get_recent_deductions(int $limit = 10): array {
        $result = $this->get_transactions('deduction', $limit, 0);
        return $result['transactions'];
    }

    /**
     * Format credits as a display string.
     *
     * @param int $credits Credit amount.
     * @return string Formatted credits string (e.g., "1,000 credits").
     */
    public static function format_credits(int $credits): string {
        return number_format($credits) . ' ' . get_string('credits', 'local_dixeo');
    }

    /**
     * Get a summary of credit usage for a period.
     *
     * @param string $period The period (day, week, month).
     * @return array Summary with total_used, average_daily, and period data.
     * @throws api_exception If an API error occurs.
     */
    public function get_usage_summary(string $period = 'month'): array {
        $stats = $this->get_usage_stats($period);

        $totalused = 0;
        $datapoints = count($stats['stats']);

        foreach ($stats['stats'] as $stat) {
            $totalused += $stat['creditsUsed'] ?? 0;
        }

        $average = $datapoints > 0 ? $totalused / $datapoints : 0;

        return [
            'period' => $period,
            'total_used' => $totalused,
            'total_used_formatted' => self::format_credits($totalused),
            'data_points' => $datapoints,
            'average_per_period' => (int) round($average),
            'average_per_period_formatted' => self::format_credits((int) round($average)),
            'stats' => $stats['stats'],
        ];
    }

    /**
     * Check if the API is configured.
     *
     * @return bool True if the API is configured.
     */
    public function is_configured(): bool {
        return $this->client->is_configured();
    }
}
