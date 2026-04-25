<?php
/**
 * Préconfiguration de la connexion base de données GLPI.
 *
 * Ce fichier est monté dans le conteneur GLPI via docker-compose.
 * Il permet à GLPI de démarrer sans passer par l'assistant web.
 *
 * Pour l'image diouxx/glpi, la connexion est gérée via les variables
 * d'environnement GLPI_DB_* définies dans le docker-compose.yml.
 * Ce fichier sert de référence et de fallback si l'image ne les traite pas.
 *
 * Chemin dans le conteneur : /var/www/html/config/config_db.php
 */
class DB extends DBmysql {
    public $dbhost     = 'postgres';
    public $dbuser     = 'glpi_user';
    public $dbpassword = 'Gl9!xP@ssw0rd';
    public $dbdefault  = 'glpi';
    public $use_timezones = true;
    public $dbencoding = 'utf8mb4';
}
