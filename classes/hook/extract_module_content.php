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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo\hook;

use core\hook\stoppable_trait;

/**
 * Hook to collect the textual content of a module Dixeo does not read natively.
 *
 * Dispatched once per module while building an AI context, only for module types Dixeo has
 * no built-in extractor for and whose search area returned nothing. The first callback that
 * provides content wins.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to provide the content of a module type Dixeo cannot read natively.')]
#[\core\attribute\tags('local_dixeo')]
class extract_module_content implements \Psr\EventDispatcher\StoppableEventInterface {
    use stoppable_trait;

    /** @var string|null Content provided by a callback. */
    private ?string $content = null;

    /**
     * Constructor.
     *
     * @param \cm_info $cm The module whose content is requested.
     */
    public function __construct(
        /** @var \cm_info The module whose content is requested. */
        public readonly \cm_info $cm,
    ) {
    }

    /**
     * Provide the content of the module, as plain text or HTML.
     *
     * @param string $content The module content.
     * @return void
     */
    public function set_content(string $content): void {
        $this->content = $content;
        $this->stop_propagation();
    }

    /**
     * Get the content provided by a callback.
     *
     * @return string|null The content, or null when no callback provided any.
     */
    public function get_content(): ?string {
        return $this->content;
    }
}
