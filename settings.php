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

/**
 * Admin settings for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    // Create settings page.
    $settings = new admin_settingpage('local_dixeo', get_string('pluginname', 'local_dixeo'));

    // API Configuration section.
    $settings->add(new admin_setting_heading(
        'local_dixeo/api_config',
        get_string('api_configuration', 'local_dixeo'),
        get_string('api_configuration_desc', 'local_dixeo')
    ));

    // API URL setting (HTTPS required).
    $settings->add(new \local_dixeo\admin_setting_configapiurl(
        'local_dixeo/api_url',
        get_string('api_url', 'local_dixeo'),
        get_string('api_url_desc', 'local_dixeo'),
        'https://api.dixeo.com',
        PARAM_URL
    ));

    // API Key setting (password field for security).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_dixeo/api_key',
        get_string('api_key', 'local_dixeo'),
        get_string('api_key_desc', 'local_dixeo'),
        ''
    ));

    // Namespace setting.
    $settings->add(new admin_setting_configtext(
        'local_dixeo/namespace',
        get_string('namespace', 'local_dixeo'),
        get_string('namespace_desc', 'local_dixeo'),
        'default',
        PARAM_ALPHANUMEXT
    ));

    // Image generation section.
    $settings->add(new admin_setting_heading(
        'local_dixeo/image_generation',
        get_string('image_generation', 'local_dixeo'),
        get_string('image_generation_desc', 'local_dixeo')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_dixeo/image_generation_enabled',
        get_string('image_generation_enabled', 'local_dixeo'),
        get_string('image_generation_enabled_desc', 'local_dixeo'),
        1
    ));

    $imagemodechoices = [
        'disabled' => get_string('image_generation_mode_disabled', 'local_dixeo'),
        'generate' => get_string('image_generation_mode_generate', 'local_dixeo'),
        'generate_edit' => get_string('image_generation_mode_generate_edit', 'local_dixeo'),
    ];

    $settings->add(new admin_setting_configselect(
        'local_dixeo/image_generation_course_mode',
        get_string('image_generation_course_mode', 'local_dixeo'),
        get_string('image_generation_course_mode_desc', 'local_dixeo'),
        'generate_edit',
        $imagemodechoices
    ));

    $settings->add(new admin_setting_configselect(
        'local_dixeo/image_generation_section_mode',
        get_string('image_generation_section_mode', 'local_dixeo'),
        get_string('image_generation_section_mode_desc', 'local_dixeo'),
        'generate_edit',
        $imagemodechoices
    ));

    if (\local_dixeo\service\plugin_installation_service::is_component_installed('filter_dixeo_imageeditor')) {
        $settings->add(new admin_setting_configselect(
            'local_dixeo/image_generation_content_mode',
            get_string('image_generation_content_mode', 'local_dixeo'),
            get_string('image_generation_content_mode_desc', 'local_dixeo'),
            'generate_edit',
            $imagemodechoices
        ));
    }

    // Credit Balance Display section.
    $settings->add(new admin_setting_heading(
        'local_dixeo/credit_info',
        get_string('credit_information', 'local_dixeo'),
        ''
    ));

    // Add credit balance display (read-only info).
    $settings->add(new \local_dixeo\admin_setting_credit_balance(
        'local_dixeo/credit_balance_display',
        get_string('current_balance', 'local_dixeo'),
        get_string('current_balance_desc', 'local_dixeo')
    ));

    // Reports link.
    $reporturl = new moodle_url('/local/dixeo/credit_report.php');
    $settings->add(new admin_setting_description(
        'local_dixeo/credit_report_link',
        get_string('credit_report', 'local_dixeo'),
        html_writer::div(
            html_writer::link($reporturl, get_string('view_credit_report', 'local_dixeo'), ['class' => 'btn btn-secondary']),
            'mb-3'
        )
    ));

    // Add to admin tree.
    $ADMIN->add('localplugins', $settings);

    // Conditionally add the Dixeo Course Designer link if the block is installed.
    if (\local_dixeo\service\plugin_installation_service::is_component_installed('block_dixeo_designer')) {
        $ADMIN->add(
            'courses',
            new admin_externalpage(
                'block_dixeo_designer_designacourse',
                get_string('designacourse', 'block_dixeo_designer'),
                new moodle_url('/blocks/dixeo_designer/designer.php'),
                ['local/dixeo:create']
            ),
            'restorecourse'
        );
    }
}
