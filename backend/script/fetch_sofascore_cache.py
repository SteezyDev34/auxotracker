#!/usr/bin/env python3
"""
Fetch Sofascore API data via headless Chrome and write to cache files.

Usage:
  python3 fetch_sofascore_cache.py --sport tennis [--date 2026-07-11]
  python3 fetch_sofascore_cache.py --sport football [--date 2026-07-11]
  python3 fetch_sofascore_cache.py --sport all [--date 2026-07-11]

Why: Sofascore blocks server-side HTTP clients (403/Varnish IP ban).
     Chrome headless uses a real TLS fingerprint + browser session,
     which bypasses the block. Data is written to cache files; PHP
     artisan commands then run with --offline and read from cache.
"""

import argparse
import base64
import hashlib
import json
import os
import random
import re
import subprocess
import sys
import time
import unicodedata
from datetime import date, datetime, timezone
from pathlib import Path

import requests

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import WebDriverException, TimeoutException

try:
    # Import paresseux/optionnel : le module dépend de `websockets`, absent de
    # l'environnement Python système utilisé en prod pour le mode Selenium
    # (défaut). Ne pas planter tout le script pour ceux qui n'utilisent pas
    # --transport extension.
    from sofascore_extension_bridge import ExtensionBridge
except ImportError:
    ExtensionBridge = None

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

STORAGE_BASE = Path(__file__).parent.parent / "storage" / "app" / "sofascore_cache"

# TEMPORAIRE (demande utilisateur, 2026-09-21) : exclut les tournois UTR et
# Doubles du fetch tennis pour réduire le volume de requêtes en direct
# (moins de joueurs/matchs à traiter = moins de risque de rate-limit/challenge
# Sofascore). Ces catégories ne sont pas exploitées aujourd'hui par le
# scoring "match serré"/martingale. À retirer si on veut les réintégrer.
TENNIS_EXCLUDE_TOURNAMENT_KEYWORDS = ("utr", "doubles")


def is_tennis_doubles_event(event: dict) -> bool:
    """TEMPORAIRE (demande utilisateur, 2026-10-01) : match de double, quel
    que soit le nom du tournoi (des doubles passent sous des uniqueTournament
    de simple, ex. "Colombus, USA Men Singles"). Sofascore marque les paires
    avec team.type == 2 (1 = joueur seul)."""
    for side in ("homeTeam", "awayTeam"):
        team = event.get(side) or {}
        if team.get("type") == 2 or "/" in (team.get("name") or ""):
            return True
    return False

SPORTS_CONFIG = {
    "tennis": {
        "mode": "live_featured",
        "live_url": "https://www.sofascore.com/api/v1/sport/tennis/events/live",
        "featured_url": "https://www.sofascore.com/api/v1/odds/1/featured-events/tennis",
    },
    "football": {
        "mode": "scheduled_tournaments",
        "sport_slug": "football",
        "cache_subdir": "football_schedule",
    },
    "basketball": {
        "mode": "scheduled_tournaments",
        "sport_slug": "basketball",
        "cache_subdir": "basketball_schedule",
    },
    "handball": {
        "mode": "scheduled_tournaments",
        "sport_slug": "handball",
        "cache_subdir": "handball_schedule",
    },
    "volleyball": {
        "mode": "scheduled_tournaments",
        "sport_slug": "volleyball",
        "cache_subdir": "volleyball_schedule",
    },
    "ice-hockey": {
        "mode": "scheduled_tournaments",
        "sport_slug": "ice-hockey",
        "cache_subdir": "ice_hockey_schedule",
    },
    "rugby": {
        "mode": "scheduled_tournaments",
        "sport_slug": "rugby",
        "cache_subdir": "rugby_schedule",
    },
    "futsal": {
        "mode": "scheduled_tournaments",
        "sport_slug": "futsal",
        "cache_subdir": "futsal_schedule",
    },
    "baseball": {
        "mode": "scheduled_tournaments",
        "sport_slug": "baseball",
        "cache_subdir": "baseball_schedule",
    },
}

# ---------------------------------------------------------------------------
# Selenium helpers
# ---------------------------------------------------------------------------

def build_driver() -> webdriver.Chrome:
    opts = Options()
    opts.add_argument("--headless=new")
    opts.add_argument("--no-sandbox")
    opts.add_argument("--disable-dev-shm-usage")
    opts.add_argument("--disable-gpu")
    opts.add_argument("--disable-software-rasterizer")
    opts.add_argument("--disable-blink-features=AutomationControlled")
    opts.add_argument("--disable-web-security")  # needed to fetch api.sofascore.com images from sofascore.com context
    opts.add_experimental_option("excludeSwitches", ["enable-automation"])
    opts.add_experimental_option("useAutomationExtension", False)
    opts.add_argument(
        "--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
    )
    driver = webdriver.Chrome(options=opts)
    driver.set_page_load_timeout(60)
    return driver


def fetch_json(driver, url: str, retries: int = 3, with_status: bool = False):
    """Execute a fetch() inside the Sofascore page context and return parsed JSON.

    Si with_status=True, retourne un tuple (data, http_status) où http_status
    est le vrai code HTTP de la réponse (404, 429, ...) quand il a pu être
    obtenu, ou None si l'échec est survenu avant d'avoir une réponse (timeout,
    exception réseau) — pour distinguer un vrai 404 d'un raté transitoire.

    `driver` est soit un webdriver.Chrome (Selenium), soit un ExtensionBridge
    (--transport extension) — même signature d'appel dans les deux cas.
    """
    if (ExtensionBridge is not None and isinstance(driver, ExtensionBridge)):
        return driver.fetch_json(url, retries=retries, with_status=with_status)

    script = """
        const [url, callback] = arguments;
        fetch(url, {
            headers: {
                'Accept': 'application/json, text/plain, */*',
                'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8',
            },
            credentials: 'include',
        })
        .then(r => r.json().then(data => callback({ok: r.ok, status: r.status, data}))
                          .catch(err => callback({ok: false, status: r.status, error: err.toString()})))
        .catch(err => callback({ok: false, status: null, error: err.toString()}));
    """
    last_status = None
    for attempt in range(1, retries + 1):
        try:
            result = driver.execute_async_script(script, url)
            if result:
                last_status = result.get("status")
            if result and result.get("ok"):
                return (result["data"], last_status) if with_status else result["data"]
            print(f"  ⚠️  fetch() KO (tentative {attempt}/{retries}): {result}", file=sys.stderr)
            if last_status == 404:
                # Un 404 est définitif — la ressource n'existe pas, retenter ne
                # changera rien. On sort immédiatement au lieu d'attendre.
                break
            is_challenge = (
                last_status == 403
                and isinstance(result, dict)
                and (result.get("data") or {}).get("error", {}).get("reason") == "challenge"
            )
            if is_challenge:
                # Rate-limit/anti-bot côté Sofascore (distinct du ban IP initial) :
                # retenter tout de suite ne sert à rien, on attend nettement plus
                # longtemps pour laisser la fenêtre de blocage se refermer.
                time.sleep(15)
            else:
                time.sleep(2)
        except WebDriverException as e:
            print(f"  ⚠️  WebDriverException (tentative {attempt}/{retries}): {e}", file=sys.stderr)
            if "invalid session id" in str(e).lower() or "session deleted" in str(e).lower():
                # Le navigateur a crashé (session Chrome morte) : retenter ne
                # sert à rien, tout appel suivant échouera pareil jusqu'à la
                # fin du script (déjà vu deux fois en prod : le run continue
                # à boucler pendant des heures sans plus rien récupérer).
                # On sort immédiatement avec un code dédié pour que
                # cache_tennis.sh redémarre automatiquement une session fraîche.
                print("  💥 Session Chrome morte (crash navigateur) — arrêt immédiat pour relance automatique", file=sys.stderr)
                sys.exit(42)
            time.sleep(2)
    return (None, last_status) if with_status else None


def warm_session(driver: webdriver.Chrome, retries: int = 3):
    """Visit Sofascore homepage to get cookies and session.

    Le tout premier chargement de sofascore.com est régulièrement plus lent
    que le timeout de page (probablement une vérification anti-bot/JS qui se
    résout une seule fois) — un rechargement juste après aboutit typiquement
    en quelques secondes. On retente donc ici avant d'abandonner, plutôt que
    de laisser planter tout le script sur ce premier chargement lent."""
    print("🌐 Ouverture de sofascore.com pour initialiser la session...")
    for attempt in range(1, retries + 1):
        try:
            driver.get("https://www.sofascore.com/")
            time.sleep(3)
            print(f"   Titre: {driver.title[:80]}")
            return
        except TimeoutException:
            print(f"   ⚠️  Timeout chargement sofascore.com (tentative {attempt}/{retries})", file=sys.stderr)
    raise TimeoutException(f"warm_session: sofascore.com inaccessible après {retries} tentatives")


# ---------------------------------------------------------------------------
# Tennis scheduled events (par tournoi du jour)
# ---------------------------------------------------------------------------

def fetch_tennis_scheduled_events(driver: webdriver.Chrome, sched_dir, target_date: str) -> list:
    """
    Pour chaque tournoi dans les pages tennis_schedule du jour, fetche
    unique-tournament/{id}/events/next/0 et filtre les events dont
    startTimestamp tombe dans la journée cible (±1h de marge).
    """
    # Bornes de la journée cible en timestamp UTC
    dt = datetime.strptime(target_date, "%Y-%m-%d")
    day_start = int(dt.replace(tzinfo=timezone.utc).timestamp()) - 3600       # -1h marge
    day_end   = int(dt.replace(tzinfo=timezone.utc).timestamp()) + 86400 + 3600  # +1h marge

    # Extraire les uniqueTournament.id depuis les pages
    unique_ids = {}
    excluded_count = 0
    for p in sorted(sched_dir.glob("page_*.json")):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for entry in data.get("scheduled", []):
                ut = entry.get("tournament", {}).get("uniqueTournament", {})
                uid = ut.get("id")
                name = ut.get("name", f"ID:{uid}")
                if uid and uid not in unique_ids:
                    if any(kw in name.lower() for kw in TENNIS_EXCLUDE_TOURNAMENT_KEYWORDS):
                        excluded_count += 1
                        continue
                    unique_ids[uid] = name
        except Exception:
            pass

    total = len(unique_ids)
    print(f"\n  🎾 Fetch events pour {total} tournois tennis du jour...")
    if excluded_count:
        print(f"  ⏭️  {excluded_count} tournoi(s) exclus (UTR/Doubles, filtre temporaire)")

    all_events = []
    seen_ids = set()

    for i, (uid, name) in enumerate(unique_ids.items(), 1):
        # Cache fichier pour éviter de re-fetcher si déjà fait aujourd'hui
        cache_file = sched_dir / f"tournament_events_{uid}.json"
        if cache_file.exists():
            try:
                cached = json.loads(cache_file.read_text())
                if not cached.get("_negative_cache"):
                    events = cached.get("events", [])
                    today_events = [e for e in events if day_start <= e.get("startTimestamp", 0) <= day_end]
                    for e in today_events:
                        if e.get("id") not in seen_ids:
                            seen_ids.add(e["id"])
                            all_events.append(e)
                    print(f"  [{i}/{total}] {name} — cache ({len(today_events)} matchs aujourd'hui)")
                    continue
            except Exception:
                pass

        url = f"https://www.sofascore.com/api/v1/unique-tournament/{uid}/scheduled-events/{target_date}"
        data = fetch_json(driver, url)

        # scheduled-events/{date} renvoie aussi des matchs des jours voisins
        # (ex. 168 matchs terminés du 30/09 dans la réponse du 01/10, constaté
        # le 2026-10-01) — même filtre de journée que la branche cache.
        today_events = []
        if data and "error" not in data:
            for e in data.get("events", []):
                if not (day_start <= e.get("startTimestamp", 0) <= day_end):
                    continue
                if e.get("id") not in seen_ids:
                    seen_ids.add(e["id"])
                    today_events.append(e)
                    all_events.append(e)

        if today_events:
            cache_file.write_text(json.dumps({"events": today_events}, ensure_ascii=False))
            print(f"  [{i}/{total}] {name} — {len(today_events)} matchs")
        else:
            cache_file.write_text(json.dumps({"_negative_cache": True, "fetched_at": datetime.now().isoformat()}, ensure_ascii=False))
        time.sleep(random.uniform(0.7, 1.4))

    print(f"  📊 Total matchs tennis du jour: {len(all_events)}")
    return all_events


# ---------------------------------------------------------------------------
# Tennis (live + featured)
# ---------------------------------------------------------------------------

def _browser_fetch_image(driver, url: str):
    """Fetch une image via fetch() dans le contexte du navigateur (credentials:
    include) et retourne les octets décodés, ou None en cas d'échec. Contourne
    le blocage HTTP direct (403 confirmé) qui touche aussi bien l'API JSON que
    les images sur ce serveur.

    `driver` est soit un webdriver.Chrome (Selenium), soit un ExtensionBridge."""
    if os.environ.get("SKIP_LOGOS") == "1":
        return None
    if (ExtensionBridge is not None and isinstance(driver, ExtensionBridge)):
        return driver.fetch_binary(url)

    script = """
        const [url, callback] = arguments;
        fetch(url, {credentials: 'include'})
        .then(r => {
            if (!r.ok) return callback({ok: false, status: r.status});
            return r.arrayBuffer().then(buf => {
                const bytes = new Uint8Array(buf);
                let binary = '';
                for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
                callback({ok: true, data: btoa(binary)});
            });
        })
        .catch(err => callback({ok: false, error: err.toString()}));
    """
    try:
        result = driver.execute_async_script(script, url)
        if result and result.get("ok") and result.get("data"):
            return base64.b64decode(result["data"])
    except Exception:
        pass
    return None


def extract_team_ids_from_event_files(cache_dir: Path, pattern: str = "tournament_events_*.json") -> dict:
    """Scanne les fichiers d'événements déjà écrits (tournament_events_*.json)
    pour en extraire les ID+nom des équipes/joueurs (homeTeam/awayTeam)."""
    ids = {}
    for p in cache_dir.glob(pattern):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for event in data.get("events", []):
                for side in ("homeTeam", "awayTeam"):
                    team = event.get(side)
                    if team and team.get("id"):
                        ids[team["id"]] = team.get("name", f"ID:{team['id']}")
        except Exception:
            pass
    return ids


def _fetch_teams_with_logo_from_prod(sport_slug: str) -> set:
    """Sofascore_id des équipes qui ont déjà un logo en base de prod, pour ce
    sport — permet de ne pas retélécharger un logo déjà présent en base quand
    le cache local a été archivé/vidé entre deux runs. Ne concerne QUE les
    logos (effectifs et stats sont, eux, toujours refetchés à chaque run)."""
    try:
        url = f"https://api.auxotracker.astcavex.fr/api/sports/{sport_slug}/teams/with-logo"
        r = requests.get(url, timeout=10)
        if r.status_code == 200 and r.json().get("success"):
            # sofascore_id est stocké en `string` côté DB (migration teams) —
            # normaliser en int pour matcher les clés de team_ids (JSON Sofascore).
            out = set()
            for v in r.json().get("data", []):
                try:
                    out.add(int(v))
                except (TypeError, ValueError):
                    pass
            return out
    except Exception as e:
        print(f"  ⚠️  Impossible de récupérer les équipes déjà logotées en prod: {e}", file=sys.stderr)
    return set()


def fetch_team_logos(driver: webdriver.Chrome, cache_dir: Path, team_ids: dict, label: str) -> None:
    """
    Télécharge les logos des équipes/joueurs apparaissant dans les matchs du
    jour, via fetch navigateur (même contrainte que les logos de tournois :
    bloqué en HTTP direct — 403 confirmé).

    Écrit dans {cache_dir}/team_logos/{sofascoreId}.png — chemin lu par
    TeamLogoService.findCachedTeamLogo() (service PHP partagé).
    """
    logos_dir = cache_dir / "team_logos"
    logos_dir.mkdir(parents=True, exist_ok=True)

    total = len(team_ids)
    if total == 0:
        return

    already_in_prod = _fetch_teams_with_logo_from_prod(label)
    if already_in_prod:
        print(f"  ⏭️  {len(already_in_prod)} équipe(s) déjà logotée(s) en prod — skip")

    print(f"\n  📸 Fetch logos équipes pour {total} équipes {label}...")

    downloaded = 0
    skipped_prod = 0
    for i, (tid, name) in enumerate(team_ids.items(), 1):
        logo_file = logos_dir / f"{tid}.png"
        if logo_file.exists():
            continue
        if tid in already_in_prod:
            skipped_prod += 1
            continue

        img_url = f"https://api.sofascore.com/api/v1/team/{tid}/image"
        content = _browser_fetch_image(driver, img_url)
        if content:
            logo_file.write_bytes(content)
            downloaded += 1
        time.sleep(random.uniform(1.0, 1.8))

    if skipped_prod:
        print(f"  📊 Logos ignorés (déjà en prod): {skipped_prod}")

    print(f"  📊 Logos équipes téléchargés: {downloaded}/{total}")


def fetch_tennis_tournament_logos(driver: webdriver.Chrome, unique_ids: dict) -> None:
    """
    Télécharge les logos des tournois tennis via un fetch JS dans le contexte du
    navigateur (mêmes contraintes que les images joueurs : l'endpoint image est
    bloqué en HTTP direct depuis le serveur — 403 confirmé — mais accessible
    via Chrome avec une session/cookies réels).

    Écrit dans tennis_leagues/league_logos/{uid}-light.png — le chemin lu par
    LeagueLogoService.findCachedLeagueLogo(), le service PHP partagé déjà
    utilisé par football/basketball/etc. pour peupler `leagues.img`.
    """
    logos_dir = STORAGE_BASE / "tennis_leagues" / "league_logos"
    logos_dir.mkdir(parents=True, exist_ok=True)

    total = len(unique_ids)
    print(f"\n  📸 Fetch logos pour {total} tournois tennis...")

    downloaded = 0
    for i, (uid, name) in enumerate(unique_ids.items(), 1):
        logo_file = logos_dir / f"{uid}-light.png"
        if logo_file.exists():
            continue

        img_url = f"https://api.sofascore.com/api/v1/unique-tournament/{uid}/image"
        try:
            content = _browser_fetch_image(driver, img_url)
            if content:
                logo_file.write_bytes(content)
                downloaded += 1
                print(f"  [{i}/{total}] {name} — logo téléchargé")
        except Exception as e:
            print(f"  [{i}/{total}] {name} — erreur logo: {e}", file=sys.stderr)
        time.sleep(random.uniform(1.0, 1.8))

    print(f"  📊 Logos tournois téléchargés: {downloaded}/{total}")


def fetch_tennis(driver: webdriver.Chrome, target_date: str, fetch_players: bool = True):
    cfg = SPORTS_CONFIG["tennis"]
    print(f"\n🎾 Tennis — date: {target_date}")

    all_events = []

    # 1. Scheduled tournaments (paginated) — récupère Wimbledon et tous les matchs du jour
    sched_dir = STORAGE_BASE / "tennis_schedule" / target_date
    sched_dir.mkdir(parents=True, exist_ok=True)
    page = 1
    while True:
        url = f"https://www.sofascore.com/api/v1/sport/tennis/scheduled-tournaments/{target_date}/page/{page}"
        print(f"  📡 Scheduled page {page}: {url}")
        data = fetch_json(driver, url)
        if data is None or "error" in (data or {}):
            print(f"  ⚠️  Fin de pagination scheduled à la page {page}")
            break
        tournaments = data.get("scheduled", data.get("uniqueTournaments", []))
        has_next = data.get("hasNextPage", False)
        print(f"  ✅ {len(tournaments)} tournois (page {page}), hasNextPage={has_next}")
        (sched_dir / f"page_{page}.json").write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
        print(f"  💾 Écrit: {sched_dir}/page_{page}.json")
        if not has_next:
            break
        page += 1

    # 1bis. Logos des tournois (fetch navigateur — endpoint image bloqué en HTTP direct)
    # SKIP_LOGOS=1 : contournement temporaire — le fetch d'images (domaine
    # api.sofascore.com, différent de www.sofascore.com) bloque en silence via
    # le tunnel extension en ce moment (2026-09-29), en cours de diagnostic.
    unique_tournament_ids = extract_unique_tournament_ids(sched_dir)
    if unique_tournament_ids and os.environ.get("SKIP_LOGOS") != "1":
        fetch_tennis_tournament_logos(driver, unique_tournament_ids)
    elif unique_tournament_ids:
        print("  ⏭️  Logos tournois ignorés (SKIP_LOGOS=1)")

    # 2. Events par tournoi (scheduled pour aujourd'hui) → source_scheduled_{date}.json
    scheduled_events = fetch_tennis_scheduled_events(driver, sched_dir, target_date)
    if scheduled_events:
        out_path = STORAGE_BASE / f"source_scheduled_{target_date}.json"
        out_path.write_text(json.dumps({"events": scheduled_events}, ensure_ascii=False), encoding="utf-8")
        print(f"  💾 source_scheduled: {len(scheduled_events)} matchs → {out_path}")
        all_events.extend(scheduled_events)

    # 3. Live + featured
    for key, url, filename_key, json_key in [
        ("live",     cfg["live_url"],     "source_live",     "events"),
        ("featured", cfg["featured_url"], "source_featured", "featuredEvents"),
    ]:
        print(f"  📡 Fetching [{key}]: {url}")
        data = fetch_json(driver, url)
        if data is None:
            print(f"  ❌ Impossible de récupérer [{key}]", file=sys.stderr)
            continue

        events = data.get(json_key, [])
        all_events.extend(events)
        print(f"  ✅ {len(events)} événement(s) [{key}]")

        out_path = STORAGE_BASE / f"{filename_key}_{target_date}.json"
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
        print(f"  💾 Écrit: {out_path}")

    # Traite les matchs du jour dans l'ordre de leur heure de démarrage : les
    # joueurs/H2H/cotes/point-by-point des matchs qui commencent le plus tôt
    # sont fetchés en premier, donc déjà prêts pour les premiers cycles de
    # sync prod (voir maybe_run_prod_sync) au lieu d'attendre l'ordre brut
    # renvoyé par l'API (par tournoi, pas par heure).
    seen_event_ids = set()
    deduped_events = []
    doubles_count = 0
    for e in all_events:
        if is_tennis_doubles_event(e):
            doubles_count += 1
            continue
        eid = e.get("id")
        if eid is not None and eid in seen_event_ids:
            continue
        if eid is not None:
            seen_event_ids.add(eid)
        deduped_events.append(e)
    deduped_events.sort(key=lambda e: e.get("startTimestamp", 0))
    all_events = deduped_events
    if doubles_count:
        print(f"  ⏭️  {doubles_count} match(s) de double exclus (filtre temporaire)")

    # Fetch player details for all players in the events + all already-cached players
    if fetch_players:
        cache_dir = STORAGE_BASE / "tennis_players"
        current_year = int(target_date[:4])

        # Écrire tout de suite les fichiers event_*.json (lecture seule du
        # cache, aucun appel Sofascore) : sans ça, ils ne sont créés que par
        # le tennis:import-from-schedule de fin de cache_tennis.sh, et les
        # imports par lot (local + prod) n'ont aucun match du jour à créer
        # pendant tout le run (constaté le 2026-10-01 : 1 seul match en prod).
        print("  📅 Mise en cache des matchs du jour (import-from-schedule --offline)...")
        _run(["docker", "compose", "exec", "-T", "web", "php", "artisan",
              "tennis:import-from-schedule", "--offline"], timeout=600)

        # Traitement par LOT de MATCH_BATCH_SIZE matchs, dans l'ordre de
        # démarrage (all_events est déjà trié plus haut) : pour chaque lot,
        # on fetch les joueurs concernés (détails, stats, classement,
        # H2H/cotes, point-by-point) puis on importe en local + calcule les
        # probas + envoie en prod, avant de passer au lot suivant. Le temps
        # que ça prend (~2min30 d'import + calcul + sync) EST le délai voulu
        # entre deux paquets de requêtes Sofascore — pas un sleep()
        # artificiel. Un lot de 10 (plutôt qu'un seul match) ramène le total
        # à quelques heures au lieu d'un cycle par match qui, mesuré, donnait
        # ~34h pour un run complet (2026-09-30) — demandé explicitement par
        # l'utilisateur pour garder l'esprit "ordre de démarrage + envoi
        # progressif" sans que le coût fixe de l'import/sync domine le run.
        MATCH_BATCH_SIZE = 10
        processed_player_ids = set()
        for i, event in enumerate(all_events, 1):
            home = event.get("homeTeam") or {}
            away = event.get("awayTeam") or {}
            match_player_ids = []
            for team in (home, away):
                pid = team.get("id")
                if pid:
                    match_player_ids.append((pid, team.get("name", f"ID:{pid}")))
            if not match_player_ids:
                continue

            match_label = f"{home.get('name', '?')} vs {away.get('name', '?')}"
            print(f"\n🎾 Match {i}/{len(all_events)}: {match_label}")

            fetch_player_details(driver, match_player_ids, cache_dir, current_year)
            fetch_player_rankings(driver, match_player_ids, cache_dir)
            fetch_h2h_and_odds_for_events(driver, [event], cache_dir)
            fetch_player_point_by_point_history(driver, match_player_ids, cache_dir)
            processed_player_ids.update(pid for pid, _ in match_player_ids)

            # Import local + calcul probas + sync prod tous les
            # MATCH_BATCH_SIZE matchs (force=True : pas d'attente
            # d'intervalle, seul le compteur de matchs traités décide).
            # archive=False : le fetch tourne encore, on ne doit pas vider
            # les dossiers de cache sous ses pieds (voir maybe_run_prod_sync)
            # — seul le flush final archive.
            if i % MATCH_BATCH_SIZE == 0:
                maybe_run_local_import(force=True)

        # Joueurs déjà en cache (jours précédents) mais pas liés à un match
        # du jour : moins urgent (pas de match imminent), traités en bloc
        # après la boucle par match, avec l'ancien rythme par intervalle.
        players_dir = cache_dir / "players"
        extra_ids = {}
        for f in players_dir.glob("player_basic_*.json"):
            try:
                d = json.loads(f.read_text())
                pid = d.get("sofascore_id")
                name = d.get("name", f"ID:{pid}")
                if pid and pid not in processed_player_ids:
                    extra_ids[pid] = name
            except Exception:
                pass
        if extra_ids:
            extra_list = list(extra_ids.items())
            print(f"\n👤 Joueurs additionnels (cache, hors matchs du jour): {len(extra_list)}")
            fetch_player_details(driver, extra_list, cache_dir, current_year)
            fetch_player_rankings(driver, extra_list, cache_dir)
            maybe_run_local_import()

    # Flush final : importer/synchroniser/archiver le dernier lot partiel,
    # même s'il n'a pas atteint le seuil normal (sinon perdu jusqu'au
    # prochain run). C'est le SEUL point du run qui archive réellement.
    maybe_run_local_import(force=True)
    maybe_run_prod_sync(force=True, archive=True)


# ---------------------------------------------------------------------------
# Tennis player details (details + stats + images)
# ---------------------------------------------------------------------------

def extract_player_ids_from_events(events: list) -> list:
    """Extract unique player sofascore IDs from a list of events."""
    ids = {}
    for event in events:
        for side in ("homeTeam", "awayTeam"):
            team = event.get(side)
            if team and team.get("id"):
                ids[team["id"]] = team.get("name", f"ID:{team['id']}")
    return list(ids.items())  # [(id, name), ...]


def _slugify(text: str) -> str:
    text = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")
    text = re.sub(r"[^a-zA-Z0-9]+", "-", text).strip("-").lower()
    return text or "player"


def _build_player_basic(details_json: dict):
    """Aplatit la réponse Sofascore team/{id} (imbriquée sous 'team') vers le
    format plat attendu par ImportTennisPlayersFromCache::processBasicPlayerCacheFile
    (mêmes clés que les champs fillable du modèle Team)."""
    team = details_json.get("team") if isinstance(details_json, dict) else None
    if not team or not team.get("id"):
        return None

    info = team.get("playerTeamInfo") or {}
    basic = {
        "sofascore_id": team["id"],
        "name": team.get("name"),
        "slug": team.get("slug") or _slugify(team.get("name") or str(team["id"])),
        "gender": team.get("gender"),
    }
    if isinstance(team.get("ranking"), int):
        basic["ranking"] = team["ranking"]
    country_code = (team.get("country") or {}).get("alpha2")
    if country_code:
        basic["country_code"] = country_code

    birth_ts = info.get("birthDateTimestamp")
    if birth_ts:
        basic["date_of_birth"] = time.strftime("%Y-%m-%d", time.gmtime(birth_ts))

    height_m = info.get("height")
    if isinstance(height_m, (int, float)) and height_m > 0:
        basic["height"] = round(height_m * 100)  # m -> cm (colonne stockée en cm)

    weight_kg = info.get("weight")
    if isinstance(weight_kg, (int, float)) and weight_kg > 0:
        basic["weight"] = round(weight_kg)

    plays = info.get("plays")
    if plays:
        basic["playing_hand"] = "left" if "left" in plays.lower() else ("right" if "right" in plays.lower() else plays)

    if info.get("birthplace"):
        basic["birthplace"] = info["birthplace"]
    if info.get("residence"):
        basic["residence"] = info["residence"]

    return basic


def _fetch_tennis_players_with_details_from_prod() -> set:
    """Sofascore_id des joueurs tennis dont les détails statiques (naissance,
    taille, main directrice...) sont déjà en base de prod — permet de ne pas
    refaire ce fetch quand le cache local a été archivé/vidé, ces infos ne
    changeant plus une fois connues. Ne concerne QUE les détails."""
    try:
        url = "https://api.auxotracker.astcavex.fr/api/tennis/players/with-details"
        r = requests.get(url, timeout=10)
        if r.status_code == 200 and r.json().get("success"):
            out = set()
            for v in r.json().get("data", []):
                try:
                    out.add(int(v))
                except (TypeError, ValueError):
                    pass
            return out
    except Exception as e:
        print(f"  ⚠️  Impossible de récupérer les joueurs tennis déjà détaillés en prod: {e}", file=sys.stderr)
    return set()


def _fetch_tennis_players_with_fresh_season_stats_from_prod(days: int = 7) -> set:
    """Sofascore_id des joueurs tennis dont les stats de la saison en cours
    ont été fetchées il y a moins de `days` jours en base de prod — évite de
    refetcher year-statistics à chaque run alors que le cache local est
    archivé/vidé après chaque sync. Les stats saison ne changent
    significativement qu'après plusieurs matchs joués, pas besoin de les
    refetcher tous les jours (réduit fortement le volume de requêtes en
    direct, utile pour éviter le rate-limit/challenge Sofascore)."""
    try:
        url = f"https://api.auxotracker.astcavex.fr/api/tennis/players/with-fresh-season-stats?days={days}"
        r = requests.get(url, timeout=10)
        if r.status_code == 200 and r.json().get("success"):
            out = set()
            for v in r.json().get("data", []):
                try:
                    out.add(int(v))
                except (TypeError, ValueError):
                    pass
            return out
    except Exception as e:
        print(f"  ⚠️  Impossible de récupérer les joueurs tennis avec stats fraîches en prod: {e}", file=sys.stderr)
    return set()


# Le dossier "tennis_players" (comme tout top-level dir sous sofascore_cache)
# est archivé après chaque sync (voir send_cache_and_archive.sh), donc le
# cache négatif 24h des stats ne survit jamais d'un run au suivant. Ce fichier
# vit directement à la racine de sofascore_cache (pas un target d'archivage)
# pour retenir durablement les joueurs déjà établis (détails en prod) dont on
# sait qu'ils n'ont pas de stats — évite de les re-fetcher/404 à chaque run.
STATS_404_BLACKLIST_PATH = STORAGE_BASE / ".tennis_stats_404_blacklist.json"
STATS_404_BLACKLIST_TTL = 14 * 86400


def _load_stats_404_blacklist() -> dict:
    try:
        if STATS_404_BLACKLIST_PATH.exists():
            return json.loads(STATS_404_BLACKLIST_PATH.read_text())
    except Exception:
        pass
    return {}


def _save_stats_404_blacklist(blacklist: dict) -> None:
    try:
        STATS_404_BLACKLIST_PATH.write_text(json.dumps(blacklist))
    except Exception as e:
        print(f"  ⚠️  Impossible d'écrire le blacklist stats 404: {e}", file=sys.stderr)


def fetch_player_details(driver: webdriver.Chrome, player_ids: list, cache_dir: Path, year: int):
    """Fetch and cache player details, stats, and images for all given player IDs."""
    players_dir = cache_dir / "players"
    stats_dir = players_dir / "statistics"
    logos_dir = players_dir / "logos"
    meta_dir = cache_dir / "metadata"
    for d in (players_dir, stats_dir, logos_dir, meta_dir):
        d.mkdir(parents=True, exist_ok=True)

    total = len(player_ids)
    print(f"\n👤 Fetch détails joueurs: {total} joueur(s)")

    already_detailed_prod = _fetch_tennis_players_with_details_from_prod()
    if already_detailed_prod:
        print(f"  ⏭️  {len(already_detailed_prod)} joueur(s) déjà détaillé(s) en prod — skip détails")

    fresh_stats_prod = _fetch_tennis_players_with_fresh_season_stats_from_prod(days=7)
    if fresh_stats_prod:
        print(f"  ⏭️  {len(fresh_stats_prod)} joueur(s) avec stats saison < 7 jours en prod — skip stats")

    stats_404_blacklist = _load_stats_404_blacklist()

    for i, (pid, name) in enumerate(player_ids, 1):
        print(f"  [{i}/{total}] {name} (ID: {pid})")
        made_request = False

        # --- Details ---
        details_file = players_dir / f"player_details_{pid}.json"
        meta_key = f"player_details_{pid}"
        meta_file = meta_dir / (hashlib.md5(meta_key.encode()).hexdigest() + ".meta")

        if details_file.exists() and meta_file.exists():
            try:
                meta = json.loads(meta_file.read_text())
                age = time.time() - (meta.get("timestamp") or 0)
                if not meta.get("negative_cache") and age < 7 * 86400:
                    print(f"    ⏭️  Détails en cache (âge: {age/3600:.1f}h)")
                elif meta.get("negative_cache") and age < 86400:
                    print(f"    ⏭️  Cache négatif détails (âge: {age/3600:.1f}h)")
                    # Ne pas faire continue ici — on vérifie quand même les stats
            except Exception:
                pass
        elif pid in already_detailed_prod:
            print(f"    ⏭️  Détails déjà en prod — skip")
        else:
            url = f"https://www.sofascore.com/api/v1/team/{pid}"
            made_request = True
            data, http_status = fetch_json(driver, url, with_status=True)
            if data and "error" not in data:
                details_file.write_text(json.dumps(data, ensure_ascii=False))
                meta_file.write_text(json.dumps({
                    "timestamp": int(time.time()),
                    "url": url,
                    "player_id": pid,
                    "player_name": data.get("team", {}).get("name", name),
                }, ensure_ascii=False))
                print(f"    ✅ Détails écrits")
            elif http_status == 404:
                # Vraie absence confirmée — seul cas qui justifie un cache
                # négatif de 24h (voir même logique pour les stats/last_events :
                # un 403 est un blocage temporaire, pas une absence de donnée,
                # et ne doit jamais être mis en cache négatif — sinon un
                # blocage anti-bot passager marque à tort des joueurs réels
                # comme indisponibles pendant 24h, constaté le 2026-09-30).
                meta_file.write_text(json.dumps({
                    "timestamp": int(time.time()),
                    "url": url,
                    "player_id": pid,
                    "player_name": name,
                    "negative_cache": True,
                }, ensure_ascii=False))
                print(f"    ⚠️  Détails non disponibles (404 confirmé, cache négatif)")
            else:
                print(f"    ⚠️  Détails non disponibles (statut: {http_status}, pas de cache négatif — retenté au prochain passage)")

        # --- Basic (fichier plat pour l'import PHP, dérivé des détails) ---
        if details_file.exists():
            try:
                details_json = json.loads(details_file.read_text())
                basic = _build_player_basic(details_json)
                if basic:
                    basic_file = players_dir / f"player_basic_{pid}.json"
                    basic_file.write_text(json.dumps(basic, ensure_ascii=False))
            except Exception as e:
                print(f"    ⚠️  Erreur génération player_basic: {e}", file=sys.stderr)

        # --- Statistics ---
        stats_file = stats_dir / f"player_statistics_{pid}.json"
        need_stats_fetch = True

        blacklist_entry = stats_404_blacklist.get(str(pid))
        if (
            blacklist_entry
            and pid in already_detailed_prod
            and time.time() - blacklist_entry < STATS_404_BLACKLIST_TTL
        ):
            age_days = (time.time() - blacklist_entry) / 86400
            print(f"    ⏭️  Stats 404 confirmé (joueur établi, blacklist âge: {age_days:.1f}j) — skip")
            need_stats_fetch = False

        if need_stats_fetch and pid in fresh_stats_prod:
            print(f"    ⏭️  Stats saison < 7 jours en prod — skip")
            need_stats_fetch = False

        if need_stats_fetch and stats_file.exists():
            try:
                existing = json.loads(stats_file.read_text())
                if existing.get("_negative_cache"):
                    # Un vrai 404 (donnée confirmée absente) reste en cache 24h.
                    # Un échec ambigu (statut inconnu/transitoire) est retenté
                    # immédiatement plutôt que bloqué toute la journée.
                    if existing.get("_http_status") == 404:
                        cached_at = existing.get("_cached_at", 0)
                        if time.time() - cached_at < 86400:
                            print(f"    ⏭️  Stats cache négatif (404 confirmé, âge: {(time.time()-cached_at)/3600:.1f}h)")
                            need_stats_fetch = False
                else:
                    print(f"    ⏭️  Stats déjà en cache")
                    need_stats_fetch = False
            except Exception:
                pass  # fichier corrompu → on re-fetch

        if need_stats_fetch:
            url = f"https://www.sofascore.com/api/v1/team/{pid}/year-statistics/{year}"
            made_request = True
            data, http_status = fetch_json(driver, url, with_status=True)
            if data and "error" not in data and not data.get("_negative_cache"):
                data["_fetched_year"] = year
                stats_file.write_text(json.dumps(data, ensure_ascii=False))
                print(f"    ✅ Stats {year} écrites")
            else:
                # Écrire un negative cache — TTL de 24h uniquement si le
                # statut confirme une vraie absence (404), sinon retenté au
                # prochain passage (voir vérification ci-dessus).
                stats_file.write_text(json.dumps({
                    "_negative_cache": True,
                    "_url": url,
                    "_http_status": http_status,
                    "_cached_at": int(time.time()),
                    "_expires_at": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(time.time() + 86400)),
                }, ensure_ascii=False))
                print(f"    ⚠️  Stats non disponibles (statut: {http_status}, cache négatif écrit)")
                if http_status == 404 and pid in already_detailed_prod:
                    stats_404_blacklist[str(pid)] = time.time()
                    _save_stats_404_blacklist(stats_404_blacklist)

        # --- Statistics année précédente (plus de données pour le score) ---
        # Cache PERMANENT : une saison passée ne change plus une fois terminée,
        # pas besoin de TTL ni de cache négatif comme pour l'année en cours.
        prev_year = year - 1
        stats_prev_file = stats_dir / f"player_statistics_prevyear_{pid}.json"
        if not stats_prev_file.exists():
            url = f"https://www.sofascore.com/api/v1/team/{pid}/year-statistics/{prev_year}"
            made_request = True
            data = fetch_json(driver, url)
            if data and "error" not in data:
                data["_fetched_year"] = prev_year
                stats_prev_file.write_text(json.dumps(data, ensure_ascii=False))
                print(f"    ✅ Stats {prev_year} (année précédente) écrites")

        # --- Image ---
        logo_file = logos_dir / f"{pid}.png"
        img_meta_file = meta_dir / f"player_image_{pid}.meta"
        if pid in already_detailed_prod:
            print(f"    ⏭️  Image déjà en prod (joueur détaillé) — skip")
        elif not logo_file.exists():
            # Check tombstone
            if img_meta_file.exists():
                try:
                    m = json.loads(img_meta_file.read_text())
                    if m.get("negative_cache") and (time.time() - (m.get("timestamp") or 0)) < 86400:
                        print(f"    ⏭️  Image tombstone valide")
                        continue
                except Exception:
                    pass

            img_url = f"https://api.sofascore.com/api/v1/team/{pid}/image"
            made_request = True
            try:
                content = _browser_fetch_image(driver, img_url)
                if content:
                    logo_file.write_bytes(content)
                    print(f"    ✅ Image téléchargée")
                else:
                    img_meta_file.write_text(json.dumps({
                        "timestamp": int(time.time()),
                        "url": img_url,
                        "player_id": pid,
                        "player_name": name,
                        "negative_cache": True,
                    }, ensure_ascii=False))
                    print(f"    ⚠️  Image non disponible (tombstone)")
            except Exception as e:
                print(f"    ⚠️  Erreur image: {e}", file=sys.stderr)

        if made_request:
            time.sleep(random.uniform(0.9, 1.8))
            # Pas d'appel à maybe_run_local_import() ici : le déclenchement se
            # fait désormais explicitement dans la boucle par lot de matchs
            # de fetch_tennis (voir MATCH_BATCH_SIZE) — un appel ici en plus
            # créait un import déclenché en plein milieu du fetch d'un match,
            # avec un sync prod parfois sauté silencieusement (force=False
            # ici vs force=True dans la boucle), constaté le 2026-09-30.


def fetch_player_rankings(driver: webdriver.Chrome, player_ids: list, cache_dir: Path):
    """Fetch team/{id}/rankings (ATP/WTA + UTR + livetennis) pour chaque joueur.

    Cache local avec TTL 7 jours (le classement ne change qu'une fois par
    semaine côté ATP/WTA) — évite de re-fetcher à chaque run."""
    rankings_dir = cache_dir / "players" / "rankings"
    rankings_dir.mkdir(parents=True, exist_ok=True)

    total = len(player_ids)
    print(f"\n🏅 Fetch rankings/UTR: {total} joueur(s)")
    fetched = 0
    for i, (pid, name) in enumerate(player_ids, 1):
        out_file = rankings_dir / f"rankings_{pid}.json"
        if out_file.exists():
            try:
                age = time.time() - out_file.stat().st_mtime
                if age < 7 * 86400:
                    continue
            except Exception:
                pass

        url = f"https://www.sofascore.com/api/v1/team/{pid}/rankings"
        data = fetch_json(driver, url)
        if data and "rankings" in data:
            out_file.write_text(json.dumps(data, ensure_ascii=False))
            fetched += 1
        time.sleep(random.uniform(0.9, 1.8))
        # Pas d'appel à maybe_run_local_import() ici — voir fetch_player_details.
        if i % 50 == 0:
            print(f"  [{i}/{total}] traités... ({fetched} nouveaux)")
    print(f"  📊 Rankings fetchés: {fetched}/{total}")


def fetch_player_point_by_point_history(driver: webdriver.Chrome, player_ids: list, cache_dir: Path, max_recent: int = 3):
    """Fetch event/{id}/point-by-point pour les derniers matchs TERMINÉS de chaque
    joueur (via team/{id}/events/last/0) — sert à calculer empiriquement la
    fréquence réelle d'atteindre 15-15/30-30/40-40 dans le 1er set, plutôt
    qu'un modèle théorique. Cache PERMANENT (un match passé ne change plus) :
    au fil des jours, de moins en moins de nouveaux fetches sont nécessaires."""
    pbp_dir = cache_dir / "players" / "point_by_point"
    pbp_processed_dir = pbp_dir / "processed"
    pbp_dir.mkdir(parents=True, exist_ok=True)

    # Cache de team/{id}/events/last/0 — jamais mis en cache jusqu'ici, alors
    # que ce même endpoint est rappelé pour TOUS les joueurs à CHAQUE run (les
    # derniers matchs d'un joueur ne changent qu'une fois par jour au plus,
    # quand il joue). Ce fetch, non caché, est celui qui génère le plus gros
    # volume de vraies requêtes live du pipeline — la cause principale du
    # rate-limit atteint pendant cette étape (constaté le 2026-09-29).
    last_events_dir = cache_dir / "players" / "last_events"
    last_events_dir.mkdir(parents=True, exist_ok=True)
    LAST_EVENTS_CACHE_TTL = 20 * 3600  # 20h

    total = len(player_ids)
    print(f"\n🎯 Fetch historique point-by-point: {total} joueur(s) (max {max_recent} matchs récents/joueur)")
    fetched, skipped_cached = 0, 0

    # Backoff adaptatif : ce endpoint (team/{id}/events/last/0), appelé en
    # boucle rapide pour ~centaines/milliers de joueurs avec un intervalle
    # quasi fixe, est repéré par l'anti-bot comme un pattern de scraping même
    # via un vrai navigateur — contrairement aux autres étapes (moins
    # répétitives) qui passent sans souci. Un 403 isolé ne déclenche qu'une
    # pause courte (retries internes de fetch_json), mais des 403 consécutifs
    # signalent qu'on a franchi le seuil de rate-limit : on marque alors une
    # vraie pause pour laisser la fenêtre se refermer, au lieu d'enchaîner à
    # la même cadence qui ne fait qu'aggraver le blocage.
    consecutive_403 = 0
    LONG_PAUSE_AFTER = 3
    LONG_PAUSE_SECONDS = 90
    # Joueurs dont le fetch a échoué en 403 (bloqué temporairement, PAS une
    # vraie absence de données) — on ne les met PAS en cache négatif (sinon on
    # les traiterait comme "confirmé sans events" pendant 20h), on les
    # retentera une fois à la fin, après une pause plus longue.
    retry_queue_403 = []

    def _fetch_last_events_once(pid):
        nonlocal consecutive_403
        url = f"https://www.sofascore.com/api/v1/team/{pid}/events/last/0"
        data, http_status = fetch_json(driver, url, with_status=True)

        if http_status == 403:
            consecutive_403 += 1
            if consecutive_403 >= LONG_PAUSE_AFTER:
                print(f"  ⏸️  {consecutive_403} x 403 consécutifs — pause de {LONG_PAUSE_SECONDS}s pour laisser le rate-limit se refermer...", file=sys.stderr)
                time.sleep(LONG_PAUSE_SECONDS)
                consecutive_403 = 0
        else:
            consecutive_403 = 0

        if data and "events" in data:
            (last_events_dir / f"last_events_{pid}.json").write_text(json.dumps(data, ensure_ascii=False))
        elif http_status != 403:
            (last_events_dir / f"last_events_{pid}.json").write_text(json.dumps({"_negative_cache": True, "_http_status": http_status}, ensure_ascii=False))
        return data, http_status

    def _process_player_events(data):
        """Traite les events['events'] d'un joueur : fetch le point-by-point
        des derniers matchs terminés (hors doubles), jusqu'à max_recent."""
        nonlocal fetched, skipped_cached, consecutive_403
        new_for_player = 0
        for event in data["events"]:
            if new_for_player >= max_recent:
                break
            if (event.get("status", {}).get("type") != "finished"):
                continue
            tournament_name = (event.get("tournament") or {}).get("name", "").lower()
            if "doubles" in tournament_name:
                continue

            event_id = event.get("id")
            if not event_id:
                continue

            pbp_file = pbp_dir / f"pbp_{event_id}.json"
            pbp_processed_file = pbp_processed_dir / f"pbp_{event_id}.json"
            if pbp_file.exists() or pbp_processed_file.exists():
                skipped_cached += 1
                continue

            pbp_url = f"https://www.sofascore.com/api/v1/event/{event_id}/point-by-point"
            pbp_data, pbp_status = fetch_json(driver, pbp_url, with_status=True)
            if pbp_status == 403:
                consecutive_403 += 1
                if consecutive_403 >= LONG_PAUSE_AFTER:
                    print(f"  ⏸️  {consecutive_403} x 403 consécutifs — pause de {LONG_PAUSE_SECONDS}s pour laisser le rate-limit se refermer...", file=sys.stderr)
                    time.sleep(LONG_PAUSE_SECONDS)
                    consecutive_403 = 0
            else:
                consecutive_403 = 0
            if pbp_data and "pointByPoint" in pbp_data:
                pbp_data["_home_team_id"] = (event.get("homeTeam") or {}).get("id")
                pbp_data["_away_team_id"] = (event.get("awayTeam") or {}).get("id")
                pbp_file.write_text(json.dumps(pbp_data, ensure_ascii=False))
                fetched += 1
                new_for_player += 1
            time.sleep(random.uniform(1.2, 2.2))

    for i, (pid, name) in enumerate(player_ids, 1):
        last_events_file = last_events_dir / f"last_events_{pid}.json"
        data = None

        if last_events_file.exists():
            try:
                age = time.time() - last_events_file.stat().st_mtime
                if age < LAST_EVENTS_CACHE_TTL:
                    cached = json.loads(last_events_file.read_text())
                    if not cached.get("_negative_cache"):
                        data = cached
                    skipped_cached += 1
            except Exception:
                pass

        if data is None:
            data, http_status = _fetch_last_events_once(pid)
            if http_status == 403:
                retry_queue_403.append((pid, name))

        if not data or "events" not in data:
            time.sleep(random.uniform(1.2, 2.2))
            continue

        _process_player_events(data)

        if i % 50 == 0:
            print(f"  [{i}/{total}] traités... ({fetched} nouveaux, {skipped_cached} déjà en cache)")
        time.sleep(random.uniform(1.0, 1.8))
        # Pas d'appel à maybe_run_local_import() ici — voir fetch_player_details.

    # Retry final : les joueurs dont le fetch team/{id}/events/last/0 a
    # échoué en 403 (bloqué temporairement, pas mis en cache négatif) sont
    # retentés une seule fois après une pause plus longue — le rate-limit
    # s'est souvent refermé entre-temps, sans perdre ces données pour autant.
    if retry_queue_403:
        print(f"\n🔁 Retry final pour {len(retry_queue_403)} joueur(s) bloqués en 403 (pause de 3 min avant de retenter)...")
        time.sleep(180)
        retried_ok = 0
        for pid, name in retry_queue_403:
            data, http_status = _fetch_last_events_once(pid)
            if data and "events" in data:
                retried_ok += 1
                _process_player_events(data)
            time.sleep(random.uniform(1.2, 2.2))
        print(f"  📊 Retry 403: {retried_ok}/{len(retry_queue_403)} récupérés avec succès")

    print(f"  📊 Point-by-point nouveaux: {fetched} | déjà en cache (skip): {skipped_cached}")


def fetch_h2h_and_odds_for_events(driver: webdriver.Chrome, events: list, cache_dir: Path):
    """Fetch H2H détaillé (event/{customId}/h2h/events) et cotes (event/{id}/odds/1/all)
    pour chaque match du jour. Volume borné par le nombre de matchs (pas de joueurs)."""
    h2h_dir = cache_dir / "tournaments" / "h2h"
    odds_dir = cache_dir / "tournaments" / "odds"
    h2h_dir.mkdir(parents=True, exist_ok=True)
    odds_dir.mkdir(parents=True, exist_ok=True)

    total = len(events)
    print(f"\n🤝 Fetch H2H + cotes: {total} match(s) du jour")
    h2h_ok, odds_ok = 0, 0
    for i, event in enumerate(events, 1):
        event_id = event.get("id")
        custom_id = event.get("customId")
        if not event_id:
            continue

        h2h_file = h2h_dir / f"h2h_{event_id}.json"
        if not h2h_file.exists() and custom_id:
            url = f"https://www.sofascore.com/api/v1/event/{custom_id}/h2h/events"
            data = fetch_json(driver, url)
            if data and "events" in data:
                h2h_file.write_text(json.dumps(data, ensure_ascii=False))
                h2h_ok += 1
            time.sleep(random.uniform(0.9, 1.8))

        odds_file = odds_dir / f"odds_{event_id}.json"
        if not odds_file.exists():
            url = f"https://www.sofascore.com/api/v1/event/{event_id}/odds/1/all"
            data = fetch_json(driver, url)
            if data and "markets" in data:
                odds_file.write_text(json.dumps(data, ensure_ascii=False))
                odds_ok += 1
            time.sleep(random.uniform(0.9, 1.8))

        # Pas d'appel à maybe_run_local_import() ici — voir fetch_player_details.
        if i % 50 == 0:
            print(f"  [{i}/{total}] traités...")

    print(f"  📊 H2H écrits: {h2h_ok} | Cotes écrites: {odds_ok} (sur {total} matchs)")


# ---------------------------------------------------------------------------
# Generic sports (scheduled-tournaments pagination + featured-events + standings)
# ---------------------------------------------------------------------------

def extract_unique_tournament_ids(cache_dir: Path) -> dict:
    """Extract unique tournament sofascore IDs from cached page_*.json files."""
    ids = {}
    for p in sorted(cache_dir.glob("page_*.json")):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for entry in data.get("scheduled", data.get("uniqueTournaments", [])):
                t = entry.get("tournament", {})
                ut = t.get("uniqueTournament", {})
                uid = ut.get("id")
                if uid:
                    ids[uid] = ut.get("name", f"ID:{uid}")
        except Exception:
            pass
    return ids


def fetch_sport_standings(driver: webdriver.Chrome, cache_dir: Path, unique_ids: dict):
    """Fetch featured-events + standings for each unique tournament via Chrome."""
    total = len(unique_ids)
    print(f"\n  👥 Fetch standings pour {total} ligues via Chrome...")
    standings_count = 0

    for i, (uid, name) in enumerate(unique_ids.items(), 1):
        # --- featured-events → get season ID ---
        featured_file = cache_dir / f"featured_events_{uid}.json"
        season_id = None

        if featured_file.exists():
            try:
                cached = json.loads(featured_file.read_text())
                if not cached.get("_negative_cache"):
                    events = cached.get("featuredEvents", [])
                    for ev in events:
                        sid = (ev.get("tournament", {}).get("season", {}).get("id")
                               or ev.get("season", {}).get("id"))
                        if sid:
                            season_id = sid
                            break
                    if season_id:
                        print(f"  [{i}/{total}] {name} — featured-events en cache, season={season_id}")
            except Exception:
                pass

        if season_id is None and not (featured_file.exists() and
                                       json.loads(featured_file.read_text()).get("_negative_cache")):
            url = f"https://www.sofascore.com/api/v1/unique-tournament/{uid}/featured-events"
            data = fetch_json(driver, url)
            if data and "error" not in data:
                featured_file.write_text(json.dumps(data, ensure_ascii=False))
                events = data.get("featuredEvents", [])
                for ev in events:
                    sid = (ev.get("tournament", {}).get("season", {}).get("id")
                           or ev.get("season", {}).get("id"))
                    if sid:
                        season_id = sid
                        break
                if season_id:
                    print(f"  [{i}/{total}] {name} — featured-events OK, season={season_id}")
                else:
                    print(f"  [{i}/{total}] {name} — featured-events sans season ID ({len(events)} events)")
            else:
                neg = {"_negative_cache": True, "error": str(data), "fetched_at": datetime.now().isoformat()}
                featured_file.write_text(json.dumps(neg, ensure_ascii=False))
                print(f"  [{i}/{total}] {name} — featured-events KO (cache négatif)")
            time.sleep(random.uniform(0.9, 1.8))

        if season_id is None:
            continue

        # --- standings ---
        standings_file = cache_dir / f"standings_{uid}_{season_id}.json"
        if standings_file.exists():
            try:
                cached = json.loads(standings_file.read_text())
                if not cached.get("_negative_cache"):
                    print(f"  [{i}/{total}] {name} — standings déjà en cache")
                    standings_count += 1
                    continue
            except Exception:
                pass

        url = f"https://www.sofascore.com/api/v1/unique-tournament/{uid}/season/{season_id}/standings/total"
        data = fetch_json(driver, url)
        if data and "error" not in data:
            standings_file.write_text(json.dumps(data, ensure_ascii=False))
            rows = sum(len(g.get("rows", [])) for g in data.get("standings", []))
            print(f"  [{i}/{total}] {name} — standings OK ({rows} équipes)")
            standings_count += 1
        else:
            neg = {"_negative_cache": True, "error": str(data), "fetched_at": datetime.now().isoformat()}
            standings_file.write_text(json.dumps(neg, ensure_ascii=False))
            print(f"  [{i}/{total}] {name} — standings KO (cache négatif)")
        time.sleep(random.uniform(0.9, 1.8))

    print(f"  📊 Standings mis en cache: {standings_count}/{total}")


def fetch_generic_scheduled_events(driver: webdriver.Chrome, cache_dir: Path, unique_ids: dict, target_date: str, sport_label: str) -> int:
    """
    Pour chaque tournoi (unique_ids), fetche
    unique-tournament/{id}/scheduled-events/{target_date} et cache le résultat
    dans tournament_events_{id}.json (même format que fetch_tennis_scheduled_events,
    généralisé aux sports en mode scheduled_tournaments : football, basketball, etc.)

    Ces fichiers sont ensuite lus par le trait PHP ImportsMatchesFromCache pour
    persister les matchs en BDD. Contrairement à featured_events_{id}.json (un
    seul match "à la une", pas forcément du jour), ceci contient le programme
    réel du jour pour ce tournoi.
    """
    dt = datetime.strptime(target_date, "%Y-%m-%d")
    day_start = int(dt.replace(tzinfo=timezone.utc).timestamp()) - 3600
    day_end   = int(dt.replace(tzinfo=timezone.utc).timestamp()) + 86400 + 3600

    total = len(unique_ids)
    print(f"\n  📅 Fetch scheduled-events du jour pour {total} tournois {sport_label}...")

    total_events = 0
    for i, (uid, name) in enumerate(unique_ids.items(), 1):
        cache_file = cache_dir / f"tournament_events_{uid}.json"

        if cache_file.exists():
            try:
                cached = json.loads(cache_file.read_text())
                if not cached.get("_negative_cache"):
                    n = len(cached.get("events", []))
                    print(f"  [{i}/{total}] {name} — cache ({n} matchs)")
                    total_events += n
                    continue
            except Exception:
                pass

        url = f"https://www.sofascore.com/api/v1/unique-tournament/{uid}/scheduled-events/{target_date}"
        data = fetch_json(driver, url)

        today_events = []
        if data and "error" not in data:
            for e in data.get("events", []):
                ts = e.get("startTimestamp", 0)
                if day_start <= ts <= day_end:
                    today_events.append(e)

        if today_events:
            cache_file.write_text(json.dumps({"events": today_events}, ensure_ascii=False))
            print(f"  [{i}/{total}] {name} — {len(today_events)} matchs")
            total_events += len(today_events)
        else:
            cache_file.write_text(json.dumps({"_negative_cache": True, "fetched_at": datetime.now().isoformat()}, ensure_ascii=False))

        time.sleep(random.uniform(0.7, 1.4))

    print(f"  📊 Total matchs {sport_label} du jour: {total_events}")
    return total_events


def extract_team_ids_from_standings(cache_dir: Path) -> dict:
    """
    Scanne les fichiers standings_{leagueId}_{seasonId}.json déjà écrits par
    fetch_sport_standings() pour en extraire l'id+nom de chaque équipe du
    classement — le même périmètre que celui que la Phase 2 PHP traite
    réellement pour les équipes (ImportSportFromCache::getTeamsFromStandingsCache
    lit ces mêmes fichiers). Contrairement à extract_team_ids_from_event_files
    (équipes des matchs du jour uniquement), on couvre ici TOUTES les équipes
    du classement, y compris celles qui ne jouent pas aujourd'hui.
    """
    result = {}
    for p in cache_dir.glob("standings_*_*.json"):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for group in data.get("standings", []):
                for row in group.get("rows", []):
                    team = row.get("team")
                    if team and team.get("id"):
                        result[team["id"]] = team.get("name", f"ID:{team['id']}")
        except Exception:
            pass
    return result


def fetch_team_players(driver: webdriver.Chrome, cache_dir: Path, team_ids: dict, label: str) -> None:
    """
    Télécharge l'effectif complet de chaque équipe via team/{id}/players —
    endpoint qui renvoie la liste réelle des joueurs (contrairement à
    team/.../top-players/overall, qui ne renvoie que les meneurs statistiques
    par catégorie et 404 pour beaucoup de petites équipes/compétitions, cf.
    tests manuels lors du développement). Pas besoin de league_id/season_id.

    Écrit dans {cache_dir}/team_players/{teamId}.json — lu par le pipeline PHP
    (ImportSportFromCache::importPlayersForTeam) pour créer/mettre à jour les
    Player, sans jamais taper l'API Sofascore depuis le serveur (bloquée 403).
    """
    players_dir = cache_dir / "team_players"
    players_dir.mkdir(parents=True, exist_ok=True)

    total = len(team_ids)
    if total == 0:
        return
    print(f"\n  🧑‍🤝‍🧑 Fetch effectifs pour {total} équipes {label}...")

    fetched = 0
    for i, (tid, name) in enumerate(team_ids.items(), 1):
        cache_file = players_dir / f"{tid}.json"
        if cache_file.exists():
            continue

        url = f"https://www.sofascore.com/api/v1/team/{tid}/players"
        data = fetch_json(driver, url)

        if data and "error" not in data and data.get("players"):
            cache_file.write_text(json.dumps(data, ensure_ascii=False))
            fetched += 1
        else:
            neg = {"_negative_cache": True, "fetched_at": datetime.now().isoformat()}
            cache_file.write_text(json.dumps(neg, ensure_ascii=False))
        time.sleep(random.uniform(0.7, 1.4))

    print(f"  📊 Effectifs équipes mis en cache: {fetched}/{total}")


def extract_player_ids_from_team_players_files(cache_dir: Path) -> dict:
    """Scanne team_players/*.json pour en extraire l'id+nom de chaque joueur trouvé."""
    ids = {}
    dir_path = cache_dir / "team_players"
    if not dir_path.is_dir():
        return ids
    for p in dir_path.glob("*.json"):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for entry in data.get("players", []):
                player = entry.get("player")
                if player and player.get("id"):
                    ids[player["id"]] = player.get("name", f"ID:{player['id']}")
        except Exception:
            pass
    return ids


def fetch_player_images(driver: webdriver.Chrome, cache_dir: Path, player_ids: dict, label: str) -> None:
    """
    Télécharge les images des joueurs listés dans team_players/*.json, via fetch
    navigateur (même contrainte 403 directe que les logos équipe/ligue/catégorie).
    Écrit dans {cache_dir}/player_images/{playerId}.png.
    """
    images_dir = cache_dir / "player_images"
    images_dir.mkdir(parents=True, exist_ok=True)

    total = len(player_ids)
    if total == 0:
        return
    print(f"\n  📸 Fetch images joueurs pour {total} joueurs {label}...")

    downloaded = 0
    for i, (pid, name) in enumerate(player_ids.items(), 1):
        img_file = images_dir / f"{pid}.png"
        if img_file.exists():
            continue

        img_url = f"https://api.sofascore.com/api/v1/player/{pid}/image"
        content = _browser_fetch_image(driver, img_url)
        if content:
            img_file.write_bytes(content)
            downloaded += 1
        time.sleep(random.uniform(1.0, 1.8))

    print(f"  📊 Images joueurs téléchargées: {downloaded}/{total}")


def fetch_sport_scheduled(driver: webdriver.Chrome, sport: str, target_date: str):
    cfg = SPORTS_CONFIG[sport]
    slug = cfg["sport_slug"]
    cache_subdir = cfg["cache_subdir"]
    cache_dir = STORAGE_BASE / cache_subdir / target_date
    cache_dir.mkdir(parents=True, exist_ok=True)

    print(f"\n⚽ {sport.capitalize()} — date: {target_date}")

    page = 1
    total_tournaments = 0
    while True:
        url = f"https://www.sofascore.com/api/v1/sport/{slug}/scheduled-tournaments/{target_date}/page/{page}"
        print(f"  📡 Page {page}: {url}")
        data = fetch_json(driver, url)

        if data is None:
            print(f"  ❌ Échec page {page}", file=sys.stderr)
            break

        if "error" in data:
            print(f"  ⚠️  Erreur API page {page}: {data['error']}")
            # Write negative cache so PHP skips this page
            neg = {"_negative_cache": True, "error": data["error"], "fetched_at": datetime.now().isoformat()}
            (cache_dir / f"page_{page}.json").write_text(json.dumps(neg, ensure_ascii=False))
            break

        tournaments = data.get("scheduled", data.get("uniqueTournaments", []))
        has_next = data.get("hasNextPage", False)
        total_tournaments += len(tournaments)
        print(f"  ✅ {len(tournaments)} tournois (page {page}), hasNextPage={has_next}")

        out_path = cache_dir / f"page_{page}.json"
        out_path.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
        print(f"  💾 Écrit: {out_path}")

        if not has_next:
            break
        page += 1
        time.sleep(random.uniform(1.2, 2.2))

    print(f"  📊 Total: {total_tournaments} tournois sur {page} page(s)")

    # Fetch featured-events + standings for all unique tournaments via Chrome
    unique_ids = extract_unique_tournament_ids(cache_dir)
    if unique_ids:
        fetch_sport_standings(driver, cache_dir, unique_ids)
        # Fetch les matchs réellement programmés aujourd'hui (Phase 2 lira ces
        # fichiers pour peupler la table matches — voir ImportsMatchesFromCache.php)
        fetch_generic_scheduled_events(driver, cache_dir, unique_ids, target_date, sport)

        # Logos des équipes apparaissant dans les matchs du jour (mêmes contraintes
        # que les logos de tournois : bloqué en HTTP direct, fetch navigateur requis)
        team_ids = extract_team_ids_from_event_files(cache_dir)
        fetch_team_logos(driver, cache_dir, team_ids, sport)

        # Joueurs de chaque équipe du classement + leurs images (même contrainte
        # 403 direct — fetch navigateur requis). Remplace l'ancienne commande PHP
        # players:import-by-team qui appelait l'API en direct depuis le serveur.
        standings_team_ids = extract_team_ids_from_standings(cache_dir)
        fetch_team_players(driver, cache_dir, standings_team_ids, sport)
        player_ids = extract_player_ids_from_team_players_files(cache_dir)
        fetch_player_images(driver, cache_dir, player_ids, sport)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Interleaving import/sync — au lieu de tout fetcher puis tout importer/
# synchroniser en une seule fois à la fin (rafale de requêtes Sofascore sans
# interruption, repérable par l'anti-bot), on déclenche l'import PHP local et
# la synchronisation prod PAR LOTS pendant le fetch. Le temps de calcul local
# (import PHP) et le temps réseau (rsync/SSH vers prod) créent un délai
# "utile" entre les paquets de requêtes Sofascore, au lieu d'un sleep() pur.
# ---------------------------------------------------------------------------

PROJECT_DIR = Path(__file__).parent.parent
# Basé sur le TEMPS écoulé (pas un compteur d'éléments traités) : la commande
# d'import PHP retraite tout le cache à chaque appel (~2min30 mesuré), donc un
# seuil par volume devient vite trop fréquent/coûteux quand le fetch avance
# vite (cache hits) ou trop rare quand il ralentit (vrais fetch + 403). Les
# matchs sont désormais fetchés dans l'ordre de leur heure de départ (voir
# tri dans fetch_tennis), donc même un intervalle fixe profite déjà aux
# matchs qui commencent le plus tôt.
LOCAL_IMPORT_INTERVAL_SECONDS = 3 * 60    # ~3 min — proche du temps de l'import lui-même (~2min30),
                                           # pas d'attente artificielle en plus (demandé par l'utilisateur)
PROD_SYNC_INTERVAL_SECONDS = 9 * 60       # ~9 min (≈ tous les 3 imports locaux) — limite le nb de connexions SSH

# Initialisé au moment du chargement du module (proche du démarrage réel du
# run) plutôt qu'à 0 — un compteur à 0 déclencherait le tout premier appel
# immédiatement (temps écoulé depuis l'epoch = énorme), ce qui a provoqué un
# archivage prématuré (voir maybe_run_prod_sync) alors que le fetch venait à
# peine de commencer.
_interleave_counter = {"last_local_import_at": time.time(), "last_prod_sync_at": time.time()}


def _run(cmd: list, cwd=None, timeout=180, env=None) -> bool:
    try:
        run_env = {**os.environ, **env} if env else None
        result = subprocess.run(cmd, cwd=cwd or PROJECT_DIR, capture_output=True, text=True, timeout=timeout, env=run_env)
        if result.returncode != 0:
            print(f"  ⚠️  Commande échouée ({' '.join(cmd)}): {result.stderr[-500:]}", file=sys.stderr)
            return False
        return True
    except subprocess.TimeoutExpired:
        print(f"  ⚠️  Timeout sur commande: {' '.join(cmd)}", file=sys.stderr)
        return False
    except Exception as e:
        print(f"  ⚠️  Erreur commande: {e}", file=sys.stderr)
        return False


def maybe_run_local_import(force: bool = False):
    """Toutes les LOCAL_IMPORT_INTERVAL_SECONDS écoulées, importe en base
    locale ce qui a déjà été fetché (le cache existant est réutilisé par les
    prochains passages, rien n'est perdu ni refait). Basé sur le temps
    écoulé plutôt qu'un compteur d'éléments : coût fixe par appel (~2min30),
    indépendant du volume traité entre deux appels."""
    now = time.time()
    if not force and (now - _interleave_counter["last_local_import_at"]) < LOCAL_IMPORT_INTERVAL_SECONDS:
        return
    _interleave_counter["last_local_import_at"] = now
    print("  🔄 Import PHP local incrémental (lot)...")
    _run(["docker", "compose", "exec", "-T", "web", "php", "artisan",
          "tennis:import-from-cache", "--force", "--skip-archive"], timeout=300)
    # Calcul des probabilités (léger, ~20s mesuré) — sans ça les matchs sont
    # importés mais pas "exploitables" (pas de stats/proba tant que cette
    # commande séparée n'a pas tourné).
    print("  🎯 Calcul des probabilités (lot)...")
    _run(["docker", "compose", "exec", "-T", "web", "php", "artisan",
          "tennis:compute-tightness-scores", "--force"], timeout=120)
    maybe_run_prod_sync(force=force)


def maybe_run_prod_sync(force: bool = False, archive: bool = False):
    """Synchronise le cache vers prod et relance l'import + calcul de probas
    là-bas. Appelé après chaque match (force=True) depuis maybe_run_local_import,
    donc en pratique quasi jamais gaté par PROD_SYNC_INTERVAL_SECONDS (ce
    seuil ne sert plus que pour le pool de joueurs additionnels hors matchs
    du jour, traité en bloc à la fin de fetch_tennis).
    `archive` contrôle l'archivage (déplacement des dossiers de cache après
    envoi) — voir send_cache_and_archive.sh : archiver PENDANT que le fetch
    tourne encore viderait le dossier de travail sous ses pieds et le ferait
    planter (FileNotFoundError constaté le 2026-09-30). Seul le flush final
    de fetch_tennis passe archive=True."""
    now = time.time()
    if not force and (now - _interleave_counter["last_prod_sync_at"]) < PROD_SYNC_INTERVAL_SECONDS:
        return
    _interleave_counter["last_prod_sync_at"] = now
    archive_env = None if archive else {"SKIP_ARCHIVE": "1"}
    label = "final (avec archivage)" if archive else "match par match (sans archivage)"
    print(f"  ☁️  Sync + import prod {label}...")
    if _run(["bash", "script/send_cache_and_archive.sh"], timeout=300, env=archive_env):
        _run(["ssh", "sc2vagr6376@bouteille.o2switch.net",
              "cd ~/api.auxotracker && php artisan tennis:import-from-cache --force "
              "&& php artisan tennis:compute-tightness-scores --force"], timeout=300)


def main():
    parser = argparse.ArgumentParser(description="Fetch Sofascore cache via Chrome headless")
    parser.add_argument("--sport", default="tennis",
                        help="Sport à fetcher (tennis, football, basketball, all…)")
    parser.add_argument("--date", default=date.today().strftime("%Y-%m-%d"),
                        help="Date au format YYYY-MM-DD (défaut: aujourd'hui)")
    parser.add_argument("--transport", choices=["selenium", "extension"], default="selenium",
                        help="selenium (défaut, Chrome headless) ou extension "
                             "(pont WebSocket vers l'extension Chrome — nécessite "
                             "Chrome ouvert avec chrome_extension_sofascore chargée)")
    args = parser.parse_args()

    target_date = args.date
    sports_to_fetch = list(SPORTS_CONFIG.keys()) if args.sport == "all" else [args.sport]

    for sp in sports_to_fetch:
        if sp not in SPORTS_CONFIG:
            print(f"❌ Sport inconnu: {sp}. Disponibles: {', '.join(SPORTS_CONFIG)}", file=sys.stderr)
            sys.exit(1)

    print(f"🚀 fetch_sofascore_cache.py — sport(s): {sports_to_fetch}, date: {target_date}, transport: {args.transport}")

    if args.transport == "extension":
        driver = ExtensionBridge()
        print("⏳ En attente de connexion de l'extension Chrome (ouvrez Chrome avec "
              "chrome_extension_sofascore chargée)...")
        driver.wait_for_extension(timeout=120)
    else:
        driver = build_driver()
        warm_session(driver)

    try:
        for sport in sports_to_fetch:
            cfg = SPORTS_CONFIG[sport]
            if cfg["mode"] == "live_featured":
                fetch_tennis(driver, target_date)
            else:
                fetch_sport_scheduled(driver, sport, target_date)

    finally:
        if (ExtensionBridge is not None and isinstance(driver, ExtensionBridge)):
            pass  # rien à fermer : le WS reste ouvert le temps du process
        else:
            driver.quit()

    print("\n✅ Fetch terminé.")


if __name__ == "__main__":
    main()
