<?php
// Sécurité : empêche l'exécution directe du fichier via l'URL en vérifiant que WordPress est bien chargé
if ( ! defined( 'ABSPATH' ) ) exit;

// Définition de la classe qui gère l'affichage des dossiers sur le frontend (côté visiteur/utilisateur connecté)
class Etudiant_Folders_Front {

    // Constructeur : s'exécute automatiquement à la création de l'objet
    public function __construct() {
        // Déclare un shortcode [etudiant_folders] utilisable dans les pages/articles pour afficher les dossiers de l'étudiant connecté
        add_shortcode( 'etudiant_folders', array( $this, 'render' ) );

        // Ajoute le chargement des fichiers CSS nécessaires sur les pages du site
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Déclare un second shortcode [dossiers_professeur] pour afficher les dossiers attribués à un professeur
        add_shortcode( 'dossiers_professeur', array( $this, 'render_professeur_folders' ) );
    }

    // Méthode qui charge la feuille de style du plugin pour le frontend
    public function enqueue_assets() {
        wp_enqueue_style(
            'ef-front-style',                                  // Identifiant unique du style
            plugins_url( '../assets/style.css', __FILE__ ),    // Chemin complet vers le fichier CSS
            array(),                                           // Pas de dépendances CSS
            '1.0'                                              // Numéro de version du style
        );
    }

    // Méthode exécutée quand le shortcode [etudiant_folders] est utilisé
    public function render() {
        // Si l'utilisateur n'est pas connecté, on affiche un message et on arrête l'exécution
        if ( ! is_user_logged_in() ) return '<p>Veuillez vous connecter.</p>';

        // Récupère l'ID de l'utilisateur actuellement connecté
        $user_id = get_current_user_id();

        // Récupère tous les dossiers qui sont attribués à cet utilisateur via une fonction personnalisée
        $folders = ef_get_user_folders( $user_id );

        // Démarre la mise en mémoire tampon de sortie (permet de construire le HTML et de le retourner ensuite)
        ob_start();

        // Inclut la classe qui gère l'accès au service Amazon S3 (stockage de fichiers)
        require_once EF_PLUGIN_DIR . 'includes/class-s3-client.php';
        $s3 = new S3_Client(); // Création de l'objet client S3

        // Début du conteneur principal HTML pour les dossiers
        echo '<div class="ef-folders-container">';

        // Boucle sur chaque dossier attribué à l'utilisateur
        foreach ( $folders as $folder ) {
            // Crée un slug (identifiant texte sans accents ni espaces) à partir du titre du dossier
            $slug = sanitize_title( $folder->post_title );

            // Récupère la liste des fichiers présents dans ce dossier sur S3
            $files = $s3->list_files($slug);

            // Bloc HTML pour un dossier
            echo '<div class="ef-folder">';
            echo '<h3 class="ef-folder-title">' . esc_html( $folder->post_title ) . '</h3>'; // Titre sécurisé du dossier
            echo '<ul class="ef-file-list">'; // Liste des fichiers

            // Si des fichiers existent, on les affiche
            if ( $files ) {
                foreach ( $files as $file ) {
                    // Génère l'URL publique du fichier
                    $url = $s3->get_public_url($slug, $file);
                    // Affiche le fichier comme lien cliquable
                    echo "<li class='ef-file'><a href='" . esc_url($url) . "'>" . esc_html($file) . "</a></li>";
                }
            } else {
                // Si aucun fichier, message informatif
                echo "<li class='ef-file ef-empty'>Aucun fichier</li>";
            }

            echo '</ul>'; // Fin liste fichiers
            echo '</div>'; // Fin bloc dossier
        }

        echo '</div>'; // Fin conteneur principal

        // Retourne le HTML généré
        return ob_get_clean();
    }

    // Méthode exécutée quand le shortcode [dossiers_professeur] est utilisé
    public function render_professeur_folders() {
        // Si l'utilisateur n'est pas connecté, message d'erreur
        if ( ! is_user_logged_in() ) {
            return '<p>Veuillez vous connecter.</p>';
        }

        // Récupère l'ID et l'objet utilisateur
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Vérifie que l'utilisateur est soit professeur, soit administrateur
        if ( ! in_array( 'professeur', (array) $user->roles, true ) && ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return '<p>Accès réservé aux professeurs.</p>';
        }

        // Prépare la requête pour récupérer les dossiers où ce professeur est assigné
        $args = array(
            'post_type'      => 'etudiant_folder',   // Type de contenu personnalisé
            'posts_per_page' => -1,                  // Tous les dossiers
            'post_status'    => 'publish',           // Seulement publiés
            'orderby'        => 'title',             // Tri par titre
            'order'          => 'ASC',               // Ordre croissant
            'meta_query'     => array(                // Filtre sur la métadonnée des professeurs assignés
                array(
                    'key'     => 'ef_assigned_professeurs',
                    'value'   => 'i:' . $user_id . ';', // Recherche exacte dans le tableau sérialisé
                    'compare' => 'LIKE'
                )
            )
        );

        // Exécute la requête pour obtenir les dossiers
        $folders = get_posts( $args );

        // Si aucun dossier trouvé, message informatif
        if ( empty( $folders ) ) {
            return '<p>Aucun dossier attribué.</p>';
        }

        // Inclut la classe S3 pour accéder aux fichiers
        require_once EF_PLUGIN_DIR . 'includes/class-s3-client.php';
        $s3 = new S3_Client();

        // Démarre la mise en mémoire tampon pour construire le HTML
        ob_start();
        echo '<div class="ef-folders-container">';

        // Boucle sur chaque dossier
        foreach ( $folders as $folder ) {
            $slug  = sanitize_title( $folder->post_title ); // Slug sécurisé
            $files = $s3->list_files( $slug ); // Liste des fichiers sur S3

            echo '<div class="ef-folder">';
            echo '<h3 class="ef-folder-title">' . esc_html( $folder->post_title ) . '</h3>';
            echo '<ul class="ef-file-list">';

            // Si le dossier contient des fichiers
            if ( $files ) {
                foreach ( $files as $file ) {
                    $url = $s3->get_public_url( $slug, $file ); // URL publique
                    echo "<li class='ef-file'><a href='" . esc_url( $url ) . "' target='_blank'>" . esc_html( $file ) . "</a></li>";
                }
            } else {
                // Aucun fichier
                echo "<li class='ef-file ef-empty'>Aucun fichier</li>";
            }

            echo '</ul>';  // Fin liste fichiers
            echo '</div>'; // Fin bloc dossier
        }

        echo '</div>'; // Fin conteneur principal

        // Retourne le HTML construit
        return ob_get_clean();
    }
}
