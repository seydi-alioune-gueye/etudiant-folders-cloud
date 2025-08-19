<?php
// Sécurité : empêche l'accès direct au fichier
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fonction utilitaire qui retourne les dossiers assignés à un utilisateur donné.
 *
 * @param int $user_id L’ID de l’utilisateur connecté.
 * @return array Liste des posts (dossiers) de type 'etudiant_folder' associés à l’utilisateur.
 */
function ef_get_user_folders( $user_id ) {

    // Définition des arguments pour la requête WP_Query / get_posts
    $args = array(
        'post_type'      => 'etudiant_folder', // Recherche uniquement dans le CPT personnalisé
        'posts_per_page' => -1,                // Pas de limite de résultats
        'post_status'    => 'publish',         // Seulement les dossiers publiés
        'meta_query'     => array(             // Requête sur la méta 'ef_assigned_users'
            array(
                'key'     => 'ef_assigned_users',       // Clé du champ méta
                'value'   => 'i:' . $user_id . ';',     // Recherche une valeur sérialisée contenant l'ID (ex: i:5;)
                'compare' => 'LIKE'                     // On utilise LIKE car le champ est un tableau sérialisé
            )
        )
    );

    // Exécute la requête et retourne les résultats (tableau de WP_Post)
    return get_posts( $args );
}
