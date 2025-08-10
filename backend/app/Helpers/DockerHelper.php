<?php

namespace App\Helpers;

class DockerHelper
{
    /**
     * Détermine si l'application s'exécute dans un conteneur Docker
     * 
     * @return bool
     */
    public static function isRunningInDocker(): bool
    {
        // Vérifier si le fichier /.dockerenv existe (indicateur standard Docker)
        if (file_exists('/.dockerenv')) {
            return true;
        }
        
        // Vérifier les variables d'environnement Docker
        if (getenv('DOCKER_CONTAINER') || getenv('CONTAINER')) {
            return true;
        }
        
        // Vérifier le hostname du conteneur
        $hostname = gethostname();
        if ($hostname && strlen($hostname) === 12 && ctype_alnum($hostname)) {
            return true;
        }
        
        // Vérifier les processus cgroup (Linux)
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = file_get_contents('/proc/1/cgroup');
            if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'containerd') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtient l'hôte de base de données approprié selon l'environnement
     * 
     * @return string
     */
    public static function getDatabaseHost(): string
    {
        if (self::isRunningInDocker()) {
            // Dans Docker, utiliser l'hôte de la machine hôte
            return 'host.docker.internal';
        }
        
        // Hors Docker, utiliser localhost
        return '127.0.0.1';
    }
}