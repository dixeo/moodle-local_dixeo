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

$string['account_frozen_warning'] = 'Su cuenta está congelada por saldo de créditos insuficiente. Añada créditos para seguir usando las funciones de Dixeo AI.';
$string['account_suspended_warning'] = 'Su cuenta ha sido suspendida. Contacte con el soporte de Dixeo para ayuda.';
$string['amount'] = 'Cantidad';
$string['api_configuration'] = 'Configuración de la API';
$string['api_configuration_desc'] = 'Configurar la conexión a la API Dixeo AI.';
$string['api_error'] = 'Error de API: {$a}';
$string['api_key'] = 'Clave API';
$string['api_key_desc'] = 'Su clave API de Dixeo. Obténgala desde el panel de Dixeo.';
$string['api_key_not_configured'] = 'La clave API de Dixeo no está configurada. Configúrela en los ajustes del plugin.';
$string['api_url'] = 'URL de la API';
$string['api_url_desc'] = 'URL base de la API Dixeo. Debe usar HTTPS (por defecto: https://api.dixeo.com).';
$string['average_per_period'] = 'Media por {$a}';
$string['cachedef_coursetemplates'] = 'Plantillas de estructura de curso en caché desde la API de Dixeo';
$string['cachedef_installedplugintypes'] = 'Tipos de complementos de actividad instalados en caché para la generación Dixeo';
$string['cachedef_moduletypes'] = 'Tipos de módulo en caché desde la API de Dixeo';
$string['configure_api'] = 'Configurar API';
$string['credit_balance'] = 'Saldo de créditos';
$string['credit_information'] = 'Información de créditos';
$string['credit_report'] = 'Informe de créditos';
$string['credits'] = 'créditos';
$string['current_balance'] = 'Saldo actual';
$string['current_balance_desc'] = 'Su saldo de créditos Dixeo actual. Los créditos se utilizan para operaciones de IA.';
$string['data_points'] = 'Puntos de datos';
$string['date'] = 'Fecha';
$string['day_fri'] = 'Vie';
$string['day_friday'] = 'Viernes';
$string['day_mon'] = 'Lun';
$string['day_monday'] = 'Lunes';
$string['day_sat'] = 'Sáb';
$string['day_saturday'] = 'Sábado';
$string['day_sun'] = 'Dom';
$string['day_sunday'] = 'Domingo';
$string['day_thu'] = 'Jue';
$string['day_thursday'] = 'Jueves';
$string['day_tue'] = 'Mar';
$string['day_tuesday'] = 'Martes';
$string['day_wed'] = 'Mié';
$string['day_wednesday'] = 'Miércoles';
$string['description'] = 'Descripción';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Sección {$a->section}, actividad {$a->module}:';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Sección {$a->section}:';
$string['designerstructurevalidate_course_summary_too_long'] = 'El resumen del curso es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_course_title_required'] = 'El título del curso es un campo obligatorio.';
$string['designerstructurevalidate_course_title_too_long'] = 'El título del curso debe tener como máximo {$a->max} caracteres.';
$string['designerstructurevalidate_failed'] = 'Este curso no puede crearse hasta que se resuelvan estos problemas:

{$a->details}';
$string['designerstructurevalidate_fill_instructions_too_long'] = 'Las instrucciones enviadas a la IA son demasiado largas (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_instructions_api_min'] = 'Las instrucciones deben tener al menos {$a->min} caracteres.';
$string['designerstructurevalidate_invalid_root'] = 'Los datos de la estructura del curso no son válidos.';
$string['designerstructurevalidate_module_instructions_required'] = 'Las instrucciones para la IA son obligatorias (al menos {$a->min} caracteres).';
$string['designerstructurevalidate_module_instructions_too_long'] = 'Las instrucciones son demasiado largas (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_module_invalid'] = 'El módulo en la posición {$a->module} de la sección {$a->section} no es válido.';
$string['designerstructurevalidate_module_summary_placeholder'] = 'Sustituya el resumen predeterminado por una descripción real de lo que cubre esta actividad.';
$string['designerstructurevalidate_module_summary_too_long'] = 'El resumen de la actividad es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_module_title_placeholder'] = 'Sustituya el título predeterminado «Nueva página» por un nombre de actividad real.';
$string['designerstructurevalidate_module_title_required'] = 'El título de la actividad es un campo obligatorio.';
$string['designerstructurevalidate_module_title_too_long'] = 'El título de la actividad es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_module_type_not_usable'] = 'El tipo «{$a->type}» no puede usarse en este sitio (falta el complemento o la biblioteca de contenido requerida).';
$string['designerstructurevalidate_module_type_required'] = 'El tipo de actividad es un campo obligatorio.';
$string['designerstructurevalidate_modules_not_array'] = 'La lista de módulos de la sección {$a} no es válida.';
$string['designerstructurevalidate_section_invalid'] = 'La sección {$a} de la estructura no es válida.';
$string['designerstructurevalidate_section_summary_too_long'] = 'El resumen de la sección es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_section_title_too_long'] = 'El título de la sección es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_sections_not_array'] = 'La lista de secciones de la estructura del curso no es válida.';
$string['dixeo:create'] = 'Crear cursos con el Diseñador de Cursos Dixeo';
$string['dixeo:edit'] = 'Editar módulos existentes con IA';
$string['dixeo:generate'] = 'Generar nuevos módulos con IA (página, etiqueta, cuestionario, glosario)';
$string['dixeo:manage'] = 'Gestionar la configuración de Dixeo y ver informes';
$string['dixeo:syncfiles'] = 'Activar, desactivar o lanzar la sincronización de archivos del curso Dixeo hacia la API externa';
$string['dixeo:viewusage'] = 'Ver informes de uso de créditos';
$string['dixeo_course_image_unsupported_type'] = 'Tipo de imagen generada no admitido.';
$string['dixeo_image_generation_disabled'] = 'La generación de imágenes está desactivada en la configuración del sitio.';
$string['dixeo_image_job_empty_result'] = 'La tarea de imagen no devolvió datos de imagen.';
$string['dixeo_pluginfile_not_found'] = 'No se pudo leer el archivo de imagen desde el almacenamiento.';
$string['dsl_error'] = 'Error al crear el módulo: {$a}';
$string['error:api_url_https_required'] = 'La URL de la API Dixeo debe ser una dirección HTTPS absoluta (por ejemplo https://api.dixeo.com).';
$string['error:authentication'] = 'Error de autenticación. Compruebe su clave API.';
$string['error:connection'] = 'Error de conexión con la API Dixeo. Compruebe su conexión de red.';
$string['error:job_failed'] = 'Error al procesar el trabajo: {$a}';
$string['error:job_not_found'] = 'No se encontró el trabajo solicitado.';
$string['error:notslideshow'] = 'El módulo del curso no es una actividad de presentación.';
$string['error:payment_required'] = 'Créditos insuficientes. Añada créditos para continuar.';
$string['error:rate_limit'] = 'Límite de solicitudes superado. Espere antes de hacer más solicitudes.';
$string['error:slidenotinslideshow'] = 'La diapositiva solicitada no pertenece a esta presentación.';
$string['error:timeout'] = 'La operación ha caducado. Puede comprobar el estado del trabajo más tarde.';
$string['error:upstream_ai'] = 'Error del servicio de IA. Inténtelo de nuevo más tarde.';
$string['error:validation'] = 'Solicitud no válida: {$a}';
$string['eventfilesyncdisabled'] = 'Sincronización de archivos Dixeo del curso desactivada';
$string['eventfilesyncdisableddesc'] = 'El usuario con id \'{$a->userid}\' desactivó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\' (removefiles={$a->removefiles}).';
$string['eventfilesyncenabled'] = 'Sincronización de archivos Dixeo del curso activada';
$string['eventfilesyncenableddesc'] = 'El usuario con id \'{$a->userid}\' activó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\'.';
$string['eventfilesynctriggered'] = 'Sincronización de archivos Dixeo del curso activada (ejecución)';
$string['eventfilesynctriggereddesc'] = 'El usuario con id \'{$a->userid}\' disparó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\'.';
$string['eventjobcancelled'] = 'Trabajo Dixeo cancelado';
$string['eventjobcancelleddesc'] = 'El usuario con id \'{$a->userid}\' canceló el trabajo Dixeo \'{$a->jobid}\' del curso con id \'{$a->courseid}\'.';
$string['feedback_correct'] = 'Bien hecho, has acertado esta respuesta. ¡Sigue así!';
$string['feedback_incorrect'] = 'No del todo esta vez. Repasar el tema te ayudará a mejorar.';
$string['feedback_partial'] = 'Vas por buen camino. Repasa el material y lo conseguirás.';
$string['files'] = 'archivos';
$string['filesync_disable_remove'] = 'Desactivar y borrar datos de sincronización';
$string['filesync_enable'] = 'Activar sincronización';
$string['filesync_error_retry'] = 'Se reintentará automáticamente';
$string['filesync_failed'] = 'Error de sincronización de archivos: {$a}';
$string['filesync_files_count'] = '{$a} archivos sincronizados';
$string['filesync_label'] = 'Sincronizar';
$string['filesync_pause'] = 'Pausar sincronización';
$string['filesync_progress'] = '{$a}% completado';
$string['filesync_resync'] = 'Sincronizar ahora';
$string['filesync_status_disabled'] = 'Sincronización desactivada';
$string['filesync_status_error'] = 'Error de sincronización';
$string['filesync_status_none'] = 'Ningún archivo sincronizado';
$string['filesync_status_outdated'] = 'Contenido modificado, sincronización necesaria';
$string['filesync_status_paused'] = 'Sincronización en pausa';
$string['filesync_status_synchronized'] = 'Archivos sincronizados';
$string['filesync_status_syncing'] = 'Sincronizando archivos...';
$string['filesync_timeout'] = 'La sincronización de archivos ha caducado antes de indexar los archivos del curso';
$string['filesync_title'] = 'Sincronización de archivos Dixeo';
$string['image_generation'] = 'Generación de imágenes';
$string['image_generation_course_mode'] = 'Imágenes del curso';
$string['image_generation_course_mode_desc'] = 'Controla las acciones de imagen por IA para la imagen de resumen del curso.';
$string['image_generation_desc'] = 'Controla la disponibilidad de la generación y edición de imágenes por IA para las imágenes del curso y de las secciones.';
$string['image_generation_enabled'] = 'Activar generación de imágenes';
$string['image_generation_enabled_desc'] = 'Si está desactivado, se bloquean todas las solicitudes de generar o editar imágenes.';
$string['image_generation_mode_disabled'] = 'Desactivado';
$string['image_generation_mode_generate'] = 'Generar';
$string['image_generation_mode_generate_edit'] = 'Generar y editar';
$string['image_generation_section_mode'] = 'Imágenes de sección';
$string['image_generation_section_mode_desc'] = 'Controla las acciones de imagen por IA para las imágenes de capítulo o sección.';
$string['last_sync'] = 'Última sincronización';
$string['namespace'] = 'Espacio de nombres';
$string['namespace_desc'] = 'Solo necesario cuando varios sitios Moodle comparten la misma clave API. Cada sitio debe usar un espacio de nombres diferente (ej. "production", "staging", "site1") para mantener sus datos separados. Deje "default" si este es el único sitio que usa esta clave API.';
$string['no_transactions'] = 'No se encontraron transacciones.';
$string['no_usage_data'] = 'No hay datos de uso disponibles para el período seleccionado.';
$string['overview'] = 'Resumen de Dixeo';
$string['page_x_of_y'] = 'Página {$a->current} de {$a->total}';
$string['pagination'] = 'Navegación de páginas';
$string['period'] = 'Período';
$string['period_day'] = 'Diario';
$string['period_month'] = 'Mensual';
$string['period_week'] = 'Semanal';
$string['pluginname'] = 'Dixeo AI';
$string['pluginname_desc'] = 'Integración Dixeo AI para generación y edición inteligente de contenido.';
$string['practice_quiz_default_title'] = 'Cuestionario de práctica';
$string['practice_quiz_difficulty_easy'] = 'fácil (recuerdo básico, conceptos sencillos, adecuado para principiantes)';
$string['practice_quiz_difficulty_hard'] = 'difícil (aplicación exigente, análisis o síntesis de conceptos avanzados)';
$string['practice_quiz_difficulty_medium'] = 'medio (profundidad moderada que requiere comprensión más allá del simple recuerdo)';
$string['practice_quiz_error_invalid_result'] = 'Resultado del trabajo no válido.';
$string['practice_quiz_error_job_not_completed'] = 'El trabajo no está completado. Estado: {$a->status}';
$string['practice_quiz_error_no_questions'] = 'No hay preguntas en el resultado del trabajo.';
$string['practice_quiz_error_wrong_module_type'] = 'El trabajo no es una generación simplequiz2.';
$string['practice_quiz_instructions'] = 'Genera un cuestionario de práctica para {$a->scopedescription}.

REQUISITOS OBLIGATORIOS — debes seguirlos exactamente:
1. NÚMERO DE PREGUNTAS: El array "questions" DEBE contener exactamente {$a->count} preguntas. No generes {$a->count} menos uno, {$a->count} más uno ni ningún otro número — exactamente {$a->count}.
2. NIVEL DE DIFICULTAD: Cada pregunta DEBE tener una dificultad {$a->difficultylabel}.
3. FORMATO: Cada pregunta DEBE ser de opción múltiple con 3 o 4 opciones de respuesta y exactamente una respuesta correcta.

Antes de terminar, verifica que la longitud del array questions sea {$a->count} y que todas las preguntas coincidan con el nivel de dificultad {$a->difficulty}.
Céntrate en el contexto del curso proporcionado.';
$string['practice_quiz_scope_activity_description'] = 'la actividad «{$a->name}»';
$string['practice_quiz_scope_course_description'] = 'el curso completo «{$a->name}»';
$string['practice_quiz_scope_section_description'] = 'la sección «{$a->name}»';
$string['privacy:metadata'] = 'El plugin Dixeo almacena identificadores operativos de la sincronización de archivos del curso y envía contenido del curso, mensajes del tutor, contexto de generación e identificadores relacionados a la API Dixeo AI. La retención y eliminación de datos en Dixeo las controla ese servicio externo.';
$string['privacy:metadata:course_ai'] = 'Configuración y estado de sincronización de archivos AI por curso.';
$string['privacy:metadata:course_ai:courseid'] = 'El curso al que pertenece esta configuración de sincronización.';
$string['privacy:metadata:course_ai:disabledat'] = 'La hora en que se deshabilitó la sincronización de archivos.';
$string['privacy:metadata:course_ai:disabledby'] = 'El usuario que deshabilitó la sincronización de archivos para el curso.';
$string['privacy:metadata:course_ai:enabled'] = 'Si la sincronización de archivos está habilitada para el curso.';
$string['privacy:metadata:course_ai:enabledat'] = 'La hora en que se habilitó la sincronización de archivos.';
$string['privacy:metadata:course_ai:enabledby'] = 'El usuario que habilitó la sincronización de archivos para el curso.';
$string['privacy:metadata:course_ai:errormessage'] = 'El último mensaje de error de sincronización, si existe.';
$string['privacy:metadata:course_ai:syncstatus'] = 'El estado actual de sincronización.';
$string['privacy:metadata:course_ai:timecreated'] = 'La hora en que se creó el registro de sincronización.';
$string['privacy:metadata:course_ai:timemodified'] = 'La hora en que se modificó por última vez el registro de sincronización.';
$string['privacy:metadata:external:context'] = 'Contexto de curso, sección o módulo proporcionado para generación o edición.';
$string['privacy:metadata:external:courseid'] = 'El ID del curso de Moodle asociado a la solicitud.';
$string['privacy:metadata:external:description'] = 'Descripción legible de una plantilla de estructura de curso almacenada en la API Dixeo.';
$string['privacy:metadata:external:files'] = 'Archivos del curso, texto extraído de SCORM y manifiestos de archivos relacionados cargados para sincronización o RAG.';
$string['privacy:metadata:external:images'] = 'Imágenes de origen (por ejemplo del curso o sección) enviadas al solicitar una edición de imagen con IA.';
$string['privacy:metadata:external:instructions'] = 'Instrucciones o prompts usados para guiar el procesamiento AI.';
$string['privacy:metadata:external:message'] = 'Mensajes del tutor o del usuario enviados para procesamiento AI.';
$string['privacy:metadata:external:moduletype'] = 'El tipo de módulo de actividad solicitado para generación.';
$string['privacy:metadata:external:name'] = 'Nombre visible de una plantilla de estructura de curso almacenada en la API Dixeo.';
$string['privacy:metadata:external:namespace'] = 'El espacio de nombres del sitio usado para separar los datos de esta instancia Moodle en la API Dixeo.';
$string['privacy:metadata:external:pagecontext'] = 'Texto o contexto visible de la página enviado con los mensajes del tutor para fundamentar la respuesta de la IA.';
$string['privacy:metadata:external:summary'] = 'Resumen del curso o de la sección usado como entrada para la generación de imágenes.';
$string['privacy:metadata:external:templatedefinition'] = 'Definición estructurada de una plantilla de curso (secciones y huecos de actividad) enviada o almacenada en la API Dixeo.';
$string['privacy:metadata:external:templateid'] = 'Identificador de una plantilla de estructura de curso usada al generar un esquema de curso.';
$string['privacy:metadata:external:title'] = 'Título del curso o de la sección usado como entrada para la generación de imágenes.';
$string['privacy:metadata:external:userid'] = 'El ID de usuario de Moodle asociado a la solicitud (por ejemplo conversaciones del tutor).';
$string['privacy:metadata:externalpurpose'] = 'Los datos se envían a la API Dixeo AI para generación de contenido, tutoría, imágenes, informes de créditos y sincronización de archivos. La retención y eliminación remotas las gestiona Dixeo según el contrato institucional; este plugin no puede eliminar copias remotas mediante los flujos de privacidad de Moodle.';
$string['privacy:metadata:jobs'] = 'Registros locales que vinculan trabajos remotos de Dixeo AI con cursos y usuarios de Moodle.';
$string['privacy:metadata:jobs:courseid'] = 'El curso al que está vinculado el trabajo.';
$string['privacy:metadata:jobs:jobid'] = 'El identificador remoto del trabajo de Dixeo.';
$string['privacy:metadata:jobs:namespace'] = 'El espacio de nombres de la API Dixeo usado para el trabajo.';
$string['privacy:metadata:jobs:operation'] = 'El tipo de operación lógica del trabajo.';
$string['privacy:metadata:jobs:timecreated'] = 'La hora en que se creó el vínculo local del trabajo.';
$string['privacy:metadata:jobs:userid'] = 'El usuario que inició el trabajo.';
$string['privacy:path:course_ai'] = 'Sincronización AI del curso';
$string['privacy:path:jobs'] = 'Trabajos Dixeo AI';
$string['recent_transactions'] = 'Historial de transacciones';
$string['state_active'] = 'Activo';
$string['state_frozen'] = 'Congelado';
$string['state_suspended'] = 'Suspendido';
$string['task_cleanup_jobs'] = 'Limpiar registros de trabajos antiguos';
$string['task_poll_image_generation'] = 'Consultar la tarea de generación de imágenes de Dixeo';
$string['task_process_file_sync'] = 'Procesar sincronización de archivos Dixeo';
$string['this_week_usage'] = 'Esta semana';
$string['total_used'] = 'Total utilizado';
$string['transaction_type_deduction'] = 'Uso';
$string['transaction_type_purchase'] = 'Compra';
$string['transaction_type_refund'] = 'Reembolso';
$string['transaction_type_reset'] = 'Renovación';
$string['type'] = 'Tipo';
$string['usage_chart_label'] = 'Uso de créditos';
$string['usage_statistics'] = 'Estadísticas de uso';
$string['view_credit_report'] = 'Ver informe detallado de créditos';
$string['week_total'] = 'Total esta semana';
