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
$string['consent_close'] = 'Cerrar';
$string['consent_continue'] = 'Continuar';
$string['consent_error_terms_required'] = 'Marque las dos casillas antes de continuar.';
$string['consent_error_version'] = 'Estas condiciones se han actualizado. Lea la versión actual y continúe de nuevo.';
$string['consent_exit'] = 'Salir';
$string['consent_manage'] = 'Condiciones y privacidad de Dixeo';
$string['consent_manage_intro'] = 'Revise las condiciones de uso y el aviso de privacidad, o revoque su aceptación.';
$string['consent_privacy_body'] = 'Este aviso explica cómo se usan sus datos personales cuando Dixeo está activado, tal como exigen los artículos 13 y 14 del RGPD. Leerlo no constituye, por sí solo, un consentimiento.

Quién es responsable. {$a->sitename}, la organización que opera este sitio, es el responsable del tratamiento: decide por qué y cómo se usan sus datos personales. Contacto: {$a->contact}. Dixeo, que proporciona los complementos y los servicios de IA, es el encargado del tratamiento. Este sitio envía datos a la API de Dixeo en {$a->apiurl} para que Dixeo pueda prestar las funciones descritas en las condiciones de uso. Las copias remotas se conservan según el contrato entre {$a->sitename} y Dixeo. Este sitio no puede eliminar esas copias remotas mediante las herramientas de privacidad de Moodle. Para preguntas sobre los servicios de Dixeo, contacte con contact@dixeo.com.

Datos que este sitio almacena o envía, según lo declarado a la API de privacidad de Moodle:

- Su identificador de usuario de Moodle, enviado con las solicitudes que usted inicia
- Los mensajes que usted escribe al tutor, las instrucciones y el texto visible de la página, para que el tutor pueda responder en el contexto de la página
- Títulos y resúmenes de cursos, secciones y actividades; indicaciones; imágenes de origen; y archivos del curso, incluido el texto extraído de paquetes SCORM, cargados para generación, edición de imágenes o sincronización de archivos
- Nombres, descripciones y definiciones de estructura de plantillas de curso almacenadas en la API de Dixeo
- Identificadores de trabajo, tipos de operación, importes de créditos y el curso o la actividad a la que pertenece cada trabajo
- Indicaciones de imagen y estado del trabajo. Los registros locales de trabajos de imagen se eliminan 7 días después de que el trabajo termina
- El idioma que usted elige para los cuestionarios y las lecciones que genera el tutor
- El identificador de usuario y la hora registrados cuando alguien activa o desactiva la sincronización de archivos de un curso

Base jurídica. Prestar tutoría, generación, imágenes, sincronización de archivos, contabilidad de créditos y análisis de uso del tutor es necesario para ejecutar las condiciones de uso (artículo 6, apartado 1, letra b). Conservar registros operativos para seguridad, soporte y contabilidad de créditos es el interés legítimo de {$a->sitename} (artículo 6, apartado 1, letra f).

Destinatarios. Sus datos se comparten con la API de Dixeo y, para los informes de créditos, con el personal que este sitio ha autorizado. Los informes de uso del tutor solo son visibles para el personal autorizado.

Transferencias internacionales. Los datos se envían a la API de Dixeo indicada arriba. Cualquier transferencia fuera de su país, y las garantías aplicables, se establecen en el contrato entre {$a->sitename} y Dixeo. Este sitio no puede ver ni describir el mecanismo de transferencia en sí.

Conservación. Los registros locales de trabajos de imagen se conservan 7 días después de que el trabajo termina. Los demás registros locales se conservan mientras exista su cuenta y se eliminan cuando sus datos personales se borran en Moodle. La conservación remota sigue el contrato entre {$a->sitename} y Dixeo.

Sus derechos. Puede pedir a {$a->sitename} acceso, rectificación, supresión, limitación o una copia portable de sus datos, y puede oponerse al tratamiento basado en intereses legítimos. También puede reclamar ante una autoridad de control. Si este sitio tiene las herramientas de privacidad de Moodle, úselas para contactar con el delegado de protección de datos.

Aceptación de las condiciones. Usted acepta las condiciones de uso para abrir un curso en el que Dixeo está activado. Se registrarán los análisis de uso del tutor. Si no está de acuerdo, elija Salir.

Uso de IA. Dixeo usa IA para redactar respuestas del tutor, contenido del curso e imágenes. Cuando un profesor lo activa, también produce sugerencias de revisión o calificación de tareas para las personas del curso. Cualquier calificación que {$a->sitename} trate como definitiva la controlan el profesor y las normas de evaluación propias de este sitio.

Sus datos no se usan para mercadotecnia.';
$string['consent_privacy_contact_admin'] = 'la administración del sitio';
$string['consent_privacy_dataprotection'] = 'Abrir sus solicitudes de privacidad';
$string['consent_privacy_heading'] = 'Aviso de privacidad';
$string['consent_processing_body'] = 'Dixeo registra análisis de uso del tutor: el número de mensajes, cuestionarios de práctica y lecciones que crea, el modo del tutor, el curso, la actividad y la hora. El texto de sus mensajes no se almacena en estos registros. El personal autorizado puede ver las cifras en los informes de uso del tutor.';
$string['consent_processing_checkbox'] = 'Mi uso del tutor (número de mensajes, modos, cursos, actividades y marcas de tiempo, sin el texto de los mensajes) se registrará para análisis e informes de uso.';
$string['consent_processing_heading'] = 'Análisis de uso';
$string['consent_review'] = 'Revisar';
$string['consent_save_processing'] = 'Guardar';
$string['consent_saved_processing'] = 'Su elección de análisis se ha guardado.';
$string['consent_status_accepted'] = 'Aceptó las condiciones de uso (versión {$a->version}) el {$a->time}. Los análisis de uso del tutor se registrarán (versión {$a->processingversion}) desde el {$a->processingtime}.';
$string['consent_status_processing_off'] = 'Los análisis de uso del tutor no se están registrando.';
$string['consent_status_processing_on'] = 'Los análisis de uso del tutor se registrarán (versión {$a->version}) desde el {$a->time}.';
$string['consent_status_terms_accepted'] = 'Aceptó las condiciones de uso (versión {$a->version}) el {$a->time}.';
$string['consent_status_terms_none'] = 'No ha aceptado las condiciones de uso actuales. Un curso en el que Dixeo está activado se lo pedirá antes de que pueda continuar.';
$string['consent_terms_body'] = 'Estas condiciones son un contrato entre usted y {$a->sitename}, la organización que opera este sitio. Se aplican siempre que usa Dixeo en un curso.

Dixeo, el proveedor de los complementos y los servicios de IA usados en este sitio, ofrece tutoría con IA, generación de contenido, generación de imágenes, sincronización de archivos del curso y contabilidad de créditos en los cursos donde está activado. Al continuar, acepta usar estas funciones conforme a estas condiciones.

No debe enviar contenido ilícito ni contenido que no tenga derecho a compartir. El resultado de la IA puede ser incompleto o inexacto. Usted es responsable de cómo lo usa, incluido cualquier trabajo que entregue para el curso.

Dixeo puede no estar disponible si el servicio se suspende o si se agotan los créditos. La sincronización de archivos y los trabajos de IA dependen de que el servicio Dixeo siga disponible.

Si no está de acuerdo, elija Salir. No podrá entrar en este curso mientras Dixeo esté activado. Puede retirar su aceptación en cualquier momento en la configuración de Dixeo, en Mis preferencias, y el curso se lo volverá a pedir. La retirada no elimina el trabajo del curso que ya haya creado.

Versión del documento {$a->version}.';
$string['consent_terms_checkbox'] = 'He leído y acepto las condiciones de uso y acuso recibo del aviso de privacidad.';
$string['consent_terms_heading'] = 'Condiciones de uso';
$string['consent_title'] = 'Condiciones de uso y aviso de privacidad';
$string['consent_withdraw_confirm'] = 'Revocar retira su aceptación de las condiciones de uso. Se eliminan los análisis de uso del tutor ya guardados. Un curso en el que Dixeo esté activado se lo volverá a pedir.';
$string['consent_withdraw_confirm_title'] = '¿Revocar la aceptación?';
$string['consent_withdraw_terms'] = 'Revocar';
$string['consent_withdrawn_terms'] = 'Su aceptación de las condiciones de uso se ha retirado. Un curso de Dixeo se lo volverá a pedir.';
$string['content_image_failed'] = 'Se ha producido un error';
$string['content_image_generating'] = 'Generando...';
$string['contentimagetitlefallback'] = 'Imagen de contenido';
$string['credit_action_course_structure'] = 'Estructura del curso';
$string['credit_action_edit_module'] = 'Editar módulo';
$string['credit_action_fill_module'] = 'Rellenar módulo';
$string['credit_action_generate_module'] = 'Generar módulo';
$string['credit_action_image_edit'] = 'Editar imagen';
$string['credit_action_image_generate'] = 'Generar imagen';
$string['credit_action_module_edit'] = 'Editar módulo';
$string['credit_action_module_fill'] = 'Rellenar módulo';
$string['credit_action_module_generate'] = 'Generar módulo';
$string['credit_action_tutor'] = 'Mensaje del tutor';
$string['credit_action_tutor_message'] = 'Mensaje del tutor';
$string['credit_action_unknown'] = 'Acción desconocida';
$string['credit_balance'] = 'Saldo de créditos';
$string['credit_component_block_dixeo_designer'] = 'Diseñador de cursos';
$string['credit_component_block_dixeo_modulegen'] = 'Generador de módulos';
$string['credit_component_block_dixeo_tutor'] = 'Tutor estudiantil';
$string['credit_component_filter_dixeo_imageeditor'] = 'Editor de imágenes';
$string['credit_component_local_dixeo'] = 'Núcleo Dixeo';
$string['credit_component_local_dixeo_editor'] = 'Editor de contenido';
$string['credit_component_unknown'] = 'Desconocido';
$string['credit_context_site'] = 'Sitio';
$string['credit_information'] = 'Información de créditos';
$string['credit_moduletype_glossary'] = 'Glosario';
$string['credit_moduletype_h5pactivity'] = 'Actividad H5P';
$string['credit_moduletype_label'] = 'Etiqueta';
$string['credit_moduletype_page'] = 'Página';
$string['credit_moduletype_quiz'] = 'Cuestionario';
$string['credit_moduletype_simplequiz2'] = 'Cuestionario simple';
$string['credit_moduletype_slideshow'] = 'Presentación';
$string['credit_report_apply_filters'] = 'Aplicar filtros';
$string['credit_report_breakdown'] = 'Créditos por módulo';
$string['credit_report_column_action'] = 'Acción';
$string['credit_report_column_course'] = 'Curso: Actividad';
$string['credit_report_column_credits'] = 'Créditos';
$string['credit_report_column_date'] = 'Fecha';
$string['credit_report_column_module'] = 'Componente';
$string['credit_report_column_user'] = 'Usuario';
$string['credit_report_filter_action'] = 'Acción';
$string['credit_report_filter_component'] = 'Componente';
$string['credit_report_filter_course'] = 'Curso';
$string['credit_report_filter_course_placeholder'] = 'Buscar cursos…';
$string['credit_report_filter_moduletype'] = 'Tipo de actividad';
$string['credit_report_filter_placeholder'] = 'Buscar o seleccionar…';
$string['credit_report_filter_user'] = 'Usuario';
$string['credit_report_filter_user_placeholder'] = 'Buscar usuarios…';
$string['credit_report_filters'] = 'Filtros';
$string['credit_report_histogram'] = 'Créditos en el tiempo';
$string['credit_report_kpi_courses'] = 'Total de cursos';
$string['credit_report_kpi_credits'] = 'Total de créditos';
$string['credit_report_kpi_rows'] = 'Total de transacciones';
$string['credit_report_kpi_users'] = 'Total de usuarios';
$string['credit_report_link_desc'] = 'Requiere la capacidad local/dixeo:manage o local/dixeo:viewusage a nivel de sitio. El informe y la exportación incluyen nombres de usuarios y cursos de todo el sitio.';
$string['credit_report_next_period'] = 'Período siguiente';
$string['credit_report_no_rows'] = 'No se encontraron registros de uso de créditos para el período y los filtros seleccionados.';
$string['credit_report_period'] = 'Período';
$string['credit_report_prev_period'] = 'Período anterior';
$string['credit_report_range_too_long'] = 'Los rangos de fechas personalizados no pueden superar un año.';
$string['credit_report_reset_filters'] = 'Restablecer';
$string['credit_report_summary'] = 'Resumen';
$string['credit_report_view_custom'] = 'Rango personalizado';
$string['credit_report_view_month'] = 'Mes';
$string['credit_report_view_week'] = 'Semana';
$string['credit_usage_report_nav'] = 'Uso de créditos Dixeo';
$string['credit_user_unknown'] = 'Desconocido';
$string['credits'] = 'créditos';
$string['current_balance'] = 'Saldo actual';
$string['current_balance_desc'] = 'Su saldo de créditos Dixeo actual. Los créditos se utilizan para operaciones de IA.';
$string['data_points'] = 'Puntos de datos';
$string['date'] = 'Fecha';
$string['day_fri'] = 'Vie';
$string['day_mon'] = 'Lun';
$string['day_sat'] = 'Sáb';
$string['day_sun'] = 'Dom';
$string['day_thu'] = 'Jue';
$string['day_tue'] = 'Mar';
$string['day_wed'] = 'Mié';
$string['description'] = 'Descripción';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Sección {$a->section}, actividad {$a->module}:';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Sección {$a->section}:';
$string['designerstructurevalidate_course_summary_too_long'] = 'El resumen del curso es demasiado largo (máximo {$a->max} caracteres).';
$string['designerstructurevalidate_course_title_required'] = 'El título del curso es un campo obligatorio.';
$string['designerstructurevalidate_course_title_too_long'] = 'El título del curso debe tener como máximo {$a->max} caracteres.';
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
$string['disabled_block_course'] = 'Este bloque no está disponible porque Dixeo se ha desactivado para este curso.';
$string['disabled_block_site'] = 'Este bloque no está disponible porque Dixeo se ha desactivado. Puede pedir a un administrador que lo active.';
$string['dixeo:contentimageedit'] = 'Editar imágenes de contenido incrustadas con IA';
$string['dixeo:contentimagegenerate'] = 'Generar imágenes de contenido incrustadas con IA';
$string['dixeo:create'] = 'Crear cursos con el Diseñador de Cursos';
$string['dixeo:edit'] = 'Editar módulos existentes con IA';
$string['dixeo:generate'] = 'Generar nuevos módulos con IA (página, etiqueta, cuestionario, glosario)';
$string['dixeo:manage'] = 'Gestionar la configuración de Dixeo y ver informes';
$string['dixeo:syncfiles'] = 'Activar, desactivar o lanzar la sincronización de archivos del curso Dixeo hacia la API externa';
$string['dixeo:viewtutorusage'] = 'Ver informe de uso del tutor en cursos';
$string['dixeo:viewtutorusagesite'] = 'Ver informe de uso del tutor en todo el sitio';
$string['dixeo:viewusage'] = 'Ver informes de uso de créditos de todo el sitio y exportar datos';
$string['dixeo_course_image_unsupported_type'] = 'Tipo de imagen generada no admitido.';
$string['dixeo_image_generation_disabled'] = 'La generación de imágenes está desactivada en la configuración del sitio.';
$string['dixeo_image_job_empty_result'] = 'La tarea de imagen no devolvió datos de imagen.';
$string['dixeo_image_job_failed'] = 'Error al generar la imagen. Inténtelo de nuevo.';
$string['dixeo_image_job_locked'] = 'Ya hay una tarea de imagen en curso para esta imagen.';
$string['dixeo_image_not_eligible'] = 'Esta imagen no se puede editar.';
$string['dixeo_pluginfile_not_found'] = 'No se pudo leer el archivo de imagen desde el almacenamiento.';
$string['dsl_error'] = 'Error al crear el módulo: {$a}';
$string['editorimageorphaned'] = 'Image removed from editor content before completion';
$string['enabled'] = 'Activar Dixeo';
$string['enabled_course'] = 'Activar Dixeo';
$string['enabled_course_help'] = 'Activa o desactiva las funciones de Dixeo en este curso.';
$string['enabled_help'] = 'Activa o desactiva las funciones de Dixeo de forma global.';
$string['error:activity_not_available'] = 'El tipo de actividad {$a} no está instalado o no está habilitado.';
$string['error:api_url_https_required'] = 'La URL de la API Dixeo debe ser una dirección HTTPS absoluta (por ejemplo https://api.dixeo.com).';
$string['error:authentication'] = 'Error de autenticación. Compruebe su clave API.';
$string['error:dixeo_disabled'] = 'Dixeo está desactivado.';
$string['error:job_not_found'] = 'No se encontró el trabajo solicitado.';
$string['error:notslideshow'] = 'El módulo del curso no es una actividad de presentación.';
$string['error:payment_required'] = 'Créditos insuficientes. Añada créditos para continuar.';
$string['error:rate_limit'] = 'Límite de solicitudes superado. Espere antes de hacer más solicitudes.';
$string['error:slidenotinslideshow'] = 'La diapositiva solicitada no pertenece a esta presentación.';
$string['error:unexpected'] = 'Se ha producido un error. Vuelva a intentarlo o pida a su administrador que revise los registros del sitio.';
$string['error:upstream_ai'] = 'Error del servicio de IA. Inténtelo de nuevo más tarde.';
$string['error:validation_failed'] = 'La solicitud no es válida. Revise los valores e inténtelo de nuevo.';
$string['eventcreditreportexported'] = 'Informe de uso de créditos exportado';
$string['eventcreditreportexporteddesc'] = 'El usuario con id \'{$a->userid}\' exportó el informe de uso de créditos (vista={$a->view}, formato={$a->dataformat}, filas={$a->rowcount}).';
$string['eventcreditreportviewed'] = 'Informe de uso de créditos consultado';
$string['eventcreditreportvieweddesc'] = 'El usuario con id \'{$a->userid}\' consultó el informe de uso de créditos (vista={$a->view}, filas={$a->rowcount}).';
$string['eventfilesyncdisabled'] = 'Sincronización de archivos Dixeo del curso desactivada';
$string['eventfilesyncdisableddesc'] = 'El usuario con id \'{$a->userid}\' desactivó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\' (removefiles={$a->removefiles}, remotedelete={$a->remotedeletestatus}).';
$string['eventfilesyncenabled'] = 'Sincronización de archivos Dixeo del curso activada';
$string['eventfilesyncenableddesc'] = 'El usuario con id \'{$a->userid}\' activó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\'.';
$string['eventfilesynctriggered'] = 'Sincronización de archivos Dixeo del curso activada (ejecución)';
$string['eventfilesynctriggereddesc'] = 'El usuario con id \'{$a->userid}\' disparó la sincronización de archivos Dixeo para el curso con id \'{$a->courseid}\'.';
$string['eventjobcancelled'] = 'Trabajo Dixeo cancelado';
$string['eventjobcancelleddesc'] = 'El usuario con id \'{$a->userid}\' canceló el trabajo Dixeo \'{$a->jobid}\' del curso con id \'{$a->courseid}\'.';
$string['eventsettingupdated'] = 'Ajuste de Dixeo actualizado';
$string['eventsettingupdateddesc'] = 'El usuario con id \'{$a->userid}\' actualizó el ajuste de Dixeo \'{$a->component}/{$a->name}\' en el contexto con id \'{$a->contextid}\'.';
$string['eventtutorusagereportexported'] = 'Informe de uso del tutor exportado';
$string['eventtutorusagereportexporteddesc'] = 'El usuario {$a->userid} exportó el informe de uso del tutor (nivel: {$a->level}, vista: {$a->view}, formato: {$a->dataformat}, filas: {$a->rowcount}).';
$string['eventtutorusagereportviewed'] = 'Informe de uso del tutor visto';
$string['eventtutorusagereportvieweddesc'] = 'El usuario {$a->userid} vio el informe de uso del tutor (nivel: {$a->level}, vista: {$a->view}, filas: {$a->rowcount}).';
$string['feedback_correct'] = 'Bien hecho, has acertado esta respuesta. ¡Sigue así!';
$string['feedback_incorrect'] = 'No del todo esta vez. Repasar el tema te ayudará a mejorar.';
$string['feedback_partial'] = 'Vas por buen camino. Repasa el material y lo conseguirás.';
$string['file_synchronization'] = 'Sincronización de archivos';
$string['files'] = 'archivos';
$string['filesync_disable_remove'] = 'Desactivar y borrar datos de sincronización';
$string['filesync_enable'] = 'Activar sincronización';
$string['filesync_enabled'] = 'Sincronización de archivos';
$string['filesync_enabled_default'] = 'Sincronización de archivos para cursos nuevos';
$string['filesync_enabled_default_help'] = 'Activa la sincronización de archivos para los cursos nuevos (los cursos existentes no se ven afectados). El Diseñador de Cursos debe sincronizar los archivos durante la generación del curso; si este ajuste está desactivado, la sincronización automática se pausa cuando el curso está listo.';
$string['filesync_enabled_help'] = 'Active o pause la sincronización de archivos de este curso. Al pausar se conservan los archivos ya sincronizados.';
$string['filesync_failed'] = 'Error de sincronización de archivos: {$a}';
$string['filesync_files_count'] = '{$a} archivos sincronizados';
$string['filesync_pause'] = 'Pausar sincronización';
$string['filesync_pending_deletion'] = 'La eliminación remota de archivos de este curso sigue pendiente';
$string['filesync_pill'] = 'Ubicación del indicador de estado';
$string['filesync_pill_coursetitle'] = 'Título del curso';
$string['filesync_pill_footer'] = 'Pie de página';
$string['filesync_pill_help'] = 'Dónde se muestra el indicador de estado de Dixeo en la página del curso.';
$string['filesync_pill_hidden'] = 'Oculto';
$string['filesync_progress'] = '{$a}% completado';
$string['filesync_resync'] = 'Sincronizar ahora';
$string['filesync_status_disabled'] = 'Sincronización desactivada';
$string['filesync_status_error'] = 'Error de sincronización';
$string['filesync_status_none'] = 'Ningún archivo sincronizado';
$string['filesync_status_outdated'] = 'Contenido modificado, sincronización necesaria';
$string['filesync_status_paused'] = 'Sincronización en pausa';
$string['filesync_status_pending_deletion'] = 'Eliminación remota de archivos pendiente';
$string['filesync_status_synchronized'] = 'Archivos sincronizados';
$string['filesync_status_syncing'] = 'Sincronizando archivos...';
$string['filesync_timeout'] = 'La sincronización de archivos ha caducado antes de indexar los archivos del curso';
$string['filesync_title'] = 'Sincronización de archivos Dixeo';
$string['generation_language'] = 'Idioma de generación';
$string['generation_language_desc'] = 'Idioma de los cuestionarios y las lecciones que genera el tutor. Al principio coincide con su idioma preferido.';
$string['generation_output_language'] = 'IDIOMA: Genere todo el contenido para el alumno (preguntas, respuestas, texto de la lección y títulos) en {$a->language}.';
$string['image_generation'] = 'Generación de imágenes';
$string['image_generation_content_mode'] = 'Imágenes de contenido';
$string['image_generation_content_mode_desc'] = 'Controla las acciones de imagen por IA para imágenes en el contenido de actividades, como Páginas y Libros.';
$string['image_generation_content_mode_disabled'] = 'Desactivado';
$string['image_generation_content_mode_generate'] = 'Generar';
$string['image_generation_content_mode_generate_edit'] = 'Generar y editar';
$string['image_generation_course_mode'] = 'Imágenes del curso';
$string['image_generation_course_mode_desc'] = 'Controla las acciones de imagen por IA para la imagen de resumen del curso.';
$string['image_generation_course_mode_disabled'] = 'Desactivado';
$string['image_generation_course_mode_generate'] = 'Generar';
$string['image_generation_course_mode_generate_edit'] = 'Generar y editar';
$string['image_generation_desc'] = 'Controla la disponibilidad de la generación y edición de imágenes por IA para las imágenes del curso y de las secciones.';
$string['image_generation_enabled'] = 'Activar generación de imágenes';
$string['image_generation_enabled_desc'] = 'Si está desactivado, se bloquean todas las solicitudes de generar o editar imágenes.';
$string['image_generation_mode_disabled'] = 'Desactivado';
$string['image_generation_mode_generate'] = 'Generar';
$string['image_generation_mode_generate_edit'] = 'Generar y editar';
$string['image_generation_section_mode'] = 'Imágenes de sección';
$string['image_generation_section_mode_desc'] = 'Controla las acciones de imagen por IA para las imágenes de capítulo o sección.';
$string['image_generation_section_mode_disabled'] = 'Desactivado';
$string['image_generation_section_mode_generate'] = 'Generar';
$string['image_generation_section_mode_generate_edit'] = 'Generar y editar';
$string['last_sync'] = 'Última sincronización';
$string['learning_intent'] = 'Intención de aprendizaje';
$string['learning_intent_help'] = 'La intención de aprendizaje define un conjunto de objetivos de aprendizaje para el curso.';
$string['namespace'] = 'Espacio de nombres';
$string['namespace_desc'] = 'Solo necesario cuando varios sitios Moodle comparten la misma clave API. Cada sitio debe usar un espacio de nombres diferente (ej. "production", "staging", "site1") para mantener sus datos separados. Deje "default" si este es el único sitio que usa esta clave API.';
$string['overview'] = 'Resumen de Dixeo';
$string['pagination'] = 'Navegación de páginas';
$string['period'] = 'Período';
$string['pluginname'] = 'Dixeo AI';
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
$string['preferences_delete_conversation'] = 'Eliminar la conversación del tutor';
$string['preferences_delete_conversation_desc'] = 'Esto elimina solo su conversación con el tutor en el curso actual. Las conversaciones de otros cursos no cambian.';
$string['privacy:metadata:consent'] = 'Registros de la aceptación de las condiciones de uso, con el usuario, la hora y el aviso que se mostró.';
$string['privacy:metadata:consent:action'] = 'Si el usuario otorgó o retiró la aceptación.';
$string['privacy:metadata:consent:documentid'] = 'El aviso guardado al que se refiere esta decisión.';
$string['privacy:metadata:consent:timecreated'] = 'Cuándo el usuario otorgó o retiró su elección.';
$string['privacy:metadata:consent:userid'] = 'Usuario al que pertenece el registro.';
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
$string['privacy:metadata:credit_usage'] = 'Registros de uso de créditos Dixeo sincronizados, enriquecidos con contexto de Moodle.';
$string['privacy:metadata:credit_usage:amount'] = 'El importe firmado de la transacción.';
$string['privacy:metadata:credit_usage:cmid'] = 'El id del módulo del curso cuando se conoce.';
$string['privacy:metadata:credit_usage:component'] = 'El componente Dixeo de origen.';
$string['privacy:metadata:credit_usage:contextid'] = 'El contexto de Moodle cuando se conoce.';
$string['privacy:metadata:credit_usage:courseid'] = 'El curso cuando se conoce.';
$string['privacy:metadata:credit_usage:credits'] = 'El importe normalizado de uso de créditos.';
$string['privacy:metadata:credit_usage:description'] = 'La descripción de la transacción de la API.';
$string['privacy:metadata:credit_usage:jobid'] = 'El identificador remoto del trabajo Dixeo cuando está disponible.';
$string['privacy:metadata:credit_usage:jobtype'] = 'El tipo de trabajo de la API cuando está disponible.';
$string['privacy:metadata:credit_usage:moduletype'] = 'El tipo de actividad de Moodle cuando se conoce.';
$string['privacy:metadata:credit_usage:operation'] = 'La operación local de respaldo.';
$string['privacy:metadata:credit_usage:timecreated'] = 'La marca de tiempo de la transacción.';
$string['privacy:metadata:credit_usage:timesynced'] = 'Cuándo se sincronizó la fila por última vez.';
$string['privacy:metadata:credit_usage:transactionid'] = 'El identificador remoto de la transacción Dixeo.';
$string['privacy:metadata:credit_usage:type'] = 'El tipo de transacción.';
$string['privacy:metadata:credit_usage:userid'] = 'El usuario iniciador cuando se conoce.';
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
$string['privacy:metadata:image_job'] = 'Async image generation and editing jobs for course content and structure.';
$string['privacy:metadata:image_job:courseid'] = 'The course the image job belongs to.';
$string['privacy:metadata:image_job:errormessage'] = 'A generic failure message when the image job failed.';
$string['privacy:metadata:image_job:jobid'] = 'The remote Dixeo job identifier.';
$string['privacy:metadata:image_job:prompt'] = 'The image generation or edit prompt.';
$string['privacy:metadata:image_job:status'] = 'The current status of the image job.';
$string['privacy:metadata:image_job:timecreated'] = 'The time when the image job record was created.';
$string['privacy:metadata:image_job:timemodified'] = 'The time when the image job record was last modified.';
$string['privacy:metadata:image_job:userid'] = 'The user who started the image job.';
$string['privacy:metadata:jobs'] = 'Registros locales que vinculan trabajos remotos de Dixeo AI con cursos y usuarios de Moodle.';
$string['privacy:metadata:jobs:accessmode'] = 'Si el trabajo es de ámbito del iniciador o compartido en el curso para el control de acceso.';
$string['privacy:metadata:jobs:cmid'] = 'El id del módulo del curso cuando se conoce.';
$string['privacy:metadata:jobs:component'] = 'El componente Dixeo de origen del trabajo.';
$string['privacy:metadata:jobs:contextid'] = 'El contexto de Moodle cuando se conoce.';
$string['privacy:metadata:jobs:courseid'] = 'El curso al que está vinculado el trabajo.';
$string['privacy:metadata:jobs:jobid'] = 'El identificador remoto del trabajo de Dixeo.';
$string['privacy:metadata:jobs:moduletype'] = 'El tipo de actividad de Moodle cuando se conoce.';
$string['privacy:metadata:jobs:namespace'] = 'El espacio de nombres de la API Dixeo usado para el trabajo.';
$string['privacy:metadata:jobs:operation'] = 'El tipo de operación lógica del trabajo.';
$string['privacy:metadata:jobs:timecreated'] = 'La hora en que se creó el vínculo local del trabajo.';
$string['privacy:metadata:jobs:userid'] = 'El usuario que inició el trabajo.';
$string['privacy:metadata:preference:generation_language'] = 'El idioma elegido para los cuestionarios y las lecciones generados por el tutor.';
$string['privacy:metadata:tutor_synthesis'] = 'Síntesis del curso de las conversaciones del tutor con el alumnado. El texto es un agregado y no está vinculado a un estudiante.';
$string['privacy:metadata:tutor_synthesis:courseid'] = 'El curso al que pertenece la síntesis';
$string['privacy:metadata:tutor_synthesis:status'] = 'Si la síntesis está pendiente, lista o ha fallado';
$string['privacy:metadata:tutor_synthesis:timecreated'] = 'Cuándo se solicitó la síntesis';
$string['privacy:metadata:tutor_synthesis:userid'] = 'El docente que solicitó la síntesis';
$string['privacy:metadata:tutor_usage_daily'] = 'Agregados diarios de uso del tutor';
$string['privacy:metadata:tutor_usage_daily:cmid'] = 'El id del módulo del curso';
$string['privacy:metadata:tutor_usage_daily:courseid'] = 'El id del curso';
$string['privacy:metadata:tutor_usage_daily:daystart'] = 'El día que cubre el agregado';
$string['privacy:metadata:tutor_usage_daily:lastactive'] = 'Marca de tiempo de la última actividad';
$string['privacy:metadata:tutor_usage_daily:lessoncreated'] = 'Lecciones Teach creadas';
$string['privacy:metadata:tutor_usage_daily:messages'] = 'Recuento de mensajes del día';
$string['privacy:metadata:tutor_usage_daily:mode'] = 'El modo del tutor';
$string['privacy:metadata:tutor_usage_daily:quizcreated'] = 'Cuestionarios de práctica creados';
$string['privacy:metadata:tutor_usage_daily:userid'] = 'El id del usuario';
$string['privacy:metadata:tutor_usage_event'] = 'Eventos de uso del tutor';
$string['privacy:metadata:tutor_usage_event:cmid'] = 'El id del módulo del curso cuando se conoce';
$string['privacy:metadata:tutor_usage_event:courseid'] = 'El curso donde ocurrió el evento';
$string['privacy:metadata:tutor_usage_event:eventtype'] = 'El tipo de evento de uso del tutor';
$string['privacy:metadata:tutor_usage_event:mode'] = 'El modo de interacción del tutor';
$string['privacy:metadata:tutor_usage_event:timecreated'] = 'Cuándo se registró el evento';
$string['privacy:metadata:tutor_usage_event:userid'] = 'El usuario que desencadenó el evento del tutor';
$string['privacy:metadata:tutor_usage_open'] = 'Sesiones de tutor abiertas';
$string['privacy:metadata:tutor_usage_open:courseid'] = 'El id del curso';
$string['privacy:metadata:tutor_usage_open:lastmessage'] = 'Marca de tiempo del último mensaje';
$string['privacy:metadata:tutor_usage_open:messagecount'] = 'Mensajes hasta ahora en la sesión';
$string['privacy:metadata:tutor_usage_open:timestart'] = 'Hora de inicio de la sesión';
$string['privacy:metadata:tutor_usage_open:userid'] = 'El id del usuario';
$string['privacy:metadata:tutor_usage_session'] = 'Sesiones de uso del tutor cerradas';
$string['privacy:metadata:tutor_usage_session:courseid'] = 'El id del curso';
$string['privacy:metadata:tutor_usage_session:duration'] = 'Duración de la sesión en segundos';
$string['privacy:metadata:tutor_usage_session:messagecount'] = 'Mensajes en la sesión';
$string['privacy:metadata:tutor_usage_session:timeend'] = 'Hora de fin de la sesión';
$string['privacy:metadata:tutor_usage_session:timestart'] = 'Hora de inicio de la sesión';
$string['privacy:metadata:tutor_usage_session:userid'] = 'El id del usuario';
$string['privacy:path:consent'] = 'Elecciones sobre condiciones y privacidad';
$string['privacy:path:conversation'] = 'Conversación del tutor';
$string['privacy:path:course_ai'] = 'Sincronización AI del curso';
$string['privacy:path:credit_usage'] = 'Uso de créditos';
$string['privacy:path:image_jobs'] = 'Dixeo image jobs';
$string['privacy:path:jobs'] = 'Trabajos Dixeo AI';
$string['privacy:path:tutor_synthesis'] = 'Síntesis de las conversaciones del alumnado';
$string['privacy:path:tutor_usage'] = 'Analíticas de uso del tutor';
$string['recent_transactions'] = 'Historial de transacciones';
$string['settings_badge_customised'] = 'Personalizado';
$string['settings_badge_dixeo'] = 'Predeterminado de Dixeo';
$string['settings_badge_site'] = 'Predeterminado del sitio';
$string['settings_button'] = 'Ajustes';
$string['settings_category'] = 'Dixeo';
$string['settings_category_designer'] = 'Diseñador de cursos';
$string['settings_category_dixeo_core'] = 'Dixeo';
$string['settings_category_dixeo_designer'] = 'Diseñador de Cursos';
$string['settings_category_dixeo_tutor'] = 'Tutor estudiantil';
$string['settings_category_filesync'] = 'Sincronización de archivos';
$string['settings_category_imagegeneration'] = 'Generación de imágenes';
$string['settings_category_tutor'] = 'Tutor';
$string['settings_column_activity'] = 'Tarea';
$string['settings_column_course'] = 'Curso';
$string['settings_column_dixeo'] = 'Dixeo';
$string['settings_column_setting'] = 'Ajuste';
$string['settings_column_site'] = 'Predeterminado del sitio';
$string['settings_group_feature'] = 'Función';
$string['settings_group_plugin'] = 'Plugin';
$string['settings_grouping'] = 'Agrupar por';
$string['settings_modal_advanced'] = 'Avanzado';
$string['settings_modal_all_features'] = 'Todas las funciones';
$string['settings_modal_all_plugins'] = 'Todos los plugins';
$string['settings_modal_breadcrumb'] = 'Sitio / Dixeo / Curso: {$a}';
$string['settings_modal_breadcrumb_activity'] = 'Sitio / Dixeo / Curso: {$a->course} / Tarea: {$a->activity}';
$string['settings_modal_categories'] = 'Categorías';
$string['settings_modal_count'] = '{$a} ajustes';
$string['settings_modal_customised'] = 'Solo personalizados';
$string['settings_modal_empty'] = 'Borre la búsqueda, desactive Solo personalizados o elija otra área.';
$string['settings_modal_error'] = 'No se han podido cargar los ajustes de Dixeo.';
$string['settings_modal_loading'] = 'Cargando ajustes';
$string['settings_modal_preferences'] = 'Mis preferencias';
$string['settings_modal_save_course'] = 'Guarde el curso antes de cargar sus ajustes de Dixeo.';
$string['settings_modal_save_error'] = 'No se han podido guardar los ajustes de Dixeo.';
$string['settings_modal_saving'] = 'Guardando los cambios...';
$string['settings_modal_search'] = 'Buscar ajustes';
$string['settings_modal_search_placeholder'] = 'Buscar por nombre, clave o plugin';
$string['settings_modal_status'] = 'Estado';
$string['settings_modal_title'] = 'Ajustes de Dixeo';
$string['settings_modal_unsaved_close'] = 'Cerrar sin guardar';
$string['settings_modal_unsaved_confirm'] = '¿Cerrar sin guardar estos cambios?';
$string['settings_modal_unsaved_count'] = '{$a} cambios sin guardar';
$string['settings_modal_unsaved_none'] = 'No hay cambios sin guardar';
$string['settings_modal_unsaved_title'] = 'Cambios sin guardar';
$string['settings_number_range'] = 'Introduzca un número entero entre {$a->min} y {$a->max}.';
$string['settings_readonly'] = 'Este ajuste no se puede cambiar';
$string['settings_revert'] = 'Revertir los cambios de {$a}';
$string['settings_value_empty'] = 'vacío';
$string['settings_value_off'] = 'Desactivado';
$string['settings_value_on'] = 'Activado';
$string['state_active'] = 'Activo';
$string['state_frozen'] = 'Congelado';
$string['state_suspended'] = 'Suspendido';
$string['task_aggregate_tutor_usage'] = 'Agregar analíticas de uso del tutor Dixeo';
$string['task_cleanup_image_jobs'] = 'Limpiar registros de tareas de imagen de Dixeo';
$string['task_poll_image'] = 'Consultar la tarea de imagen de Dixeo';
$string['task_poll_image_generation'] = 'Consultar la tarea de generación de imágenes de Dixeo';
$string['task_process_file_sync'] = 'Procesar sincronización de archivos Dixeo';
$string['task_process_remote_file_deletion'] = 'Reintentar eliminación remota de archivos Dixeo';
$string['teach_lesson_default_title'] = 'Lección personalizada';
$string['teach_lesson_error_invalid_result'] = 'Resultado del trabajo no válido.';
$string['teach_lesson_error_job_not_completed'] = 'El trabajo no está completado. Estado: {$a->status}';
$string['teach_lesson_error_no_content'] = 'No hay contenido en el resultado del trabajo.';
$string['teach_lesson_error_wrong_module_type'] = 'El trabajo no es una generación de página.';
$string['teach_lesson_instructions'] = 'Genera una lección de módulo Page personalizada para {$a->scopedescription}.

El alumno ha preguntado:
"{$a->learnerrequest}"

REQUISITOS OBLIGATORIOS — DEBES seguirlos exactamente:
1. TIPO DE MÓDULO: Genera un módulo Page con un nombre claro y descriptivo, un breve resumen introductorio (intro) y un contenido principal rico (content).
2. ESTRUCTURA: Organiza la lección con encabezados claros y secciones lógicas. Usa ejemplos cuando sea útil.
3. SOLICITUD DEL ALUMNO: Responde directamente a la solicitud del alumno — profundiza en el tema o explícalo en términos más sencillos como pidió.
4. ALINEACIÓN: Basa la lección en el contexto del curso proporcionado. No inventes hechos que contradigan el material de origen.
5. SOLO TEXTO: Produce únicamente texto. No incluyas imágenes, ilustraciones, etiquetas <img> ni marcadores [img-gen ...]. Las lecciones personalizadas no procesan marcadores de imagen.

Antes de terminar, verifica que el campo content sea sustancial, solo texto, y responda directamente a la solicitud del alumno.';
$string['total_used'] = 'Total utilizado';
$string['tutor_usage_duration_days'] = '{$a->days}d {$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_hours'] = '{$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_minutes'] = '{$a} min';
$string['tutor_usage_duration_seconds'] = '{$a}s';
$string['tutor_usage_mode_guide'] = 'Guide';
$string['tutor_usage_mode_normal'] = 'Estándar';
$string['tutor_usage_mode_quiz'] = 'Quiz';
$string['tutor_usage_mode_teach'] = 'Teach';
$string['tutor_usage_report_breadcrumb'] = 'Navegación del informe';
$string['tutor_usage_report_cell_tooltip_adoption'] = '{$a->active} de {$a->total} usuarios';
$string['tutor_usage_report_change_since_month'] = 'desde el mes pasado';
$string['tutor_usage_report_change_since_week'] = 'desde la semana pasada';
$string['tutor_usage_report_column_guide'] = 'Guide';
$string['tutor_usage_report_column_lastactive'] = 'Última actividad';
$string['tutor_usage_report_column_messages'] = 'Mensajes';
$string['tutor_usage_report_column_moduletype'] = 'Tipo de actividad';
$string['tutor_usage_report_column_name'] = 'Nombre';
$string['tutor_usage_report_column_quiz'] = 'Quiz';
$string['tutor_usage_report_column_standard'] = 'Estándar';
$string['tutor_usage_report_column_teach'] = 'Teach';
$string['tutor_usage_report_course_page'] = 'Página del curso';
$string['tutor_usage_report_filter_roles'] = 'Usuarios';
$string['tutor_usage_report_heatmap'] = 'Mapa de calor de actividad';
$string['tutor_usage_report_heatmap_info'] = 'Número total de mensajes enviados en cada franja de tres horas, por día de la semana.';
$string['tutor_usage_report_kpi_adoption'] = 'Adopción';
$string['tutor_usage_report_kpi_adoption_info'] = 'Proporción de usuarios del ámbito que usaron el tutor al menos una vez durante el periodo.';
$string['tutor_usage_report_kpi_adoption_secondary'] = '{$a->active} activos / {$a->inactive} inactivos / {$a->total} total';
$string['tutor_usage_report_kpi_duration'] = 'Duración';
$string['tutor_usage_report_kpi_duration_info'] = 'Tiempo total interactuando con el tutor durante el periodo.';
$string['tutor_usage_report_kpi_duration_secondary'] = 'Mediana {$a->median} / Media {$a->average}';
$string['tutor_usage_report_kpi_engagement'] = 'Compromiso';
$string['tutor_usage_report_kpi_engagement_info'] = 'El total del número de días en que los usuarios interactuaron con el tutor durante el periodo';
$string['tutor_usage_report_kpi_engagement_info_user'] = 'Número de días distintos en los que este usuario usó el tutor durante el periodo.';
$string['tutor_usage_report_kpi_engagement_secondary'] = 'Última actividad: {$a}';
$string['tutor_usage_report_kpi_engagement_secondary_aggregate'] = 'Mediana {$a->median} / Media {$a->average} días activos por usuario activo';
$string['tutor_usage_report_kpi_engagement_value'] = '{$a} días';
$string['tutor_usage_report_kpi_messages'] = 'Mensajes';
$string['tutor_usage_report_kpi_messages_info'] = 'Número total de mensajes enviados al tutor durante el periodo.';
$string['tutor_usage_report_kpi_messages_secondary'] = 'Mediana {$a->median} / Media {$a->average}';
$string['tutor_usage_report_kpi_mode_desc_lesson'] = 'lecciones creadas';
$string['tutor_usage_report_kpi_mode_desc_messages'] = 'mensajes enviados';
$string['tutor_usage_report_kpi_mode_desc_quiz'] = 'cuestionarios enviados';
$string['tutor_usage_report_kpi_mode_info_guide'] = 'Mensajes enviados en modo Guía, donde el tutor lleva al alumno hasta la respuesta en lugar de darla.';
$string['tutor_usage_report_kpi_mode_info_normal'] = 'Mensajes enviados en modo Estándar, donde el tutor responde directamente a las preguntas.';
$string['tutor_usage_report_kpi_mode_info_quiz'] = 'Mensajes enviados en modo Cuestionario, los resultados del cuestionario se envían al tutor para su análisis.';
$string['tutor_usage_report_kpi_mode_info_teach'] = 'Mensajes enviados en modo Enseñar, las lecciones generadas pasan a formar parte de la conversación.';
$string['tutor_usage_report_kpi_mode_secondary_quiz'] = 'Cuestionarios creados: {$a}';
$string['tutor_usage_report_kpi_sessions'] = 'Sesiones';
$string['tutor_usage_report_kpi_sessions_info'] = 'Número de sesiones con el tutor. Una sesión empieza con el primer mensaje y se cierra tras {$a} sin uno nuevo.';
$string['tutor_usage_report_kpi_sessions_secondary'] = 'Mediana {$a->median} / Media {$a->average}';
$string['tutor_usage_report_level_site'] = 'Todos los cursos';
$string['tutor_usage_report_nav'] = 'Uso del tutor Dixeo';
$string['tutor_usage_report_next_period'] = 'Periodo siguiente';
$string['tutor_usage_report_no_rows'] = 'No se encontraron registros de uso del tutor para el periodo y filtros seleccionados.';
$string['tutor_usage_report_performance'] = 'Rendimiento';
$string['tutor_usage_report_performance_activities'] = 'Actividades calificadas';
$string['tutor_usage_report_performance_axis_grade'] = 'Calificación (%)';
$string['tutor_usage_report_performance_axis_usage'] = 'Mensajes (todo el tiempo)';
$string['tutor_usage_report_performance_column_average'] = 'Calificación media';
$string['tutor_usage_report_performance_column_user_vs_average'] = 'Usuario / media';
$string['tutor_usage_report_performance_course_total'] = 'Total del curso';
$string['tutor_usage_report_performance_mean_grade'] = 'Calificación media';
$string['tutor_usage_report_performance_mean_usage'] = 'Uso medio';
$string['tutor_usage_report_performance_na'] = '—';
$string['tutor_usage_report_performance_scatter'] = 'Uso frente a calificación';
$string['tutor_usage_report_performance_scatter_empty'] = 'Aún no hay estudiantes calificados para mostrar.';
$string['tutor_usage_report_performance_user_vs_average'] = '{$a->user} / {$a->average}';
$string['tutor_usage_report_period'] = 'Periodo';
$string['tutor_usage_report_prev_period'] = 'Periodo anterior';
$string['tutor_usage_report_range_too_long'] = 'Los rangos de fechas personalizados no pueden superar los seis meses.';
$string['tutor_usage_report_rolescope_all'] = 'Todos';
$string['tutor_usage_report_rolescope_students'] = 'Estudiantes';
$string['tutor_usage_report_rolescope_teachers'] = 'Profesores';
$string['tutor_usage_report_stacked_bar'] = 'Mensajes por modo a lo largo del tiempo';
$string['tutor_usage_report_summary'] = 'Resumen';
$string['tutor_usage_report_table_courses'] = 'Cursos';
$string['tutor_usage_report_table_modules'] = 'Actividades';
$string['tutor_usage_report_table_users'] = 'Usuarios';
$string['tutor_usage_report_view_custom'] = 'Rango personalizado';
$string['tutor_usage_report_view_month'] = 'Mes';
$string['tutor_usage_report_view_week'] = 'Semana';
$string['type'] = 'Tipo';
$string['view_credit_report'] = 'Ver informe de uso de créditos';
