<?php

/**
 * Script de synchronisation des données tennis
 * Exporte les données depuis l'environnement local vers le serveur
 * 
 * Usage: php sync_tennis_data.php [--dry-run] [--tables=teams,players] [--import-from=metadata.json]
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TennisDataSync
{
    private $localConnection;
    private $remoteConnection;
    private $dryRun = false;
    private $tablesToSync = ['teams', 'players'];
    private $importFromFile = null;
    private $stats = [
        'teams' => ['exported' => 0, 'updated' => 0, 'errors' => 0],
        'players' => ['exported' => 0, 'updated' => 0, 'errors' => 0]
    ];

    public function __construct($options = [])
    {
        $this->dryRun = $options['dry_run'] ?? false;
        $this->tablesToSync = $options['tables'] ?? $this->tablesToSync;
        $this->importFromFile = $options['import_from'] ?? null;
        
        $this->setupConnections();
    }

    /**
     * Configuration des connexions de base de données
     */
    private function setupConnections()
    {
        // Configuration locale (depuis .env local)
        $this->localConnection = [
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'auxotracker'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'port' => env('DB_PORT', 3306)
        ];

        // Configuration serveur (à adapter selon votre configuration)
        $this->remoteConnection = [
            'host' => 'bouteille',  // ou l'IP du serveur
            'database' => 'auxotracker',
            'username' => 'sc2vagr6376',
            'password' => '', // À définir
            'port' => 3306
        ];
    }

    /**
     * Exécution de la synchronisation
     */
    public function sync()
    {
        echo "🚀 Début de la synchronisation des données tennis\n";
        echo "Mode: " . ($this->dryRun ? "DRY RUN" : "PRODUCTION") . "\n";
        echo "Tables: " . implode(', ', $this->tablesToSync) . "\n";
        
        if ($this->importFromFile) {
            echo "📁 Import depuis: " . $this->importFromFile . "\n";
        }
        echo "\n";

        try {
            if ($this->importFromFile) {
                // Mode import depuis fichiers JSON
                $this->syncFromJsonFiles();
            } else {
                // Mode synchronisation directe base à base
                $localPdo = $this->connectToDatabase($this->localConnection, 'locale');
                $remotePdo = $this->connectToDatabase($this->remoteConnection, 'serveur');

                // Synchronisation des tables dans l'ordre (teams avant players)
                $orderedTables = ['teams', 'players'];
                foreach ($orderedTables as $table) {
                    if (in_array($table, $this->tablesToSync)) {
                        $this->syncTable($localPdo, $remotePdo, $table);
                    }
                }
            }

            $this->displayStats();

        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Connexion à une base de données
     */
    private function connectToDatabase($config, $name)
    {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            echo "✅ Connexion {$name} établie\n";
            return $pdo;
            
        } catch (PDOException $e) {
            throw new Exception("Impossible de se connecter à la base {$name}: " . $e->getMessage());
        }
    }

    /**
     * Synchronisation d'une table
     */
    private function syncTable($localPdo, $remotePdo, $tableName)
    {
        echo "\n📊 Synchronisation de la table '{$tableName}'\n";

        // Récupération des données locales modifiées récemment (dernières 24h)
        $sql = "SELECT * FROM {$tableName} WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $localPdo->prepare($sql);
        $stmt->execute();
        $localData = $stmt->fetchAll();

        echo "📥 {count($localData)} enregistrements à synchroniser\n";

        foreach ($localData as $record) {
            try {
                if ($this->dryRun) {
                    echo "  [DRY RUN] Synchronisation {$tableName} ID: {$record['id']}\n";
                    $this->stats[$tableName]['exported']++;
                } else {
                    $this->syncRecord($remotePdo, $tableName, $record);
                }
            } catch (Exception $e) {
                echo "  ❌ Erreur pour {$tableName} ID {$record['id']}: " . $e->getMessage() . "\n";
                $this->stats[$tableName]['errors']++;
            }
        }
    }

    /**
     * Synchronisation d'un enregistrement
     */
    private function syncRecord($remotePdo, $tableName, $record)
    {
        // Vérifier si l'enregistrement existe déjà
        $checkSql = "SELECT id FROM {$tableName} WHERE id = :id";
        $checkStmt = $remotePdo->prepare($checkSql);
        $checkStmt->execute(['id' => $record['id']]);
        $exists = $checkStmt->fetch();

        if ($exists) {
            // Mise à jour
            $this->updateRecord($remotePdo, $tableName, $record);
            $this->stats[$tableName]['updated']++;
            echo "  ✏️  Mis à jour {$tableName} ID: {$record['id']}\n";
        } else {
            // Insertion
            $this->insertRecord($remotePdo, $tableName, $record);
            $this->stats[$tableName]['exported']++;
            echo "  ➕ Créé {$tableName} ID: {$record['id']}\n";
        }
    }

    /**
     * Insertion d'un nouvel enregistrement
     */
    private function insertRecord($remotePdo, $tableName, $record)
    {
        $columns = array_keys($record);
        $placeholders = ':' . implode(', :', $columns);
        $columnsList = implode(', ', $columns);

        $sql = "INSERT INTO {$tableName} ({$columnsList}) VALUES ({$placeholders})";
        $stmt = $remotePdo->prepare($sql);
        $stmt->execute($record);
    }

    /**
     * Mise à jour d'un enregistrement existant
     */
    private function updateRecord($remotePdo, $tableName, $record)
    {
        $id = $record['id'];
        unset($record['id']); // Retirer l'ID des données à mettre à jour

        $setParts = [];
        foreach (array_keys($record) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE {$tableName} SET {$setClause} WHERE id = :id";
        $record['id'] = $id; // Remettre l'ID pour la condition WHERE
        
        $stmt = $remotePdo->prepare($sql);
        $stmt->execute($record);
    }

    /**
     * Synchronisation depuis des fichiers JSON exportés
     */
    private function syncFromJsonFiles()
    {
        echo "\n📊 Synchronisation depuis fichiers JSON\n";

        // Vérifier que le fichier de métadonnées existe
        if (!file_exists($this->importFromFile)) {
            throw new Exception("Fichier de métadonnées introuvable: " . $this->importFromFile);
        }

        // Lire les métadonnées
        $metadata = json_decode(file_get_contents($this->importFromFile), true);
        if (!$metadata) {
            throw new Exception("Impossible de lire les métadonnées JSON");
        }

        echo "📅 Export du: " . $metadata['export_date'] . "\n";
        echo "📊 Statistiques d'export:\n";
        foreach ($metadata['stats'] as $key => $value) {
            echo "   - {$key}: {$value}\n";
        }

        // Connexion à la base de données locale (qui sera la cible)
        $targetPdo = $this->connectToDatabase($this->localConnection, 'cible');

        // Répertoire contenant les fichiers JSON
        $importDir = dirname($this->importFromFile);

        // Synchroniser les équipes d'abord
        if (in_array('teams', $this->tablesToSync)) {
            $teamsFile = $importDir . '/' . $metadata['files']['teams'];
            if (file_exists($teamsFile)) {
                $this->importJsonFile($targetPdo, 'teams', $teamsFile);
            } else {
                echo "⚠️ Fichier teams non trouvé: $teamsFile\n";
            }
        }

        // Puis les joueurs
        if (in_array('players', $this->tablesToSync)) {
            $playersFile = $importDir . '/' . $metadata['files']['players'];
            if (file_exists($playersFile)) {
                $this->importJsonFile($targetPdo, 'players', $playersFile);
            } else {
                echo "⚠️ Fichier players non trouvé: $playersFile\n";
            }
        }
    }

    /**
     * Importer un fichier JSON dans une table
     */
    private function importJsonFile($pdo, $tableName, $jsonFile)
    {
        echo "\n📥 Import de {$tableName} depuis " . basename($jsonFile) . "\n";

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data) {
            echo "❌ Impossible de lire le fichier JSON: $jsonFile\n";
            return;
        }

        echo "📊 {count($data)} enregistrements à traiter\n";

        foreach ($data as $record) {
            try {
                if ($this->dryRun) {
                    echo "  [DRY RUN] Import {$tableName} ID: {$record['id']}\n";
                    $this->stats[$tableName]['exported']++;
                } else {
                    $this->syncRecord($pdo, $tableName, $record);
                }
            } catch (Exception $e) {
                echo "  ❌ Erreur pour {$tableName} ID {$record['id']}: " . $e->getMessage() . "\n";
                $this->stats[$tableName]['errors']++;
            }
        }
    }

    /**
     * Affichage des statistiques
     */
    private function displayStats()
    {
        echo "\n📈 Statistiques de synchronisation:\n";
        echo "=====================================\n";
        
        foreach ($this->stats as $table => $stats) {
            echo "Table {$table}:\n";
            echo "  - Créés: {$stats['exported']}\n";
            echo "  - Mis à jour: {$stats['updated']}\n";
            echo "  - Erreurs: {$stats['errors']}\n\n";
        }
    }
}

// Traitement des arguments de ligne de commande
$options = [];
$args = array_slice($argv, 1);

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif (strpos($arg, '--tables=') === 0) {
        $tables = substr($arg, 9);
        $options['tables'] = explode(',', $tables);
    }
}

// Exécution de la synchronisation
try {
    $sync = new TennisDataSync($options);
    $sync->sync();
    echo "\n✅ Synchronisation terminée avec succès!\n";
} catch (Exception $e) {
    echo "\n❌ Erreur fatale: " . $e->getMessage() . "\n";
    exit(1);
}