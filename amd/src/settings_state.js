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
 * Display rules for one Dixeo settings row.
 *
 * @module     local_dixeo/settings_state
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Admin page for the master switch. Other settings have no admin anchor.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} wwwroot Site root without a trailing slash.
     * @returns {string}
     */
    const shortnameUrl = function(setting, wwwroot) {
        if (setting.component === 'local_dixeo' && setting.name === 'enabled') {
            return wwwroot + '/admin/settings.php?section=local_dixeo#id_s_local_dixeo_enabled';
        }
        return '';
    };

    /**
     * One layer object from the payload, or null when this setting has no such layer.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} level site, dixeo, or course.
     * @returns {Object|null}
     */
    const layerAt = function(setting, level) {
        const layers = Array.isArray(setting.layers) ? setting.layers : [];
        const found = layers.find(function(layer) {
            return layer && layer.level === level;
        });
        return found || null;
    };

    /**
     * Label for one select value. The raw value is used when no label was loaded.
     *
     * @param {Object} setting Catalogue row. optionlabels maps a value to its text.
     * @param {string} value
     * @returns {string}
     */
    const optionLabel = function(setting, value) {
        const labels = setting.optionlabels || {};
        const key = value || '';
        return labels[key] || key;
    };

    /**
     * Text for a parent cell. Null means the layer is absent and the cell stays blank.
     * An empty value with no own row is absent too. A stored empty string displays the empty label.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} level site or dixeo.
     * @param {{on: string, off: string, empty: string}} labels
     * @returns {string|null}
     */
    const parentText = function(setting, level, labels) {
        const layer = layerAt(setting, level);
        if (!layer) {
            return null;
        }
        if (setting.type === 'bool') {
            return layer.value === '1' ? labels.on : labels.off;
        }
        if (setting.type === 'select') {
            return optionLabel(setting, layer.value);
        }
        if (layer.value) {
            return layer.value;
        }
        if (layer.stored === undefined || layer.stored === null) {
            return null;
        }
        return labels.empty;
    };

    /**
     * Accent on the cell that supplied the value. Every other control stays grey.
     *
     * @param {string} source customised, dixeo, or site.
     * @param {string} cell customised, dixeo, or site.
     * @returns {string}
     */
    const borderClass = function(source, cell) {
        if (source === cell) {
            return 'local-dixeo-settings-supplied';
        }
        return 'local-dixeo-settings-inherited';
    };

    /**
     * Raw value at one layer, or null when this setting has no such layer.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} level site or dixeo.
     * @returns {string|null}
     */
    const layerRaw = function(setting, level) {
        const layer = layerAt(setting, level);
        if (!layer) {
            return null;
        }
        if (layer.value === undefined || layer.value === null) {
            return '';
        }
        return String(layer.value);
    };

    /**
     * Value the current level inherits. Dixeo when that layer exists, otherwise the site value.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @returns {string}
     */
    const immediateParent = function(setting) {
        const dixeo = layerRaw(setting, 'dixeo');
        if (dixeo !== null) {
            return dixeo;
        }
        const site = layerRaw(setting, 'site');
        return site === null ? '' : site;
    };

    /**
     * Layer that supplies the value when the current level has no own row.
     *
     * A stored Dixeo row supplies Dixeo. Otherwise a core site layer supplies the site
     * value. A Dixeo plugin setting has no site layer, so its catalogue default is Dixeo.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @returns {string} dixeo or site.
     */
    const inheritedSource = function(setting) {
        const dixeo = layerAt(setting, 'dixeo');
        if (dixeo && (dixeo.customised === true || dixeo.customised === 1)) {
            return 'dixeo';
        }
        if (layerAt(setting, 'site')) {
            return 'site';
        }
        return 'dixeo';
    };

    /**
     * Badge source for a draft. An own value is customised. A parent value uses the layer that supplies it.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} draft
     * @returns {string} customised, dixeo, or site.
     */
    const draftOrigin = function(setting, draft) {
        if (draft !== immediateParent(setting)) {
            return 'customised';
        }
        return inheritedSource(setting);
    };

    /**
     * @param {string} value Bool storage, "1" or "0".
     * @returns {string}
     */
    const toggleBool = function(value) {
        return value === '1' ? '0' : '1';
    };

    /**
     * Text for a raw value in a control.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} value
     * @param {{on: string, off: string}} labels
     * @returns {string}
     */
    const displayFor = function(setting, value, labels) {
        if (setting.type === 'bool') {
            return value === '1' ? labels.on : labels.off;
        }
        if (setting.type === 'select') {
            return optionLabel(setting, value);
        }
        return value || '';
    };

    /**
     * Saved value plus the parent values a draft can copy. The draft starts equal to the saved value.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @returns {{setting: Object, saved: string, draft: string, parent: string, site: string|null, dixeo: string|null}}
     */
    const entryFor = function(setting) {
        const saved = (setting.value === undefined || setting.value === null) ? '' : String(setting.value);
        return {
            setting: setting,
            saved: saved,
            draft: saved,
            parent: immediateParent(setting),
            site: layerRaw(setting, 'site'),
            dixeo: layerRaw(setting, 'dixeo'),
        };
    };

    /**
     * Next draft for a row action. Null means the action does not apply.
     *
     * @param {Object} entry Row from {@link entryFor}.
     * @param {string} action revert, parent, or toggle.
     * @param {string} [layer] site or dixeo, for a parent click.
     * @returns {string|null}
     */
    const nextDraft = function(entry, action, layer) {
        if (action === 'revert') {
            return entry.saved;
        }
        if (action === 'parent' && layer === 'site') {
            return entry.site;
        }
        if (action === 'parent' && layer === 'dixeo') {
            return entry.dixeo;
        }
        if (action === 'toggle') {
            return toggleBool(entry.draft);
        }
        return null;
    };

    /**
     * Display state for the current draft.
     *
     * @param {Object} entry Row from {@link entryFor}.
     * @param {{on: string, off: string, badges: {customised: string, dixeo: string, site: string}}} labels
     * @returns {Object}
     */
    const draftView = function(entry, labels) {
        const source = draftOrigin(entry.setting, entry.draft);
        return {
            badge: labels.badges[source],
            display: displayFor(entry.setting, entry.draft, labels),
            valueclass: borderClass(source, 'customised'),
            siteclass: borderClass(source, 'site'),
            dixeoclass: borderClass(source, 'dixeo'),
            showrevert: entry.draft !== entry.saved,
            pressed: entry.draft === '1',
            badgeown: source === 'customised',
        };
    };

    /**
     * Settings whose draft differs from the saved value, in the save service shape.
     *
     * @param {Object} entries Map of row entries.
     * @returns {Array<{component: string, name: string, value: string}>}
     */
    const changedSettings = function(entries) {
        const changes = [];
        Object.keys(entries).forEach(function(key) {
            const entry = entries[key];
            if (entry.draft === entry.saved) {
                return;
            }
            changes.push({
                component: entry.setting.component,
                name: entry.setting.name,
                value: entry.draft,
            });
        });
        return changes;
    };

    /**
     * Search text for one row: label, shortname, plugin id, feature id, and help.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} label
     * @param {string} help
     * @returns {string}
     */
    const searchText = function(setting, label, help) {
        return [label, setting.shortname, setting.component, setting.feature, help].join('\n').toLowerCase();
    };

    /**
     * Whether a row stays visible. Search, Customised only, and the category all apply.
     * Customised means the draft has its own value, including one that is not saved yet.
     *
     * @param {Object} entry Row from {@link entryFor}, with haystack set.
     * @param {{query: string, customisedOnly: boolean, category: string}} filter
     * @returns {boolean}
     */
    const matches = function(entry, filter) {
        if (filter.customisedOnly && entry.draft === entry.parent) {
            return false;
        }
        const query = (filter.query || '').trim().toLowerCase();
        if (query && String(entry.haystack || '').indexOf(query) === -1) {
            return false;
        }
        return categoryMatches(entry, filter.category);
    };

    /**
     * All matches every row. A feature or plugin value matches that group. Preferences matches none.
     *
     * @param {Object} entry Row from {@link entryFor}.
     * @param {string} category all, preferences, feature:id, or plugin:id.
     * @returns {boolean}
     */
    const categoryMatches = function(entry, category) {
        const value = category || 'all';
        if (value === 'all') {
            return true;
        }
        if (value === 'preferences') {
            return false;
        }
        if (value.indexOf('feature:') === 0) {
            return entry.setting.feature === value.substring('feature:'.length);
        }
        if (value.indexOf('plugin:') === 0) {
            return entry.setting.plugingroup === value.substring('plugin:'.length);
        }
        return false;
    };

    /**
     * Category buttons for the current grouping. Empty groups stay hidden unless selected.
     * Preferences is always last and has no settings.
     *
     * @param {Object} entries Map of row entries.
     * @param {string} grouping feature or plugin.
     * @param {{query: string, customisedOnly: boolean, category: string}} filter
     * @param {{allFeatures: string, allPlugins: string, preferences: string, names: Object}} labels
     * @returns {Array}
     */
    const categoryItems = function(entries, grouping, filter, labels) {
        const mode = grouping === 'plugin' ? 'plugin' : 'feature';
        const selected = filter.category || 'all';
        const countFor = function(category) {
            return Object.keys(entries).filter(function(key) {
                return matches(entries[key], {
                    query: filter.query,
                    customisedOnly: filter.customisedOnly,
                    category: category,
                });
            }).length;
        };
        const items = [{
            value: 'all',
            label: mode === 'plugin' ? labels.allPlugins : labels.allFeatures,
            count: countFor('all'),
            preferences: false,
        }];
        const seen = {};
        Object.keys(entries).forEach(function(key) {
            const setting = entries[key].setting;
            const id = mode === 'plugin' ? setting.plugingroup : setting.feature;
            if (id) {
                seen[id] = true;
            }
        });
        const names = labels.names || {};
        Object.keys(seen).sort().forEach(function(id) {
            const value = mode + ':' + id;
            items.push({
                value: value,
                label: names[id] || id,
                count: countFor(value),
                preferences: false,
            });
        });
        items.push({
            value: 'preferences',
            label: labels.preferences,
            count: 0,
            preferences: true,
        });
        items.forEach(function(item) {
            item.hidden = item.count === 0 && item.value !== selected && !item.preferences;
        });
        return items;
    };

    /**
     * How many drafts differ from the saved value.
     *
     * @param {Object} entries Map of row entries.
     * @returns {number}
     */
    const dirtyCount = function(entries) {
        return Object.keys(entries).filter(function(key) {
            return entries[key].draft !== entries[key].saved;
        }).length;
    };

    /**
     * Template context for one row, including parent cells, the source badge, and draft actions.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} label
     * @param {string} help
     * @param {string} wwwroot
     * @param {Object} labels on, off, empty, revert, and badges.
     * @returns {Object}
     */
    const rowView = function(setting, label, help, wwwroot, labels) {
        const site = parentText(setting, 'site', labels);
        const dixeo = parentText(setting, 'dixeo', labels);
        const entry = entryFor(setting);
        const view = draftView(entry, labels);
        return {
            component: setting.component,
            name: setting.name,
            label: label,
            help: help,
            badge: view.badge,
            badgeown: view.badgeown,
            shortname: setting.shortname,
            shortnameurl: shortnameUrl(setting, wwwroot),
            isbool: setting.type === 'bool',
            istextarea: setting.type === 'textarea',
            isselect: setting.type === 'select',
            options: (Array.isArray(setting.options) ? setting.options : []).map(function(value) {
                return {
                    value: value,
                    label: optionLabel(setting, value),
                    selected: value === entry.draft,
                };
            }),
            displayvalue: view.display,
            valueon: view.pressed,
            hassite: site !== null,
            sitevalue: site || '',
            siteclass: view.siteclass,
            hasdixeo: dixeo !== null,
            dixeovalue: dixeo || '',
            dixeoclass: view.dixeoclass,
            valueclass: view.valueclass,
            revertlabel: String(labels.revert || '').replace('{$a}', label),
            showrevert: view.showrevert,
        };
    };

    return {
        shortnameUrl: shortnameUrl,
        rowView: rowView,
        entryFor: entryFor,
        nextDraft: nextDraft,
        draftView: draftView,
        dirtyCount: dirtyCount,
        changedSettings: changedSettings,
        searchText: searchText,
        matches: matches,
        categoryItems: categoryItems,
    };
});
