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
import re
import sys
import time
import unicodedata
from datetime import date, datetime, timezone
from pathlib import Path

import requests

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import WebDriverException, TimeoutException

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

STORAGE_BASE = Path(__file__).parent.parent / "storage" / "app" / "sofascore_cache"

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


def fetch_json(driver: webdriver.Chrome, url: str, retries: int = 3, with_status: bool = False):
    """Execute a fetch() inside the Sofascore page context and return parsed JSON.

    Si with_status=True, retourne un tuple (data, http_status) où http_status
    est le vrai code HTTP de la réponse (404, 429, ...) quand il a pu être
    obtenu, ou None si l'échec est survenu avant d'avoir une réponse (timeout,
    exception réseau) — pour distinguer un vrai 404 d'un raté transitoire.
    """
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
            time.sleep(2)
        except WebDriverException as e:
            print(f"  ⚠️  WebDriverException (tentative {attempt}/{retries}): {e}", file=sys.stderr)
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
    for p in sorted(sched_dir.glob("page_*.json")):
        try:
            data = json.loads(p.read_text())
            if data.get("_negative_cache"):
                continue
            for entry in data.get("scheduled", []):
                ut = entry.get("tournament", {}).get("uniqueTournament", {})
                uid = ut.get("id")
                if uid and uid not in unique_ids:
                    unique_ids[uid] = ut.get("name", f"ID:{uid}")
        except Exception:
            pass

    total = len(unique_ids)
    print(f"\n  🎾 Fetch events pour {total} tournois tennis du jour...")

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

        today_events = []
        if data and "error" not in data:
            for e in data.get("events", []):
                if e.get("id") not in seen_ids:
                    seen_ids.add(e["id"])
                    today_events.append(e)
                    all_events.append(e)

        if today_events:
            cache_file.write_text(json.dumps({"events": today_events}, ensure_ascii=False))
            print(f"  [{i}/{total}] {name} — {len(today_events)} matchs")
        else:
            cache_file.write_text(json.dumps({"_negative_cache": True, "fetched_at": datetime.now().isoformat()}, ensure_ascii=False))
        time.sleep(0.2)

    print(f"  📊 Total matchs tennis du jour: {len(all_events)}")
    return all_events


# ---------------------------------------------------------------------------
# Tennis (live + featured)
# ---------------------------------------------------------------------------

def _browser_fetch_image(driver: webdriver.Chrome, url: str):
    """Fetch une image via fetch() dans le contexte du navigateur (credentials:
    include) et retourne les octets décodés, ou None en cas d'échec. Contourne
    le blocage HTTP direct (403 confirmé) qui touche aussi bien l'API JSON que
    les images sur ce serveur."""
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
        url = f"https://api.auxotracker.p-com.studio/api/sports/{sport_slug}/teams/with-logo"
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
        time.sleep(0.1)

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

    downloaded = 0
    for i, (uid, name) in enumerate(unique_ids.items(), 1):
        logo_file = logos_dir / f"{uid}-light.png"
        if logo_file.exists():
            continue

        img_url = f"https://api.sofascore.com/api/v1/unique-tournament/{uid}/image"
        try:
            result = driver.execute_async_script(script, img_url)
            if result and result.get("ok") and result.get("data"):
                logo_file.write_bytes(base64.b64decode(result["data"]))
                downloaded += 1
                print(f"  [{i}/{total}] {name} — logo téléchargé")
        except Exception as e:
            print(f"  [{i}/{total}] {name} — erreur logo: {e}", file=sys.stderr)
        time.sleep(0.1)

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
    unique_tournament_ids = extract_unique_tournament_ids(sched_dir)
    if unique_tournament_ids:
        fetch_tennis_tournament_logos(driver, unique_tournament_ids)

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

    # Fetch player details for all players in the events + all already-cached players
    if fetch_players:
        cache_dir = STORAGE_BASE / "tennis_players"
        current_year = int(target_date[:4])

        # IDs from today's events
        event_ids = dict(extract_player_ids_from_events(all_events)) if all_events else {}

        # IDs from existing player_basic_*.json files (already imported players)
        players_dir = cache_dir / "players"
        for f in players_dir.glob("player_basic_*.json"):
            try:
                d = json.loads(f.read_text())
                pid = d.get("sofascore_id")
                name = d.get("name", f"ID:{pid}")
                if pid and pid not in event_ids:
                    event_ids[pid] = name
            except Exception:
                pass

        player_ids = list(event_ids.items())
        print(f"\n👤 Total joueurs à traiter (events + cache): {len(player_ids)}")
        fetch_player_details(driver, player_ids, cache_dir, current_year)


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
    changeant plus une fois connues. Ne concerne QUE les détails : les stats
    (year-statistics) sont, elles, toujours refetchées à chaque run."""
    try:
        url = "https://api.auxotracker.p-com.studio/api/tennis/players/with-details"
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
        print(f"  ⏭️  {len(already_detailed_prod)} joueur(s) déjà détaillé(s) en prod — skip détails (stats toujours refetchées)")

    for i, (pid, name) in enumerate(player_ids, 1):
        print(f"  [{i}/{total}] {name} (ID: {pid})")

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
            data = fetch_json(driver, url)
            if data and "error" not in data:
                details_file.write_text(json.dumps(data, ensure_ascii=False))
                meta_file.write_text(json.dumps({
                    "timestamp": int(time.time()),
                    "url": url,
                    "player_id": pid,
                    "player_name": data.get("team", {}).get("name", name),
                }, ensure_ascii=False))
                print(f"    ✅ Détails écrits")
            else:
                meta_file.write_text(json.dumps({
                    "timestamp": int(time.time()),
                    "url": url,
                    "player_id": pid,
                    "player_name": name,
                    "negative_cache": True,
                }, ensure_ascii=False))
                print(f"    ⚠️  Détails non disponibles (cache négatif)")

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
        if stats_file.exists():
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
            data, http_status = fetch_json(driver, url, with_status=True)
            if data and "error" not in data and not data.get("_negative_cache"):
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
            script = """
                const [url, callback] = arguments;
                fetch(url, {credentials: 'include'})
                .then(r => {
                    if (!r.ok) return callback({ok: false, status: r.status});
                    return r.arrayBuffer().then(buf => {
                        const bytes = new Uint8Array(buf);
                        let binary = '';
                        for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
                        callback({ok: true, data: btoa(binary), type: r.headers.get('content-type')});
                    });
                })
                .catch(err => callback({ok: false, error: err.toString()}));
            """
            try:
                result = driver.execute_async_script(script, img_url)
                if result and result.get("ok") and result.get("data"):
                    import base64
                    logo_file.write_bytes(base64.b64decode(result["data"]))
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

        time.sleep(0.3)


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
            time.sleep(0.3)

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
        time.sleep(0.3)

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

        time.sleep(0.2)

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
        time.sleep(0.2)

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
        time.sleep(0.1)

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
        time.sleep(0.5)

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

def main():
    parser = argparse.ArgumentParser(description="Fetch Sofascore cache via Chrome headless")
    parser.add_argument("--sport", default="tennis",
                        help="Sport à fetcher (tennis, football, basketball, all…)")
    parser.add_argument("--date", default=date.today().strftime("%Y-%m-%d"),
                        help="Date au format YYYY-MM-DD (défaut: aujourd'hui)")
    args = parser.parse_args()

    target_date = args.date
    sports_to_fetch = list(SPORTS_CONFIG.keys()) if args.sport == "all" else [args.sport]

    for sp in sports_to_fetch:
        if sp not in SPORTS_CONFIG:
            print(f"❌ Sport inconnu: {sp}. Disponibles: {', '.join(SPORTS_CONFIG)}", file=sys.stderr)
            sys.exit(1)

    print(f"🚀 fetch_sofascore_cache.py — sport(s): {sports_to_fetch}, date: {target_date}")

    driver = build_driver()
    try:
        warm_session(driver)

        for sport in sports_to_fetch:
            cfg = SPORTS_CONFIG[sport]
            if cfg["mode"] == "live_featured":
                fetch_tennis(driver, target_date)
            else:
                fetch_sport_scheduled(driver, sport, target_date)

    finally:
        driver.quit()

    print("\n✅ Fetch terminé.")


if __name__ == "__main__":
    main()
