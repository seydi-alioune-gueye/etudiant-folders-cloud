<?php
// Sécurité : si la constante ABSPATH (chemin racine de WordPress) n’est pas définie,
// cela signifie que le fichier est appelé directement depuis l’URL. On interrompt alors l’exécution.
if ( ! defined( 'ABSPATH' ) ) exit;

// Définition d'une classe pour encapsuler la logique d'enregistrement du Custom Post Type (CPT)
// Ici, on crée un CPT spécifique pour gérer des "Dossiers Étudiants".
class Etudiant_Folders_CPT {

    // Méthode statique qui sera appelée pour enregistrer le CPT.
    // L’utilisation de "static" permet de l’appeler sans instancier la classe.
    public static function register() {

        // Appel de la fonction WordPress "register_post_type" pour créer un nouveau type de contenu.
        // Ici, le slug interne du CPT sera "etudiant_folder".
        register_post_type( 'etudiant_folder', array(

            // Tableau des libellés affichés dans l’interface d’administration WordPress.
            'labels' => array(
                // Nom au pluriel affiché dans les menus.
                'name'               => 'Dossiers',
                // Nom au singulier.
                'singular_name'      => 'Dossier',
                // Texte du bouton pour ajouter un nouvel élément.
                'add_new'            => 'Ajouter un dossier',
                // Texte affiché sur la page de création d’un nouvel élément.
                'add_new_item'       => 'Ajouter un nouveau dossier',
                // Texte affiché sur la page d’édition.
                'edit_item'          => 'Modifier le dossier',
                // Texte pour créer un élément depuis zéro.
                'new_item'           => 'Nouveau dossier',
                // Texte du lien pour afficher l’élément.
                'view_item'          => 'Voir le dossier',
                // Libellé pour la recherche dans la liste des éléments.
                'search_items'       => 'Rechercher des dossiers',
                // Texte affiché quand aucun élément n’est trouvé.
                'not_found'          => 'Aucun dossier trouvé',
                // Texte affiché quand aucun élément n’est trouvé dans la corbeille.
                'not_found_in_trash' => 'Aucun dossier dans la corbeille',
                // Libellé du lien "Tous les éléments".
                'all_items'          => 'Tous les dossiers',
                // Nom affiché dans le menu admin.
                'menu_name'          => 'Dossiers Étudiants',
                // Nom affiché dans la barre d’outils admin.
                'name_admin_bar'     => 'Dossier',
            ),

            // Ce CPT n'est pas public (pas accessible directement depuis le front-end).
            'public'          => false,
            // Il est tout de même visible dans l’interface admin de WordPress.
            'show_ui'         => true,
            // Icône utilisée dans le menu admin (Dashicons).
            'menu_icon'       => 'dashicons-portfolio',
            // Position du menu dans l’admin (9 = juste avant "Médias").
            'menu_position'   => 9,
            // Liste des fonctionnalités supportées par ce CPT (ici, uniquement le titre).
            'supports'        => array( 'title' ),

            // Définition de capacités personnalisées pour ce CPT.
            // Cela permet de gérer des permissions fines pour ce type de contenu.
            'capability_type' => array( 'etudiant_folder', 'etudiant_folders' ),
            // Active le mapping automatique des capacités personnalisées vers les capacités WordPress standards.
            'map_meta_cap'    => true,
        ));
    }
}

// Association de la méthode "register" de la classe à l’action "init" de WordPress.
// Cela garantit que le CPT sera enregistré au démarrage du CMS, avant toute utilisation.
add_action( 'init', array( 'Etudiant_Folders_CPT', 'register' ) );
