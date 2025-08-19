<?php
/**
 * Plugin Name: Étudiant Folders               
 * Description: Gestion de dossiers et fichiers pour chaque élève et professeur. 
 * Version: 1.0                                
 * Author: Alioune GUEYE                        
 */

// Sécurité : empêche l'accès direct au fichier via l'URL
if ( ! defined( 'ABSPATH' ) ) exit;

// ✅ Définition d'une constante qui contient le chemin absolu vers le dossier du plugin
define( 'EF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Charge automatiquement les dépendances si le fichier autoload existe (ex. AWS SDK via Composer)
if ( file_exists( EF_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once EF_PLUGIN_DIR . 'vendor/autoload.php'; 
}

// Inclusion des fichiers PHP nécessaires au fonctionnement du plugin
require_once EF_PLUGIN_DIR . 'includes/class-etudiant-folders-cpt.php';   // Classe gérant le Custom Post Type "etudiant_folder"
require_once EF_PLUGIN_DIR . 'includes/class-etudiant-folders-admin.php'; // Classe gérant l'administration des dossiers/fichiers
require_once EF_PLUGIN_DIR . 'includes/class-etudiant-folders-front.php'; // Classe gérant l'affichage côté frontend (utilisateurs)
require_once EF_PLUGIN_DIR . 'includes/helpers.php';                      // Fonctions utilitaires réutilisables
require_once EF_PLUGIN_DIR . 'includes/class-s3-client.php';              // Classe gérant la communication avec le service S3

// Ajout de capacités personnalisées aux rôles "administrator" et "professeur"
add_action('init', function() {
    // Tableau des rôles à mettre à jour
    $roles_to_update = ['administrator', 'professeur'];

    // Parcourt chaque rôle pour lui ajouter des permissions
    foreach ($roles_to_update as $role_slug) {
        $role = get_role($role_slug); // Récupère l'objet rôle
        if ($role) {
            // Permissions WordPress standard
            $role->add_cap('upload_files'); // Permet d'uploader des fichiers
            $role->add_cap('read');         // Permet de lire le contenu
            $role->add_cap('ef_manage_folders'); // Capacité personnalisée pour gérer les dossiers étudiants

            // ✅ Capacités spécifiques au CPT "etudiant_folder"
            $role->add_cap('read_etudiant_folder');                // Lire un dossier
            $role->add_cap('edit_etudiant_folder');                // Modifier un dossier
            $role->add_cap('edit_etudiant_folders');               // Modifier plusieurs dossiers
            $role->add_cap('edit_published_etudiant_folders');     // Modifier les dossiers publiés
            $role->add_cap('publish_etudiant_folders');            // Publier des dossiers
            $role->add_cap('delete_etudiant_folder');              // Supprimer un dossier
            $role->add_cap('delete_published_etudiant_folders');   // Supprimer un dossier publié

            // 🔹 Si c'est un administrateur → donne les droits complets sur TOUS les dossiers
            if ($role_slug === 'administrator') {
                $role->add_cap('edit_others_etudiant_folders');   // Modifier les dossiers créés par d'autres
                $role->add_cap('delete_others_etudiant_folders'); // Supprimer les dossiers créés par d'autres
            }
        }
    }
});

// Initialise les composants du plugin une fois que WordPress est complètement chargé
add_action( 'plugins_loaded', function() {
    new Etudiant_Folders_Admin();  // Instancie la classe d’administration (backend)
    new Etudiant_Folders_Front();  // Instancie la classe de gestion du frontend (shortcode, affichage)
});
