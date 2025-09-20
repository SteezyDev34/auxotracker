#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Test rapide des proxies pour SofaScore
"""

import requests
import json
import sys

def test_proxy(proxy_config, proxy_name):
    """
    Teste un proxy spécifique
    """
    print(f"\n🔍 Test du proxy: {proxy_name}")
    
    headers = {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8",
        "Accept-Encoding": "gzip, deflate, br",
        "Referer": "https://www.sofascore.com/",
        "Origin": "https://www.sofascore.com"
    }
    
    url = "https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/2025-01-19"
    
    try:
        print(f"📡 Connexion via: {proxy_config if proxy_config else 'Direct'}")
        
        response = requests.get(
            url,
            headers=headers,
            proxies=proxy_config,
            timeout=15,
            verify=False
        )
        
        print(f"📊 Status: {response.status_code}")
        
        if response.status_code == 200:
            try:
                data = response.json()
                print("✅ SUCCÈS! Données JSON récupérées")
                print(f"📄 Taille réponse: {len(response.text)} caractères")
                if 'events' in data:
                    print(f"🎾 Événements trouvés: {len(data.get('events', []))}")
                return True
            except json.JSONDecodeError:
                print("❌ Erreur: Réponse non-JSON")
                print(f"Contenu: {response.text[:100]}...")
        else:
            print(f"❌ Erreur HTTP: {response.status_code}")
            if response.status_code == 403:
                print("🚫 Accès refusé par SofaScore")
            
    except requests.exceptions.ProxyError as e:
        print(f"❌ Erreur proxy: {e}")
    except requests.exceptions.Timeout:
        print("⏰ Timeout - proxy trop lent")
    except requests.exceptions.RequestException as e:
        print(f"❌ Erreur réseau: {e}")
    
    return False

def main():
    """
    Test de tous les proxies
    """
    print("🚀 Test rapide des proxies SofaScore")
    print("=" * 50)
    
    # Liste des proxies à tester
    proxies_to_test = [
        (None, "Connexion directe"),
        ({"http": "http://51.38.191.151:80", "https": "https://51.38.191.151:80"}, "Proxy HTTPS FR 1"),
        ({"http": "http://51.38.230.146:443", "https": "https://51.38.230.146:443"}, "Proxy HTTPS FR 2"),
        ({"http": "http://51.15.228.52:8080", "https": "http://51.15.228.52:8080"}, "Proxy HTTP FR 1"),
        ({"http": "http://188.165.49.152:80", "https": "http://188.165.49.152:80"}, "Proxy HTTP FR 2"),
        ({"http": "http://217.182.210.152:80", "https": "http://217.182.210.152:80"}, "Proxy HTTP FR 3"),
    ]
    
    successful_proxies = []
    
    for proxy_config, proxy_name in proxies_to_test:
        if test_proxy(proxy_config, proxy_name):
            successful_proxies.append((proxy_config, proxy_name))
            print(f"✅ {proxy_name} fonctionne!")
            break  # Arrêter au premier proxy qui fonctionne
        else:
            print(f"❌ {proxy_name} a échoué")
    
    print("\n" + "=" * 50)
    if successful_proxies:
        print(f"🎉 Proxy fonctionnel trouvé: {successful_proxies[0][1]}")
    else:
        print("😞 Aucun proxy fonctionnel trouvé")
    
    return len(successful_proxies) > 0

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)