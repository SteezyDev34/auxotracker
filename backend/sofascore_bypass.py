#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de contournement SofaScore avec techniques anti-détection avancées
Auteur: Assistant IA
Date: 2025-01-19
"""

import requests
import random
import time
import json
import sys
from urllib.parse import urljoin
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

class SofaScoreBypass:
    """
    Classe pour contourner la détection SofaScore avec plusieurs techniques
    """
    
    def __init__(self):
        """Initialisation avec configuration anti-détection"""
        self.session = requests.Session()
        self.setup_session()
        
        # Liste de User-Agents réalistes
        self.user_agents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        ]
        
        # Proxies gratuits français (à tester)
        self.proxies_list = [
            None,  # Pas de proxy (connexion directe)
            # Proxies HTTPS haute anonymité (meilleure qualité)
            {"http": "http://51.38.191.151:80", "https": "https://51.38.191.151:80"},
            {"http": "http://51.38.230.146:443", "https": "https://51.38.230.146:443"},
            # Proxies HTTP précédents (fallback)
            {"http": "http://51.15.228.52:8080", "https": "http://51.15.228.52:8080"},
            {"http": "http://188.165.49.152:80", "https": "http://188.165.49.152:80"},
            {"http": "http://217.182.210.152:80", "https": "http://217.182.210.152:80"},
        ]
        
        # Index du proxy actuel
        self.current_proxy_index = 0
        self.failed_proxies = set()  # Proxies qui ont échoué
        
        self.base_url = "https://www.sofascore.com"
        
    def setup_session(self):
        """Configuration de la session avec retry et timeouts"""
        retry_strategy = Retry(
            total=3,
            backoff_factor=1,
            status_forcelist=[429, 500, 502, 503, 504],
        )
        adapter = HTTPAdapter(max_retries=retry_strategy)
        self.session.mount("http://", adapter)
        self.session.mount("https://", adapter)
        
    def get_random_headers(self):
        """Génère des headers aléatoires réalistes"""
        user_agent = random.choice(self.user_agents)
        
        headers = {
            "User-Agent": user_agent,
            "Accept": "application/json, text/plain, */*",
            "Accept-Language": random.choice([
                "fr-FR,fr;q=0.9,en;q=0.8",
                "en-US,en;q=0.9,fr;q=0.8",
                "fr-FR,fr;q=0.8,en-US;q=0.5,en;q=0.3"
            ]),
            "Accept-Encoding": "gzip, deflate, br",
            "Referer": "https://www.sofascore.com/",
            "Origin": "https://www.sofascore.com",
            "Connection": "keep-alive",
            "Sec-Fetch-Dest": "empty",
            "Sec-Fetch-Mode": "cors",
            "Sec-Fetch-Site": "same-origin",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache"
        }
        
        # Ajouter des headers aléatoires supplémentaires
        if random.choice([True, False]):
            headers["DNT"] = "1"
            
        if "Chrome" in user_agent:
            headers["sec-ch-ua"] = '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"'
            headers["sec-ch-ua-mobile"] = "?0"
            headers["sec-ch-ua-platform"] = f'"{random.choice(["Windows", "macOS", "Linux"])}"'
            
        return headers
    
    def simulate_human_behavior(self):
        """Simule un comportement humain avec délais aléatoires"""
        # Délai aléatoire entre 1 et 5 secondes
        delay = random.uniform(1.0, 5.0)
        print(f"⏳ Simulation comportement humain: attente {delay:.2f}s")
        time.sleep(delay)
        
    def get_next_proxy(self):
        """
        Obtient le prochain proxy disponible avec rotation intelligente
        """
        available_proxies = [p for i, p in enumerate(self.proxies_list) if i not in self.failed_proxies]
        
        if not available_proxies:
            # Réinitialiser si tous les proxies ont échoué
            self.failed_proxies.clear()
            available_proxies = self.proxies_list
            print("🔄 Réinitialisation des proxies - nouvelle tentative")
        
        # Privilégier les proxies HTTPS (indices 1 et 2)
        https_proxies = [p for i, p in enumerate(available_proxies) if i in [1, 2] and i not in self.failed_proxies]
        if https_proxies:
            return random.choice(https_proxies)
        
        return random.choice(available_proxies) if available_proxies else None

    def make_request_with_fallback(self, url, max_attempts=5):
        """
        Effectue une requête avec fallback intelligent sur plusieurs proxies
        """
        for attempt in range(max_attempts):
            print(f"🔄 Tentative {attempt + 1}/{max_attempts}")
            
            # Rotation intelligente des proxies
            proxy = self.get_next_proxy()
            headers = self.get_random_headers()
            
            try:
                # Simulation comportement humain
                if attempt > 0:
                    self.simulate_human_behavior()
                
                print(f"📡 Requête vers: {url}")
                if proxy:
                    proxy_info = list(proxy.values())[0] if proxy else "Direct"
                    print(f"🔀 Via proxy: {proxy_info}")
                else:
                    print("🔀 Connexion directe (sans proxy)")
                
                response = self.session.get(
                    url,
                    headers=headers,
                    proxies=proxy,
                    timeout=30,
                    allow_redirects=True,
                    verify=False  # Ignorer les certificats SSL pour les proxies
                )
                
                print(f"📊 Status: {response.status_code}")
                
                if response.status_code == 200:
                    try:
                        data = response.json()
                        print("✅ Succès! Données JSON récupérées")
                        return data
                    except json.JSONDecodeError:
                        print("❌ Erreur: Réponse non-JSON")
                        print(f"Contenu: {response.text[:200]}...")
                        
                elif response.status_code == 403:
                    print("🚫 Erreur 403: Accès refusé - Marquage proxy comme défaillant")
                    if proxy:
                        proxy_index = self.proxies_list.index(proxy)
                        self.failed_proxies.add(proxy_index)
                    
                elif response.status_code == 429:
                    print("⏰ Erreur 429: Trop de requêtes")
                    time.sleep(random.uniform(10, 30))
                    
                else:
                    print(f"❌ Erreur HTTP: {response.status_code}")
                    
            except requests.exceptions.ProxyError as e:
                print(f"❌ Erreur proxy: {e}")
                if proxy:
                    proxy_index = self.proxies_list.index(proxy)
                    self.failed_proxies.add(proxy_index)
                    
            except requests.exceptions.RequestException as e:
                print(f"❌ Erreur réseau: {e}")
                
            # Délai avant nouvelle tentative
            if attempt < max_attempts - 1:
                delay = random.uniform(3, 8)
                print(f"⏳ Attente {delay:.1f}s avant nouvelle tentative...")
                time.sleep(delay)
                
        print("💥 Échec de toutes les tentatives")
        return None
    
    def get_tennis_events(self, date="2025-01-19"):
        """
        Récupère les événements tennis pour une date donnée
        """
        url = f"{self.base_url}/api/v1/sport/tennis/scheduled-events/{date}"
        
        print(f"🎾 Récupération des événements tennis pour {date}")
        print("=" * 60)
        
        # Première visite de la page principale pour établir une session
        print("🏠 Visite de la page principale...")
        try:
            main_response = self.session.get(
                self.base_url,
                headers=self.get_random_headers(),
                timeout=15
            )
            print(f"📊 Page principale: {main_response.status_code}")
        except:
            print("⚠️  Impossible d'accéder à la page principale")
        
        # Délai avant requête API
        self.simulate_human_behavior()
        
        # Requête API avec fallback
        return self.make_request_with_fallback(url)

def main():
    """Fonction principale"""
    if len(sys.argv) > 1:
        date = sys.argv[1]
    else:
        date = "2025-01-19"
    
    print("🚀 SofaScore Bypass - Démarrage")
    print(f"📅 Date cible: {date}")
    print("=" * 60)
    
    bypass = SofaScoreBypass()
    data = bypass.get_tennis_events(date)
    
    if data:
        print("\n🎉 SUCCÈS!")
        print("=" * 60)
        print(json.dumps(data, indent=2, ensure_ascii=False))
    else:
        print("\n💔 ÉCHEC")
        print("Impossible de récupérer les données")
        sys.exit(1)

if __name__ == "__main__":
    main()