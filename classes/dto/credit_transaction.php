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
 * Data transfer object for a Dixeo credit transaction.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_transaction {
    /** @var string Deduction transaction type. */
    public const TYPE_DEDUCTION = 'deduction';

    /** @var string Refund transaction type. */
    public const TYPE_REFUND = 'refund';

    /** @var string Purchase transaction type. */
    public const TYPE_PURCHASE = 'purchase';

    /** @var string Reset transaction type. */
    public const TYPE_RESET = 'reset';

    /**
     * Constructor.
     *
     * @param string $id Transaction UUID.
     * @param string $type Transaction type.
     * @param int $amount Signed amount in API mills.
     * @param int $balanceafter Balance after transaction.
     * @param int $createdat Unix timestamp.
     * @param string|null $description Human-readable description.
     * @param string|null $jobid Associated job UUID.
     * @param string|null $jobtype API job type.
     * @param string|null $moduletype Moodle module type.
     */
    public function __construct(
        /** @var string Transaction UUID. */
        public readonly string $id,
        /** @var string Transaction type. */
        public readonly string $type,
        /** @var int Signed amount in API mills. */
        public readonly int $amount,
        /** @var int Balance after transaction. */
        public readonly int $balanceafter,
        /** @var int Unix timestamp. */
        public readonly int $createdat,
        /** @var string|null Human-readable description. */
        public readonly ?string $description = null,
        /** @var string|null Associated job UUID. */
        public readonly ?string $jobid = null,
        /** @var string|null API job type. */
        public readonly ?string $jobtype = null,
        /** @var string|null Moodle module type. */
        public readonly ?string $moduletype = null,
    ) {
    }

    /**
     * Create from API response array.
     *
     * @param array $data API transaction payload.
     * @return self
     */
    public static function from_array(array $data): self {
        $createdat = 0;
        if (!empty($data['createdAt'])) {
            $createdat = strtotime((string) $data['createdAt']);
            if ($createdat === false) {
                $createdat = 0;
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            amount: (int) ($data['amount'] ?? 0),
            balanceafter: (int) ($data['balanceAfter'] ?? 0),
            createdat: $createdat,
            description: isset($data['description']) ? (string) $data['description'] : null,
            jobid: !empty($data['jobId']) ? (string) $data['jobId'] : null,
            jobtype: !empty($data['jobType']) ? (string) $data['jobType'] : null,
            moduletype: !empty($data['moduleType']) ? (string) $data['moduleType'] : null,
        );
    }

    /**
     * Normalized positive credits for usage display.
     *
     * @return int
     */
    public function get_usage_credits(): int {
        return abs($this->amount);
    }

    /**
     * Convert to array for backward-compatible service responses.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balanceAfter' => $this->balanceafter,
            'createdAt' => $this->createdat > 0 ? gmdate('c', $this->createdat) : '',
            'description' => $this->description,
            'jobId' => $this->jobid,
            'jobType' => $this->jobtype,
            'moduleType' => $this->moduletype,
        ];
    }
}
