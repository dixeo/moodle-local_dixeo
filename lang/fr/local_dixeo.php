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

$string['account_frozen_warning'] = 'Votre compte est gelé en raison d\'un solde de crédits insuffisant. Veuillez ajouter des crédits pour continuer à utiliser les fonctionnalités Dixeo AI.';
$string['account_suspended_warning'] = 'Votre compte a été suspendu. Veuillez contacter le support Dixeo pour assistance.';
$string['amount'] = 'Montant';
$string['api_configuration'] = 'Configuration de l\'API';
$string['api_configuration_desc'] = 'Configurer la connexion à l\'API Dixeo AI.';
$string['api_error'] = 'Erreur API : {$a}';
$string['api_key'] = 'Clé API';
$string['api_key_desc'] = 'Votre clé API Dixeo. Obtenez-en une depuis le tableau de bord Dixeo.';
$string['api_key_not_configured'] = 'La clé API Dixeo n\'est pas configurée. Veuillez la configurer dans les paramètres du plugin.';
$string['api_url'] = 'URL de l\'API';
$string['api_url_desc'] = 'URL de base de l\'API Dixeo. Doit utiliser HTTPS (par défaut : https://api.dixeo.com).';
$string['average_per_period'] = 'Moyenne par {$a}';
$string['cachedef_coursetemplates'] = 'Modèles de structure de cours mis en cache depuis l\'API Dixeo';
$string['cachedef_installedplugintypes'] = 'Types de plugins d\'activité installés mis en cache pour la génération Dixeo';
$string['cachedef_moduletypes'] = 'Types de modules mis en cache depuis l\'API Dixeo';
$string['configure_api'] = 'Configurer l\'API';
$string['consent_close'] = 'Fermer';
$string['consent_continue'] = 'Continuer';
$string['consent_error_terms_required'] = 'Cochez les deux cases avant de continuer.';
$string['consent_error_version'] = 'Ces conditions ont été mises à jour. Lisez la version actuelle, puis continuez.';
$string['consent_exit'] = 'Quitter';
$string['consent_manage'] = 'Conditions et confidentialité Dixeo';
$string['consent_manage_intro'] = 'Relisez les conditions d\'utilisation et la notice de confidentialité, ou révoquez votre acceptation.';
$string['consent_privacy_body'] = 'Cette notice explique comment vos données personnelles sont utilisées lorsque Dixeo est activé, conformément aux articles 13 et 14 du RGPD. La lire ne constitue pas, à elle seule, un consentement.

Qui est responsable. {$a->sitename}, l\'organisation qui exploite ce site, est le responsable du traitement : elle décide pourquoi et comment vos données personnelles sont utilisées. Contact : {$a->contact}. Dixeo, qui fournit les plugins et les services d\'IA, est le sous-traitant. Ce site envoie des données à l\'API Dixeo à l\'adresse {$a->apiurl} afin que Dixeo puisse fournir les fonctions décrites dans les conditions d\'utilisation. Les copies distantes sont conservées selon le contrat entre {$a->sitename} et Dixeo. Ce site ne peut pas supprimer ces copies distantes par les outils de confidentialité de Moodle. Pour toute question sur les services de Dixeo, contactez contact@dixeo.com.

Données que ce site stocke ou envoie, telles que déclarées à l\'API de confidentialité de Moodle :

- Votre identifiant d\'utilisateur Moodle, envoyé avec les demandes que vous lancez
- Les messages que vous écrivez au tuteur, les instructions et le texte visible de la page, afin que le tuteur puisse répondre dans le contexte de la page
- Les titres et résumés de cours, de sections et d\'activités ; les invites ; les images sources ; et les fichiers du cours, y compris le texte extrait des paquets SCORM, téléversés pour la génération, la modification d\'images ou la synchronisation des fichiers
- Les noms, descriptions et définitions de structure des modèles de cours stockés sur l\'API Dixeo
- Les identifiants de travaux, les types d\'opération, les montants de crédits, et le cours ou l\'activité auquel chaque travail est rattaché
- Les invites d\'image et l\'état des travaux. Les enregistrements locaux de travaux d\'image sont supprimés 7 jours après la fin du travail
- La langue que vous choisissez pour les tests et les leçons générés par le tuteur
- L\'identifiant d\'utilisateur et l\'heure enregistrés lorsqu\'une personne active ou désactive la synchronisation des fichiers d\'un cours

Base juridique. Le tutorat, la génération, les images, la synchronisation des fichiers, le suivi des crédits et les analyses d\'utilisation du tuteur sont nécessaires à l\'exécution des conditions d\'utilisation (article 6, paragraphe 1, point b). La conservation des enregistrements opérationnels pour la sécurité, l\'assistance et le suivi des crédits relève de l\'intérêt légitime de {$a->sitename} (article 6, paragraphe 1, point f).

Destinataires. Vos données sont partagées avec l\'API Dixeo et, pour les rapports de crédits, avec le personnel que ce site a autorisé. Les rapports d\'utilisation du tuteur ne sont visibles que par le personnel autorisé.

Transferts internationaux. Les données sont envoyées à l\'API Dixeo indiquée ci-dessus. Tout transfert hors de votre pays, et les garanties qui s\'y appliquent, sont prévus dans le contrat entre {$a->sitename} et Dixeo. Ce site ne peut ni voir ni décrire le mécanisme de transfert lui-même.

Conservation. Les enregistrements locaux de travaux d\'image sont conservés 7 jours après la fin du travail. Les autres enregistrements locaux sont conservés tant que votre compte existe et sont retirés lorsque vos données personnelles sont supprimées dans Moodle. La conservation distante suit le contrat entre {$a->sitename} et Dixeo.

Vos droits. Vous pouvez demander à {$a->sitename} l\'accès, la rectification, l\'effacement, la limitation ou une copie portable de vos données, et vous pouvez vous opposer à un traitement fondé sur l\'intérêt légitime. Vous pouvez aussi introduire une réclamation auprès d\'une autorité de contrôle. Si ce site dispose des outils de confidentialité de Moodle, utilisez-les pour contacter le délégué à la protection des données.

Acceptation des conditions. Vous acceptez les conditions d\'utilisation pour ouvrir un cours où Dixeo est activé. Les analyses d\'utilisation du tuteur seront enregistrées. Si vous n\'acceptez pas, choisissez Quitter.

Usage de l\'IA. Dixeo utilise l\'IA pour rédiger les réponses du tuteur, le contenu du cours et les images. Lorsqu\'un enseignant l\'active, elle produit aussi des suggestions de relecture ou de notation de devoirs pour les personnes du cours. Toute note que {$a->sitename} considère comme définitive est contrôlée par l\'enseignant et par les règles d\'évaluation propres à ce site.

Vos données ne sont pas utilisées à des fins de prospection.';
$string['consent_privacy_contact_admin'] = 'l\'administrateur du site';
$string['consent_privacy_dataprotection'] = 'Ouvrir vos demandes de confidentialité';
$string['consent_privacy_heading'] = 'Notice de confidentialité';
$string['consent_processing_body'] = 'Dixeo enregistre des analyses d\'utilisation du tuteur : le nombre de messages, de tests d\'entraînement et de leçons que vous créez, le mode du tuteur, le cours, l\'activité et l\'heure. Le texte de vos messages n\'est pas stocké dans ces enregistrements. Le personnel autorisé peut consulter ces chiffres dans les rapports d\'utilisation du tuteur.';
$string['consent_processing_checkbox'] = 'Mon utilisation du tuteur (nombre de messages, modes, cours, activités et horodatages, sans le texte des messages) sera enregistrée pour des analyses et des rapports d\'utilisation.';
$string['consent_processing_heading'] = 'Analyses d\'utilisation';
$string['consent_review'] = 'Consulter';
$string['consent_save_processing'] = 'Enregistrer';
$string['consent_saved_processing'] = 'Votre choix d\'analyse a été enregistré.';
$string['consent_status_accepted'] = 'Vous avez accepté les conditions d\'utilisation (version {$a->version}) le {$a->time}. Les analyses d\'utilisation du tuteur seront enregistrées (version {$a->processingversion}) depuis le {$a->processingtime}.';
$string['consent_status_processing_off'] = 'Les analyses d\'utilisation du tuteur ne sont pas enregistrées.';
$string['consent_status_processing_on'] = 'Les analyses d\'utilisation du tuteur seront enregistrées (version {$a->version}) depuis le {$a->time}.';
$string['consent_status_terms_accepted'] = 'Vous avez accepté les conditions d\'utilisation (version {$a->version}) le {$a->time}.';
$string['consent_status_terms_none'] = 'Vous n\'avez pas accepté les conditions d\'utilisation actuelles. Un cours où Dixeo est activé vous le demandera avant que vous puissiez continuer.';
$string['consent_terms_body'] = 'Ces conditions sont un contrat entre vous et {$a->sitename}, l\'organisation qui exploite ce site. Elles s\'appliquent chaque fois que vous utilisez Dixeo dans un cours.

Dixeo, fournisseur des plugins et des services d\'IA utilisés sur ce site, propose le tutorat par IA, la génération de contenu, la génération d\'images, la synchronisation des fichiers de cours et le suivi des crédits dans les cours où il est activé. En continuant, vous acceptez d\'utiliser ces fonctions selon ces conditions.

Vous ne devez pas soumettre de contenu illicite ni de contenu que vous n\'avez pas le droit de partager. Le résultat de l\'IA peut être incomplet ou inexact. Vous êtes responsable de l\'usage que vous en faites, y compris des travaux que vous remettez pour le cours.

Dixeo peut être indisponible si le service est suspendu ou si les crédits sont épuisés. La synchronisation des fichiers et les travaux d\'IA dépendent de la disponibilité du service Dixeo.

Si vous n\'acceptez pas, choisissez Quitter. Vous ne pourrez pas entrer dans ce cours tant que Dixeo est activé. Vous pouvez retirer votre acceptation à tout moment dans la configuration Dixeo, sous Mes préférences, et le cours vous la demandera de nouveau. Le retrait ne supprime pas les travaux de cours que vous avez déjà créés.

Version du document {$a->version}.';
$string['consent_terms_checkbox'] = 'J\'ai lu et j\'accepte les conditions d\'utilisation et je prends connaissance de la notice de confidentialité.';
$string['consent_terms_heading'] = 'Conditions d\'utilisation';
$string['consent_title'] = 'Conditions d\'utilisation et notice de confidentialité';
$string['consent_withdraw_confirm'] = 'La révocation retire votre acceptation des conditions d\'utilisation. Les analyses d\'utilisation du tuteur déjà enregistrées sont supprimées. Un cours où Dixeo est activé vous les demandera de nouveau.';
$string['consent_withdraw_confirm_title'] = 'Révoquer l\'acceptation ?';
$string['consent_withdraw_terms'] = 'Révoquer';
$string['consent_withdrawn_terms'] = 'Votre acceptation des conditions d\'utilisation a été retirée. Un cours Dixeo vous la demandera de nouveau.';
$string['content_image_failed'] = 'Une erreur s\'est produite';
$string['content_image_generating'] = 'Génération...';
$string['contentimagetitlefallback'] = 'Image de contenu';
$string['credit_action_course_structure'] = 'Structure du cours';
$string['credit_action_edit_module'] = 'Modifier le module';
$string['credit_action_fill_module'] = 'Remplir le module';
$string['credit_action_generate_module'] = 'Générer le module';
$string['credit_action_image_edit'] = 'Modifier l\'image';
$string['credit_action_image_generate'] = 'Générer une image';
$string['credit_action_module_edit'] = 'Modifier le module';
$string['credit_action_module_fill'] = 'Remplir le module';
$string['credit_action_module_generate'] = 'Générer le module';
$string['credit_action_tutor'] = 'Message tuteur';
$string['credit_action_tutor_message'] = 'Message tuteur';
$string['credit_action_tutor_synthesis'] = 'Synthèse des conversations des étudiants';
$string['credit_action_unknown'] = 'Action inconnue';
$string['credit_balance'] = 'Solde de crédits';
$string['credit_component_block_dixeo_designer'] = 'Concepteur de cours';
$string['credit_component_block_dixeo_modulegen'] = 'Générateur de modules';
$string['credit_component_block_dixeo_tutor'] = 'Tuteur étudiant';
$string['credit_component_filter_dixeo_imageeditor'] = 'Éditeur d\'images';
$string['credit_component_local_dixeo'] = 'Noyau Dixeo';
$string['credit_component_local_dixeo_editor'] = 'Éditeur de contenu';
$string['credit_component_unknown'] = 'Inconnu';
$string['credit_context_site'] = 'Site';
$string['credit_information'] = 'Informations sur les crédits';
$string['credit_moduletype_glossary'] = 'Glossaire';
$string['credit_moduletype_h5pactivity'] = 'Activité H5P';
$string['credit_moduletype_label'] = 'Zone texte';
$string['credit_moduletype_page'] = 'Page';
$string['credit_moduletype_quiz'] = 'Test';
$string['credit_moduletype_simplequiz2'] = 'Quiz simple';
$string['credit_moduletype_slideshow'] = 'Diaporama';
$string['credit_report_apply_filters'] = 'Appliquer les filtres';
$string['credit_report_breakdown'] = 'Crédits par module';
$string['credit_report_column_action'] = 'Action';
$string['credit_report_column_course'] = 'Cours : Activité';
$string['credit_report_column_credits'] = 'Crédits';
$string['credit_report_column_date'] = 'Date';
$string['credit_report_column_module'] = 'Composant';
$string['credit_report_column_user'] = 'Utilisateur';
$string['credit_report_filter_action'] = 'Action';
$string['credit_report_filter_component'] = 'Composant';
$string['credit_report_filter_course'] = 'Cours';
$string['credit_report_filter_course_placeholder'] = 'Rechercher des cours…';
$string['credit_report_filter_moduletype'] = 'Type d\'activité';
$string['credit_report_filter_placeholder'] = 'Rechercher ou sélectionner…';
$string['credit_report_filter_user'] = 'Utilisateur';
$string['credit_report_filter_user_placeholder'] = 'Rechercher des utilisateurs…';
$string['credit_report_filters'] = 'Filtres';
$string['credit_report_histogram'] = 'Crédits dans le temps';
$string['credit_report_kpi_courses'] = 'Total des cours';
$string['credit_report_kpi_credits'] = 'Total des crédits';
$string['credit_report_kpi_rows'] = 'Total des transactions';
$string['credit_report_kpi_users'] = 'Total des utilisateurs';
$string['credit_report_link_desc'] = 'Nécessite la capacité local/dixeo:manage ou local/dixeo:viewusage au niveau du site. Le rapport et l\'export incluent les noms d\'utilisateurs et de cours sur tout le site.';
$string['credit_report_next_period'] = 'Période suivante';
$string['credit_report_no_rows'] = 'Aucun enregistrement d\'utilisation de crédits pour la période et les filtres sélectionnés.';
$string['credit_report_period'] = 'Période';
$string['credit_report_prev_period'] = 'Période précédente';
$string['credit_report_range_too_long'] = 'Les plages de dates personnalisées ne peuvent pas dépasser un an.';
$string['credit_report_reset_filters'] = 'Réinitialiser';
$string['credit_report_summary'] = 'Résumé';
$string['credit_report_view_custom'] = 'Plage personnalisée';
$string['credit_report_view_month'] = 'Mois';
$string['credit_report_view_week'] = 'Semaine';
$string['credit_usage_report_nav'] = 'Utilisation des crédits Dixeo';
$string['credit_user_unknown'] = 'Inconnu';
$string['credits'] = 'crédits';
$string['current_balance'] = 'Solde actuel';
$string['current_balance_desc'] = 'Votre solde de crédits Dixeo actuel. Les crédits sont utilisés pour les opérations IA.';
$string['data_points'] = 'Points de données';
$string['date'] = 'Date';
$string['day_fri'] = 'Ven';
$string['day_mon'] = 'Lun';
$string['day_sat'] = 'Sam';
$string['day_sun'] = 'Dim';
$string['day_thu'] = 'Jeu';
$string['day_tue'] = 'Mar';
$string['day_wed'] = 'Mer';
$string['description'] = 'Description';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Section {$a->section}, activité {$a->module} :';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Section {$a->section} :';
$string['designerstructurevalidate_course_summary_too_long'] = 'Le résumé du cours est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_course_title_required'] = 'Le titre du cours est un champ obligatoire.';
$string['designerstructurevalidate_course_title_too_long'] = 'Le titre du cours doit comporter au maximum {$a->max} caractères.';
$string['designerstructurevalidate_fill_instructions_too_long'] = 'Les consignes envoyées à l\'IA sont trop longues (maximum {$a->max} caractères).';
$string['designerstructurevalidate_instructions_api_min'] = 'Les consignes doivent comporter au moins {$a->min} caractères.';
$string['designerstructurevalidate_invalid_root'] = 'Les données de structure du cours sont invalides.';
$string['designerstructurevalidate_module_instructions_required'] = 'Les consignes pour l\'IA sont obligatoires (au moins {$a->min} caractères).';
$string['designerstructurevalidate_module_instructions_too_long'] = 'Les consignes sont trop longues (maximum {$a->max} caractères).';
$string['designerstructurevalidate_module_invalid'] = 'Le module à la position {$a->module} dans la section {$a->section} est invalide.';
$string['designerstructurevalidate_module_summary_placeholder'] = 'Remplacez le résumé par défaut par une vraie description de ce que couvre cette activité.';
$string['designerstructurevalidate_module_summary_too_long'] = 'Le résumé de l\'activité est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_module_title_placeholder'] = 'Remplacez le titre par défaut « Nouvelle page » par un vrai nom d\'activité.';
$string['designerstructurevalidate_module_title_required'] = 'Le titre de l\'activité est un champ obligatoire.';
$string['designerstructurevalidate_module_title_too_long'] = 'Le titre de l\'activité est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_module_type_not_usable'] = 'Le type « {$a->type} » ne peut pas être utilisé sur ce site (plugin manquant ou bibliothèque de contenu requise).';
$string['designerstructurevalidate_module_type_required'] = 'Le type d\'activité est un champ obligatoire.';
$string['designerstructurevalidate_modules_not_array'] = 'La liste des modules de la section {$a} est invalide.';
$string['designerstructurevalidate_section_invalid'] = 'La section {$a} de la structure est invalide.';
$string['designerstructurevalidate_section_summary_too_long'] = 'Le résumé de la section est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_section_title_too_long'] = 'Le titre de la section est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_sections_not_array'] = 'La liste des sections de la structure du cours est invalide.';
$string['disabled_block_course'] = 'Ce bloc n\'est pas disponible, car Dixeo a été désactivé pour ce cours.';
$string['disabled_block_site'] = 'Ce bloc n\'est pas disponible, car Dixeo a été désactivé. Vous pouvez demander à un administrateur de l\'activer.';
$string['dixeo:contentimageedit'] = 'Modifier des images de contenu intégrées avec l\'IA';
$string['dixeo:contentimagegenerate'] = 'Générer des images de contenu intégrées avec l\'IA';
$string['dixeo:create'] = 'Créer des cours avec le Concepteur de Cours';
$string['dixeo:edit'] = 'Modifier les modules existants avec l\'IA';
$string['dixeo:generate'] = 'Générer de nouveaux modules avec l\'IA (page, étiquette, test, glossaire)';
$string['dixeo:manage'] = 'Gérer les paramètres Dixeo et consulter les rapports';
$string['dixeo:syncfiles'] = 'Activer, désactiver ou déclencher la synchronisation des fichiers de cours Dixeo vers l\'API externe';
$string['dixeo:viewtutorusage'] = 'Voir le rapport d\'usage du tuteur dans les cours';
$string['dixeo:viewtutorusagesite'] = 'Voir le rapport d\'usage du tuteur sur tout le site';
$string['dixeo:viewusage'] = 'Consulter les rapports d\'utilisation des crédits sur tout le site et exporter les données';
$string['dixeo_course_image_unsupported_type'] = 'Type d\'image générée non pris en charge.';
$string['dixeo_image_generation_disabled'] = 'La génération d\'images est désactivée par les paramètres du site.';
$string['dixeo_image_job_empty_result'] = 'La tâche d\'image n\'a renvoyé aucune donnée d\'image.';
$string['dixeo_image_job_failed'] = 'Échec de la génération de l\'image. Veuillez réessayer.';
$string['dixeo_image_job_locked'] = 'Une tâche d\'image est déjà en cours pour cette image.';
$string['dixeo_image_not_eligible'] = 'Cette image ne peut pas être modifiée.';
$string['dixeo_pluginfile_not_found'] = 'Impossible de lire le fichier image depuis le stockage.';
$string['dsl_error'] = 'Échec de la création du module : {$a}';
$string['editorimageorphaned'] = 'Image removed from editor content before completion';
$string['enabled'] = 'Activer Dixeo';
$string['enabled_course'] = 'Activer Dixeo';
$string['enabled_course_help'] = 'Activer ou désactiver les fonctions Dixeo dans ce cours.';
$string['enabled_help'] = 'Activer ou désactiver les fonctions Dixeo globalement.';
$string['error:activity_not_available'] = 'Le type d\'activité {$a} n\'est pas installé ou n\'est pas activé.';
$string['error:api_url_https_required'] = 'L\'URL de l\'API Dixeo doit être une adresse HTTPS absolue (par exemple https://api.dixeo.com).';
$string['error:authentication'] = 'Échec de l\'authentification. Veuillez vérifier votre clé API.';
$string['error:dixeo_disabled'] = 'Dixeo est désactivé.';
$string['error:job_not_found'] = 'Le travail demandé n\'a pas été trouvé.';
$string['error:notslideshow'] = 'Le module de cours n\'est pas une activité diaporama.';
$string['error:payment_required'] = 'Crédits insuffisants. Veuillez ajouter des crédits pour continuer.';
$string['error:rate_limit'] = 'Limite de requêtes dépassée. Veuillez patienter avant d\'effectuer d\'autres requêtes.';
$string['error:slidenotinslideshow'] = 'La diapositive demandée n\'appartient pas à ce diaporama.';
$string['error:tutor_synthesis_busy'] = 'Une synthèse est déjà en cours de demande. Réessayez dans un instant.';
$string['error:tutor_synthesis_unavailable'] = 'Ce service n\'est pas disponible. Contactez votre administrateur.';
$string['error:unexpected'] = 'Une erreur est survenue. Veuillez réessayer ou demander à votre administrateur de consulter les journaux du site.';
$string['error:upstream_ai'] = 'Erreur du service IA. Veuillez réessayer plus tard.';
$string['error:validation_failed'] = 'La requête est invalide. Vérifiez les valeurs et réessayez.';
$string['eventcreditreportexported'] = 'Rapport d\'utilisation des crédits exporté';
$string['eventcreditreportexporteddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a exporté le rapport d\'utilisation des crédits (vue={$a->view}, format={$a->dataformat}, lignes={$a->rowcount}).';
$string['eventcreditreportviewed'] = 'Rapport d\'utilisation des crédits consulté';
$string['eventcreditreportvieweddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a consulté le rapport d\'utilisation des crédits (vue={$a->view}, lignes={$a->rowcount}).';
$string['eventfilesyncdisabled'] = 'Synchronisation des fichiers Dixeo du cours désactivée';
$string['eventfilesyncdisableddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a désactivé la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\' (removefiles={$a->removefiles}, remotedelete={$a->remotedeletestatus}).';
$string['eventfilesyncenabled'] = 'Synchronisation des fichiers Dixeo du cours activée';
$string['eventfilesyncenableddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a activé la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\'.';
$string['eventfilesynctriggered'] = 'Synchronisation des fichiers Dixeo du cours déclenchée';
$string['eventfilesynctriggereddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a déclenché la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\'.';
$string['eventjobcancelled'] = 'Tâche Dixeo annulée';
$string['eventjobcancelleddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a annulé la tâche Dixeo \'{$a->jobid}\' pour le cours avec l\'id \'{$a->courseid}\'.';
$string['eventsettingupdated'] = 'Paramètre Dixeo mis à jour';
$string['eventsettingupdateddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a mis à jour le paramètre Dixeo \'{$a->component}/{$a->name}\' dans le contexte avec l\'id \'{$a->contextid}\'.';
$string['eventtutorusagereportexported'] = 'Rapport d\'usage du tuteur exporté';
$string['eventtutorusagereportexporteddesc'] = 'L\'utilisateur {$a->userid} a exporté le rapport d\'usage du tuteur (niveau : {$a->level}, vue : {$a->view}, format : {$a->dataformat}, lignes : {$a->rowcount}).';
$string['eventtutorusagereportviewed'] = 'Rapport d\'usage du tuteur consulté';
$string['eventtutorusagereportvieweddesc'] = 'L\'utilisateur {$a->userid} a consulté le rapport d\'usage du tuteur (niveau : {$a->level}, vue : {$a->view}, lignes : {$a->rowcount}).';
$string['feedback_correct'] = 'Bravo, vous avez trouvé la bonne réponse. Continuez comme ça !';
$string['feedback_incorrect'] = 'Pas tout à fait cette fois. Revoir le sujet vous aidera à progresser.';
$string['feedback_partial'] = 'Vous êtes sur la bonne voie. Révisez le sujet et vous y arriverez.';
$string['file_synchronization'] = 'Synchronisation des fichiers';
$string['files'] = 'fichiers';
$string['filesync_disable_remove'] = 'Désactiver et effacer les données de synchronisation';
$string['filesync_enable'] = 'Activer la synchronisation';
$string['filesync_enabled'] = 'Synchronisation des fichiers';
$string['filesync_enabled_default'] = 'Synchronisation des fichiers pour les nouveaux cours';
$string['filesync_enabled_default_help'] = 'Active la synchronisation des fichiers pour les nouveaux cours (les cours existants ne sont pas affectés). Le Concepteur de Cours doit synchroniser les fichiers pendant la génération du cours ; si ce réglage est désactivé, la synchronisation automatique est mise en pause une fois le cours prêt.';
$string['filesync_enabled_help'] = 'Activez ou mettez en pause la synchronisation des fichiers de ce cours. La pause conserve les fichiers déjà synchronisés.';
$string['filesync_failed'] = 'Échec de la synchronisation des fichiers : {$a}';
$string['filesync_files_count'] = '{$a} fichiers synchronisés';
$string['filesync_pause'] = 'Mettre en pause la synchronisation';
$string['filesync_pending_deletion'] = 'La suppression distante des fichiers de ce cours est encore en attente';
$string['filesync_pill'] = 'Emplacement de l\'indicateur d\'état';
$string['filesync_pill_coursetitle'] = 'Titre du cours';
$string['filesync_pill_footer'] = 'Pied de page';
$string['filesync_pill_help'] = 'Où l\'indicateur d\'état Dixeo apparaît sur la page du cours.';
$string['filesync_pill_hidden'] = 'Masqué';
$string['filesync_progress'] = '{$a} % terminé';
$string['filesync_resync'] = 'Synchroniser maintenant';
$string['filesync_status_disabled'] = 'Synchronisation désactivée';
$string['filesync_status_error'] = 'Erreur de synchronisation';
$string['filesync_status_none'] = 'Aucun fichier synchronisé';
$string['filesync_status_outdated'] = 'Contenu modifié, synchronisation nécessaire';
$string['filesync_status_paused'] = 'Synchronisation en pause';
$string['filesync_status_pending_deletion'] = 'Suppression distante des fichiers en attente';
$string['filesync_status_synchronized'] = 'Fichiers synchronisés';
$string['filesync_status_syncing'] = 'Synchronisation en cours...';
$string['filesync_timeout'] = 'La synchronisation des fichiers a expiré avant l\'indexation des fichiers du cours';
$string['filesync_title'] = 'Synchronisation de fichiers Dixeo';
$string['generation_language'] = 'Langue de génération';
$string['generation_language_desc'] = 'Langue des tests et des leçons générés par le tuteur. Elle correspond d\'abord à votre langue préférée.';
$string['generation_output_language'] = 'LANGUE : Générez tout le contenu destiné aux apprenants (questions, réponses, texte de leçon et titres) en {$a->language}.';
$string['image_generation'] = 'Génération d\'images';
$string['image_generation_content_mode'] = 'Images de contenu';
$string['image_generation_content_mode_desc'] = 'Contrôle les actions d\'image IA pour les images dans le contenu des activités, comme les Pages et les Livres.';
$string['image_generation_content_mode_disabled'] = 'Désactivé';
$string['image_generation_content_mode_generate'] = 'Générer';
$string['image_generation_content_mode_generate_edit'] = 'Générer et modifier';
$string['image_generation_course_mode'] = 'Images de cours';
$string['image_generation_course_mode_desc'] = 'Contrôle les actions d\'image IA pour l\'image d\'aperçu du cours.';
$string['image_generation_course_mode_disabled'] = 'Désactivé';
$string['image_generation_course_mode_generate'] = 'Générer';
$string['image_generation_course_mode_generate_edit'] = 'Générer et modifier';
$string['image_generation_desc'] = 'Contrôle la disponibilité de la génération et de la modification d\'images par IA pour les images de cours et de section.';
$string['image_generation_enabled'] = 'Activer la génération d\'images';
$string['image_generation_enabled_desc'] = 'Si désactivé, toutes les demandes de génération ou de modification d\'images sont bloquées.';
$string['image_generation_mode_disabled'] = 'Désactivé';
$string['image_generation_mode_generate'] = 'Générer';
$string['image_generation_mode_generate_edit'] = 'Générer et modifier';
$string['image_generation_section_mode'] = 'Images de section';
$string['image_generation_section_mode_desc'] = 'Contrôle les actions d\'image IA pour les images de chapitre ou de section.';
$string['image_generation_section_mode_disabled'] = 'Désactivé';
$string['image_generation_section_mode_generate'] = 'Générer';
$string['image_generation_section_mode_generate_edit'] = 'Générer et modifier';
$string['last_sync'] = 'Dernière synchronisation';
$string['learning_intent'] = 'Intention d\'apprentissage';
$string['learning_intent_help'] = 'L\'intention d\'apprentissage définit un ensemble d\'objectifs d\'apprentissage pour le cours.';
$string['namespace'] = 'Espace de noms';
$string['namespace_desc'] = 'Requis uniquement lorsque plusieurs sites Moodle partagent la même clé API. Chaque site doit utiliser un espace de noms différent (ex. « production », « staging », « site1 ») pour garder les données séparées. Laissez « default » si c\'est le seul site utilisant cette clé API.';
$string['overview'] = 'Aperçu Dixeo';
$string['pagination'] = 'Navigation des pages';
$string['period'] = 'Période';
$string['pluginname'] = 'Dixeo AI';
$string['practice_quiz_default_title'] = 'Quiz d\'entraînement';
$string['practice_quiz_difficulty_easy'] = 'facile (rappel de base, concepts simples, adapté aux débutants)';
$string['practice_quiz_difficulty_hard'] = 'difficile (application exigeante, analyse ou synthèse de concepts avancés)';
$string['practice_quiz_difficulty_medium'] = 'moyen (profondeur modérée nécessitant une compréhension au-delà du simple rappel)';
$string['practice_quiz_error_invalid_result'] = 'Résultat du travail non valide.';
$string['practice_quiz_error_job_not_completed'] = 'Le travail n\'est pas terminé. Statut : {$a->status}';
$string['practice_quiz_error_no_questions'] = 'Aucune question dans le résultat du travail.';
$string['practice_quiz_error_wrong_module_type'] = 'Le travail n\'est pas une génération simplequiz2.';
$string['practice_quiz_instructions'] = 'Générez un quiz d\'entraînement pour {$a->scopedescription}.

EXIGENCES OBLIGATOIRES — vous DEVEZ les respecter exactement :
1. NOMBRE DE QUESTIONS : Le tableau « questions » DOIT contenir exactement {$a->count} questions. Ne produisez pas {$a->count} moins un, {$a->count} plus un, ni aucun autre nombre — exactement {$a->count}.
2. NIVEAU DE DIFFICULTÉ : Chaque question DOIT être de difficulté {$a->difficultylabel}.
3. FORMAT : Chaque question DOIT être à choix multiples avec 3 ou 4 options de réponse et exactement une bonne réponse.

Avant de terminer, vérifiez que la longueur du tableau questions est égale à {$a->count} et que toutes les questions correspondent au niveau de difficulté {$a->difficulty}.
Concentrez-vous sur le contexte de cours fourni.';
$string['practice_quiz_scope_activity_description'] = 'l\'activité « {$a->name} »';
$string['practice_quiz_scope_course_description'] = 'l\'ensemble du cours « {$a->name} »';
$string['practice_quiz_scope_section_description'] = 'la section « {$a->name} »';
$string['preferences_delete_conversation'] = 'Supprimer la conversation du tuteur';
$string['preferences_delete_conversation_desc'] = 'Cette action supprime uniquement votre conversation avec le tuteur dans le cours actuel. Les conversations des autres cours restent en place.';
$string['privacy:metadata:consent'] = 'Enregistrements de l\'acceptation des conditions d\'utilisation, avec l\'utilisateur, l\'heure et la notice qui a été affichée.';
$string['privacy:metadata:consent:action'] = 'Indique si l\'utilisateur a accordé ou retiré son acceptation.';
$string['privacy:metadata:consent:documentid'] = 'La notice enregistrée à laquelle cette décision se rapporte.';
$string['privacy:metadata:consent:timecreated'] = 'Moment où l\'utilisateur a accordé ou retiré son choix.';
$string['privacy:metadata:consent:userid'] = 'Utilisateur auquel l\'enregistrement appartient.';
$string['privacy:metadata:course_ai'] = 'Configuration et statut de synchronisation des fichiers IA par cours.';
$string['privacy:metadata:course_ai:courseid'] = 'Le cours auquel appartient cette configuration de synchronisation.';
$string['privacy:metadata:course_ai:disabledat'] = 'L\'heure de désactivation de la synchronisation des fichiers.';
$string['privacy:metadata:course_ai:disabledby'] = 'L\'utilisateur qui a désactivé la synchronisation des fichiers pour le cours.';
$string['privacy:metadata:course_ai:enabled'] = 'Indique si la synchronisation des fichiers est activée pour le cours.';
$string['privacy:metadata:course_ai:enabledat'] = 'L\'heure d\'activation de la synchronisation des fichiers.';
$string['privacy:metadata:course_ai:enabledby'] = 'L\'utilisateur qui a activé la synchronisation des fichiers pour le cours.';
$string['privacy:metadata:course_ai:errormessage'] = 'Le dernier message d\'erreur de synchronisation, le cas échéant.';
$string['privacy:metadata:course_ai:syncstatus'] = 'Le statut actuel de synchronisation.';
$string['privacy:metadata:course_ai:timecreated'] = 'L\'heure de création de l\'enregistrement de synchronisation.';
$string['privacy:metadata:course_ai:timemodified'] = 'L\'heure de dernière modification de l\'enregistrement de synchronisation.';
$string['privacy:metadata:credit_usage'] = 'Enregistrements d\'utilisation de crédits Dixeo synchronisés, enrichis avec le contexte Moodle.';
$string['privacy:metadata:credit_usage:amount'] = 'Le montant signé de la transaction.';
$string['privacy:metadata:credit_usage:cmid'] = 'L\'identifiant du module de cours lorsqu\'il est connu.';
$string['privacy:metadata:credit_usage:component'] = 'Le composant Dixeo d\'origine.';
$string['privacy:metadata:credit_usage:contextid'] = 'Le contexte Moodle lorsqu\'il est connu.';
$string['privacy:metadata:credit_usage:courseid'] = 'Le cours lorsqu\'il est connu.';
$string['privacy:metadata:credit_usage:credits'] = 'Le montant normalisé d\'utilisation des crédits.';
$string['privacy:metadata:credit_usage:description'] = 'La description de la transaction API.';
$string['privacy:metadata:credit_usage:jobid'] = 'L\'identifiant distant de la tâche Dixeo lorsqu\'il est disponible.';
$string['privacy:metadata:credit_usage:jobtype'] = 'Le type de tâche API lorsqu\'il est disponible.';
$string['privacy:metadata:credit_usage:moduletype'] = 'Le type d\'activité Moodle lorsqu\'il est connu.';
$string['privacy:metadata:credit_usage:operation'] = 'Le repli d\'opération locale.';
$string['privacy:metadata:credit_usage:timecreated'] = 'L\'horodatage de la transaction.';
$string['privacy:metadata:credit_usage:timesynced'] = 'La date de dernière synchronisation de la ligne.';
$string['privacy:metadata:credit_usage:transactionid'] = 'L\'identifiant distant de la transaction Dixeo.';
$string['privacy:metadata:credit_usage:type'] = 'Le type de transaction.';
$string['privacy:metadata:credit_usage:userid'] = 'L\'utilisateur initiateur lorsqu\'il est connu.';
$string['privacy:metadata:external:context'] = 'Contexte de cours, section ou module fourni pour la génération ou l\'édition.';
$string['privacy:metadata:external:courseid'] = 'L\'identifiant du cours Moodle associé à la requête.';
$string['privacy:metadata:external:description'] = 'Description lisible d\'un modèle de structure de cours stocké sur l\'API Dixeo.';
$string['privacy:metadata:external:files'] = 'Fichiers de cours, texte SCORM extrait et manifestes de fichiers associés téléversés pour la synchronisation ou le RAG.';
$string['privacy:metadata:external:images'] = 'Images sources (par exemple images de cours ou de section) envoyées lors d\'une demande d\'édition d\'image par IA.';
$string['privacy:metadata:external:instructions'] = 'Instructions ou prompts utilisés pour guider le traitement IA.';
$string['privacy:metadata:external:message'] = 'Messages du tuteur ou de l\'utilisateur soumis au traitement IA.';
$string['privacy:metadata:external:moduletype'] = 'Le type de module d\'activité demandé pour la génération.';
$string['privacy:metadata:external:name'] = 'Nom affiché d\'un modèle de structure de cours stocké sur l\'API Dixeo.';
$string['privacy:metadata:external:namespace'] = 'L\'espace de noms du site utilisé pour séparer les données de cette instance Moodle sur l\'API Dixeo.';
$string['privacy:metadata:external:pagecontext'] = 'Texte ou contexte visible de la page envoyé avec les messages du tuteur pour ancrer la réponse de l\'IA.';
$string['privacy:metadata:external:summary'] = 'Résumé du cours ou de la section utilisé comme entrée pour la génération d\'images.';
$string['privacy:metadata:external:templatedefinition'] = 'Définition structurée d\'un modèle de cours (sections et emplacements d\'activités) envoyée ou stockée sur l\'API Dixeo.';
$string['privacy:metadata:external:templateid'] = 'Identifiant d\'un modèle de structure de cours utilisé lors de la génération d\'un plan de cours.';
$string['privacy:metadata:external:title'] = 'Titre du cours ou de la section utilisé comme entrée pour la génération d\'images.';
$string['privacy:metadata:external:userid'] = 'L\'identifiant de l\'utilisateur Moodle associé à la requête (par exemple les conversations du tuteur).';
$string['privacy:metadata:externalpurpose'] = 'Les données sont envoyées à l\'API Dixeo AI pour la génération de contenu, le tutorat, les images, les rapports de crédits et la synchronisation des fichiers. La conservation et la suppression distantes sont gérées par Dixeo selon le contrat institutionnel ; ce plugin ne peut pas supprimer les copies distantes via les flux de confidentialité Moodle.';
$string['privacy:metadata:image_job'] = 'Async image generation and editing jobs for course content and structure.';
$string['privacy:metadata:image_job:courseid'] = 'The course the image job belongs to.';
$string['privacy:metadata:image_job:errormessage'] = 'A generic failure message when the image job failed.';
$string['privacy:metadata:image_job:jobid'] = 'The remote Dixeo job identifier.';
$string['privacy:metadata:image_job:prompt'] = 'The image generation or edit prompt.';
$string['privacy:metadata:image_job:status'] = 'The current status of the image job.';
$string['privacy:metadata:image_job:timecreated'] = 'The time when the image job record was created.';
$string['privacy:metadata:image_job:timemodified'] = 'The time when the image job record was last modified.';
$string['privacy:metadata:image_job:userid'] = 'The user who started the image job.';
$string['privacy:metadata:jobs'] = 'Enregistrements locaux liant les tâches distantes Dixeo AI aux cours et utilisateurs Moodle.';
$string['privacy:metadata:jobs:accessmode'] = 'Indique si la tâche est limitée à l\'initiateur ou partagée au niveau du cours pour le contrôle d\'accès.';
$string['privacy:metadata:jobs:cmid'] = 'L\'identifiant du module de cours lorsqu\'il est connu.';
$string['privacy:metadata:jobs:component'] = 'Le composant Dixeo d\'origine pour la tâche.';
$string['privacy:metadata:jobs:contextid'] = 'Le contexte Moodle lorsqu\'il est connu.';
$string['privacy:metadata:jobs:courseid'] = 'Le cours auquel la tâche est liée.';
$string['privacy:metadata:jobs:jobid'] = 'L\'identifiant distant de la tâche Dixeo.';
$string['privacy:metadata:jobs:moduletype'] = 'Le type d\'activité Moodle lorsqu\'il est connu.';
$string['privacy:metadata:jobs:namespace'] = 'L\'espace de noms de l\'API Dixeo utilisé pour la tâche.';
$string['privacy:metadata:jobs:operation'] = 'Le type d\'opération logique de la tâche.';
$string['privacy:metadata:jobs:timecreated'] = 'L\'heure de création de la liaison locale de la tâche.';
$string['privacy:metadata:jobs:userid'] = 'L\'utilisateur qui a lancé la tâche.';
$string['privacy:metadata:preference:generation_language'] = 'La langue choisie pour les tests et les leçons générés par le tuteur.';
$string['privacy:metadata:tutor_synthesis'] = 'Synthèse de cours des conversations des étudiants avec le tuteur. Le texte est un agrégat et n\'est pas lié à un étudiant.';
$string['privacy:metadata:tutor_synthesis:courseid'] = 'Le cours auquel la synthèse appartient';
$string['privacy:metadata:tutor_synthesis:status'] = 'Si la synthèse est en attente, prête ou en échec';
$string['privacy:metadata:tutor_synthesis:timecreated'] = 'Quand la synthèse a été demandée';
$string['privacy:metadata:tutor_synthesis:userid'] = 'L\'enseignant qui a demandé la synthèse';
$string['privacy:metadata:tutor_usage_daily'] = 'Agrégats quotidiens d\'usage du tuteur';
$string['privacy:metadata:tutor_usage_daily:cmid'] = 'L\'identifiant du module de cours';
$string['privacy:metadata:tutor_usage_daily:courseid'] = 'L\'identifiant du cours';
$string['privacy:metadata:tutor_usage_daily:daystart'] = 'Le jour couvert par l\'agrégat';
$string['privacy:metadata:tutor_usage_daily:lastactive'] = 'Horodatage de la dernière activité';
$string['privacy:metadata:tutor_usage_daily:lessoncreated'] = 'Leçons Teach créées';
$string['privacy:metadata:tutor_usage_daily:messages'] = 'Nombre de messages pour la journée';
$string['privacy:metadata:tutor_usage_daily:mode'] = 'Le mode du tuteur';
$string['privacy:metadata:tutor_usage_daily:quizcreated'] = 'Quiz d\'entraînement créés';
$string['privacy:metadata:tutor_usage_daily:userid'] = 'L\'identifiant de l\'utilisateur';
$string['privacy:metadata:tutor_usage_event'] = 'Événements d\'usage du tuteur';
$string['privacy:metadata:tutor_usage_event:cmid'] = 'L\'identifiant du module de cours lorsqu\'il est connu';
$string['privacy:metadata:tutor_usage_event:courseid'] = 'Le cours où l\'événement s\'est produit';
$string['privacy:metadata:tutor_usage_event:eventtype'] = 'Le type d\'événement d\'usage du tuteur';
$string['privacy:metadata:tutor_usage_event:mode'] = 'Le mode d\'interaction du tuteur';
$string['privacy:metadata:tutor_usage_event:timecreated'] = 'Quand l\'événement a été enregistré';
$string['privacy:metadata:tutor_usage_event:userid'] = 'L\'utilisateur qui a déclenché l\'événement du tuteur';
$string['privacy:metadata:tutor_usage_open'] = 'Sessions de tuteur ouvertes';
$string['privacy:metadata:tutor_usage_open:courseid'] = 'L\'identifiant du cours';
$string['privacy:metadata:tutor_usage_open:lastmessage'] = 'Horodatage du dernier message';
$string['privacy:metadata:tutor_usage_open:messagecount'] = 'Messages jusqu\'à présent dans la session';
$string['privacy:metadata:tutor_usage_open:timestart'] = 'Heure de début de la session';
$string['privacy:metadata:tutor_usage_open:userid'] = 'L\'identifiant de l\'utilisateur';
$string['privacy:metadata:tutor_usage_session'] = 'Sessions d\'usage du tuteur fermées';
$string['privacy:metadata:tutor_usage_session:courseid'] = 'L\'identifiant du cours';
$string['privacy:metadata:tutor_usage_session:duration'] = 'Durée de la session en secondes';
$string['privacy:metadata:tutor_usage_session:messagecount'] = 'Messages dans la session';
$string['privacy:metadata:tutor_usage_session:timeend'] = 'Heure de fin de la session';
$string['privacy:metadata:tutor_usage_session:timestart'] = 'Heure de début de la session';
$string['privacy:metadata:tutor_usage_session:userid'] = 'L\'identifiant de l\'utilisateur';
$string['privacy:path:consent'] = 'Choix relatifs aux conditions et à la confidentialité';
$string['privacy:path:conversation'] = 'Conversation du tuteur';
$string['privacy:path:course_ai'] = 'Synchronisation IA du cours';
$string['privacy:path:credit_usage'] = 'Utilisation des crédits';
$string['privacy:path:image_jobs'] = 'Dixeo image jobs';
$string['privacy:path:jobs'] = 'Tâches Dixeo AI';
$string['privacy:path:tutor_synthesis'] = 'Synthèse des conversations des étudiants';
$string['privacy:path:tutor_usage'] = 'Analyses d\'usage du tuteur';
$string['recent_transactions'] = 'Historique des transactions';
$string['settings_badge_customised'] = 'Personnalisé';
$string['settings_badge_dixeo'] = 'Valeur Dixeo par défaut';
$string['settings_badge_site'] = 'Valeur par défaut du site';
$string['settings_button'] = 'Paramètres';
$string['settings_category'] = 'Dixeo';
$string['settings_category_designer'] = 'Concepteur de cours';
$string['settings_category_dixeo_core'] = 'Dixeo';
$string['settings_category_dixeo_designer'] = 'Concepteur de Cours';
$string['settings_category_dixeo_tutor'] = 'Tuteur étudiant';
$string['settings_category_filesync'] = 'Synchronisation des fichiers';
$string['settings_category_imagegeneration'] = 'Génération d\'images';
$string['settings_category_tutor'] = 'Tuteur';
$string['settings_column_activity'] = 'Devoir';
$string['settings_column_course'] = 'Cours';
$string['settings_column_dixeo'] = 'Dixeo';
$string['settings_column_setting'] = 'Paramètre';
$string['settings_column_site'] = 'Valeur par défaut du site';
$string['settings_group_feature'] = 'Fonction';
$string['settings_group_plugin'] = 'Plugin';
$string['settings_grouping'] = 'Grouper par';
$string['settings_modal_advanced'] = 'Avancé';
$string['settings_modal_all_features'] = 'Toutes les fonctions';
$string['settings_modal_all_plugins'] = 'Tous les plugins';
$string['settings_modal_breadcrumb'] = 'Site / Dixeo / Cours : {$a}';
$string['settings_modal_breadcrumb_activity'] = 'Site / Dixeo / Cours : {$a->course} / Devoir : {$a->activity}';
$string['settings_modal_categories'] = 'Catégories';
$string['settings_modal_count'] = '{$a} paramètres';
$string['settings_modal_customised'] = 'Personnalisés uniquement';
$string['settings_modal_empty'] = 'Effacez la recherche, désactivez Personnalisés uniquement, ou choisissez une autre zone.';
$string['settings_modal_error'] = 'Les paramètres Dixeo n\'ont pas pu être chargés.';
$string['settings_modal_loading'] = 'Chargement des paramètres';
$string['settings_modal_preferences'] = 'Mes préférences';
$string['settings_modal_save_course'] = 'Enregistrez le cours avant de charger ses paramètres Dixeo.';
$string['settings_modal_save_error'] = 'Les paramètres Dixeo n\'ont pas pu être enregistrés.';
$string['settings_modal_saving'] = 'Enregistrement des modifications...';
$string['settings_modal_search'] = 'Rechercher des paramètres';
$string['settings_modal_search_placeholder'] = 'Rechercher par nom, clé ou plugin';
$string['settings_modal_status'] = 'État';
$string['settings_modal_title'] = 'Paramètres Dixeo';
$string['settings_modal_unsaved_close'] = 'Fermer sans enregistrer';
$string['settings_modal_unsaved_confirm'] = 'Fermer sans enregistrer ces modifications ?';
$string['settings_modal_unsaved_count'] = '{$a} modifications non enregistrées';
$string['settings_modal_unsaved_none'] = 'Aucune modification non enregistrée';
$string['settings_modal_unsaved_title'] = 'Modifications non enregistrées';
$string['settings_number_range'] = 'Saisissez un nombre entier entre {$a->min} et {$a->max}.';
$string['settings_readonly'] = 'Ce paramètre ne peut pas être modifié';
$string['settings_revert'] = 'Annuler les modifications de {$a}';
$string['settings_value_empty'] = 'vide';
$string['settings_value_off'] = 'Désactivé';
$string['settings_value_on'] = 'Activé';
$string['state_active'] = 'Actif';
$string['state_frozen'] = 'Gelé';
$string['state_suspended'] = 'Suspendu';
$string['task_aggregate_tutor_usage'] = 'Agréger les analyses d\'usage du tuteur Dixeo';
$string['task_cleanup_image_jobs'] = 'Nettoyer les enregistrements de tâches d\'image Dixeo';
$string['task_poll_image'] = 'Interroger la tâche d\'image Dixeo';
$string['task_poll_image_generation'] = 'Interroger la tâche de génération d\'images Dixeo';
$string['task_process_file_sync'] = 'Traiter la synchronisation des fichiers Dixeo';
$string['task_process_remote_file_deletion'] = 'Réessayer la suppression distante des fichiers Dixeo';
$string['teach_lesson_default_title'] = 'Leçon personnalisée';
$string['teach_lesson_error_invalid_result'] = 'Résultat du travail non valide.';
$string['teach_lesson_error_job_not_completed'] = 'Le travail n\'est pas terminé. Statut : {$a->status}';
$string['teach_lesson_error_no_content'] = 'Aucun contenu dans le résultat du travail.';
$string['teach_lesson_error_wrong_module_type'] = 'Le travail n\'est pas une génération de page.';
$string['teach_lesson_instructions'] = 'Générez une leçon de module Page personnalisée pour {$a->scopedescription}.

L\'apprenant a demandé :
"{$a->learnerrequest}"

EXIGENCES OBLIGATOIRES — vous DEVEZ les respecter exactement :
1. TYPE DE MODULE : Produisez un module Page avec un nom clair et descriptif, un bref résumé d\'introduction (intro) et un contenu principal riche (content).
2. STRUCTURE : Organisez la leçon avec des titres clairs et des sections logiques. Utilisez des exemples lorsque c\'est utile.
3. DEMANDE DE L\'APPRENANT : Répondez directement à la demande de l\'apprenant — approfondissez le sujet ou expliquez-le en termes plus simples comme il l\'a demandé.
4. ALIGNEMENT : Basez la leçon sur le contexte de cours fourni. N\'inventez pas de faits qui contredisent le matériel source.
5. TEXTE UNIQUEMENT : Produisez uniquement du texte. N\'incluez pas d\'images, d\'illustrations, de balises <img> ni d\'espaces réservés [img-gen ...]. Les leçons personnalisées ne traitent pas les espaces réservés d\'image.

Avant de terminer, vérifiez que le champ content est substantiel, uniquement textuel, et répond directement à la demande de l\'apprenant.';
$string['total_used'] = 'Total utilisé';
$string['tutor_usage_duration_days'] = '{$a->days}j {$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_hours'] = '{$a->hours}h {$a->minutes}m';
$string['tutor_usage_duration_minutes'] = '{$a} min';
$string['tutor_usage_duration_seconds'] = '{$a}s';
$string['tutor_usage_mode_guide'] = 'Guide';
$string['tutor_usage_mode_normal'] = 'Standard';
$string['tutor_usage_mode_quiz'] = 'Quiz';
$string['tutor_usage_mode_teach'] = 'Teach';
$string['tutor_usage_report_breadcrumb'] = 'Navigation du rapport';
$string['tutor_usage_report_cell_tooltip_adoption'] = '{$a->active} sur {$a->total} utilisateurs';
$string['tutor_usage_report_change_since_month'] = 'depuis le mois dernier';
$string['tutor_usage_report_change_since_week'] = 'depuis la semaine dernière';
$string['tutor_usage_report_column_guide'] = 'Guide';
$string['tutor_usage_report_column_lastactive'] = 'Dernière activité';
$string['tutor_usage_report_column_messages'] = 'Messages';
$string['tutor_usage_report_column_moduletype'] = 'Type d\'activité';
$string['tutor_usage_report_column_name'] = 'Nom';
$string['tutor_usage_report_column_quiz'] = 'Quiz';
$string['tutor_usage_report_column_standard'] = 'Standard';
$string['tutor_usage_report_column_teach'] = 'Teach';
$string['tutor_usage_report_course_page'] = 'Page du cours';
$string['tutor_usage_report_filter_roles'] = 'Utilisateurs';
$string['tutor_usage_report_heatmap'] = 'Carte de chaleur d\'activité';
$string['tutor_usage_report_heatmap_info'] = 'Nombre total de messages envoyés dans chaque créneau de trois heures, par jour de la semaine.';
$string['tutor_usage_report_kpi_adoption'] = 'Adoption';
$string['tutor_usage_report_kpi_adoption_info'] = 'Proportion des utilisateurs du périmètre ayant utilisé le tuteur au moins une fois pendant la période.';
$string['tutor_usage_report_kpi_adoption_secondary'] = '{$a->active} actifs / {$a->inactive} inactifs / {$a->total} total';
$string['tutor_usage_report_kpi_duration'] = 'Durée';
$string['tutor_usage_report_kpi_duration_info'] = 'Temps total passé à interagir avec le tuteur pendant la période.';
$string['tutor_usage_report_kpi_duration_secondary'] = 'Médiane {$a->median} / Moyenne {$a->average}';
$string['tutor_usage_report_kpi_engagement'] = 'Engagement';
$string['tutor_usage_report_kpi_engagement_info'] = 'Le total du nombre de jours où les utilisateurs ont interagi avec le tuteur pendant la période';
$string['tutor_usage_report_kpi_engagement_info_user'] = 'Nombre de jours distincts où cet utilisateur a utilisé le tuteur pendant la période.';
$string['tutor_usage_report_kpi_engagement_secondary'] = 'Dernière activité : {$a}';
$string['tutor_usage_report_kpi_engagement_secondary_aggregate'] = 'Médiane {$a->median} / Moyenne {$a->average} jours actifs par utilisateur actif';
$string['tutor_usage_report_kpi_engagement_value'] = '{$a} jours';
$string['tutor_usage_report_kpi_messages'] = 'Messages';
$string['tutor_usage_report_kpi_messages_info'] = 'Nombre total de messages envoyés au tuteur pendant la période.';
$string['tutor_usage_report_kpi_messages_secondary'] = 'Médiane {$a->median} / Moyenne {$a->average}';
$string['tutor_usage_report_kpi_mode_desc_lesson'] = 'leçons créées';
$string['tutor_usage_report_kpi_mode_desc_messages'] = 'messages envoyés';
$string['tutor_usage_report_kpi_mode_desc_quiz'] = 'quiz soumis';
$string['tutor_usage_report_kpi_mode_info_guide'] = 'Messages envoyés en mode Guide, où le tuteur amène l\'apprenant à la réponse au lieu de la donner.';
$string['tutor_usage_report_kpi_mode_info_normal'] = 'Messages envoyés en mode Standard, où le tuteur répond directement aux questions.';
$string['tutor_usage_report_kpi_mode_info_quiz'] = 'Messages envoyés en mode Quiz, les résultats du test sont transmis au tuteur pour analyse.';
$string['tutor_usage_report_kpi_mode_info_teach'] = 'Messages envoyés en mode Enseigner, les leçons générées font partie de la conversation.';
$string['tutor_usage_report_kpi_mode_secondary_quiz'] = 'Quiz créés : {$a}';
$string['tutor_usage_report_kpi_sessions'] = 'Sessions';
$string['tutor_usage_report_kpi_sessions_info'] = 'Nombre de sessions avec le tuteur. Une session commence au premier message et se termine après {$a} sans nouveau message.';
$string['tutor_usage_report_kpi_sessions_secondary'] = 'Médiane {$a->median} / Moyenne {$a->average}';
$string['tutor_usage_report_level_site'] = 'Tous les cours';
$string['tutor_usage_report_nav'] = 'Usage du tuteur Dixeo';
$string['tutor_usage_report_next_period'] = 'Période suivante';
$string['tutor_usage_report_no_rows'] = 'Aucun enregistrement d\'usage du tuteur trouvé pour la période et les filtres sélectionnés.';
$string['tutor_usage_report_performance'] = 'Performance';
$string['tutor_usage_report_performance_activities'] = 'Activités notées';
$string['tutor_usage_report_performance_axis_grade'] = 'Note (%)';
$string['tutor_usage_report_performance_axis_usage'] = 'Messages (tout le temps)';
$string['tutor_usage_report_performance_column_average'] = 'Note moyenne';
$string['tutor_usage_report_performance_column_user_vs_average'] = 'Utilisateur / moyenne';
$string['tutor_usage_report_performance_course_total'] = 'Total du cours';
$string['tutor_usage_report_performance_mean_grade'] = 'Note moyenne';
$string['tutor_usage_report_performance_mean_usage'] = 'Usage moyen';
$string['tutor_usage_report_performance_na'] = '—';
$string['tutor_usage_report_performance_scatter'] = 'Usage vs note';
$string['tutor_usage_report_performance_scatter_empty'] = 'Aucun étudiant noté à afficher pour le moment.';
$string['tutor_usage_report_performance_user_vs_average'] = '{$a->user} / {$a->average}';
$string['tutor_usage_report_period'] = 'Période';
$string['tutor_usage_report_prev_period'] = 'Période précédente';
$string['tutor_usage_report_range_too_long'] = 'Les plages de dates personnalisées ne peuvent pas dépasser six mois.';
$string['tutor_usage_report_rolescope_all'] = 'Tous';
$string['tutor_usage_report_rolescope_students'] = 'Étudiants';
$string['tutor_usage_report_rolescope_teachers'] = 'Enseignants';
$string['tutor_usage_report_stacked_bar'] = 'Messages par mode dans le temps';
$string['tutor_usage_report_summary'] = 'Résumé';
$string['tutor_usage_report_synthesis_button'] = 'Synthèse';
$string['tutor_usage_report_synthesis_empty'] = 'Aucun message d\'étudiants au cours des 3 derniers mois.';
$string['tutor_usage_report_synthesis_failed'] = 'La synthèse n\'a pas pu être générée.';
$string['tutor_usage_report_synthesis_generating'] = 'Génération de la synthèse…';
$string['tutor_usage_report_synthesis_intro'] = 'Cliquez sur Synthèse pour générer la première synthèse de ce que les étudiants demandent au tuteur.';
$string['tutor_usage_report_synthesis_title'] = 'Synthèse des conversations des étudiants';
$string['tutor_usage_report_synthesis_truncated'] = 'La synthèse a utilisé les messages les plus récents.';
$string['tutor_usage_report_synthesis_unchanged'] = 'Aucun nouveau message depuis la dernière synthèse.';
$string['tutor_usage_report_synthesis_update'] = 'Mettre à jour';
$string['tutor_usage_report_synthesis_window'] = 'Couvre les conversations des étudiants du {$a->from} au {$a->to}.';
$string['tutor_usage_report_table_courses'] = 'Cours';
$string['tutor_usage_report_table_modules'] = 'Activités';
$string['tutor_usage_report_table_users'] = 'Utilisateurs';
$string['tutor_usage_report_view_custom'] = 'Plage personnalisée';
$string['tutor_usage_report_view_month'] = 'Mois';
$string['tutor_usage_report_view_week'] = 'Semaine';
$string['type'] = 'Type';
$string['view_credit_report'] = 'Voir le rapport d\'utilisation des crédits';
