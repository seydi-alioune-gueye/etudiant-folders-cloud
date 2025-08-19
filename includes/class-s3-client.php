<?php
// Utilisation du namespace AWS S3 pour pouvoir utiliser la classe S3Client fournie par AWS SDK
use Aws\S3\S3Client;

// Sécurité : empêche l'accès direct au fichier via l'URL
if ( ! defined('ABSPATH') ) exit;

// Définition de la classe qui gère toutes les opérations avec Amazon S3 / Backblaze B2
class S3_Client {

    // Propriétés privées pour stocker l'instance du client S3 et le nom du bucket
    private $client;
    private $bucket;

    // Constructeur : s'exécute à l'instanciation de la classe
    public function __construct() {
        // Nom du bucket S3/Backblaze à utiliser (à modifier si nécessaire)
        $this->bucket = 'etudiant-folders'; // 📝 À adapter si ton bucket porte un autre nom

        // Création d'une instance du client S3
        $this->client = new S3Client([
            'version'     => 'latest',                                   // Utilise la dernière version de l'API
            'region'      => 'eu-central-003',                           // Région du service S3 (Backblaze utilise un format spécifique)
            'endpoint'    => 'https://s3.eu-central-003.backblazeb2.com',// URL de l'endpoint S3 de Backblaze
            'use_path_style_endpoint' => true,                           // Utilisation du format de chemin plutôt que du style virtuel
            'credentials' => [                                           // Clés d'authentification
                'key'    => '',                 // 🔐 Clé d'accès (à remplacer par la vraie clé)
                'secret' => '',           // 🔐 Clé secrète (à remplacer par la vraie clé)
            ]
        ]);
    }

    /**
     * Upload un fichier vers un dossier (prefix) spécifique dans le bucket
     * @param string $tmp_path     Chemin vers le fichier temporaire à uploader
     * @param string $folder_slug  Nom du dossier (prefix)
     * @param string $filename     Nom du fichier final
     * @return bool                True si succès, False si échec
     */
    public function upload_file($tmp_path, $folder_slug, $filename) {
        // Construit la clé complète sous la forme dossier/fichier
        $key = $folder_slug . '/' . $filename;

        try {
            // Envoie du fichier vers S3
            $this->client->putObject([
                'Bucket'     => $this->bucket,    // Nom du bucket
                'Key'        => $key,             // Chemin complet (prefix + fichier)
                'SourceFile' => $tmp_path,        // Fichier source à envoyer
                'ACL'        => 'public-read',    // Access Control List (public si on veut un lien direct)
            ]);
        } catch (Exception $e) {
            // En cas d'erreur, on log l'exception dans error_log()
            error_log('Erreur S3 upload : ' . $e->getMessage());
            return false;
        }

        return true; // Succès
    }

    /**
     * Liste les fichiers d’un dossier donné (prefix)
     * @param string $folder_slug Nom du dossier à lister
     * @return array Liste des noms de fichiers
     */
    public function list_files($folder_slug) {
        try {
            // Récupère la liste des objets dans le dossier donné
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $folder_slug . '/', // Ajoute "/" pour ne prendre que ce dossier
            ]);

            $fichiers = [];

            // Si le résultat contient bien des fichiers
            if (!empty($result['Contents'])) {
                foreach ($result['Contents'] as $obj) {
                    $key = $obj['Key']; // Chemin complet
                    if (basename($key)) { // Vérifie qu'on a bien un fichier (pas un dossier vide)
                        $fichiers[] = basename($key); // Ajoute seulement le nom du fichier
                    }
                }
            }

            return $fichiers;
        } catch (Exception $e) {
            // Log l'erreur et retourne un tableau vide
            error_log('Erreur S3 list : ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Génère une URL temporaire (signée) pour télécharger un fichier depuis S3
     * @param string $folder_slug Nom du dossier
     * @param string $filename    Nom du fichier
     * @return string URL temporaire ou "#" en cas d'erreur
     */
    public function get_file_url($folder_slug, $filename) {
        // Construit la clé complète (dossier + fichier)
        $key = $folder_slug . '/' . $filename;

        try {
            // Prépare une commande pour obtenir un objet depuis S3
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            // Crée une URL pré-signée valable 60 minutes
            $request = $this->client->createPresignedRequest($cmd, '+60 minutes');

            // Retourne l'URL sous forme de chaîne
            return (string) $request->getUri();
        } catch (Exception $e) {
            // Log l'erreur et retourne un lien neutre
            error_log('Erreur URL S3 : ' . $e->getMessage());
            return '#';
        }
    }

    /**
     * Retourne une URL publique directe vers un fichier (si ACL = public-read)
     * @param string $folder_slug Nom du dossier
     * @param string $filename    Nom du fichier
     * @return string URL publique
     */
    public function get_public_url($folder_slug, $filename) {
        // Construit la clé complète
        $key = $folder_slug . '/' . $filename;
        // Endpoint public du bucket
        $endpoint = 'https://s3.eu-central-003.backblazeb2.com';

        // Retourne l'URL complète vers le fichier encodé pour être valide
        return $endpoint . '/' . $this->bucket . '/' . rawurlencode($key);
    }
}
