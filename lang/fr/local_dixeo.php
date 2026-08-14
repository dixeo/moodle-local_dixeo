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
$string['credit_balance'] = 'Solde de crédits';
$string['credit_information'] = 'Informations sur les crédits';
$string['credit_report'] = 'Rapport de crédits';
$string['credits'] = 'crédits';
$string['current_balance'] = 'Solde actuel';
$string['current_balance_desc'] = 'Votre solde de crédits Dixeo actuel. Les crédits sont utilisés pour les opérations IA.';
$string['data_points'] = 'Points de données';
$string['date'] = 'Date';
$string['day_fri'] = 'Ven';
$string['day_friday'] = 'Vendredi';
$string['day_mon'] = 'Lun';
$string['day_monday'] = 'Lundi';
$string['day_sat'] = 'Sam';
$string['day_saturday'] = 'Samedi';
$string['day_sun'] = 'Dim';
$string['day_sunday'] = 'Dimanche';
$string['day_thu'] = 'Jeu';
$string['day_thursday'] = 'Jeudi';
$string['day_tue'] = 'Mar';
$string['day_tuesday'] = 'Mardi';
$string['day_wed'] = 'Mer';
$string['day_wednesday'] = 'Mercredi';
$string['description'] = 'Description';
$string['designerstructurevalidate_aggregate_prefix_section'] = 'Section {$a->section}, activité {$a->module} :';
$string['designerstructurevalidate_aggregate_prefix_section_only'] = 'Section {$a->section} :';
$string['designerstructurevalidate_course_summary_too_long'] = 'Le résumé du cours est trop long (maximum {$a->max} caractères).';
$string['designerstructurevalidate_course_title_required'] = 'Le titre du cours est un champ obligatoire.';
$string['designerstructurevalidate_course_title_too_long'] = 'Le titre du cours doit comporter au maximum {$a->max} caractères.';
$string['designerstructurevalidate_failed'] = 'Ce cours ne peut pas être créé tant que ces problèmes ne sont pas résolus :

{$a->details}';
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
$string['dixeo:create'] = 'Créer des cours avec le Concepteur de Cours Dixeo';
$string['dixeo:edit'] = 'Modifier les modules existants avec l\'IA';
$string['dixeo:generate'] = 'Générer de nouveaux modules avec l\'IA (page, étiquette, test, glossaire)';
$string['dixeo:manage'] = 'Gérer les paramètres Dixeo et consulter les rapports';
$string['dixeo:syncfiles'] = 'Activer, désactiver ou déclencher la synchronisation des fichiers de cours Dixeo vers l\'API externe';
$string['dixeo:viewusage'] = 'Consulter les rapports d\'utilisation des crédits';
$string['dixeo_course_image_unsupported_type'] = 'Type d\'image générée non pris en charge.';
$string['dixeo_image_generation_disabled'] = 'La génération d\'images est désactivée par les paramètres du site.';
$string['dixeo_image_job_empty_result'] = 'La tâche d\'image n\'a renvoyé aucune donnée d\'image.';
$string['dixeo_pluginfile_not_found'] = 'Impossible de lire le fichier image depuis le stockage.';
$string['dsl_error'] = 'Échec de la création du module : {$a}';
$string['error:api_url_https_required'] = 'L\'URL de l\'API Dixeo doit être une adresse HTTPS absolue (par exemple https://api.dixeo.com).';
$string['error:authentication'] = 'Échec de l\'authentification. Veuillez vérifier votre clé API.';
$string['error:connection'] = 'Échec de la connexion à l\'API Dixeo. Veuillez vérifier votre connexion réseau.';
$string['error:job_failed'] = 'Échec du traitement du travail : {$a}';
$string['error:job_not_found'] = 'Le travail demandé n\'a pas été trouvé.';
$string['error:notslideshow'] = 'Le module de cours n\'est pas une activité diaporama.';
$string['error:payment_required'] = 'Crédits insuffisants. Veuillez ajouter des crédits pour continuer.';
$string['error:rate_limit'] = 'Limite de requêtes dépassée. Veuillez patienter avant d\'effectuer d\'autres requêtes.';
$string['error:slidenotinslideshow'] = 'La diapositive demandée n\'appartient pas à ce diaporama.';
$string['error:timeout'] = 'L\'opération a expiré. Vous pouvez vérifier le statut du travail plus tard.';
$string['error:upstream_ai'] = 'Erreur du service IA. Veuillez réessayer plus tard.';
$string['error:validation'] = 'Requête invalide : {$a}';
$string['eventfilesyncdisabled'] = 'Synchronisation des fichiers Dixeo du cours désactivée';
$string['eventfilesyncdisableddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a désactivé la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\' (removefiles={$a->removefiles}).';
$string['eventfilesyncenabled'] = 'Synchronisation des fichiers Dixeo du cours activée';
$string['eventfilesyncenableddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a activé la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\'.';
$string['eventfilesynctriggered'] = 'Synchronisation des fichiers Dixeo du cours déclenchée';
$string['eventfilesynctriggereddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a déclenché la synchronisation des fichiers Dixeo pour le cours avec l\'id \'{$a->courseid}\'.';
$string['eventjobcancelled'] = 'Tâche Dixeo annulée';
$string['eventjobcancelleddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a annulé la tâche Dixeo \'{$a->jobid}\' pour le cours avec l\'id \'{$a->courseid}\'.';
$string['feedback_correct'] = 'Bravo, vous avez trouvé la bonne réponse. Continuez comme ça !';
$string['feedback_incorrect'] = 'Pas tout à fait cette fois. Revoir le sujet vous aidera à progresser.';
$string['feedback_partial'] = 'Vous êtes sur la bonne voie. Révisez le sujet et vous y arriverez.';
$string['files'] = 'fichiers';
$string['filesync_disable_remove'] = 'Désactiver et effacer les données de synchronisation';
$string['filesync_enable'] = 'Activer la synchronisation';
$string['filesync_error_retry'] = 'Nouvelle tentative automatique';
$string['filesync_failed'] = 'Échec de la synchronisation des fichiers : {$a}';
$string['filesync_files_count'] = '{$a} fichiers synchronisés';
$string['filesync_label'] = 'Synchroniser';
$string['filesync_pause'] = 'Mettre en pause la synchronisation';
$string['filesync_progress'] = '{$a} % terminé';
$string['filesync_resync'] = 'Synchroniser maintenant';
$string['filesync_status_disabled'] = 'Synchronisation désactivée';
$string['filesync_status_error'] = 'Erreur de synchronisation';
$string['filesync_status_none'] = 'Aucun fichier synchronisé';
$string['filesync_status_outdated'] = 'Contenu modifié, synchronisation nécessaire';
$string['filesync_status_paused'] = 'Synchronisation en pause';
$string['filesync_status_synchronized'] = 'Fichiers synchronisés';
$string['filesync_status_syncing'] = 'Synchronisation en cours...';
$string['filesync_timeout'] = 'La synchronisation des fichiers a expiré avant l\'indexation des fichiers du cours';
$string['filesync_title'] = 'Synchronisation de fichiers Dixeo';
$string['generation_output_language'] = 'LANGUE : Générez tout le contenu destiné aux apprenants (questions, réponses, texte de leçon et titres) en {$a->language}.';
$string['image_generation'] = 'Génération d\'images';
$string['image_generation_course_mode'] = 'Images de cours';
$string['image_generation_course_mode_desc'] = 'Contrôle les actions d\'image IA pour l\'image d\'aperçu du cours.';
$string['image_generation_desc'] = 'Contrôle la disponibilité de la génération et de la modification d\'images par IA pour les images de cours et de section.';
$string['image_generation_enabled'] = 'Activer la génération d\'images';
$string['image_generation_enabled_desc'] = 'Si désactivé, toutes les demandes de génération ou de modification d\'images sont bloquées.';
$string['image_generation_mode_disabled'] = 'Désactivé';
$string['image_generation_mode_generate'] = 'Générer';
$string['image_generation_mode_generate_edit'] = 'Générer et modifier';
$string['image_generation_section_mode'] = 'Images de section';
$string['image_generation_section_mode_desc'] = 'Contrôle les actions d\'image IA pour les images de chapitre ou de section.';
$string['last_sync'] = 'Dernière synchronisation';
$string['namespace'] = 'Espace de noms';
$string['namespace_desc'] = 'Requis uniquement lorsque plusieurs sites Moodle partagent la même clé API. Chaque site doit utiliser un espace de noms différent (ex. « production », « staging », « site1 ») pour garder les données séparées. Laissez « default » si c\'est le seul site utilisant cette clé API.';
$string['no_transactions'] = 'Aucune transaction trouvée.';
$string['no_usage_data'] = 'Aucune donnée d\'utilisation disponible pour la période sélectionnée.';
$string['overview'] = 'Aperçu Dixeo';
$string['page_x_of_y'] = 'Page {$a->current} sur {$a->total}';
$string['pagination'] = 'Navigation des pages';
$string['period'] = 'Période';
$string['period_day'] = 'Quotidien';
$string['period_month'] = 'Mensuel';
$string['period_week'] = 'Hebdomadaire';
$string['pluginname'] = 'Dixeo AI';
$string['pluginname_desc'] = 'Intégration Dixeo AI pour la génération et l\'édition intelligente de contenu.';
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
$string['privacy:metadata'] = 'Le plugin Dixeo stocke des identifiants opérationnels pour la synchronisation des fichiers de cours et envoie le contenu des cours, les messages du tuteur, le contexte de génération et des identifiants associés à l\'API Dixeo AI. La conservation et la suppression des données chez Dixeo sont contrôlées par ce service externe.';
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
$string['privacy:metadata:jobs'] = 'Enregistrements locaux liant les tâches distantes Dixeo AI aux cours et utilisateurs Moodle.';
$string['privacy:metadata:jobs:courseid'] = 'Le cours auquel la tâche est liée.';
$string['privacy:metadata:jobs:jobid'] = 'L\'identifiant distant de la tâche Dixeo.';
$string['privacy:metadata:jobs:namespace'] = 'L\'espace de noms de l\'API Dixeo utilisé pour la tâche.';
$string['privacy:metadata:jobs:operation'] = 'Le type d\'opération logique de la tâche.';
$string['privacy:metadata:jobs:timecreated'] = 'L\'heure de création de la liaison locale de la tâche.';
$string['privacy:metadata:jobs:userid'] = 'L\'utilisateur qui a lancé la tâche.';
$string['privacy:path:course_ai'] = 'Synchronisation IA du cours';
$string['privacy:path:jobs'] = 'Tâches Dixeo AI';
$string['recent_transactions'] = 'Historique des transactions';
$string['state_active'] = 'Actif';
$string['state_frozen'] = 'Gelé';
$string['state_suspended'] = 'Suspendu';
$string['task_cleanup_jobs'] = 'Nettoyer les anciens enregistrements de travaux';
$string['task_poll_image_generation'] = 'Interroger la tâche de génération d\'images Dixeo';
$string['task_process_file_sync'] = 'Traiter la synchronisation des fichiers Dixeo';
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
$string['this_week_usage'] = 'Cette semaine';
$string['total_used'] = 'Total utilisé';
$string['transaction_type_deduction'] = 'Utilisation';
$string['transaction_type_purchase'] = 'Achat';
$string['transaction_type_refund'] = 'Remboursement';
$string['transaction_type_reset'] = 'Renouvellement';
$string['type'] = 'Type';
$string['usage_chart_label'] = 'Utilisation des crédits';
$string['usage_statistics'] = 'Statistiques d\'utilisation';
$string['view_credit_report'] = 'Voir le rapport détaillé des crédits';
$string['week_total'] = 'Total cette semaine';
