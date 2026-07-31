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

/**
 * Language strings for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['account_frozen_warning'] = 'Ihr Konto ist wegen niedrigen Kreditstands eingefroren. Bitte laden Sie Credits auf, um Dixeo-AI-Funktionen weiter zu nutzen.';
$string['account_suspended_warning'] = 'Ihr Konto wurde gesperrt. Bitte wenden Sie sich an den Dixeo-Support.';
$string['amount'] = 'Betrag';
$string['api_configuration'] = 'API-Konfiguration';
$string['api_configuration_desc'] = 'Verbindung zur Dixeo-AI-API konfigurieren.';
$string['api_error'] = 'API-Fehler: {$a}';
$string['api_key'] = 'API-Schlüssel';
$string['api_key_desc'] = 'Ihr Dixeo-API-Schlüssel. Sie erhalten ihn im Dixeo-Dashboard.';
$string['api_key_not_configured'] = 'Der Dixeo-API-Schlüssel ist nicht konfiguriert. Bitte konfigurieren Sie ihn in den Plugin-Einstellungen.';
$string['api_url'] = 'API-URL';
$string['api_url_desc'] = 'Basis-URL für die Dixeo-API. Muss HTTPS verwenden (Standard: https://api.dixeo.com).';
$string['average_per_period'] = 'Durchschnitt pro {$a}';
$string['cachedef_coursetemplates'] = 'Zwischengespeicherte Kursstrukturvorlagen von der Dixeo-API';
$string['cachedef_installedplugintypes'] = 'Zwischengespeicherte installierte Aktivitäts-Plugin-Typen für die Dixeo-Generierung';
$string['cachedef_moduletypes'] = 'Zwischengespeicherte Modultypen von der Dixeo-API';
$string['configure_api'] = 'API konfigurieren';
$string['contentimagetitlefallback'] = 'Inhaltsbild';
$string['credit_action_course_structure'] = 'Kursstruktur';
$string['credit_action_edit_module'] = 'Modul bearbeiten';
$string['credit_action_fill_module'] = 'Modul ausfüllen';
$string['credit_action_generate_module'] = 'Modul generieren';
$string['credit_action_image_edit'] = 'Bild bearbeiten';
$string['credit_action_image_generate'] = 'Bild generieren';
$string['credit_action_module_edit'] = 'Modul bearbeiten';
$string['credit_action_module_fill'] = 'Modul ausfüllen';
$string['credit_action_module_generate'] = 'Modul generieren';
$string['credit_action_tutor'] = 'Tutor-Nachricht';
$string['credit_action_tutor_message'] = 'Tutor-Nachricht';
$string['credit_action_unknown'] = 'Unbekannte Aktion';
$string['credit_balance'] = 'Kreditstand';
$string['credit_component_block_dixeo_designer'] = 'Kursdesigner';
$string['credit_component_block_dixeo_modulegen'] = 'Modulgenerator';
$string['credit_component_block_dixeo_tutor'] = 'Studenten-Tutor';
$string['credit_component_filter_dixeo_imageeditor'] = 'Bildeditor';
$string['credit_component_local_dixeo'] = 'Dixeo-Kern';
$string['credit_component_local_dixeo_editor'] = 'Inhaltseditor';
$string['credit_component_unknown'] = 'Unbekannt';
$string['credit_context_site'] = 'Website';
$string['credit_information'] = 'Kreditinformationen';
$string['credit_moduletype_glossary'] = 'Glossar';
$string['credit_moduletype_h5pactivity'] = 'H5P-Aktivität';
$string['credit_moduletype_label'] = 'Textfeld';
$string['credit_moduletype_page'] = 'Seite';
$string['credit_moduletype_quiz'] = 'Test';
$string['credit_moduletype_simplequiz2'] = 'Einfacher Test';
$string['credit_moduletype_slideshow'] = 'Diashow';
$string['credit_report'] = 'Kreditbericht';
$string['credit_report_apply_filters'] = 'Filter anwenden';
$string['credit_report_breakdown'] = 'Credits nach Modul';
$string['credit_report_column_action'] = 'Aktion';
$string['credit_report_column_course'] = 'Kurs: Aktivität';
$string['credit_report_column_credits'] = 'Credits';
$string['credit_report_column_date'] = 'Datum';
$string['credit_report_column_module'] = 'Komponente';
$string['credit_report_column_user'] = 'Benutzer';
$string['credit_report_filter_action'] = 'Aktion';
$string['credit_report_filter_component'] = 'Komponente';
$string['credit_report_filter_course'] = 'Kurs';
$string['credit_report_filter_course_placeholder'] = 'Kurse suchen…';
$string['credit_report_filter_credits_max'] = 'Max. Credits';
$string['credit_report_filter_credits_min'] = 'Min. Credits';
$string['credit_report_filter_moduletype'] = 'Aktivitätstyp';
$string['credit_report_filter_noselection'] = 'Keine Auswahl';
$string['credit_report_filter_placeholder'] = 'Suchen oder auswählen…';
$string['credit_report_filter_user'] = 'Benutzer';
$string['credit_report_filter_user_placeholder'] = 'Benutzer suchen…';
$string['credit_report_filters'] = 'Filter';
$string['credit_report_histogram'] = 'Credits im Zeitverlauf';
$string['credit_report_kpi_courses'] = 'Kurse gesamt';
$string['credit_report_kpi_credits'] = 'Credits gesamt';
$string['credit_report_kpi_rows'] = 'Transaktionen gesamt';
$string['credit_report_kpi_users'] = 'Benutzer gesamt';
$string['credit_report_link_desc'] = 'Erfordert die Berechtigung local/dixeo:manage oder local/dixeo:viewusage auf Systemebene. Bericht und Export enthalten site-weite Benutzer- und Kursnamen.';
$string['credit_report_next_period'] = 'Nächster Zeitraum';
$string['credit_report_no_chart_data'] = 'Keine Diagrammdaten für den ausgewählten Zeitraum.';
$string['credit_report_no_rows'] = 'Keine Kreditnutzungsdatensätze für den ausgewählten Zeitraum und die Filter gefunden.';
$string['credit_report_period'] = 'Zeitraum';
$string['credit_report_prev_period'] = 'Vorheriger Zeitraum';
$string['credit_report_reset_filters'] = 'Zurücksetzen';
$string['credit_report_summary'] = 'Zusammenfassung';
$string['credit_report_view_custom'] = 'Benutzerdefinierter Zeitraum';
$string['credit_report_view_month'] = 'Monat';
$string['credit_report_view_week'] = 'Woche';
$string['credit_usage_report_nav'] = 'Dixeo-Kreditnutzung';
$string['credit_user_unknown'] = 'Unbekannt';
$string['credits'] = 'Credits';
$string['current_balance'] = 'Aktueller Kontostand';
$string['current_balance_desc'] = 'Ihr aktueller Dixeo-Kreditstand. Credits werden für KI-Operationen verwendet.';
$string['data_points'] = 'Datenpunkte';
$string['date'] = 'Datum';
$string['day_fri'] = 'Fr';
$string['day_friday'] = 'Freitag';
$string['day_mon'] = 'Mo';
$string['day_monday'] = 'Montag';
$string['day_sat'] = 'Sa';
$string['day_saturday'] = 'Samstag';
$string['day_sun'] = 'So';
$string['day_sunday'] = 'Sonntag';
$string['day_thu'] = 'Do';
$string['day_thursday'] = 'Donnerstag';
$string['day_tue'] = 'Di';
$string['day_tuesday'] = 'Dienstag';
$string['day_wed'] = 'Mi';
$string['day_wednesday'] = 'Mittwoch';
$string['description'] = 'Beschreibung';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Abschnitt {$a->section}, Aktivität {$a->module}:';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Abschnitt {$a->section}:';
$string['designerstructurevalidate_course_summary_too_long'] = 'Die Kurszusammenfassung ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_course_title_required'] = 'Der Kurstitel ist ein Pflichtfeld.';
$string['designerstructurevalidate_course_title_too_long'] = 'Der Kurstitel darf höchstens {$a->max} Zeichen lang sein.';
$string['designerstructurevalidate_failed'] = 'Dieser Kurs kann erst erstellt werden, wenn diese Probleme behoben sind:

{$a->details}';
$string['designerstructurevalidate_fill_instructions_too_long'] = 'Die an die KI gesendeten Anweisungen sind zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_instructions_api_min'] = 'Anweisungen müssen mindestens {$a->min} Zeichen lang sein.';
$string['designerstructurevalidate_invalid_root'] = 'Die Kursstrukturdaten sind ungültig.';
$string['designerstructurevalidate_module_instructions_required'] = 'Anweisungen für die KI sind erforderlich (mindestens {$a->min} Zeichen).';
$string['designerstructurevalidate_module_instructions_too_long'] = 'Die Anweisungen sind zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_module_invalid'] = 'Das Modul an Position {$a->module} in Abschnitt {$a->section} ist ungültig.';
$string['designerstructurevalidate_module_summary_placeholder'] = 'Ersetzen Sie die Standardzusammenfassung durch eine echte Beschreibung dessen, was diese Aktivität abdeckt.';
$string['designerstructurevalidate_module_summary_too_long'] = 'Die Aktivitätszusammenfassung ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_module_title_placeholder'] = 'Ersetzen Sie den Standardtitel „Neue Seite" durch einen echten Aktivitätsnamen.';
$string['designerstructurevalidate_module_title_required'] = 'Der Aktivitätstitel ist ein Pflichtfeld.';
$string['designerstructurevalidate_module_title_too_long'] = 'Der Aktivitätstitel ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_module_type_not_usable'] = 'Der Typ „{$a->type}" kann auf dieser Website nicht verwendet werden (fehlendes Plugin oder erforderliche Inhaltsbibliothek).';
$string['designerstructurevalidate_module_type_required'] = 'Der Aktivitätstyp ist ein Pflichtfeld.';
$string['designerstructurevalidate_modules_not_array'] = 'Die Modulliste in Abschnitt {$a} ist ungültig.';
$string['designerstructurevalidate_section_invalid'] = 'Abschnitt {$a} in der Struktur ist ungültig.';
$string['designerstructurevalidate_section_summary_too_long'] = 'Die Abschnittszusammenfassung ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_section_title_too_long'] = 'Der Abschnittstitel ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_sections_not_array'] = 'Die Abschnittsliste der Kursstruktur ist ungültig.';
$string['dixeo:contentimageedit'] = 'Eingebettete Inhaltsbilder mit KI bearbeiten';
$string['dixeo:contentimagegenerate'] = 'Eingebettete Inhaltsbilder mit KI generieren';
$string['dixeo:create'] = 'Kurse mit dem Dixeo-Kursdesigner erstellen';
$string['dixeo:edit'] = 'Bestehende Module mit KI bearbeiten';
$string['dixeo:generate'] = 'Neue Module mit KI erstellen (Seite, Beschriftung, Test, Glossar)';
$string['dixeo:manage'] = 'Dixeo-Einstellungen verwalten und Berichte anzeigen';
$string['dixeo:syncfiles'] = 'Dixeo-Kursdateisynchronisation mit der externen API aktivieren, deaktivieren oder auslösen';
$string['dixeo:viewusage'] = 'Site-weite Kreditnutzungsberichte anzeigen und Daten exportieren';
$string['dixeo_course_image_unsupported_type'] = 'Nicht unterstützter Typ des generierten Bildes.';
$string['dixeo_image_generation_disabled'] = 'Die Bildgenerierung ist in den Website-Einstellungen deaktiviert.';
$string['dixeo_image_job_empty_result'] = 'Der Bildauftrag lieferte keine Bilddaten.';
$string['dixeo_image_job_failed'] = 'Bildgenerierung fehlgeschlagen. Bitte versuchen Sie es erneut.';
$string['dixeo_image_job_locked'] = 'Für dieses Bild läuft bereits ein Bildauftrag.';
$string['dixeo_image_not_eligible'] = 'Dieses Bild kann nicht bearbeitet werden.';
$string['dixeo_pluginfile_not_found'] = 'Die Bilddatei konnte nicht aus dem Speicher gelesen werden.';
$string['dsl_error'] = 'Modulerstellung fehlgeschlagen: {$a}';
$string['editorimageorphaned'] = 'Image removed from editor content before completion';
$string['error:api_url_https_required'] = 'Die Dixeo-API-URL muss eine absolute HTTPS-Adresse sein (beispielsweise https://api.dixeo.com).';
$string['error:authentication'] = 'Authentifizierung fehlgeschlagen. Bitte überprüfen Sie Ihren API-Schlüssel.';
$string['error:connection'] = 'Verbindung zur Dixeo-API fehlgeschlagen. Bitte überprüfen Sie Ihre Netzwerkverbindung.';
$string['error:job_failed'] = 'Auftragsverarbeitung fehlgeschlagen: {$a}';
$string['error:job_not_found'] = 'Der angeforderte Auftrag wurde nicht gefunden.';
$string['error:notslideshow'] = 'Das Kursmodul ist keine Slideshow-Aktivität.';
$string['error:payment_required'] = 'Unzureichende Credits. Bitte laden Sie Credits auf, um fortzufahren.';
$string['error:rate_limit'] = 'Ratenlimit überschritten. Bitte warten Sie, bevor Sie weitere Anfragen senden.';
$string['error:slidenotinslideshow'] = 'Die angeforderte Folie gehört nicht zu dieser Slideshow.';
$string['error:timeout'] = 'Zeitüberschreitung. Sie können den Auftragsstatus später prüfen.';
$string['error:upstream_ai'] = 'KI-Servicefehler. Bitte versuchen Sie es später erneut.';
$string['error:validation'] = 'Ungültige Anfrage: {$a}';
$string['eventcreditreportexported'] = 'Kreditnutzungsbericht exportiert';
$string['eventcreditreportexporteddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Kreditnutzungsbericht exportiert (Ansicht={$a->view}, Format={$a->dataformat}, Zeilen={$a->rowcount}).';
$string['eventcreditreportviewed'] = 'Kreditnutzungsbericht angesehen';
$string['eventcreditreportvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Kreditnutzungsbericht angesehen (Ansicht={$a->view}, Zeilen={$a->rowcount}).';
$string['eventfilesyncdisabled'] = 'Dixeo-Kursdatei-Synchronisation deaktiviert';
$string['eventfilesyncdisableddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' deaktiviert (removefiles={$a->removefiles}).';
$string['eventfilesyncenabled'] = 'Dixeo-Kursdatei-Synchronisation aktiviert';
$string['eventfilesyncenableddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' aktiviert.';
$string['eventfilesynctriggered'] = 'Dixeo-Kursdatei-Synchronisation ausgelöst';
$string['eventfilesynctriggereddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' ausgelöst.';
$string['eventjobcancelled'] = 'Dixeo-Auftrag abgebrochen';
$string['eventjobcancelleddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Dixeo-Auftrag \'{$a->jobid}\' für den Kurs mit der ID \'{$a->courseid}\' abgebrochen.';
$string['feedback_correct'] = 'Gut gemacht, diese Antwort war richtig. Weiter so!';
$string['feedback_incorrect'] = 'Diesmal nicht ganz richtig. Den Stoff zu wiederholen wird dir helfen, dich zu verbessern.';
$string['feedback_partial'] = 'Du bist auf dem richtigen Weg. Schau dir den Stoff an, dann klappt es.';
$string['files'] = 'Dateien';
$string['filesync_disable_remove'] = 'Deaktivieren und Sync-Daten löschen';
$string['filesync_enable'] = 'Synchronisation aktivieren';
$string['filesync_error_retry'] = 'Wird automatisch erneut versucht';
$string['filesync_failed'] = 'Dateisynchronisation fehlgeschlagen: {$a}';
$string['filesync_files_count'] = '{$a} Dateien synchronisiert';
$string['filesync_label'] = 'Synchronisieren';
$string['filesync_pause'] = 'Synchronisation pausieren';
$string['filesync_progress'] = '{$a}% abgeschlossen';
$string['filesync_resync'] = 'Jetzt synchronisieren';
$string['filesync_status_disabled'] = 'Synchronisation deaktiviert';
$string['filesync_status_error'] = 'Synchronisationsfehler';
$string['filesync_status_none'] = 'Keine Dateien synchronisiert';
$string['filesync_status_outdated'] = 'Inhalt geändert, Synchronisation nötig';
$string['filesync_status_paused'] = 'Synchronisation pausiert';
$string['filesync_status_synchronized'] = 'Dateien synchronisiert';
$string['filesync_status_syncing'] = 'Dateien werden synchronisiert...';
$string['filesync_timeout'] = 'Zeitüberschreitung bei der Dateisynchronisation, bevor Kursdateien indiziert wurden';
$string['filesync_title'] = 'Dixeo-Dateisynchronisation';
$string['generation_output_language'] = 'SPRACHE: Generieren Sie alle für Lernende sichtbaren Inhalte (Fragen, Antworten, Lektionstexte und Titel) in {$a->language}.';
$string['image_generation'] = 'Bildgenerierung';
$string['image_generation_content_mode'] = 'Inhaltsbilder';
$string['image_generation_content_mode_desc'] = 'Steuert KI-Bildaktionen für Bilder in Aktivitätsinhalten, z. B. Seiten und Bücher.';
$string['image_generation_course_mode'] = 'Kursbilder';
$string['image_generation_course_mode_desc'] = 'Steuert KI-Bildaktionen für das Kursübersichtsbild.';
$string['image_generation_desc'] = 'Steuert die Verfügbarkeit von KI-Bildgenerierung und Bildbearbeitung für Kurs- und Abschnittsbilder.';
$string['image_generation_enabled'] = 'Bildgenerierung aktivieren';
$string['image_generation_enabled_desc'] = 'Wenn deaktiviert, werden alle Anfragen zum Generieren oder Bearbeiten von Bildern blockiert.';
$string['image_generation_mode_disabled'] = 'Deaktiviert';
$string['image_generation_mode_generate'] = 'Generieren';
$string['image_generation_mode_generate_edit'] = 'Generieren und Bearbeiten';
$string['image_generation_section_mode'] = 'Abschnittsbilder';
$string['image_generation_section_mode_desc'] = 'Steuert KI-Bildaktionen für Kapitel-/Abschnittsbilder.';
$string['last_sync'] = 'Letzte Synchronisation';
$string['namespace'] = 'Namespace';
$string['namespace_desc'] = 'Nur erforderlich, wenn mehrere Moodle-Sites denselben API-Schlüssel nutzen. Jede Website sollte einen anderen Namespace verwenden (z. B. "production", "staging", "site1"), um Daten getrennt zu halten. Lassen Sie "default", wenn dies die einzige Website ist, die diesen API-Schlüssel verwendet.';
$string['no_transactions'] = 'Keine Transaktionen gefunden.';
$string['no_usage_data'] = 'Keine Nutzungsdaten für den gewählten Zeitraum verfügbar.';
$string['overview'] = 'Dixeo-Übersicht';
$string['page_x_of_y'] = 'Seite {$a->current} von {$a->total}';
$string['pagination'] = 'Seitennavigation';
$string['period'] = 'Zeitraum';
$string['period_day'] = 'Täglich';
$string['period_month'] = 'Monatlich';
$string['period_week'] = 'Wöchentlich';
$string['pluginname'] = 'Dixeo AI';
$string['pluginname_desc'] = 'Dixeo-AI-Integration für intelligente Inhaltserstellung und -bearbeitung.';
$string['practice_quiz_default_title'] = 'Übungsquiz';
$string['practice_quiz_difficulty_easy'] = 'einfach (Grundwissen, unkomplizierte Konzepte, für Anfänger geeignet)';
$string['practice_quiz_difficulty_hard'] = 'schwer (anspruchsvolle Anwendung, Analyse oder Synthese fortgeschrittener Konzepte)';
$string['practice_quiz_difficulty_medium'] = 'mittel (moderate Tiefe, Verständnis über reines Abrufen hinaus erforderlich)';
$string['practice_quiz_error_invalid_result'] = 'Ungültiges Auftragsergebnis.';
$string['practice_quiz_error_job_not_completed'] = 'Auftrag ist nicht abgeschlossen. Status: {$a->status}';
$string['practice_quiz_error_no_questions'] = 'Keine Fragen im Auftragsergebnis.';
$string['practice_quiz_error_wrong_module_type'] = 'Der Auftrag ist keine simplequiz2-Generierung.';
$string['practice_quiz_instructions'] = 'Generieren Sie ein Übungsquiz für {$a->scopedescription}.

PFLICHTANFORDERUNGEN — Sie MÜSSEN diese exakt befolgen:
1. FRAGENANZAHL: Das Array „questions" MUSS genau {$a->count} Fragen enthalten. Geben Sie nicht {$a->count} minus eins, {$a->count} plus eins oder eine andere Anzahl aus — genau {$a->count}.
2. SCHWIERIGKEITSGRAD: Jede Frage MUSS dem Schwierigkeitsgrad {$a->difficultylabel} entsprechen.
3. FORMAT: Jede Frage MUSS eine Multiple-Choice-Frage mit 3 oder 4 Antwortoptionen und genau einer richtigen Antwort sein.

Überprüfen Sie vor dem Abschluss, dass die Länge des questions-Arrays {$a->count} entspricht und alle Fragen dem Schwierigkeitsgrad {$a->difficulty} entsprechen.
Konzentrieren Sie sich auf den bereitgestellten Kurskontext.';
$string['practice_quiz_scope_activity_description'] = 'die Aktivität „{$a->name}"';
$string['practice_quiz_scope_course_description'] = 'den gesamten Kurs „{$a->name}"';
$string['practice_quiz_scope_section_description'] = 'den Abschnitt „{$a->name}"';
$string['privacy:metadata'] = 'Das Dixeo-Plugin speichert operative Kennungen für die Kursdatei-Synchronisation und sendet Kursinhalte, Tutor-Nachrichten, Generierungskontext und zugehörige Kennungen zur Verarbeitung an die Dixeo-AI-API. Speicherung und Löschung der bei Dixeo gehaltenen Daten steuert dieser externe Dienst.';
$string['privacy:metadata:course_ai'] = 'KI-Dateisynchronisationskonfiguration und -status pro Kurs.';
$string['privacy:metadata:course_ai:courseid'] = 'Der Kurs, zu dem diese Synchronisationskonfiguration gehört.';
$string['privacy:metadata:course_ai:disabledat'] = 'Der Zeitpunkt der Deaktivierung der Dateisynchronisation.';
$string['privacy:metadata:course_ai:disabledby'] = 'Der Benutzer, der die Dateisynchronisation für den Kurs deaktiviert hat.';
$string['privacy:metadata:course_ai:enabled'] = 'Ob die Dateisynchronisation für den Kurs aktiviert ist.';
$string['privacy:metadata:course_ai:enabledat'] = 'Der Zeitpunkt der Aktivierung der Dateisynchronisation.';
$string['privacy:metadata:course_ai:enabledby'] = 'Der Benutzer, der die Dateisynchronisation für den Kurs aktiviert hat.';
$string['privacy:metadata:course_ai:errormessage'] = 'Die letzte Synchronisationsfehlermeldung, falls vorhanden.';
$string['privacy:metadata:course_ai:syncstatus'] = 'Der aktuelle Synchronisationsstatus.';
$string['privacy:metadata:course_ai:timecreated'] = 'Der Erstellungszeitpunkt des Synchronisationsdatensatzes.';
$string['privacy:metadata:course_ai:timemodified'] = 'Der Zeitpunkt der letzten Änderung des Synchronisationsdatensatzes.';
$string['privacy:metadata:credit_usage'] = 'Synchronisierte Dixeo-Kreditnutzungsdatensätze, angereichert mit Moodle-Kontext.';
$string['privacy:metadata:credit_usage:amount'] = 'Der vorzeichenbehaftete Transaktionsbetrag.';
$string['privacy:metadata:credit_usage:cmid'] = 'Die Kursmodul-ID, sofern bekannt.';
$string['privacy:metadata:credit_usage:component'] = 'Die ursprüngliche Dixeo-Komponente.';
$string['privacy:metadata:credit_usage:contextid'] = 'Der Moodle-Kontext, sofern bekannt.';
$string['privacy:metadata:credit_usage:courseid'] = 'Der Kurs, sofern bekannt.';
$string['privacy:metadata:credit_usage:credits'] = 'Der normalisierte Kreditnutzungsbetrag.';
$string['privacy:metadata:credit_usage:description'] = 'Die API-Transaktionsbeschreibung.';
$string['privacy:metadata:credit_usage:jobid'] = 'Die entfernte Dixeo-Auftrags-ID, sofern verfügbar.';
$string['privacy:metadata:credit_usage:jobtype'] = 'Der API-Auftragstyp, sofern verfügbar.';
$string['privacy:metadata:credit_usage:moduletype'] = 'Der Moodle-Aktivitätstyp, sofern verfügbar.';
$string['privacy:metadata:credit_usage:operation'] = 'Der lokale Vorgangs-Fallback.';
$string['privacy:metadata:credit_usage:timecreated'] = 'Der Transaktionszeitstempel.';
$string['privacy:metadata:credit_usage:timesynced'] = 'Zeitpunkt der letzten Synchronisation.';
$string['privacy:metadata:credit_usage:transactionid'] = 'Die entfernte Dixeo-Transaktions-ID.';
$string['privacy:metadata:credit_usage:type'] = 'Der Transaktionstyp.';
$string['privacy:metadata:credit_usage:userid'] = 'Der initiierende Benutzer, sofern bekannt.';
$string['privacy:metadata:external:context'] = 'Kurs-, Abschnitts- oder Modulkontext für Generierung oder Bearbeitung.';
$string['privacy:metadata:external:courseid'] = 'Die Moodle-Kurs-ID, die mit der Anfrage verknüpft ist.';
$string['privacy:metadata:external:description'] = 'Lesbare Beschreibung einer Kursstrukturvorlage, die auf der Dixeo-API gespeichert wird.';
$string['privacy:metadata:external:files'] = 'Kursdateien, extrahierter SCORM-Text und zugehörige Dateimanifeste, die für Synchronisation oder RAG hochgeladen werden.';
$string['privacy:metadata:external:images'] = 'Quellbilder (z. B. Kurs- oder Abschnittsbilder), die bei einer KI-Bildbearbeitung gesendet werden.';
$string['privacy:metadata:external:instructions'] = 'Anweisungen oder Prompts zur Steuerung der KI-Verarbeitung.';
$string['privacy:metadata:external:message'] = 'Tutor- oder Benutzernachrichten, die zur KI-Verarbeitung übermittelt werden.';
$string['privacy:metadata:external:moduletype'] = 'Der für die Generierung angeforderte Aktivitätsmodultyp.';
$string['privacy:metadata:external:name'] = 'Anzeigename einer Kursstrukturvorlage, die auf der Dixeo-API gespeichert wird.';
$string['privacy:metadata:external:namespace'] = 'Der Site-Namespace zur Trennung der Daten dieser Moodle-Instanz auf der Dixeo-API.';
$string['privacy:metadata:external:pagecontext'] = 'Sichtbarer Seitentext oder -kontext, der mit Tutorennachrichten gesendet wird, um die KI-Antwort zu fundieren.';
$string['privacy:metadata:external:summary'] = 'Kurs- oder Abschnittszusammenfassung als Eingabe für die Bildgenerierung.';
$string['privacy:metadata:external:templatedefinition'] = 'Strukturierte Definition einer Kursvorlage (Abschnitte und Aktivitätsplätze), die an die Dixeo-API gesendet oder dort gespeichert wird.';
$string['privacy:metadata:external:templateid'] = 'Kennung einer Kursstrukturvorlage bei der Generierung einer Kursgliederung.';
$string['privacy:metadata:external:title'] = 'Kurs- oder Abschnittstitel als Eingabe für die Bildgenerierung.';
$string['privacy:metadata:external:userid'] = 'Die Moodle-Benutzer-ID, die mit der Anfrage verknüpft ist (z. B. Tutorengespräche).';
$string['privacy:metadata:externalpurpose'] = 'Daten werden an die Dixeo-AI-API gesendet für Inhaltsgenerierung, Tutoring, Bildgenerierung, Kreditberichte und Kursdatei-Synchronisation. Remote-Aufbewahrung und -Löschung verwaltet Dixeo gemäß dem institutionellen Vertrag; dieses Plugin kann Remote-Kopien nicht über Moodle-Privacy-Workflows löschen.';
$string['privacy:metadata:image_job'] = 'Async image generation and editing jobs for course content and structure.';
$string['privacy:metadata:image_job:courseid'] = 'The course the image job belongs to.';
$string['privacy:metadata:image_job:errormessage'] = 'A generic failure message when the image job failed.';
$string['privacy:metadata:image_job:jobid'] = 'The remote Dixeo job identifier.';
$string['privacy:metadata:image_job:prompt'] = 'The image generation or edit prompt.';
$string['privacy:metadata:image_job:status'] = 'The current status of the image job.';
$string['privacy:metadata:image_job:timecreated'] = 'The time when the image job record was created.';
$string['privacy:metadata:image_job:timemodified'] = 'The time when the image job record was last modified.';
$string['privacy:metadata:image_job:userid'] = 'The user who started the image job.';
$string['privacy:metadata:jobs'] = 'Lokale Datensätze, die entfernte Dixeo-AI-Aufträge mit Moodle-Kursen und -Benutzern verknüpfen.';
$string['privacy:metadata:jobs:cmid'] = 'Die Kursmodul-ID, sofern bekannt.';
$string['privacy:metadata:jobs:component'] = 'Die ursprüngliche Dixeo-Komponente für den Auftrag.';
$string['privacy:metadata:jobs:contextid'] = 'Der Moodle-Kontext, sofern bekannt.';
$string['privacy:metadata:jobs:courseid'] = 'Der Kurs, dem der Auftrag zugeordnet ist.';
$string['privacy:metadata:jobs:jobid'] = 'Die entfernte Dixeo-Auftrags-ID.';
$string['privacy:metadata:jobs:moduletype'] = 'Der Moodle-Aktivitätstyp, sofern bekannt.';
$string['privacy:metadata:jobs:namespace'] = 'Der für den Auftrag verwendete Dixeo-API-Namespace.';
$string['privacy:metadata:jobs:operation'] = 'Der logische Vorgangstyp des Auftrags.';
$string['privacy:metadata:jobs:timecreated'] = 'Der Zeitpunkt der Erstellung der lokalen Auftragsbindung.';
$string['privacy:metadata:jobs:userid'] = 'Der Benutzer, der den Auftrag gestartet hat.';
$string['privacy:path:course_ai'] = 'Kurs-KI-Synchronisation';
$string['privacy:path:credit_usage'] = 'Kreditnutzung';
$string['privacy:path:image_jobs'] = 'Dixeo image jobs';
$string['privacy:path:jobs'] = 'Dixeo-AI-Aufträge';
$string['recent_transactions'] = 'Transaktionsverlauf';
$string['state_active'] = 'Aktiv';
$string['state_frozen'] = 'Eingefroren';
$string['state_suspended'] = 'Gesperrt';
$string['task_cleanup_image_jobs'] = 'Dixeo-Bildauftragsdatensätze bereinigen';
$string['task_cleanup_jobs'] = 'Alte Auftragsdatensätze bereinigen';
$string['task_poll_image'] = 'Dixeo-Bildauftrag abfragen';
$string['task_poll_image_generation'] = 'Dixeo-Bildgenerierungsauftrag abfragen';
$string['task_process_file_sync'] = 'Dixeo-Dateisynchronisation verarbeiten';
$string['teach_lesson_default_title'] = 'Individuelle Lektion';
$string['teach_lesson_error_invalid_result'] = 'Ungültiges Auftragsergebnis.';
$string['teach_lesson_error_job_not_completed'] = 'Auftrag ist nicht abgeschlossen. Status: {$a->status}';
$string['teach_lesson_error_no_content'] = 'Kein Inhalt im Auftragsergebnis.';
$string['teach_lesson_error_wrong_module_type'] = 'Der Auftrag ist keine Page-Generierung.';
$string['teach_lesson_instructions'] = 'Generieren Sie eine individuelle Page-Modul-Lektion für {$a->scopedescription}.

Der Lernende hat gefragt:
"{$a->learnerrequest}"

PFLICHTANFORDERUNGEN — Sie MÜSSEN diese exakt befolgen:
1. MODULTYP: Geben Sie ein Page-Modul mit einem klaren, beschreibenden Namen, einer kurzen einleitenden Zusammenfassung (intro) und reichhaltigem Hauptinhalt (content) aus.
2. STRUKTUR: Organisieren Sie die Lektion mit klaren Überschriften und logischen Abschnitten. Verwenden Sie Beispiele, wo hilfreich.
3. LERNENDENANFRAGE: Gehen Sie direkt auf die Anfrage des Lernenden ein — vertiefen Sie das Thema oder erklären Sie es in einfacheren Begriffen, wie er es verlangt hat.
4. AUSRICHTUNG: Stützen Sie die Lektion auf den bereitgestellten Kurskontext. Erfinden Sie keine Fakten, die dem Quellmaterial widersprechen.

Überprüfen Sie vor dem Abschluss, dass das content-Feld substantiell ist und direkt auf die Anfrage des Lernenden eingeht.';
$string['this_week_usage'] = 'Diese Woche';
$string['total_used'] = 'Gesamtverbrauch';
$string['transaction_type_deduction'] = 'Nutzung';
$string['transaction_type_purchase'] = 'Kauf';
$string['transaction_type_refund'] = 'Rückerstattung';
$string['transaction_type_reset'] = 'Erneuerung';
$string['type'] = 'Typ';
$string['usage_chart_label'] = 'Kreditverbrauch';
$string['usage_statistics'] = 'Nutzungsstatistiken';
$string['view_credit_report'] = 'Kreditnutzungsbericht anzeigen';
$string['viewusage_desc'] = 'Gewährt Zugriff auf die Kreditübersicht, den detaillierten Bericht und Exporte für die gesamte Website. Der Bericht enthält Benutzer- und Kursnamen. Nur vertrauenswürdigen Rollen zuweisen; Manager erhalten dies standardmäßig. Benutzer mit local/dixeo:manage können den Bericht ebenfalls öffnen.';
$string['week_total'] = 'Gesamtverbrauch diese Woche';
