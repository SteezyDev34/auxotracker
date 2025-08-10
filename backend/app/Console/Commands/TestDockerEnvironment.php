<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\DockerHelper;
use Illuminate\Support\Facades\DB;

class TestDockerEnvironment extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'docker:test-env';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Teste la détection d\'environnement Docker et affiche la configuration de base de données';

    /**
     * Exécute la commande console.
     */
    public function handle()
    {
        $this->info('=== Test de détection d\'environnement Docker ===');
        
        $isDocker = DockerHelper::isRunningInDocker();
        $dbHost = DockerHelper::getDatabaseHost();
        $configHost = config('database.connections.mysql.host');
        
        $this->line('');
        $this->line('Environnement détecté: ' . ($isDocker ? 'Docker' : 'Local'));
        $this->line('Hôte DB recommandé: ' . $dbHost);
        $this->line('Hôte DB configuré: ' . $configHost);
        $this->line('Variable DB_HOST: ' . env('DB_HOST', 'non définie'));
        
        $this->line('');
        $this->info('=== Informations système ===');
        $this->line('Fichier /.dockerenv existe: ' . (file_exists('/.dockerenv') ? 'Oui' : 'Non'));
        $this->line('Variable DOCKER_CONTAINER: ' . (getenv('DOCKER_CONTAINER') ? 'Définie' : 'Non définie'));
        $this->line('Hostname: ' . gethostname());
        
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = file_get_contents('/proc/1/cgroup');
            $hasDocker = strpos($cgroup, 'docker') !== false;
            $hasContainerd = strpos($cgroup, 'containerd') !== false;
            $this->line('Cgroup contient docker: ' . ($hasDocker ? 'Oui' : 'Non'));
            $this->line('Cgroup contient containerd: ' . ($hasContainerd ? 'Oui' : 'Non'));
        }
        
        $this->line('');
        $this->info('=== Test de connexion à la base de données ===');
        
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $this->info('✅ Connexion à la base de données réussie');
            $this->line('Driver: ' . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            $this->line('Version serveur: ' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION));
        } catch (\Exception $e) {
            $this->error('❌ Échec de la connexion à la base de données');
            $this->error('Erreur: ' . $e->getMessage());
        }
        
        return 0;
    }
}