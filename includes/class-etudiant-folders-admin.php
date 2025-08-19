<?php
/**
 * ======================================================
 *  Fichier : class-etudiant-folders-admin.php
 *  Rôle    : Gère toute la partie administration du plugin
 *            - Ajout de menus et sous-menus dans l’admin WordPress
 *            - Gestion du formulaire d’upload de fichiers
 *            - Affichage et filtrage des dossiers selon les rôles
 *            - Gestion des meta boxes pour lier des utilisateurs et professeurs à un dossier
 *            - Restriction des droits pour les rôles personnalisés
 * ======================================================
 */

// Sécurité : si le fichier est appelé directement (en dehors de WordPress),
// on stoppe immédiatement l’exécution.
if ( ! defined( 'ABSPATH' ) ) exit;

// Déclaration de la classe principale pour gérer l’interface d’admin
class Etudiant_Folders_Admin {

    /**
     * Constructeur
     * Lie les méthodes de la classe aux hooks WordPress appropriés.
     */
    public function __construct() {
        // Ajoute un sous-menu "Gestion fichiers" dans le menu du CPT
        add_action( 'admin_menu', array( $this, 'menu' ) );

        // Gère la soumission du formulaire d’upload de fichiers (admin_post_ef_upload)
        add_action( 'admin_post_ef_upload', array( $this, 'handle_upload' ) );

        // Ajoute une meta box pour assigner des utilisateurs à un dossier
        add_action( 'add_meta_boxes', array( $this, 'add_user_metabox' ) );

        // Sauvegarde des utilisateurs assignés lors de l’enregistrement d’un dossier
        add_action( 'save_post_etudiant_folder', array( $this, 'save_user_meta' ) );

        // Enqueue les CSS nécessaires pour l’admin
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Restreint la liste des dossiers visibles selon le rôle de l’utilisateur
        add_action( 'pre_get_posts', array( $this, 'restrict_folders_list' ) );

        // Personnalise les capacités selon le rôle "professeur"
        add_filter( 'map_meta_cap', array( $this, 'customize_caps_for_professor' ), 10, 4 );
        /**
     * ✅ AJOUT : la métabox "Professeur assigné" et sa sauvegarde
     * sont enregistrées UNIQUEMENT si l'utilisateur courant est administrateur
     */
        if ( current_user_can( 'administrator' ) ) {
        add_action( 'add_meta_boxes', array( $this, 'add_professeur_metabox' ) );
        add_action( 'save_post_etudiant_folder', array( $this, 'save_professeur_meta' ) );
    }
    }

    /**
     * Ajoute un sous-menu dans le menu du CPT "etudiant_folder"
     */
    public function menu() {
        add_submenu_page(
            'edit.php?post_type=etudiant_folder', // Menu parent (liste des dossiers)
            'Gestion des fichiers',               // Titre de la page
            'Gestion fichiers',                   // Libellé du sous-menu
            'ef_manage_folders',                  // Capacité requise pour voir ce sous-menu
            'ef-upload',                           // Slug de la page
            array( $this, 'render_page' )          // Fonction de rappel pour afficher le contenu
        );
    }

    /**
     * Charge les styles CSS nécessaires dans l’administration
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'ef-style',                                        // Identifiant du style
            plugins_url( '../assets/style.css', __FILE__ )     // URL vers le fichier CSS
        );
    }

    /**
     * Affiche la page "Gestion des fichiers"
     * Inclut un formulaire d’upload + la liste des fichiers existants
     */
    public function render_page() {
        // Prépare la requête pour récupérer tous les dossiers
        $args = array(
            'post_type'   => 'etudiant_folder', // Type de contenu
            'numberposts' => -1                 // Pas de limite de résultats
        );

        // Si l’utilisateur n’est pas admin → on ne lui montre que ses propres dossiers
        if ( ! current_user_can('administrator') ) {
            $args['author'] = get_current_user_id();
        }

        // Récupération des dossiers
        $folders = get_posts($args);
        ?>

        <div class="wrap">
            <h1>Gestion des fichiers étudiants</h1>

            <!-- Formulaire d’upload de fichiers -->
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'ef_upload_action', 'ef_upload_nonce' ); ?>
                <input type="hidden" name="action" value="ef_upload">

                <!-- Sélecteur du dossier cible -->
                <label>Dossier :
                    <select name="folder_id" required>
                        <?php foreach ( $folders as $f ) echo "<option value='{$f->ID}'>{$f->post_title}</option>"; ?>
                    </select>
                </label>
                <br><br>

                <!-- Sélecteur de fichiers -->
                <input type="file" name="files[]" multiple required>
                <br><br>

                <button type="submit" class="button button-primary">Uploader</button>
            </form>

            <hr>
            <h2>Fichiers existants</h2>

            <?php
            // Chargement du client S3 (Backblaze)
            require_once EF_PLUGIN_DIR . 'includes/class-s3-client.php';
            $s3 = new S3_Client();
            ?>

            <div class="ef-folders-container">
                <?php foreach ( $folders as $folder ) :
                    // Slug du dossier (utilisé dans le stockage S3)
                    $slug = sanitize_title( $folder->post_title );

                    // Liste des fichiers présents dans ce dossier
                    $files = $s3->list_files($slug);
                ?>
                    <div class="ef-folder">
                        <h3><?php echo esc_html( $folder->post_title ); ?></h3>
                        <ul class="ef-file-list">
                            <?php if ( $files ) :
                                foreach ( $files as $file ) :
                                    $url = $s3->get_public_url($slug, $file);
                                    echo "<li class='ef-file'>" . esc_html($file) . "</li>";
                                endforeach;
                            else : ?>
                                <li class="ef-file ef-empty">Aucun fichier</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Traite le formulaire d’upload
     */
    public function handle_upload() {
        // Vérifie la capacité de l’utilisateur
        if ( ! current_user_can( 'ef_manage_folders' ) ) {
            wp_die( 'Accès interdit' );
        }

        // Vérifie le nonce pour la sécurité
        if ( ! isset($_POST['ef_upload_nonce']) || ! wp_verify_nonce($_POST['ef_upload_nonce'], 'ef_upload_action') ) {
            wp_die( 'Sécurité échouée (nonce).' );
        }

        // Récupère le dossier cible
        $folder_id = intval( $_POST['folder_id'] );
        $folder = get_post($folder_id);

        if ( ! $folder ) {
            wp_die( 'Dossier introuvable.' );
        }

        // Professeur : ne peut uploader que dans ses propres dossiers
        if ( ! current_user_can('administrator') && intval($folder->post_author) !== get_current_user_id() ) {
            wp_die( 'Vous ne pouvez pas ajouter des fichiers à un dossier qui ne vous appartient pas.' );
        }

        // Slug du dossier pour le stockage S3
        $folder_slug = sanitize_title( get_the_title( $folder_id ) );

        // Chargement du client S3
        require_once EF_PLUGIN_DIR . 'includes/class-s3-client.php';
        $s3 = new S3_Client();

        // Types de fichiers autorisés
        $allowed_types = ['pdf','docx','xlsx','jpg','png','zip'];

        // Taille max autorisée : 10 Mo
        $max_size = 10 * 1024 * 1024;

        $errors = [];

        // Parcours de tous les fichiers envoyés
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            $name = sanitize_file_name($_FILES['files']['name'][$i]);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $size = $_FILES['files']['size'][$i];

            // Vérification de l’extension
            if (!in_array($ext, $allowed_types)) {
                $errors[] = "$name : extension non autorisée ($ext)";
                continue;
            }

            // Vérification de la taille
            if ($size > $max_size) {
                $errors[] = "$name : fichier trop volumineux (" . size_format($size) . ")";
                continue;
            }

            // Upload vers S3
            $ok = $s3->upload_file($tmp, $folder_slug, $name);

            if (!$ok) {
                $errors[] = "$name : erreur lors de l'envoi à Backblaze.";
            }
        }

        // Affiche les erreurs si nécessaire
        if (!empty($errors)) {
            wp_die('Erreurs d’upload :<br>' . implode('<br>', $errors));
        }

        // Redirection après succès
        wp_redirect(admin_url('edit.php?post_type=etudiant_folder&page=ef-upload&uploaded=1'));
        exit;
    }

    /**
     * Ajoute la meta box "Utilisateurs assignés"
     */
    public function add_user_metabox() {
        add_meta_box(
            'ef_users',
            'Utilisateurs assignés',
            array( $this, 'render_user_metabox' ),
            'etudiant_folder',
            'side',
            'default'
        );
    }

    /**
     * Affiche le contenu de la meta box "Utilisateurs assignés"
     */
    public function render_user_metabox( $post ) {
        $users    = get_users( array( 'role' => 'Subscriber' ) );
        $assigned = (array) get_post_meta( $post->ID, 'ef_assigned_users', true );

        foreach ( $users as $user ) {
            $checked = in_array( $user->ID, $assigned ) ? 'checked' : '';
            echo "<label><input type='checkbox' name='ef_users[]' value='{$user->ID}' $checked> {$user->display_name}</label><br>";
        }
    }

    /**
     * Sauvegarde les utilisateurs assignés
     */
    public function save_user_meta( $post_id ) {
        if ( isset( $_POST['ef_users'] ) ) {
            update_post_meta( $post_id, 'ef_assigned_users', array_map( 'intval', $_POST['ef_users'] ) );
        } else {
            delete_post_meta( $post_id, 'ef_assigned_users' );
        }
    }

    /**
     * Restreint la liste des dossiers visibles dans l’admin
     */
    public function restrict_folders_list( $query ) {
        // Seulement dans l’admin et sur la requête principale
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // On cible seulement la liste du CPT "etudiant_folder"
        global $pagenow;
        if ( $pagenow !== 'edit.php' || $query->get('post_type') !== 'etudiant_folder' ) {
            return;
        }

        // Si non-admin → on ne montre que les dossiers créés par l’utilisateur
        if ( ! current_user_can('administrator') ) {
            $query->set( 'author', get_current_user_id() );
        }
    }

    /**
     * Personnalise les droits (capabilities) pour le rôle "professeur"
     */
    public function customize_caps_for_professor( $caps, $cap, $user_id, $args ) {
        // Capacités à personnaliser
        $caps_to_modify = [
            'edit_etudiant_folder',
            'delete_etudiant_folder',
            'edit_published_etudiant_folders',
            'delete_published_etudiant_folders',
        ];

        // Si la capacité demandée n’est pas dans notre liste → on ne change rien
        if ( ! in_array( $cap, $caps_to_modify, true ) ) {
            return $caps;
        }

        $user = get_userdata( $user_id );

        // Admin : droits complets
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return ['do_not_check']; // Hack WordPress pour dire "autorisé"
        }

        // On ne traite que les professeurs
        if ( ! in_array( 'professeur', (array) $user->roles, true ) ) {
            return $caps;
        }

        // ID du post concerné
        $post_id = isset( $args[0] ) ? intval( $args[0] ) : 0;
        if ( ! $post_id ) {
            return $caps;
        }

        $post = get_post( $post_id );

        // Vérifie que c’est bien un dossier étudiant
        if ( ! $post || $post->post_type !== 'etudiant_folder' ) {
            return $caps;
        }

        // Autoriser si le professeur est l’auteur
        if ( intval( $post->post_author ) === $user_id ) {
            return ['edit_posts'];
        }

        // Sinon → accès refusé
        return ['do_not_allow'];
    }

    /**
     * Ajoute la meta box "Professeur assigné"
     */
    public function add_professeur_metabox() {
    /**
     * ✅ AJOUT : sécurité supplémentaire.
     * Même si un hook externe essaye de forcer l'appel à cette méthode,
     * on empêche l'ajout de la métabox si l'utilisateur n'est pas administrateur.
     */
    if ( ! current_user_can( 'administrator' ) ) {
        return;
    }

    add_meta_box(
        'ef_professeur',
        'Professeur assigné',
        array( $this, 'render_professeur_metabox' ),
        'etudiant_folder',
        'side',
        'default'
    );
}


    /**
     * Affiche la meta box "Professeur assigné"
     */
    public function render_professeur_metabox( $post ) {
        // IDs des professeurs déjà assignés
        $assigned_profs = (array) get_post_meta( $post->ID, 'ef_assigned_professeurs', true );

        // Liste des utilisateurs ayant le rôle "professeur"
        $profs = get_users( array( 'role' => 'professeur' ) );

        if ( empty( $profs ) ) {
            echo '<p>Aucun professeur disponible.</p>';
            return;
        }

        foreach ( $profs as $prof ) {
            $checked = in_array( $prof->ID, $assigned_profs ) ? 'checked' : '';
            echo "<label><input type='checkbox' name='ef_assigned_professeurs[]' value='{$prof->ID}' {$checked}> {$prof->display_name}</label><br>";
        }
    }

    /**
     * Sauvegarde les professeurs assignés
     */
    public function save_professeur_meta( $post_id ) {
        if ( isset( $_POST['ef_assigned_professeurs'] ) && is_array( $_POST['ef_assigned_professeurs'] ) ) {
            $prof_ids = array_map( 'intval', $_POST['ef_assigned_professeurs'] );
            update_post_meta( $post_id, 'ef_assigned_professeurs', $prof_ids );
        } else {
            delete_post_meta( $post_id, 'ef_assigned_professeurs' );
        }
    }
}
?>
