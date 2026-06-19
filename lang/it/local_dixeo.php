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

$string['account_frozen_warning'] = 'Il tuo account è congelato per saldo crediti insufficiente. Aggiungi crediti per continuare a usare le funzionalità Dixeo AI.';
$string['account_suspended_warning'] = 'Il tuo account è stato sospeso. Contatta il supporto Dixeo per assistenza.';
$string['amount'] = 'Importo';
$string['api_configuration'] = 'Configurazione API';
$string['api_configuration_desc'] = 'Configurare la connessione all\'API Dixeo AI.';
$string['api_error'] = 'Errore API: {$a}';
$string['api_key'] = 'Chiave API';
$string['api_key_desc'] = 'La tua chiave API Dixeo. Ottienila dalla dashboard Dixeo.';
$string['api_key_not_configured'] = 'La chiave API Dixeo non è configurata. Configurala nelle impostazioni del plugin.';
$string['api_url'] = 'URL API';
$string['api_url_desc'] = 'URL base per l\'API Dixeo. Deve usare HTTPS (predefinito: https://api.dixeo.com).';
$string['average_per_period'] = 'Media per {$a}';
$string['cachedef_coursetemplates'] = 'Modelli di struttura del corso memorizzati nella cache dall\'API Dixeo';
$string['cachedef_installedplugintypes'] = 'Tipi di plugin attività installati memorizzati nella cache per la generazione Dixeo';
$string['cachedef_moduletypes'] = 'Tipi di modulo memorizzati nella cache dall\'API Dixeo';
$string['configure_api'] = 'Configura API';
$string['contentimagetitlefallback'] = 'Content image';
$string['credit_balance'] = 'Saldo crediti';
$string['credit_information'] = 'Informazioni sui crediti';
$string['credit_report'] = 'Report crediti';
$string['credits'] = 'crediti';
$string['current_balance'] = 'Saldo attuale';
$string['current_balance_desc'] = 'Il tuo saldo crediti Dixeo attuale. I crediti sono usati per le operazioni IA.';
$string['data_points'] = 'Punti dati';
$string['date'] = 'Data';
$string['day_fri'] = 'Ven';
$string['day_friday'] = 'Venerdì';
$string['day_mon'] = 'Lun';
$string['day_monday'] = 'Lunedì';
$string['day_sat'] = 'Sab';
$string['day_saturday'] = 'Sabato';
$string['day_sun'] = 'Dom';
$string['day_sunday'] = 'Domenica';
$string['day_thu'] = 'Gio';
$string['day_thursday'] = 'Giovedì';
$string['day_tue'] = 'Mar';
$string['day_tuesday'] = 'Martedì';
$string['day_wed'] = 'Mer';
$string['day_wednesday'] = 'Mercoledì';
$string['description'] = 'Descrizione';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Sezione {$a->section}, attività {$a->module}:';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Sezione {$a->section}:';
$string['designerstructurevalidate_course_summary_too_long'] = 'Il riepilogo del corso è troppo lungo (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_course_title_required'] = 'Il titolo del corso è un campo obbligatorio.';
$string['designerstructurevalidate_course_title_too_long'] = 'Il titolo del corso deve contenere al massimo {$a->max} caratteri.';
$string['designerstructurevalidate_failed'] = 'Questo corso non può essere creato finché questi problemi non sono risolti:

{$a->details}';
$string['designerstructurevalidate_fill_instructions_too_long'] = 'Le istruzioni inviate all\'IA sono troppo lunghe (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_instructions_api_min'] = 'Le istruzioni devono contenere almeno {$a->min} caratteri.';
$string['designerstructurevalidate_invalid_root'] = 'I dati della struttura del corso non sono validi.';
$string['designerstructurevalidate_module_instructions_required'] = 'Le istruzioni per l\'IA sono obbligatorie (almeno {$a->min} caratteri).';
$string['designerstructurevalidate_module_instructions_too_long'] = 'Le istruzioni sono troppo lunghe (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_module_invalid'] = 'Il modulo alla posizione {$a->module} nella sezione {$a->section} non è valido.';
$string['designerstructurevalidate_module_summary_placeholder'] = 'Sostituisci il riepilogo predefinito con una descrizione reale di ciò che copre questa attività.';
$string['designerstructurevalidate_module_summary_too_long'] = 'Il riepilogo dell\'attività è troppo lungo (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_module_title_placeholder'] = 'Sostituisci il titolo predefinito «Nuova pagina» con un nome di attività reale.';
$string['designerstructurevalidate_module_title_required'] = 'Il titolo dell\'attività è un campo obbligatorio.';
$string['designerstructurevalidate_module_title_too_long'] = 'Il titolo dell\'attività è troppo lungo (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_module_type_not_usable'] = 'Il tipo «{$a->type}» non può essere usato su questo sito (plugin mancante o libreria di contenuti richiesta).';
$string['designerstructurevalidate_module_type_required'] = 'Il tipo di attività è un campo obbligatorio.';
$string['designerstructurevalidate_modules_not_array'] = 'L\'elenco dei moduli nella sezione {$a} non è valido.';
$string['designerstructurevalidate_section_invalid'] = 'La sezione {$a} nella struttura non è valida.';
$string['designerstructurevalidate_section_summary_too_long'] = 'Il riepilogo della sezione è troppo lungo (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_section_title_too_long'] = 'Il titolo della sezione è troppo lungo (massimo {$a->max} caratteri).';
$string['designerstructurevalidate_sections_not_array'] = 'L\'elenco delle sezioni della struttura del corso non è valido.';
$string['dixeo:contentimageedit'] = 'Edit embedded content images with AI';
$string['dixeo:contentimagegenerate'] = 'Generate embedded content images with AI';
$string['dixeo:create'] = 'Creare corsi con il Progettatore di Corsi Dixeo';
$string['dixeo:edit'] = 'Modificare i moduli esistenti con IA';
$string['dixeo:generate'] = 'Generare nuovi moduli con IA (pagina, etichetta, quiz, glossario)';
$string['dixeo:manage'] = 'Gestire le impostazioni Dixeo e visualizzare i report';
$string['dixeo:syncfiles'] = 'Abilitare, disabilitare o avviare la sincronizzazione dei file del corso Dixeo verso l\'API esterna';
$string['dixeo:viewusage'] = 'Visualizzare i report sull\'utilizzo dei crediti';
$string['dixeo_course_image_unsupported_type'] = 'Tipo di immagine generata non supportato.';
$string['dixeo_image_generation_disabled'] = 'La generazione di immagini è disabilitata nelle impostazioni del sito.';
$string['dixeo_image_job_empty_result'] = 'Il job immagine non ha restituito dati immagine.';
$string['dixeo_pluginfile_not_found'] = 'Impossibile leggere il file immagine dallo spazio di archiviazione.';
$string['dsl_error'] = 'Creazione del modulo non riuscita: {$a}';
$string['error:api_url_https_required'] = 'L\'URL dell\'API Dixeo deve essere un indirizzo HTTPS assoluto (ad esempio https://api.dixeo.com).';
$string['error:authentication'] = 'Autenticazione fallita. Controlla la tua chiave API.';
$string['error:connection'] = 'Connessione all\'API Dixeo fallita. Controlla la connessione di rete.';
$string['error:job_failed'] = 'Elaborazione del lavoro fallita: {$a}';
$string['error:job_not_found'] = 'Il lavoro richiesto non è stato trovato.';
$string['error:notslideshow'] = 'Il modulo del corso non è un\'attività presentazione.';
$string['error:payment_required'] = 'Crediti insufficienti. Aggiungi crediti per continuare.';
$string['error:rate_limit'] = 'Limite di richieste superato. Attendi prima di effettuare altre richieste.';
$string['error:slidenotinslideshow'] = 'La diapositiva richiesta non appartiene a questa presentazione.';
$string['error:timeout'] = 'Operazione scaduta. Puoi controllare lo stato del lavoro in seguito.';
$string['error:upstream_ai'] = 'Errore del servizio IA. Riprova più tardi.';
$string['error:validation'] = 'Richiesta non valida: {$a}';
$string['eventfilesyncdisabled'] = 'Sincronizzazione file Dixeo del corso disabilitata';
$string['eventfilesyncdisableddesc'] = 'L\'utente con id \'{$a->userid}\' ha disabilitato la sincronizzazione file Dixeo per il corso con id \'{$a->courseid}\' (removefiles={$a->removefiles}).';
$string['eventfilesyncenabled'] = 'Sincronizzazione file Dixeo del corso abilitata';
$string['eventfilesyncenableddesc'] = 'L\'utente con id \'{$a->userid}\' ha abilitato la sincronizzazione file Dixeo per il corso con id \'{$a->courseid}\'.';
$string['eventfilesynctriggered'] = 'Sincronizzazione file Dixeo del corso avviata';
$string['eventfilesynctriggereddesc'] = 'L\'utente con id \'{$a->userid}\' ha avviato la sincronizzazione file Dixeo per il corso con id \'{$a->courseid}\'.';
$string['eventjobcancelled'] = 'Job Dixeo annullato';
$string['eventjobcancelleddesc'] = 'L\'utente con id \'{$a->userid}\' ha annullato il job Dixeo \'{$a->jobid}\' per il corso con id \'{$a->courseid}\'.';
$string['feedback_correct'] = 'Corretto!';
$string['files'] = 'file';
$string['filesync_disable_remove'] = 'Disattiva e cancella dati di sincronizzazione';
$string['filesync_enable'] = 'Abilita sincronizzazione';
$string['filesync_error_retry'] = 'Riprova automatica';
$string['filesync_failed'] = 'Sincronizzazione file non riuscita: {$a}';
$string['filesync_files_count'] = '{$a} file sincronizzati';
$string['filesync_label'] = 'Sincronizza';
$string['filesync_pause'] = 'Metti in pausa sincronizzazione';
$string['filesync_progress'] = '{$a}% completato';
$string['filesync_resync'] = 'Sincronizza ora';
$string['filesync_status_disabled'] = 'Sincronizzazione disabilitata';
$string['filesync_status_error'] = 'Errore di sincronizzazione';
$string['filesync_status_none'] = 'Nessun file sincronizzato';
$string['filesync_status_outdated'] = 'Contenuto modificato, sincronizzazione necessaria';
$string['filesync_status_paused'] = 'Sincronizzazione in pausa';
$string['filesync_status_synchronized'] = 'File sincronizzati';
$string['filesync_status_syncing'] = 'Sincronizzazione in corso...';
$string['filesync_timeout'] = 'Timeout della sincronizzazione file prima dell\'indicizzazione dei file del corso';
$string['filesync_title'] = 'Sincronizzazione file Dixeo';
$string['image_generation'] = 'Generazione di immagini';
$string['image_generation_content_mode'] = 'Embedded content images';
$string['image_generation_content_mode_desc'] = 'Controls AI image actions for images embedded in course content (filter_dixeo_imageeditor).';
$string['image_generation_course_mode'] = 'Immagini del corso';
$string['image_generation_course_mode_desc'] = 'Controlla le azioni IA sulle immagini per l\'immagine di riepilogo del corso.';
$string['image_generation_desc'] = 'Controlla la disponibilità della generazione e modifica immagini tramite IA per le immagini del corso e delle sezioni.';
$string['image_generation_enabled'] = 'Abilita generazione immagini';
$string['image_generation_enabled_desc'] = 'Se disabilitato, tutte le richieste di generazione o modifica immagini vengono bloccate.';
$string['image_generation_mode_disabled'] = 'Disabilitato';
$string['image_generation_mode_generate'] = 'Genera';
$string['image_generation_mode_generate_edit'] = 'Genera e modifica';
$string['image_generation_section_mode'] = 'Immagini delle sezioni';
$string['image_generation_section_mode_desc'] = 'Controlla le azioni IA sulle immagini per i capitoli o le sezioni.';
$string['last_sync'] = 'Ultima sincronizzazione';
$string['namespace'] = 'Namespace';
$string['namespace_desc'] = 'Necessario solo quando più siti Moodle condividono la stessa chiave API. Ogni sito deve usare un namespace diverso (es. "production", "staging", "site1") per mantenere i dati separati. Lascia "default" se questo è l\'unico sito che usa questa chiave API.';
$string['no_transactions'] = 'Nessuna transazione trovata.';
$string['no_usage_data'] = 'Nessun dato di utilizzo disponibile per il periodo selezionato.';
$string['overview'] = 'Panoramica Dixeo';
$string['page_x_of_y'] = 'Pagina {$a->current} di {$a->total}';
$string['pagination'] = 'Navigazione pagine';
$string['period'] = 'Periodo';
$string['period_day'] = 'Giornaliero';
$string['period_month'] = 'Mensile';
$string['period_week'] = 'Settimanale';
$string['pluginname'] = 'Dixeo AI';
$string['pluginname_desc'] = 'Integrazione Dixeo AI per la generazione e modifica intelligente dei contenuti.';
$string['privacy:metadata'] = 'Il plugin Dixeo memorizza identificatori operativi per la sincronizzazione dei file del corso e invia contenuti del corso, messaggi del tutor, contesto di generazione e identificatori correlati all\'API Dixeo AI. La conservazione e la cancellazione dei dati presso Dixeo sono controllate da quel servizio esterno.';
$string['privacy:metadata:course_ai'] = 'Configurazione e stato della sincronizzazione file AI per corso.';
$string['privacy:metadata:course_ai:courseid'] = 'Il corso a cui appartiene questa configurazione di sincronizzazione.';
$string['privacy:metadata:course_ai:disabledat'] = 'L\'ora in cui è stata disabilitata la sincronizzazione dei file.';
$string['privacy:metadata:course_ai:disabledby'] = 'L\'utente che ha disabilitato la sincronizzazione dei file per il corso.';
$string['privacy:metadata:course_ai:enabled'] = 'Se la sincronizzazione dei file è abilitata per il corso.';
$string['privacy:metadata:course_ai:enabledat'] = 'L\'ora in cui è stata abilitata la sincronizzazione dei file.';
$string['privacy:metadata:course_ai:enabledby'] = 'L\'utente che ha abilitato la sincronizzazione dei file per il corso.';
$string['privacy:metadata:course_ai:errormessage'] = 'L\'ultimo messaggio di errore di sincronizzazione, se presente.';
$string['privacy:metadata:course_ai:syncstatus'] = 'Lo stato attuale della sincronizzazione.';
$string['privacy:metadata:course_ai:timecreated'] = 'L\'ora di creazione del record di sincronizzazione.';
$string['privacy:metadata:course_ai:timemodified'] = 'L\'ora dell\'ultima modifica del record di sincronizzazione.';
$string['privacy:metadata:external:context'] = 'Contesto di corso, sezione o modulo fornito per generazione o modifica.';
$string['privacy:metadata:external:courseid'] = 'L\'ID del corso Moodle associato alla richiesta.';
$string['privacy:metadata:external:description'] = 'Descrizione leggibile di un modello di struttura del corso memorizzato sull\'API Dixeo.';
$string['privacy:metadata:external:files'] = 'File del corso, testo SCORM estratto e manifesti di file correlati caricati per sincronizzazione o RAG.';
$string['privacy:metadata:external:images'] = 'Immagini di origine (ad esempio del corso o della sezione) inviate quando si richiede una modifica immagine con IA.';
$string['privacy:metadata:external:instructions'] = 'Istruzioni o prompt usati per guidare l\'elaborazione IA.';
$string['privacy:metadata:external:message'] = 'Messaggi del tutor o dell\'utente inviati per l\'elaborazione IA.';
$string['privacy:metadata:external:moduletype'] = 'Il tipo di modulo attività richiesto per la generazione.';
$string['privacy:metadata:external:name'] = 'Nome visualizzato di un modello di struttura del corso memorizzato sull\'API Dixeo.';
$string['privacy:metadata:external:namespace'] = 'Lo spazio dei nomi del sito usato per separare i dati di questa istanza Moodle sull\'API Dixeo.';
$string['privacy:metadata:external:pagecontext'] = 'Testo o contesto visibile della pagina inviato con i messaggi del tutor per fondare la risposta dell\'IA.';
$string['privacy:metadata:external:summary'] = 'Riepilogo del corso o della sezione usato come input per la generazione di immagini.';
$string['privacy:metadata:external:templatedefinition'] = 'Definizione strutturata di un modello di corso (sezioni e slot attività) inviata o memorizzata sull\'API Dixeo.';
$string['privacy:metadata:external:templateid'] = 'Identificatore di un modello di struttura del corso usato nella generazione di uno schema di corso.';
$string['privacy:metadata:external:title'] = 'Titolo del corso o della sezione usato come input per la generazione di immagini.';
$string['privacy:metadata:external:userid'] = 'L\'ID utente Moodle associato alla richiesta (ad esempio conversazioni del tutor).';
$string['privacy:metadata:externalpurpose'] = 'I dati sono inviati all\'API Dixeo AI per generazione di contenuti, tutoring, immagini, report crediti e sincronizzazione file. Conservazione ed eliminazione remote sono gestite da Dixeo secondo il contratto istituzionale; questo plugin non può eliminare copie remote tramite i flussi privacy di Moodle.';
$string['privacy:metadata:jobs'] = 'Record locali che collegano i job remoti di Dixeo AI a corsi e utenti Moodle.';
$string['privacy:metadata:jobs:courseid'] = 'Il corso a cui è vincolato il job.';
$string['privacy:metadata:jobs:jobid'] = 'L\'identificatore remoto del job Dixeo.';
$string['privacy:metadata:jobs:namespace'] = 'Il namespace dell\'API Dixeo usato per il job.';
$string['privacy:metadata:jobs:operation'] = 'Il tipo di operazione logica del job.';
$string['privacy:metadata:jobs:timecreated'] = 'L\'ora di creazione del binding locale del job.';
$string['privacy:metadata:jobs:userid'] = 'L\'utente che ha avviato il job.';
$string['privacy:path:course_ai'] = 'Sincronizzazione AI del corso';
$string['privacy:path:jobs'] = 'Job Dixeo AI';
$string['recent_transactions'] = 'Cronologia transazioni';
$string['state_active'] = 'Attivo';
$string['state_frozen'] = 'Congelato';
$string['state_suspended'] = 'Sospeso';
$string['task_cleanup_jobs'] = 'Pulire i vecchi record dei lavori';
$string['task_poll_image_generation'] = 'Interrogare il job di generazione immagini Dixeo';
$string['task_process_file_sync'] = 'Elaborare la sincronizzazione file Dixeo';
$string['this_week_usage'] = 'Questa settimana';
$string['total_used'] = 'Totale utilizzato';
$string['transaction_type_deduction'] = 'Utilizzo';
$string['transaction_type_purchase'] = 'Acquisto';
$string['transaction_type_refund'] = 'Rimborso';
$string['transaction_type_reset'] = 'Rinnovo';
$string['type'] = 'Tipo';
$string['usage_chart_label'] = 'Utilizzo crediti';
$string['usage_statistics'] = 'Statistiche di utilizzo';
$string['view_credit_report'] = 'Visualizza report dettagliato crediti';
$string['week_total'] = 'Totale questa settimana';
