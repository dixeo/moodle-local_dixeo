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
$string['consent_close'] = 'Schließen';
$string['consent_continue'] = 'Weiter';
$string['consent_error_terms_required'] = 'Setzen Sie beide Häkchen, bevor Sie fortfahren.';
$string['consent_error_version'] = 'Diese Bedingungen wurden aktualisiert. Lesen Sie die aktuelle Fassung und fahren Sie dann fort.';
$string['consent_exit'] = 'Beenden';
$string['consent_manage'] = 'Dixeo-Bedingungen und Datenschutz';
$string['consent_manage_intro'] = 'Lesen Sie die Nutzungsbedingungen und den Datenschutzhinweis erneut oder widerrufen Sie Ihre Annahme.';
$string['consent_privacy_body'] = 'Dieser Hinweis erklärt, wie Ihre personenbezogenen Daten verwendet werden, wenn Dixeo aktiviert ist, wie es die Artikel 13 und 14 der DSGVO verlangen. Ihn zu lesen ist für sich genommen keine Einwilligung.

Wer verantwortlich ist. {$a->sitename}, die Organisation, die diese Website betreibt, ist der Verantwortliche: sie entscheidet, warum und wie Ihre personenbezogenen Daten verwendet werden. Kontakt: {$a->contact}. Dixeo, das die Plugins und KI-Dienste bereitstellt, ist der Auftragsverarbeiter. Diese Website sendet Daten an die Dixeo-API unter {$a->apiurl}, damit Dixeo die in den Nutzungsbedingungen beschriebenen Funktionen bereitstellen kann. Entfernte Kopien werden nach dem Vertrag zwischen {$a->sitename} und Dixeo aufbewahrt. Diese Website kann diese entfernten Kopien nicht über die Datenschutzwerkzeuge von Moodle löschen. Fragen zu den Diensten von Dixeo richten Sie an contact@dixeo.com.

Daten, die diese Website speichert oder sendet, wie gegenüber der Datenschutz-API von Moodle angegeben:

- Ihre Moodle-Benutzer-ID, gesendet mit Anfragen, die Sie starten
- Tutor-Nachrichten, die Sie schreiben, Anweisungen und sichtbarer Seitentext, damit der Tutor im Kontext der Seite antworten kann
- Titel und Zusammenfassungen von Kursen, Abschnitten und Aktivitäten; Prompts; Quellbilder; und Kursdateien, einschließlich aus SCORM-Paketen extrahiertem Text, die für Generierung, Bildbearbeitung oder Dateisynchronisation hochgeladen werden
- Namen, Beschreibungen und Strukturdefinitionen von Kursvorlagen, die auf der Dixeo-API gespeichert sind
- Auftrags-IDs, Vorgangsarten, Kreditbeträge und der Kurs oder die Aktivität, zu dem jeder Auftrag gehört
- Bild-Prompts und Auftragsstatus. Lokale Bildauftragsdatensätze werden 7 Tage nach Abschluss des Auftrags gelöscht
- Die Sprache, die Sie für Tests und Lektionen wählen, die der Tutor erzeugt
- Die Benutzer-ID und die Uhrzeit, die erfasst werden, wenn jemand die Dateisynchronisation eines Kurses aktiviert oder deaktiviert

Rechtsgrundlage. Tutoring, Generierung, Bilder, Dateisynchronisation, Kreditabrechnung und Tutor-Nutzungsanalysen sind erforderlich, um die Nutzungsbedingungen zu erfüllen (Artikel 6 Absatz 1 Buchstabe b). Operative Aufzeichnungen für Sicherheit, Support und Kreditabrechnung beruhen auf dem berechtigten Interesse von {$a->sitename} (Artikel 6 Absatz 1 Buchstabe f).

Empfänger. Ihre Daten werden mit der Dixeo-API und, für Kreditberichte, mit Personal geteilt, das diese Website dazu berechtigt hat. Tutor-Nutzungsberichte sind nur für berechtigtes Personal sichtbar.

Internationale Übermittlungen. Daten werden an die oben genannte Dixeo-API gesendet. Jede Übermittlung außerhalb Ihres Landes und die dafür geltenden Garantien sind im Vertrag zwischen {$a->sitename} und Dixeo festgelegt. Diese Website kann den Übermittlungsmechanismus selbst weder sehen noch beschreiben.

Aufbewahrung. Lokale Bildauftragsdatensätze werden 7 Tage nach Abschluss des Auftrags aufbewahrt. Andere lokale Datensätze bleiben bestehen, solange Ihr Konto existiert, und werden entfernt, wenn Ihre personenbezogenen Daten in Moodle gelöscht werden. Die entfernte Aufbewahrung folgt dem Vertrag zwischen {$a->sitename} und Dixeo.

Ihre Rechte. Sie können von {$a->sitename} Auskunft, Berichtigung, Löschung, Einschränkung oder eine übertragbare Kopie Ihrer Daten verlangen und einer Verarbeitung auf Grundlage berechtigter Interessen widersprechen. Sie können auch Beschwerde bei einer Aufsichtsbehörde einlegen. Wenn diese Website die Datenschutzwerkzeuge von Moodle hat, nutzen Sie diese, um die Datenschutzbeauftragte oder den Datenschutzbeauftragten zu erreichen.

Annahme der Bedingungen. Sie akzeptieren die Nutzungsbedingungen, um einen Kurs zu öffnen, in dem Dixeo aktiviert ist. Tutor-Nutzungsanalysen werden erfasst. Wenn Sie nicht zustimmen, wählen Sie Beenden.

Einsatz von KI. Dixeo nutzt KI, um Tutor-Antworten, Kursinhalte und Bilder zu entwerfen. Wenn eine Lehrkraft es einschaltet, erzeugt sie außerdem Vorschläge zur Durchsicht oder Bewertung von Aufgaben für Personen im Kurs. Eine Note, die {$a->sitename} als endgültig behandelt, bleibt unter der Kontrolle der Lehrkraft und der eigenen Bewertungsregeln dieser Website.

Ihre Daten werden nicht für Werbung verwendet.';
$string['consent_privacy_contact_admin'] = 'die Website-Administration';
$string['consent_privacy_dataprotection'] = 'Ihre Datenschutzanfragen öffnen';
$string['consent_privacy_heading'] = 'Datenschutzhinweis';
$string['consent_processing_body'] = 'Dixeo erfasst Tutor-Nutzungsanalysen: die Anzahl der Nachrichten, Übungstests und Lektionen, die Sie erstellen, den Tutormodus, den Kurs, die Aktivität und die Uhrzeit. Der Text Ihrer Nachrichten wird in diesen Datensätzen nicht gespeichert. Berechtigtes Personal kann die Zahlen in Tutor-Nutzungsberichten sehen.';
$string['consent_processing_checkbox'] = 'Meine Tutornutzung (Nachrichtenanzahl, Modi, Kurse, Aktivitäten und Zeitstempel, ohne Nachrichtentext) wird für Nutzungsanalysen und Berichte erfasst.';
$string['consent_processing_heading'] = 'Nutzungsanalysen';
$string['consent_review'] = 'Überprüfen';
$string['consent_save_processing'] = 'Speichern';
$string['consent_saved_processing'] = 'Ihre Analysenauswahl wurde gespeichert.';
$string['consent_status_accepted'] = 'Sie haben die Nutzungsbedingungen akzeptiert (Version {$a->version}) am {$a->time}. Tutor-Nutzungsanalysen werden erfasst (Version {$a->processingversion}) seit {$a->processingtime}.';
$string['consent_status_processing_off'] = 'Tutor-Nutzungsanalysen werden nicht erfasst.';
$string['consent_status_processing_on'] = 'Tutor-Nutzungsanalysen werden erfasst (Version {$a->version}) seit {$a->time}.';
$string['consent_status_terms_accepted'] = 'Sie haben die Nutzungsbedingungen akzeptiert (Version {$a->version}) am {$a->time}.';
$string['consent_status_terms_none'] = 'Sie haben die aktuellen Nutzungsbedingungen nicht akzeptiert. Ein Kurs, in dem Dixeo aktiviert ist, fragt Sie, bevor Sie fortfahren können.';
$string['consent_terms_body'] = 'Diese Bedingungen sind ein Vertrag zwischen Ihnen und {$a->sitename}, der Organisation, die diese Website betreibt. Sie gelten, wann immer Sie Dixeo in einem Kurs nutzen.

Dixeo, der Anbieter der Plugins und KI-Dienste auf dieser Website, bietet KI-Tutoring, Inhaltserzeugung, Bilderzeugung, Synchronisation von Kursdateien und Kreditabrechnung in Kursen an, in denen es aktiviert ist. Wenn Sie fortfahren, stimmen Sie zu, diese Funktionen unter diesen Bedingungen zu nutzen.

Sie dürfen keine rechtswidrigen Inhalte und keine Inhalte übermitteln, die Sie nicht teilen dürfen. KI-Ausgaben können unvollständig oder unzutreffend sein. Sie sind dafür verantwortlich, wie Sie sie verwenden, einschließlich der Arbeiten, die Sie für den Kurs einreichen.

Dixeo kann nicht verfügbar sein, wenn der Dienst ausgesetzt ist oder die Kredite aufgebraucht sind. Dateisynchronisation und KI-Aufträge hängen davon ab, dass der Dixeo-Dienst verfügbar bleibt.

Wenn Sie nicht zustimmen, wählen Sie Beenden. Sie können diesen Kurs nicht betreten, solange Dixeo aktiviert ist. Sie können Ihre Annahme jederzeit in der Dixeo-Konfiguration unter Meine Präferenzen widerrufen, und der Kurs fragt Sie erneut. Der Widerruf löscht keine Kursarbeiten, die Sie bereits erstellt haben.

Dokumentversion {$a->version}.';
$string['consent_terms_checkbox'] = 'Ich habe die Nutzungsbedingungen gelesen und akzeptiere sie und nehme den Datenschutzhinweis zur Kenntnis.';
$string['consent_terms_heading'] = 'Nutzungsbedingungen';
$string['consent_title'] = 'Nutzungsbedingungen und Datenschutzhinweis';
$string['consent_withdraw_confirm'] = 'Der Widerruf nimmt Ihre Annahme der Nutzungsbedingungen zurück. Bereits gespeicherte Tutor-Nutzungsanalysen werden gelöscht. Ein Kurs, in dem Dixeo aktiviert ist, fragt Sie erneut.';
$string['consent_withdraw_confirm_title'] = 'Annahme widerrufen?';
$string['consent_withdraw_terms'] = 'Widerrufen';
$string['consent_withdrawn_terms'] = 'Ihre Annahme der Nutzungsbedingungen wurde widerrufen. Ein Dixeo-Kurs wird Sie erneut fragen.';
$string['content_image_failed'] = 'Es ist ein Fehler aufgetreten';
$string['content_image_generating'] = 'Wird generiert...';
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
$string['credit_report_filter_moduletype'] = 'Aktivitätstyp';
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
$string['credit_report_no_rows'] = 'Keine Kreditnutzungsdatensätze für den ausgewählten Zeitraum und die Filter gefunden.';
$string['credit_report_period'] = 'Zeitraum';
$string['credit_report_prev_period'] = 'Vorheriger Zeitraum';
$string['credit_report_range_too_long'] = 'Benutzerdefinierte Zeiträume dürfen ein Jahr nicht überschreiten.';
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
$string['day_mon'] = 'Mo';
$string['day_sat'] = 'Sa';
$string['day_sun'] = 'So';
$string['day_thu'] = 'Do';
$string['day_tue'] = 'Di';
$string['day_wed'] = 'Mi';
$string['description'] = 'Beschreibung';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Abschnitt {$a->section}, Aktivität {$a->module}:';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Abschnitt {$a->section}:';
$string['designerstructurevalidate_course_summary_too_long'] = 'Die Kurszusammenfassung ist zu lang (maximal {$a->max} Zeichen).';
$string['designerstructurevalidate_course_title_required'] = 'Der Kurstitel ist ein Pflichtfeld.';
$string['designerstructurevalidate_course_title_too_long'] = 'Der Kurstitel darf höchstens {$a->max} Zeichen lang sein.';
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
$string['disabled_block_course'] = 'Dieser Block ist nicht verfügbar, weil Dixeo für diesen Kurs deaktiviert wurde.';
$string['disabled_block_site'] = 'Dieser Block ist nicht verfügbar, weil Dixeo deaktiviert wurde. Sie können einen Administrator bitten, ihn zu aktivieren.';
$string['dixeo:contentimageedit'] = 'Eingebettete Inhaltsbilder mit KI bearbeiten';
$string['dixeo:contentimagegenerate'] = 'Eingebettete Inhaltsbilder mit KI generieren';
$string['dixeo:create'] = 'Kurse mit dem Kursdesigner erstellen';
$string['dixeo:edit'] = 'Bestehende Module mit KI bearbeiten';
$string['dixeo:generate'] = 'Neue Module mit KI erstellen (Seite, Beschriftung, Test, Glossar)';
$string['dixeo:manage'] = 'Dixeo-Einstellungen verwalten und Berichte anzeigen';
$string['dixeo:syncfiles'] = 'Dixeo-Kursdateisynchronisation mit der externen API aktivieren, deaktivieren oder auslösen';
$string['dixeo:viewtutorusage'] = 'Tutor-Nutzungsbericht in Kursen anzeigen';
$string['dixeo:viewtutorusagesite'] = 'Site-weiten Tutor-Nutzungsbericht anzeigen';
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
$string['enabled'] = 'Dixeo aktivieren';
$string['enabled_course'] = 'Dixeo aktivieren';
$string['enabled_course_help'] = 'Dixeo-Funktionen in diesem Kurs aktivieren oder deaktivieren.';
$string['enabled_help'] = 'Dixeo-Funktionen global aktivieren oder deaktivieren.';
$string['error:activity_not_available'] = 'Der Aktivitätstyp {$a} ist nicht installiert oder nicht aktiviert.';
$string['error:api_url_https_required'] = 'Die Dixeo-API-URL muss eine absolute HTTPS-Adresse sein (beispielsweise https://api.dixeo.com).';
$string['error:authentication'] = 'Authentifizierung fehlgeschlagen. Bitte überprüfen Sie Ihren API-Schlüssel.';
$string['error:dixeo_disabled'] = 'Dixeo ist ausgeschaltet.';
$string['error:job_not_found'] = 'Der angeforderte Auftrag wurde nicht gefunden.';
$string['error:notslideshow'] = 'Das Kursmodul ist keine Slideshow-Aktivität.';
$string['error:payment_required'] = 'Unzureichende Credits. Bitte laden Sie Credits auf, um fortzufahren.';
$string['error:rate_limit'] = 'Ratenlimit überschritten. Bitte warten Sie, bevor Sie weitere Anfragen senden.';
$string['error:slidenotinslideshow'] = 'Die angeforderte Folie gehört nicht zu dieser Slideshow.';
$string['error:unexpected'] = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder bitten Sie Ihre Administration, die Website-Protokolle zu prüfen.';
$string['error:upstream_ai'] = 'KI-Servicefehler. Bitte versuchen Sie es später erneut.';
$string['error:validation_failed'] = 'Die Anfrage ist ungültig. Prüfen Sie die Werte und versuchen Sie es erneut.';
$string['eventcreditreportexported'] = 'Kreditnutzungsbericht exportiert';
$string['eventcreditreportexporteddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Kreditnutzungsbericht exportiert (Ansicht={$a->view}, Format={$a->dataformat}, Zeilen={$a->rowcount}).';
$string['eventcreditreportviewed'] = 'Kreditnutzungsbericht angesehen';
$string['eventcreditreportvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Kreditnutzungsbericht angesehen (Ansicht={$a->view}, Zeilen={$a->rowcount}).';
$string['eventfilesyncdisabled'] = 'Dixeo-Kursdatei-Synchronisation deaktiviert';
$string['eventfilesyncdisableddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' deaktiviert (removefiles={$a->removefiles}, remotedelete={$a->remotedeletestatus}).';
$string['eventfilesyncenabled'] = 'Dixeo-Kursdatei-Synchronisation aktiviert';
$string['eventfilesyncenableddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' aktiviert.';
$string['eventfilesynctriggered'] = 'Dixeo-Kursdatei-Synchronisation ausgelöst';
$string['eventfilesynctriggereddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Dateisynchronisation für den Kurs mit der ID \'{$a->courseid}\' ausgelöst.';
$string['eventjobcancelled'] = 'Dixeo-Auftrag abgebrochen';
$string['eventjobcancelleddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Dixeo-Auftrag \'{$a->jobid}\' für den Kurs mit der ID \'{$a->courseid}\' abgebrochen.';
$string['eventsettingupdated'] = 'Dixeo-Einstellung aktualisiert';
$string['eventsettingupdateddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Dixeo-Einstellung \'{$a->component}/{$a->name}\' im Kontext mit der ID \'{$a->contextid}\' aktualisiert.';
$string['eventtutorusagereportexported'] = 'Tutor-Nutzungsbericht exportiert';
$string['eventtutorusagereportexporteddesc'] = 'Benutzer {$a->userid} hat den Tutor-Nutzungsbericht exportiert (Ebene: {$a->level}, Ansicht: {$a->view}, Format: {$a->dataformat}, Zeilen: {$a->rowcount}).';
$string['eventtutorusagereportviewed'] = 'Tutor-Nutzungsbericht angesehen';
$string['eventtutorusagereportvieweddesc'] = 'Benutzer {$a->userid} hat den Tutor-Nutzungsbericht angesehen (Ebene: {$a->level}, Ansicht: {$a->view}, Zeilen: {$a->rowcount}).';
$string['feedback_correct'] = 'Gut gemacht, diese Antwort war richtig. Weiter so!';
$string['feedback_incorrect'] = 'Diesmal nicht ganz richtig. Den Stoff zu wiederholen wird dir helfen, dich zu verbessern.';
$string['feedback_partial'] = 'Du bist auf dem richtigen Weg. Schau dir den Stoff an, dann klappt es.';
$string['file_synchronization'] = 'Dateisynchronisation';
$string['files'] = 'Dateien';
$string['filesync_disable_remove'] = 'Deaktivieren und Sync-Daten löschen';
$string['filesync_enable'] = 'Synchronisation aktivieren';
$string['filesync_enabled'] = 'Dateisynchronisation';
$string['filesync_enabled_default'] = 'Dateisynchronisation für neue Kurse';
$string['filesync_enabled_default_help'] = 'Aktiviert die Dateisynchronisation für neue Kurse (bestehende Kurse bleiben unverändert). Der Kursdesigner muss Dateien während der Kurserstellung synchronisieren; ist diese Einstellung aus, wird die automatische Synchronisation pausiert, sobald der Kurs fertig ist.';
$string['filesync_enabled_help'] = 'Dateisynchronisation für diesen Kurs einschalten oder pausieren. Beim Pausieren bleiben bereits synchronisierte Dateien erhalten.';
$string['filesync_failed'] = 'Dateisynchronisation fehlgeschlagen: {$a}';
$string['filesync_files_count'] = '{$a} Dateien synchronisiert';
$string['filesync_pause'] = 'Synchronisation pausieren';
$string['filesync_pending_deletion'] = 'Die Remote-Dateilöschung für diesen Kurs ist noch ausstehend';
$string['filesync_pill'] = 'Position der Statusanzeige';
$string['filesync_pill_coursetitle'] = 'Kurstitel';
$string['filesync_pill_footer'] = 'Seitenfuß';
$string['filesync_pill_help'] = 'Wo die Dixeo-Statusanzeige auf einer Kursseite erscheint.';
$string['filesync_pill_hidden'] = 'Ausgeblendet';
$string['filesync_progress'] = '{$a}% abgeschlossen';
$string['filesync_resync'] = 'Jetzt synchronisieren';
$string['filesync_status_disabled'] = 'Synchronisation deaktiviert';
$string['filesync_status_error'] = 'Synchronisationsfehler';
$string['filesync_status_none'] = 'Keine Dateien synchronisiert';
$string['filesync_status_outdated'] = 'Inhalt geändert, Synchronisation nötig';
$string['filesync_status_paused'] = 'Synchronisation pausiert';
$string['filesync_status_pending_deletion'] = 'Remote-Dateilöschung ausstehend';
$string['filesync_status_synchronized'] = 'Dateien synchronisiert';
$string['filesync_status_syncing'] = 'Dateien werden synchronisiert...';
$string['filesync_timeout'] = 'Zeitüberschreitung bei der Dateisynchronisation, bevor Kursdateien indiziert wurden';
$string['filesync_title'] = 'Dixeo-Dateisynchronisation';
$string['generation_language'] = 'Generierungssprache';
$string['generation_language_desc'] = 'Sprache für Quiz und Lektionen, die der Tutor erzeugt. Sie entspricht zunächst Ihrer bevorzugten Sprache.';
$string['generation_output_language'] = 'SPRACHE: Generieren Sie alle für Lernende sichtbaren Inhalte (Fragen, Antworten, Lektionstexte und Titel) in {$a->language}.';
$string['image_generation'] = 'Bildgenerierung';
$string['image_generation_content_mode'] = 'Inhaltsbilder';
$string['image_generation_content_mode_desc'] = 'Steuert KI-Bildaktionen für Bilder in Aktivitätsinhalten, z. B. Seiten und Bücher.';
$string['image_generation_content_mode_disabled'] = 'Deaktiviert';
$string['image_generation_content_mode_generate'] = 'Generieren';
$string['image_generation_content_mode_generate_edit'] = 'Generieren und Bearbeiten';
$string['image_generation_course_mode'] = 'Kursbilder';
$string['image_generation_course_mode_desc'] = 'Steuert KI-Bildaktionen für das Kursübersichtsbild.';
$string['image_generation_course_mode_disabled'] = 'Deaktiviert';
$string['image_generation_course_mode_generate'] = 'Generieren';
$string['image_generation_course_mode_generate_edit'] = 'Generieren und Bearbeiten';
$string['image_generation_desc'] = 'Steuert die Verfügbarkeit von KI-Bildgenerierung und Bildbearbeitung für Kurs- und Abschnittsbilder.';
$string['image_generation_enabled'] = 'Bildgenerierung aktivieren';
$string['image_generation_enabled_desc'] = 'Wenn deaktiviert, werden alle Anfragen zum Generieren oder Bearbeiten von Bildern blockiert.';
$string['image_generation_mode_disabled'] = 'Deaktiviert';
$string['image_generation_mode_generate'] = 'Generieren';
$string['image_generation_mode_generate_edit'] = 'Generieren und Bearbeiten';
$string['image_generation_section_mode'] = 'Abschnittsbilder';
$string['image_generation_section_mode_desc'] = 'Steuert KI-Bildaktionen für Kapitel-/Abschnittsbilder.';
$string['image_generation_section_mode_disabled'] = 'Deaktiviert';
$string['image_generation_section_mode_generate'] = 'Generieren';
$string['image_generation_section_mode_generate_edit'] = 'Generieren und Bearbeiten';
$string['last_sync'] = 'Letzte Synchronisation';
$string['learning_intent'] = 'Lernabsicht';
$string['learning_intent_help'] = 'Die Lernabsicht definiert die Lernziele für den Kurs.';
$string['namespace'] = 'Namespace';
$string['namespace_desc'] = 'Nur erforderlich, wenn mehrere Moodle-Sites denselben API-Schlüssel nutzen. Jede Website sollte einen anderen Namespace verwenden (z. B. "production", "staging", "site1"), um Daten getrennt zu halten. Lassen Sie "default", wenn dies die einzige Website ist, die diesen API-Schlüssel verwendet.';
$string['overview'] = 'Dixeo-Übersicht';
$string['pagination'] = 'Seitennavigation';
$string['period'] = 'Zeitraum';
$string['pluginname'] = 'Dixeo AI';
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
$string['preferences_delete_conversation'] = 'Tutor-Konversation löschen';
$string['preferences_delete_conversation_desc'] = 'Dies löscht nur Ihre Tutor-Konversation in diesem Kurs. Konversationen in anderen Kursen bleiben unverändert.';
$string['privacy:metadata:consent'] = 'Aufzeichnungen über die Annahme der Nutzungsbedingungen, einschließlich des Nutzers, des Zeitpunkts und des angezeigten Hinweises.';
$string['privacy:metadata:consent:action'] = 'Ob der Nutzer die Annahme erteilt oder widerrufen hat.';
$string['privacy:metadata:consent:documentid'] = 'Der gespeicherte Hinweis, auf den sich diese Entscheidung bezieht.';
$string['privacy:metadata:consent:timecreated'] = 'Wann der Nutzer erteilt oder widerrufen hat.';
$string['privacy:metadata:consent:userid'] = 'Der Nutzer, zu dem der Datensatz gehört.';
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
$string['privacy:metadata:jobs:accessmode'] = 'Ob der Auftrag initiatorbezogen oder kursgeteilt für die Zugriffskontrolle ist.';
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
$string['privacy:metadata:preference:generation_language'] = 'Die für Quiz und Lektionen des Tutors gewählte Sprache.';
$string['privacy:metadata:tutor_usage_daily'] = 'Tägliche Tutor-Nutzungsaggregate';
$string['privacy:metadata:tutor_usage_daily:cmid'] = 'Die Kursmodul-ID';
$string['privacy:metadata:tutor_usage_daily:courseid'] = 'Die Kurs-ID';
$string['privacy:metadata:tutor_usage_daily:daystart'] = 'Der Tag, den das Aggregat abdeckt';
$string['privacy:metadata:tutor_usage_daily:lastactive'] = 'Zeitstempel der letzten Aktivität';
$string['privacy:metadata:tutor_usage_daily:lessoncreated'] = 'Erstellte Teach-Lektionen';
$string['privacy:metadata:tutor_usage_daily:messages'] = 'Nachrichtenanzahl für den Tag';
$string['privacy:metadata:tutor_usage_daily:mode'] = 'Der Tutor-Modus';
$string['privacy:metadata:tutor_usage_daily:quizcreated'] = 'Erstellte Übungsquizze';
$string['privacy:metadata:tutor_usage_daily:userid'] = 'Die Benutzer-ID';
$string['privacy:metadata:tutor_usage_event'] = 'Tutor-Nutzungsereignisse';
$string['privacy:metadata:tutor_usage_event:cmid'] = 'Die Kursmodul-ID, falls bekannt';
$string['privacy:metadata:tutor_usage_event:courseid'] = 'Der Kurs, in dem das Ereignis aufgetreten ist';
$string['privacy:metadata:tutor_usage_event:eventtype'] = 'Der Typ des Tutor-Nutzungsereignisses';
$string['privacy:metadata:tutor_usage_event:mode'] = 'Der Tutor-Interaktionsmodus';
$string['privacy:metadata:tutor_usage_event:timecreated'] = 'Wann das Ereignis aufgezeichnet wurde';
$string['privacy:metadata:tutor_usage_event:userid'] = 'Der Benutzer, der das Tutor-Ereignis ausgelöst hat';
$string['privacy:metadata:tutor_usage_open'] = 'Offene Tutor-Sitzungen';
$string['privacy:metadata:tutor_usage_open:courseid'] = 'Die Kurs-ID';
$string['privacy:metadata:tutor_usage_open:lastmessage'] = 'Zeitstempel der letzten Nachricht';
$string['privacy:metadata:tutor_usage_open:messagecount'] = 'Nachrichten bisher in der Sitzung';
$string['privacy:metadata:tutor_usage_open:timestart'] = 'Sitzungsstartzeit';
$string['privacy:metadata:tutor_usage_open:userid'] = 'Die Benutzer-ID';
$string['privacy:metadata:tutor_usage_session'] = 'Geschlossene Tutor-Nutzungssitzungen';
$string['privacy:metadata:tutor_usage_session:courseid'] = 'Die Kurs-ID';
$string['privacy:metadata:tutor_usage_session:duration'] = 'Sitzungsdauer in Sekunden';
$string['privacy:metadata:tutor_usage_session:messagecount'] = 'Nachrichten in der Sitzung';
$string['privacy:metadata:tutor_usage_session:timeend'] = 'Sitzungsendzeit';
$string['privacy:metadata:tutor_usage_session:timestart'] = 'Sitzungsstartzeit';
$string['privacy:metadata:tutor_usage_session:userid'] = 'Die Benutzer-ID';
$string['privacy:path:consent'] = 'Entscheidungen zu Bedingungen und Datenschutz';
$string['privacy:path:conversation'] = 'Tutor-Konversation';
$string['privacy:path:course_ai'] = 'Kurs-KI-Synchronisation';
$string['privacy:path:credit_usage'] = 'Kreditnutzung';
$string['privacy:path:image_jobs'] = 'Dixeo image jobs';
$string['privacy:path:jobs'] = 'Dixeo-AI-Aufträge';
$string['privacy:path:tutor_usage'] = 'Tutor-Nutzungsanalysen';
$string['recent_transactions'] = 'Transaktionsverlauf';
$string['settings_badge_customised'] = 'Angepasst';
$string['settings_badge_dixeo'] = 'Dixeo-Standard';
$string['settings_badge_site'] = 'Website-Standard';
$string['settings_button'] = 'Einstellungen';
$string['settings_category'] = 'Dixeo';
$string['settings_category_designer'] = 'Kursdesigner';
$string['settings_category_dixeo_core'] = 'Dixeo';
$string['settings_category_dixeo_designer'] = 'Kursdesigner';
$string['settings_category_dixeo_tutor'] = 'Studenten-Tutor';
$string['settings_category_filesync'] = 'Dateisynchronisation';
$string['settings_category_imagegeneration'] = 'Bildgenerierung';
$string['settings_category_tutor'] = 'Tutor';
$string['settings_column_course'] = 'Kurs';
$string['settings_column_dixeo'] = 'Dixeo';
$string['settings_column_setting'] = 'Einstellung';
$string['settings_column_site'] = 'Website-Standard';
$string['settings_group_feature'] = 'Funktion';
$string['settings_group_plugin'] = 'Plugin';
$string['settings_grouping'] = 'Gruppieren nach';
$string['settings_modal_advanced'] = 'Erweitert';
$string['settings_modal_all_features'] = 'Alle Funktionen';
$string['settings_modal_all_plugins'] = 'Alle Plugins';
$string['settings_modal_breadcrumb'] = 'Website / Dixeo / Kurs: {$a}';
$string['settings_modal_categories'] = 'Kategorien';
$string['settings_modal_count'] = '{$a} Einstellungen';
$string['settings_modal_customised'] = 'Nur angepasst';
$string['settings_modal_empty'] = 'Löschen Sie die Suche, schalten Sie „Nur angepasst“ aus oder wählen Sie einen anderen Bereich.';
$string['settings_modal_error'] = 'Die Dixeo-Einstellungen konnten nicht geladen werden.';
$string['settings_modal_loading'] = 'Einstellungen werden geladen';
$string['settings_modal_preferences'] = 'Meine Präferenzen';
$string['settings_modal_save_course'] = 'Speichern Sie den Kurs, bevor seine Dixeo-Einstellungen geladen werden können.';
$string['settings_modal_save_error'] = 'Die Dixeo-Einstellungen konnten nicht gespeichert werden.';
$string['settings_modal_saving'] = 'Änderungen werden gespeichert...';
$string['settings_modal_search'] = 'Einstellungen suchen';
$string['settings_modal_search_placeholder'] = 'Nach Name, Schlüssel oder Plugin suchen';
$string['settings_modal_status'] = 'Status';
$string['settings_modal_title'] = 'Dixeo-Einstellungen';
$string['settings_modal_unsaved_close'] = 'Schließen ohne zu speichern';
$string['settings_modal_unsaved_confirm'] = 'Diese Änderungen ohne Speichern schließen?';
$string['settings_modal_unsaved_count'] = '{$a} ungespeicherte Änderungen';
$string['settings_modal_unsaved_none'] = 'Keine ungespeicherten Änderungen';
$string['settings_modal_unsaved_title'] = 'Ungespeicherte Änderungen';
$string['settings_readonly'] = 'Diese Einstellung kann nicht geändert werden';
$string['settings_revert'] = 'Änderungen an {$a} rückgängig machen';
$string['settings_value_empty'] = 'leer';
$string['settings_value_off'] = 'Aus';
$string['settings_value_on'] = 'Ein';
$string['state_active'] = 'Aktiv';
$string['state_frozen'] = 'Eingefroren';
$string['state_suspended'] = 'Gesperrt';
$string['task_aggregate_tutor_usage'] = 'Dixeo-Tutor-Nutzungsanalysen aggregieren';
$string['task_cleanup_image_jobs'] = 'Dixeo-Bildauftragsdatensätze bereinigen';
$string['task_poll_image'] = 'Dixeo-Bildauftrag abfragen';
$string['task_poll_image_generation'] = 'Dixeo-Bildgenerierungsauftrag abfragen';
$string['task_process_file_sync'] = 'Dixeo-Dateisynchronisation verarbeiten';
$string['task_process_remote_file_deletion'] = 'Dixeo-Remote-Dateilöschung erneut versuchen';
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
5. NUR TEXT: Erzeugen Sie ausschließlich Text. Fügen Sie keine Bilder, Illustrationen, <img>-Tags oder [img-gen ...]-Platzhalter ein. Individuelle Lektionen verarbeiten keine Bildplatzhalter.

Überprüfen Sie vor dem Abschluss, dass das content-Feld substantiell, rein textuell ist und direkt auf die Anfrage des Lernenden eingeht.';
$string['total_used'] = 'Gesamtverbrauch';
$string['tutor_usage_duration_days'] = '{$a->days}T {$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_hours'] = '{$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_minutes'] = '{$a} Min.';
$string['tutor_usage_duration_seconds'] = '{$a}s';
$string['tutor_usage_mode_guide'] = 'Guide';
$string['tutor_usage_mode_normal'] = 'Standard';
$string['tutor_usage_mode_quiz'] = 'Quiz';
$string['tutor_usage_mode_teach'] = 'Teach';
$string['tutor_usage_report_breadcrumb'] = 'Berichtsnavigation';
$string['tutor_usage_report_cell_tooltip_adoption'] = '{$a->active} von {$a->total} Benutzern';
$string['tutor_usage_report_change_since_month'] = 'seit letztem Monat';
$string['tutor_usage_report_change_since_week'] = 'seit letzter Woche';
$string['tutor_usage_report_column_guide'] = 'Guide';
$string['tutor_usage_report_column_lastactive'] = 'Zuletzt aktiv';
$string['tutor_usage_report_column_messages'] = 'Nachrichten';
$string['tutor_usage_report_column_moduletype'] = 'Aktivitätstyp';
$string['tutor_usage_report_column_name'] = 'Name';
$string['tutor_usage_report_column_quiz'] = 'Quiz';
$string['tutor_usage_report_column_standard'] = 'Standard';
$string['tutor_usage_report_column_teach'] = 'Teach';
$string['tutor_usage_report_course_page'] = 'Kursseite';
$string['tutor_usage_report_filter_roles'] = 'Benutzer';
$string['tutor_usage_report_heatmap'] = 'Aktivitäts-Heatmap';
$string['tutor_usage_report_heatmap_info'] = 'Gesamtzahl der gesendeten Nachrichten in jedem Drei-Stunden-Zeitfenster, nach Wochentag.';
$string['tutor_usage_report_kpi_adoption'] = 'Adoption';
$string['tutor_usage_report_kpi_adoption_info'] = 'Anteil der Nutzer im Bereich, die den Tutor im Zeitraum mindestens einmal genutzt haben.';
$string['tutor_usage_report_kpi_adoption_secondary'] = '{$a->active} aktiv / {$a->inactive} inaktiv / {$a->total} gesamt';
$string['tutor_usage_report_kpi_duration'] = 'Dauer';
$string['tutor_usage_report_kpi_duration_info'] = 'Gesamtzeit der Interaktion mit dem Tutor während des Zeitraums.';
$string['tutor_usage_report_kpi_duration_secondary'] = 'Median {$a->median} / Durchschnitt {$a->average}';
$string['tutor_usage_report_kpi_engagement'] = 'Engagement';
$string['tutor_usage_report_kpi_engagement_info'] = 'Die Summe der Tage, an denen Nutzer während des Zeitraums mit dem Tutor interagiert haben';
$string['tutor_usage_report_kpi_engagement_info_user'] = 'Anzahl unterschiedlicher Tage, an denen dieser Nutzer den Tutor im Zeitraum genutzt hat.';
$string['tutor_usage_report_kpi_engagement_secondary'] = 'Zuletzt aktiv: {$a}';
$string['tutor_usage_report_kpi_engagement_secondary_aggregate'] = 'Median {$a->median} / Durchschnitt {$a->average} aktive Tage pro aktivem Nutzer';
$string['tutor_usage_report_kpi_engagement_value'] = '{$a} Tage';
$string['tutor_usage_report_kpi_messages'] = 'Nachrichten';
$string['tutor_usage_report_kpi_messages_info'] = 'Gesamtzahl der im Zeitraum an den Tutor gesendeten Nachrichten.';
$string['tutor_usage_report_kpi_messages_secondary'] = 'Median {$a->median} / Durchschnitt {$a->average}';
$string['tutor_usage_report_kpi_mode_desc_lesson'] = 'erstellte Lektionen';
$string['tutor_usage_report_kpi_mode_desc_messages'] = 'gesendete Nachrichten';
$string['tutor_usage_report_kpi_mode_desc_quiz'] = 'abgegebene Quizze';
$string['tutor_usage_report_kpi_mode_info_guide'] = 'Nachrichten im Modus Anleiten, in dem der Tutor zur Antwort hinführt, statt sie zu nennen.';
$string['tutor_usage_report_kpi_mode_info_normal'] = 'Nachrichten im Modus Standard, in dem der Tutor Fragen direkt beantwortet.';
$string['tutor_usage_report_kpi_mode_info_quiz'] = 'Nachrichten im Modus Test, die Testergebnisse werden dem Tutor zur Auswertung übermittelt.';
$string['tutor_usage_report_kpi_mode_info_teach'] = 'Nachrichten im Modus Unterrichten, die erzeugten Lektionen werden Teil der Unterhaltung.';
$string['tutor_usage_report_kpi_mode_secondary_quiz'] = 'Erstellte Quizze: {$a}';
$string['tutor_usage_report_kpi_sessions'] = 'Sitzungen';
$string['tutor_usage_report_kpi_sessions_info'] = 'Anzahl der Tutor-Sitzungen. Eine Sitzung beginnt mit der ersten Nachricht und endet nach {$a} ohne neue Nachricht.';
$string['tutor_usage_report_kpi_sessions_secondary'] = 'Median {$a->median} / Durchschnitt {$a->average}';
$string['tutor_usage_report_level_site'] = 'Alle Kurse';
$string['tutor_usage_report_nav'] = 'Dixeo-Tutor-Nutzung';
$string['tutor_usage_report_next_period'] = 'Nächster Zeitraum';
$string['tutor_usage_report_no_rows'] = 'Keine Tutor-Nutzungsdaten für den ausgewählten Zeitraum und die Filter gefunden.';
$string['tutor_usage_report_performance'] = 'Leistung';
$string['tutor_usage_report_performance_activities'] = 'Bewertete Aktivitäten';
$string['tutor_usage_report_performance_axis_grade'] = 'Note (%)';
$string['tutor_usage_report_performance_axis_usage'] = 'Nachrichten (gesamt)';
$string['tutor_usage_report_performance_column_average'] = 'Durchschnittsnote';
$string['tutor_usage_report_performance_column_user_vs_average'] = 'Nutzer / Durchschnitt';
$string['tutor_usage_report_performance_course_total'] = 'Kursgesamt';
$string['tutor_usage_report_performance_mean_grade'] = 'Durchschnittsnote';
$string['tutor_usage_report_performance_mean_usage'] = 'Durchschnittliche Nutzung';
$string['tutor_usage_report_performance_na'] = '—';
$string['tutor_usage_report_performance_scatter'] = 'Nutzung vs. Note';
$string['tutor_usage_report_performance_scatter_empty'] = 'Noch keine bewerteten Studierenden zum Anzeigen.';
$string['tutor_usage_report_performance_user_vs_average'] = '{$a->user} / {$a->average}';
$string['tutor_usage_report_period'] = 'Zeitraum';
$string['tutor_usage_report_prev_period'] = 'Vorheriger Zeitraum';
$string['tutor_usage_report_range_too_long'] = 'Benutzerdefinierte Zeiträume dürfen sechs Monate nicht überschreiten.';
$string['tutor_usage_report_rolescope_all'] = 'Alle';
$string['tutor_usage_report_rolescope_students'] = 'Lernende';
$string['tutor_usage_report_rolescope_teachers'] = 'Lehrende';
$string['tutor_usage_report_stacked_bar'] = 'Nachrichten nach Modus im Zeitverlauf';
$string['tutor_usage_report_summary'] = 'Zusammenfassung';
$string['tutor_usage_report_table_courses'] = 'Kurse';
$string['tutor_usage_report_table_modules'] = 'Aktivitäten';
$string['tutor_usage_report_table_users'] = 'Benutzer';
$string['tutor_usage_report_view_custom'] = 'Benutzerdefinierter Zeitraum';
$string['tutor_usage_report_view_month'] = 'Monat';
$string['tutor_usage_report_view_week'] = 'Woche';
$string['type'] = 'Typ';
$string['view_credit_report'] = 'Kreditnutzungsbericht anzeigen';
