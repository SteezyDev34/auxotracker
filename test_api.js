// Script de test pour vérifier l'API des bookmakers et user-bookmakers
const API_BASE_URL = 'http://localhost:8001/api';

// Fonction pour tester l'API des bookmakers
async function testBookmakersAPI() {
    try {
        console.log('Test de l\'API des bookmakers...');
        const response = await fetch(`${API_BASE_URL}/bookmakers`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Bookmakers récupérés:', data);
        return data;
    } catch (error) {
        console.error('Erreur lors du test de l\'API des bookmakers:', error);
        return null;
    }
}

// Fonction pour tester l'API user-bookmakers (nécessite un token)
async function testUserBookmakersAPI(token) {
    try {
        console.log('Test de l\'API user-bookmakers...');
        const response = await fetch(`${API_BASE_URL}/user-bookmakers`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('User-bookmakers récupérés:', data);
        return data;
    } catch (error) {
        console.error('Erreur lors du test de l\'API user-bookmakers:', error);
        return null;
    }
}

// Exécution des tests
async function runTests() {
    console.log('=== Début des tests API ===');
    
    // Test des bookmakers (pas besoin d'authentification)
    await testBookmakersAPI();
    
    // Pour tester user-bookmakers, il faudrait un token valide
    console.log('\nPour tester l\'API user-bookmakers, connectez-vous dans l\'application et récupérez le token depuis localStorage.');
    
    console.log('=== Fin des tests API ===');
}

// Exécution si le script est appelé directement
if (typeof window === 'undefined') {
    // Node.js environment
    const fetch = require('node-fetch');
    runTests();
} else {
    // Browser environment
    window.runAPITests = runTests;
    console.log('Fonctions de test disponibles: runAPITests()');
}