<?php
/**
 * Script de diagnostic pour tester l'accès à l'API SofaScore
 * Permet d'identifier les raisons du blocage sur serveur
 */

// Script standalone - pas besoin de Laravel

class SofaScoreAccessTest
{
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
    ];

    /**
     * Tester l'accès à l'API SofaScore avec différentes configurations
     */
    public function runDiagnostic()
    {
        echo "🔍 === DIAGNOSTIC D'ACCÈS API SOFASCORE ===\n\n";
        
        // Informations système
        $this->displaySystemInfo();
        
        // Test de base
        $this->testBasicAccess();
        
        // Test avec headers améliorés
        $this->testWithEnhancedHeaders();
        
        // Test avec proxy (si configuré)
        $this->testWithProxy();
        
        // Test de géolocalisation
        $this->testGeolocation();
    }

    /**
     * Afficher les informations système
     */
    private function displaySystemInfo()
    {
        echo "📊 === INFORMATIONS SYSTÈME ===\n";
        echo "🖥️  Système: " . php_uname() . "\n";
        echo "🌐 IP publique: " . $this->getPublicIP() . "\n";
        echo "🏢 Fournisseur: " . $this->getISPInfo() . "\n";
        echo "📍 Géolocalisation: " . $this->getGeolocation() . "\n\n";
    }

    /**
     * Test d'accès de base
     */
    private function testBasicAccess()
    {
        echo "🧪 === TEST D'ACCÈS DE BASE ===\n";
        
        $url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/" . date('Y-m-d');
        
        $result = $this->makeRequest($url);
        
        echo "📡 URL testée: {$url}\n";
        
        if ($result['success']) {
            echo "📊 Statut: {$result['status']}\n";
            echo "📏 Taille réponse: " . strlen($result['body']) . " octets\n";
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                echo "✅ Accès de base: RÉUSSI\n";
            } else {
                echo "❌ Accès de base: ÉCHEC\n";
            }
        } else {
            echo "❌ Erreur: {$result['error']}\n";
        }
        
        echo "\n";
    }

    /**
     * Test avec headers améliorés
     */
    private function testWithEnhancedHeaders()
    {
        echo "🔧 === TEST AVEC HEADERS AMÉLIORÉS ===\n";
        
        $url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/" . date('Y-m-d');
        $userAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
        
        $headers = [
            'User-Agent' => $userAgent,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Referer' => 'https://www.sofascore.com/',
            'Origin' => 'https://www.sofascore.com',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'DNT' => '1',
            'Connection' => 'keep-alive'
        ];
        
        $result = $this->makeRequest($url, $headers);
        
        echo "📡 URL testée: {$url}\n";
        echo "🤖 User-Agent: {$userAgent}\n";
        
        if ($result['success']) {
            echo "📊 Statut: {$result['status']}\n";
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                echo "✅ Headers améliorés: RÉUSSI\n";
            } else {
                echo "❌ Headers améliorés: ÉCHEC\n";
            }
        } else {
            echo "❌ Erreur: {$result['error']}\n";
        }
        
        echo "\n";
    }

    /**
     * Test avec proxy (si configuré)
     */
    private function testWithProxy()
    {
        echo "🔄 === TEST AVEC PROXY ===\n";
        
        // Vérifier si un proxy est configuré
        $proxyUrl = getenv('HTTP_PROXY') ?: getenv('HTTPS_PROXY');
        
        if (!$proxyUrl) {
            echo "⚠️ Aucun proxy configuré (variables HTTP_PROXY/HTTPS_PROXY)\n\n";
            return;
        }
        
        echo "🌐 Proxy configuré: {$proxyUrl}\n";
        
        // Test avec proxy
        $url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/" . date('Y-m-d');
        
        $result = $this->makeRequest($url, [], $proxyUrl);
        
        if ($result['success']) {
            echo "📊 Statut avec proxy: {$result['status']}\n";
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                echo "✅ Accès via proxy: RÉUSSI\n";
            } else {
                echo "❌ Accès via proxy: ÉCHEC\n";
            }
        } else {
            echo "❌ Erreur proxy: {$result['error']}\n";
        }
        
        echo "\n";
    }

    /**
     * Test de géolocalisation
     */
    private function testGeolocation()
    {
        echo "📍 === TEST DE GÉOLOCALISATION ===\n";
        
        $geoData = $this->getDetailedGeolocation();
        
        if ($geoData) {
            echo "🌍 Pays: " . ($geoData['country'] ?? 'Inconnu') . "\n";
            echo "🏙️  Ville: " . ($geoData['city'] ?? 'Inconnue') . "\n";
            echo "🏢 ISP: " . ($geoData['isp'] ?? 'Inconnu') . "\n";
            echo "🏭 Organisation: " . ($geoData['org'] ?? 'Inconnue') . "\n";
            echo "🤖 Type: " . ($geoData['hosting'] ? 'Datacenter/Hosting' : 'Résidentiel') . "\n";
            
            if ($geoData['hosting']) {
                echo "⚠️ ATTENTION: IP de datacenter détectée - risque de blocage élevé\n";
            }
        }
        
        echo "\n";
    }

    /**
     * Obtenir l'IP publique
     */
    private function getPublicIP()
    {
        $result = $this->makeRequest('https://api.ipify.org');
        
        if ($result['success'] && $result['status'] >= 200 && $result['status'] < 300) {
            return $result['body'];
        }
        
        return 'Inconnue';
    }

    /**
     * Obtenir les informations ISP
     */
    private function getISPInfo()
    {
        $ip = $this->getPublicIP();
        $result = $this->makeRequest("http://ip-api.com/json/{$ip}");
        
        if ($result['success'] && $result['status'] >= 200 && $result['status'] < 300) {
            $data = json_decode($result['body'], true);
            return $data['isp'] ?? 'Inconnu';
        }
        
        return 'Inconnu';
    }

    /**
     * Obtenir la géolocalisation
     */
    private function getGeolocation()
    {
        $ip = $this->getPublicIP();
        $result = $this->makeRequest("http://ip-api.com/json/{$ip}");
        
        if ($result['success'] && $result['status'] >= 200 && $result['status'] < 300) {
            $data = json_decode($result['body'], true);
            return ($data['city'] ?? 'Inconnue') . ', ' . ($data['country'] ?? 'Inconnu');
        }
        
        return 'Inconnue';
    }

    /**
     * Obtenir des informations détaillées de géolocalisation
     */
    private function getDetailedGeolocation()
    {
        $ip = $this->getPublicIP();
        $result = $this->makeRequest("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,org,hosting");
        
        if ($result['success'] && $result['status'] >= 200 && $result['status'] < 300) {
            return json_decode($result['body'], true);
        }
        
        return null;
    }

    /**
     * Effectue une requête HTTP avec gestion d'erreurs
     */
    private function makeRequest($url, $headers = [], $proxy = null)
    {
        $ch = curl_init();
        
        // Configuration de base
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ]);
        
        // Headers personnalisés
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }
        
        // Configuration proxy
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            return [
                'success' => false,
                'error' => $error ?: 'Erreur cURL inconnue'
            ];
        }
        
        return [
            'success' => true,
            'status' => $httpCode,
            'body' => $response,
            'headers' => []
        ];
    }
}

// Exécution du diagnostic
if (php_sapi_name() === 'cli') {
    $test = new SofaScoreAccessTest();
    $test->runDiagnostic();
}