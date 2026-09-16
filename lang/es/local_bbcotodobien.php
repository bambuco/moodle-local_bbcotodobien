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
 * Spanish language pack for ToDo good
 *
 * @package    local_bbcotodobien
 * @category   string
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Activo';
$string['addaudittype'] = 'Añadir tipo de auditoría';
$string['addrule'] = 'Añadir regla';
$string['allcategories'] = 'Todas las categorías';
$string['auditrerun'] = 'La auditoría se ejecutó de nuevo.';
$string['audittypecreated'] = 'Tipo de auditoría creado';
$string['audittypedeleted'] = 'Tipo de auditoría eliminado';
$string['audittypename'] = 'Nombre';
$string['audittypename_help'] = 'Un nombre breve que identifica este tipo de auditoría para profesores y administradores.';
$string['audittypeupdated'] = 'Tipo de auditoría actualizado';
$string['bbcotodobien:execute'] = 'Ejecutar auditorías de ToDo bien';
$string['bbcotodobien:viewreport'] = 'Ver informes de ToDo bien';
$string['categories'] = 'Categorías';
$string['categories_help'] = 'Limita este tipo de auditoría a los cursos de las categorías seleccionadas y sus descendientes. Déjelo vacío para aplicarlo a todos los cursos del sitio.';
$string['cliauditcompleted'] = 'Auditoría completada: curso {$a->courseid}, tipo {$a->audittypeid}, auditoría {$a->auditid}, cumplimiento {$a->compliance}% ({$a->status}).';
$string['clirulereevaluated'] = 'Regla reevaluada: curso {$a->courseid}, regla {$a->ruleconfigid}, auditoría {$a->auditid}, cumplimiento {$a->compliance}% ({$a->status}).';
$string['compliance'] = 'Cumplimiento';
$string['currentreport'] = 'Informe actual';
$string['dashboard'] = 'Tablero de auditorías';
$string['deleteaudittype'] = 'Eliminar tipo de auditoría';
$string['deleteaudittypeconfirm'] = '¿Seguro que desea eliminar el tipo de auditoría "{$a}" y todas sus reglas e historial?';
$string['deleterule'] = 'Eliminar regla';
$string['deleteruleconfirm'] = '¿Seguro que desea eliminar la regla "{$a}" y todos sus resultados históricos?';
$string['editaudittype'] = 'Editar tipo de auditoría';
$string['editrule'] = 'Editar regla';
$string['entitycourseaudit'] = 'Auditoría de curso';
$string['erroraudittypenotapplicable'] = 'El tipo de auditoría no aplica a este curso.';
$string['errorcannotviewdashboard'] = 'No tiene permiso para ver el tablero de ToDo bien.';
$string['errorcliconflictingoptions'] = 'Indique --audittypeid o --ruleconfigid, no ambos.';
$string['errorclismissingoptions'] = 'Debe indicar --courseid y --audittypeid o --ruleconfigid.';
$string['errorinvalidaudittype'] = 'El tipo de auditoría no existe.';
$string['errorinvalidcourseaudit'] = 'La auditoría del curso no existe.';
$string['errorinvaliddataformat'] = 'El formato de descarga seleccionado no está disponible.';
$string['errorinvalidruleclass'] = 'La clase de regla seleccionada no es válida.';
$string['errorinvalidruleconfig'] = 'La configuración de la regla no existe.';
$string['errorinvalidruleexport'] = 'El archivo de exportación de reglas no es válido.';
$string['errornorulesselected'] = 'Seleccione al menos una regla para exportar.';
$string['eventauditcompleted'] = 'Auditoría de curso completada';
$string['eventrulereevaluated'] = 'Regla de auditoría reevaluada';
$string['exportrules'] = 'Exportar reglas';
$string['exportrules_help'] = 'Elija qué reglas de este tipo de auditoría incluir en el archivo JSON descargable.';
$string['generatesnapshot'] = 'Generar instantánea de auditorías actual';
$string['generatesnapshot_help'] = 'Crea un archivo descargable con la última auditoría almacenada de cada curso de este tipo. Elija uno de los formatos de datos instalados en el sitio.';
$string['guidance'] = 'Orientación';
$string['guidance_help'] = 'Texto formativo opcional que se muestra a los profesores cuando esta regla no se cumple. Puede incluir archivos y enlaces.';
$string['headeralert'] = 'Alerta de ToDo bien';
$string['headeralertmessage'] = 'El cumplimiento de auditoría en este curso es de {$a}%.';
$string['history'] = 'Historial';
$string['importrules'] = 'Importar reglas';
$string['importrules_help'] = 'Importa reglas desde un archivo JSON a este tipo de auditoría. Se omiten las reglas cuyo nombre ya existe en este tipo.';
$string['importrulessummary'] = 'Se importaron {$a->imported} regla(s). Se omitieron {$a->skippedname} por nombre duplicado y {$a->skippedclass} por clase de regla desconocida.';
$string['includehidden'] = 'Incluir cursos ocultos';
$string['includehidden_desc'] = 'Si se activa, las auditorías programadas también procesan cursos ocultos (visible = 0).';
$string['lastrun'] = 'Última ejecución';
$string['loadparameters'] = 'Cargar parámetros';
$string['manageaudittypes'] = 'Gestionar tipos de auditoría';
$string['managesnapshotfiles'] = 'Gestionar archivos';
$string['mandatory'] = 'Obligatoria';
$string['maxhistorypercourse'] = 'Máximo de informes históricos por curso';
$string['maxhistorypercourse_desc'] = 'Número máximo de informes de auditoría almacenados por curso y tipo de auditoría. Use 0 para no limitar el volumen.';
$string['neverrun'] = 'Nunca ejecutada';
$string['noapplicabletypes'] = 'Ningún tipo de auditoría aplica a este curso.';
$string['noaudittypes'] = 'Aún no se han creado tipos de auditoría.';
$string['nodetails'] = 'Aún no hay detalles de diagnóstico para esta regla.';
$string['noruleclasses'] = 'Aún no hay clases de reglas disponibles. Las reglas concretas aparecerán aquí cuando se instalen.';
$string['norules'] = 'Aún no se han configurado reglas para este tipo de auditoría.';
$string['nosnapshots'] = 'Aún no se han generado instantáneas para este tipo de auditoría.';
$string['notavailableyet'] = 'Esta página aún no está disponible. Se habilitará en una fase posterior de implementación.';
$string['optional'] = 'Opcional';
$string['pluginname'] = 'ToDo bien';
$string['privacy:metadata:course_audit'] = 'Almacena cada ejecución de auditoría sobre un curso, incluyendo quién la ejecutó.';
$string['privacy:metadata:course_audit:audittypeid'] = 'El tipo de auditoría que se ejecutó.';
$string['privacy:metadata:course_audit:compliance'] = 'El porcentaje de cumplimiento global de la ejecución.';
$string['privacy:metadata:course_audit:courseid'] = 'El curso que se auditó.';
$string['privacy:metadata:course_audit:status'] = 'El estado global de la ejecución.';
$string['privacy:metadata:course_audit:timecreated'] = 'La hora en que se creó la ejecución.';
$string['privacy:metadata:course_audit:timemodified'] = 'La hora en que se actualizó por última vez la ejecución.';
$string['privacy:metadata:course_audit:userid'] = 'El usuario que ejecutó la auditoría (0 para tareas programadas).';
$string['privacy:metadata:rule_result'] = 'Almacena los resultados consolidados de cada regla en una ejecución de auditoría.';
$string['privacy:metadata:rule_result:compliance'] = 'El porcentaje de cumplimiento de la regla.';
$string['privacy:metadata:rule_result:courseauditid'] = 'La ejecución de auditoría de curso padre.';
$string['privacy:metadata:rule_result:ruleconfigid'] = 'La regla configurada que se evaluó.';
$string['privacy:metadata:rule_result:status'] = 'El estado del resultado de la regla.';
$string['privacy:metadata:rule_result:timecreated'] = 'La hora en que se registró el resultado de la regla.';
$string['privacy:metadata:rule_result:userid'] = 'El usuario que ejecutó la evaluación de la regla (0 para tareas programadas).';
$string['privacy:metadata:snapshots'] = 'Archivos institucionales de instantáneas de resultados de auditoría, incluyendo campos de identidad de los contactos del curso. Se almacenan a nivel de sitio por tipo de auditoría, no por usuario.';
$string['reevaluate'] = 'Reevaluar';
$string['reevaluateall'] = 'Reevaluar todo';
$string['retentiondays'] = 'Retención del historial (días)';
$string['retentiondays_desc'] = 'Elimina el historial de auditoría anterior a este número de días. Use 0 para conservarlo hasta que aplique el límite de volumen.';
$string['rule_cm_content_contains'] = 'El contenido de la actividad contiene texto';
$string['rule_cm_content_contains_desc'] = 'Comprueba que las actividades de un módulo dado con un número ID dado contengan una cadena literal o una expresión regular en un campo de la tabla del módulo, después de la conversión de formato de Moodle (y de los filtros de texto, salvo que se desactiven).';
$string['rule_cm_content_excludes'] = 'El contenido de la actividad no contiene texto';
$string['rule_cm_content_excludes_desc'] = 'Comprueba que las actividades de un módulo dado con un número ID dado no contengan una cadena literal o una expresión regular en un campo de la tabla del módulo, después de la conversión de formato de Moodle (y de los filtros de texto, salvo que se desactiven).';
$string['rule_cm_html_selector'] = 'El HTML de la actividad contiene una clase CSS';
$string['rule_cm_html_selector_desc'] = 'Analiza el HTML con formato de un campo de la tabla del módulo y comprueba que un elemento con la clase CSS indicada contenga una cadena literal o una expresión regular.';
$string['rule_forum_coursecontact'] = 'Foro iniciado por un contacto del curso';
$string['rule_forum_coursecontact_desc'] = 'Comprueba que un foro general con el número ID dado tenga al menos un debate iniciado por un usuario con un rol de contacto del curso en ese curso.';
$string['rule_grade_category_moditems'] = 'La categoría de calificación contiene ítems de actividad';
$string['rule_grade_category_moditems_desc'] = 'Comprueba que una categoría de calificación identificada por número ID contenga al menos un ítem de calificación que pertenezca a una actividad.';
$string['rule_grade_category_weights'] = 'Los pesos de la categoría de calificación suman 100%';
$string['rule_grade_category_weights_desc'] = 'Si la categoría de calificación usa agregación por media ponderada, los pesos de sus hijos que no son crédito extra deben sumar 100% dentro de la tolerancia configurada.';
$string['rule_section_activity_dates'] = 'Las actividades de la sección tienen fechas de inicio y fin';
$string['rule_section_activity_dates_desc'] = 'Comprueba que cada actividad en las secciones del curso (incluida la sección general) que tiene un par nativo de fechas de inicio y fin tenga ambas fechas definidas. Las actividades con una sola fecha se ignoran. Las secciones ocultas se omiten salvo que se incluyan.';
$string['rule_section_date_label'] = 'El resumen de la sección contiene una etiqueta con fecha';
$string['rule_section_date_label_desc'] = 'Comprueba que el resumen de cada sección del curso (incluida la sección general) contenga una etiqueta literal (sin distinguir mayúsculas) seguida de una fecha que PHP strtotime() pueda interpretar. Las secciones ocultas se omiten salvo que se incluyan.';
$string['ruleactivitydates_fail'] = 'La actividad no tiene ambas fechas de inicio y fin.';
$string['ruleactivitydates_fail_list'] = 'Las siguientes actividades no tienen alguna de las fechas de inicio o fin:';
$string['ruleactivitydates_na'] = 'Solo hay una fecha nativa definida, por lo que esta actividad no se incluye en la puntuación.';
$string['ruleactivitydates_pass'] = 'La actividad tiene ambas fechas de inicio y fin.';
$string['ruleclass'] = 'Clase de regla';
$string['ruleclass_help'] = 'La implementación de la regla de validación. Tras elegir una clase, cargue sus parámetros para configurarlos.';
$string['rulecontentcontains_fail'] = 'El campo no contiene el texto esperado.';
$string['rulecontentcontains_fail_list'] = 'Las siguientes actividades no contienen el texto esperado:';
$string['rulecontentcontains_pass'] = 'El campo contiene el texto esperado.';
$string['rulecontentexcludes_fail'] = 'El campo contiene el texto excluido.';
$string['rulecontentexcludes_fail_list'] = 'Las siguientes actividades contienen el texto excluido:';
$string['rulecontentexcludes_pass'] = 'El campo no contiene el texto excluido.';
$string['rulecreated'] = 'Regla creada';
$string['rulecssclass'] = 'Clase CSS';
$string['rulecssclass_help'] = 'Nombre de la clase a buscar (con o sin el punto inicial), por ejemplo quality-note.';
$string['ruledatelabel'] = 'Etiqueta de fecha';
$string['ruledatelabel_help'] = 'Texto literal que debe aparecer en el resumen de la sección inmediatamente antes de una fecha interpretable, por ejemplo Inicio:';
$string['ruledeleted'] = 'Regla eliminada';
$string['ruleevaluationerror'] = 'No se pudo evaluar la regla.';
$string['ruleexcludesections'] = 'Secciones excluidas';
$string['ruleexcludesections_help'] = 'Números de sección separados por comas que se omitirán, incluido 0 para la sección general.';
$string['ruleexportfile'] = 'Archivo de exportación de reglas';
$string['rulefailingactivities_list'] = 'Las siguientes actividades no cumplen esta comprobación:';
$string['rulefailingsections_list'] = 'Las siguientes secciones no cumplen esta comprobación:';
$string['rulefield'] = 'Campo de base de datos';
$string['rulefield_help'] = 'Columna de la tabla de instancia del módulo a inspeccionar, por ejemplo intro o content.';
$string['ruleforumdiscussion_fail'] = 'Ningún debate fue iniciado por un contacto del curso.';
$string['ruleforumdiscussion_fail_list'] = 'Los siguientes foros no tienen un debate iniciado por un contacto del curso:';
$string['ruleforumdiscussion_pass'] = 'Un contacto del curso inició al menos un debate.';
$string['ruleforumtypefail'] = 'El foro existe pero no es un foro general.';
$string['rulegradecategorymissing'] = 'No se encontró ninguna categoría de calificación con número ID "{$a}".';
$string['rulegradecategorymoditems_fail'] = 'La categoría de calificación "{$a}" no contiene ítems de calificación de actividad.';
$string['rulegradecategorymoditems_pass'] = 'La categoría de calificación "{$a}" contiene al menos un ítem de calificación de actividad.';
$string['rulegradeidnumber'] = 'Número ID de la categoría de calificación';
$string['rulegradeidnumber_help'] = 'Número ID de la categoría de calificación (almacenado en el ítem de calificación de la categoría).';
$string['rulegradeweights_fail'] = 'Los pesos de la categoría "{$a->idnumber}" suman {$a->sum}%, lo que está fuera de la tolerancia aceptada.';
$string['rulegradeweights_na'] = 'La categoría de calificación "{$a}" no usa agregación por media ponderada.';
$string['rulegradeweights_pass'] = 'Los pesos de la categoría "{$a}" suman 100% dentro de la tolerancia aceptada.';
$string['rulehtmlinvalid'] = 'El HTML del campo no es válido.';
$string['rulehtmlselector_fail_noclass'] = 'No se encontró ningún elemento con la clase "{$a}".';
$string['rulehtmlselector_fail_noclass_list'] = 'Las siguientes actividades no tienen ningún elemento con la clase "{$a}":';
$string['rulehtmlselector_fail_notext'] = 'Los elementos con la clase "{$a}" no contienen el texto esperado.';
$string['rulehtmlselector_fail_notext_list'] = 'Las siguientes actividades no contienen el texto esperado en un elemento con la clase "{$a}":';
$string['rulehtmlselector_pass'] = 'Un elemento con la clase "{$a}" contiene el texto esperado.';
$string['ruleidnumber'] = 'Número ID';
$string['ruleidnumber_help'] = 'Número ID del módulo de curso usado para encontrar las actividades a evaluar.';
$string['ruleincludehiddensections'] = 'Incluir secciones ocultas';
$string['ruleincludehiddensections_help'] = 'Si se activa, también se evalúan las secciones con la visibilidad del curso desactivada (visible = 0). No se tienen en cuenta las restricciones de disponibilidad.';
$string['ruleinvalidmodule'] = 'El módulo "{$a}" no está disponible.';
$string['ruleinvalidregex'] = 'La expresión regular no es válida.';
$string['rulematchmode'] = 'Modo de coincidencia';
$string['rulematchmode_help'] = 'El modo literal busca el texto en la versión de texto plano del campo (tras la conversión de formato y, salvo que se desactiven, tras los filtros de texto), sin distinguir mayúsculas. El modo de expresión regular usa una regex de PHP sin delimitadores.';
$string['rulematchmodeliteral'] = 'Texto literal';
$string['rulematchmoderegex'] = 'Expresión regular';
$string['rulemissingfield'] = 'El campo "{$a->field}" no existe en el módulo {$a->modname}.';
$string['rulemissinginstance'] = 'Falta la instancia de la actividad.';
$string['rulemissingparams'] = 'A la regla le faltan parámetros obligatorios.';
$string['rulemodname'] = 'Módulo';
$string['rulemodname_help'] = 'El tipo de actividad a buscar, por ejemplo Página o Etiqueta.';
$string['rulename'] = 'Nombre';
$string['rulenomatches'] = 'No se encontró ninguna actividad {$a->modname} con número ID "{$a->idnumber}".';
$string['ruleparameters'] = 'Parámetros de la regla';
$string['rulepattern'] = 'Texto o patrón';
$string['rulepattern_help'] = 'Texto literal o expresión regular (sin delimitadores) a buscar en el campo tras la conversión de formato (y tras los filtros de texto, salvo que se desactiven).';
$string['rules'] = 'Reglas';
$string['rulesectiondatelabel_fail_nodate'] = 'La etiqueta "{$a}" no va seguida de una fecha válida.';
$string['rulesectiondatelabel_fail_nodate_list'] = 'Las siguientes secciones no tienen una fecha válida después de la etiqueta "{$a}".';
$string['rulesectiondatelabel_fail_nolabel'] = 'El resumen de la sección no contiene la etiqueta "{$a}".';
$string['rulesectiondatelabel_fail_nolabel_list'] = 'Las siguientes secciones no contienen la etiqueta "{$a}".';
$string['rulesectiondatelabel_pass'] = 'El resumen de la sección contiene la etiqueta "{$a}" seguida de una fecha válida.';
$string['rulesfor'] = 'Reglas de {$a}';
$string['ruleskipfilters'] = 'Cumplir sin aplicar filtros';
$string['ruleskipfilters_help'] = 'Si se activa, no se aplican los filtros de texto de Moodle (por ejemplo multilang) antes de buscar. La comprobación usa el contenido almacenado tras la conversión de formato únicamente.';
$string['ruleupdated'] = 'Regla actualizada';
$string['ruleweighting'] = 'Ponderación';
$string['ruleweighting_help'] = 'Las reglas obligatorias se incluyen en el porcentaje de cumplimiento. Las reglas opcionales se evalúan e informan, pero no afectan la puntuación global.';
$string['scheduledtaskuser'] = 'Tarea programada';
$string['selectcategories'] = 'Déjelo vacío para todas las categorías';
$string['selectrules'] = 'Reglas a exportar';
$string['skipcomplete'] = 'Omitir cursos con cumplimiento completo';
$string['skipcomplete_desc'] = 'Si se activa, las auditorías programadas omiten un tipo de auditoría de un curso cuando la última ejecución ya tiene 100% de cumplimiento.';
$string['skipunchangeddays'] = 'Omitir cursos sin cambios (días)';
$string['skipunchangeddays_desc'] = 'Omite cursos que no se han modificado y cuya última auditoría fue hace al menos este número de días. Use 0 para desactivar esta omisión.';
$string['snapshotcolauditdate'] = 'Fecha de la auditoría';
$string['snapshotcolcategorypath'] = 'Árbol de categorías';
$string['snapshotcolcontactemail'] = 'Email del contacto';
$string['snapshotcolcontactfirstname'] = 'Nombre del contacto';
$string['snapshotcolcontactid'] = 'ID del contacto';
$string['snapshotcolcontactidnumber'] = 'ID number del contacto';
$string['snapshotcolcontactlastname'] = 'Apellido del contacto';
$string['snapshotcolcontactusername'] = 'Usuario del contacto';
$string['snapshotcolcoursecategory'] = 'Categoría del curso';
$string['snapshotcolcoursefullname'] = 'Nombre completo del curso';
$string['snapshotcolcourseid'] = 'ID del curso';
$string['snapshotcolcourseidnumber'] = 'ID number del curso';
$string['snapshotcolcourseshortname'] = 'Nombre corto del curso';
$string['snapshotcreated'] = 'Se generó la instantánea.';
$string['snapshotdataformat'] = 'Formato de archivo';
$string['snapshotnotice'] = 'Está viendo una instantánea histórica. La reevaluación está desactivada.';
$string['snapshots'] = 'Instantáneas';
$string['snapshotsfor'] = 'Instantáneas de {$a}';
$string['statuserror'] = 'Error';
$string['statusfail'] = 'No cumple';
$string['statusna'] = 'No aplicable';
$string['statuspass'] = 'Cumple';
$string['stubrule'] = 'Regla de prueba';
$string['stubruleexpected'] = 'Esperado';
$string['stubrulefailed'] = 'Falló';
$string['stubrulepassed'] = 'Superó';
$string['taskauditcourses'] = 'Ejecutar auditorías programadas de cursos';
$string['taskauditcoursessummary'] = 'Auditorías programadas: {$a->ran} ejecutadas, {$a->skipped} omitidas, {$a->errors} errores.';
$string['taskcleanuphistory'] = 'Limpiar el historial de auditoría de ToDo bien';
$string['taskcleanuphistorysummary'] = 'Se eliminaron {$a} informe(s) histórico(s) de auditoría.';
$string['unknowncategory'] = 'Categoría desconocida ({$a})';
$string['viewcoursereport'] = 'Ver informe del curso';
$string['viewdetail'] = 'Ver detalle';
$string['viewsnapshot'] = 'Ver instantánea';
$string['weighttolerance'] = 'Tolerancia de pesos de calificación';
$string['weighttolerance_desc'] = 'Margen decimal aceptado al comprobar que los ítems de calificación ponderados suman 100%.';
