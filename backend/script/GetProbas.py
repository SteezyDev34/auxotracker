import json
import os
import random
from datetime import datetime, timedelta
import time

import requests

SIM_MATCHES = 8000
# Fichier de log pour les joueurs non trouvés
PLAYERS_NOT_FOUND_LOG = "players_not_found.log"


########################################
# HTTP (SofaScore)
########################################

SOFASCORE_HEADERS = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
    "Accept": "application/json, text/plain, */*",
    "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8",
    "Referer": "https://www.sofascore.com/",
    "Origin": "https://www.sofascore.com",
    "Connection": "keep-alive",
}

_SOFASCORE_SESSION = requests.Session()


def _sofascore_url_to_path(url):
    """Convertit une URL SofaScore API v1 en path relatif (après /api/v1).

    Exemple:
        https://www.sofascore.com/api/v1/sport/tennis/events/live
        -> /sport/tennis/events/live

    Returns:
        str | None
    """
    prefixes = (
        "https://www.sofascore.com/api/v1",
        "https://api.sofascore.com/api/v1",
    )

    for p in prefixes:
        if url.startswith(p):
            rest = url[len(p):]
            if rest.startswith("/"):
                return rest
            return "/" + rest

    return None


def _sofascore_get_json_via_proxy(proxy_url, api_path, timeout=30):
    """Appelle un proxy HTTP local qui fetch SofaScore à notre place."""
    resp = requests.get(proxy_url, params={"path": api_path}, timeout=timeout)
    if not resp.ok:
        raise RuntimeError(f"Proxy SofaScore error (status={resp.status_code}) sur {proxy_url} path={api_path} | extrait={resp.text[:300]}")
    return resp.json()


def sofascore_get_json(url, max_attempts=3, base_delay=1.0, timeout=20):
    """Récupère une URL SofaScore en JSON avec headers + retry.

    Args:
        url (str): URL complète
        max_attempts (int): Nombre de tentatives
        base_delay (float): Délai de base (backoff linéaire)
        timeout (int): Timeout requests en secondes

    Returns:
        dict | list: JSON décodé

    Raises:
        RuntimeError: si la requête échoue (403/429 persistants, etc.)
    """
    proxy_url = os.environ.get("SOFASCORE_PROXY_URL")
    proxy_path = _sofascore_url_to_path(url) if proxy_url else None

    # Option: forcer le proxy si défini (utile quand le direct est bloqué en 403)
    force_proxy = os.environ.get("SOFASCORE_FORCE_PROXY", "").strip().lower() in ("1", "true", "yes")
    if force_proxy and proxy_url and proxy_path:
        return _sofascore_get_json_via_proxy(proxy_url, proxy_path, timeout=max(timeout, 30))

    last_status = None
    last_text = None

    for attempt in range(1, max_attempts + 1):
        try:
            resp = _SOFASCORE_SESSION.get(url, headers=SOFASCORE_HEADERS, timeout=timeout)
            last_status = resp.status_code
            last_text = resp.text[:300] if resp.text else ""

            if resp.status_code == 429:
                time.sleep(base_delay * attempt)
                continue

            if resp.status_code == 403:
                # Si un proxy est configuré, on bascule dessus (cas courant: blocage réseau/IP).
                if proxy_url and proxy_path:
                    return _sofascore_get_json_via_proxy(proxy_url, proxy_path, timeout=max(timeout, 30))

                # Sinon, on retente quand même.
                time.sleep(base_delay * attempt)
                continue

            resp.raise_for_status()
            return resp.json()
        except Exception:
            time.sleep(base_delay * attempt)

    # Dernière chance: proxy si dispo
    if proxy_url and proxy_path:
        return _sofascore_get_json_via_proxy(proxy_url, proxy_path, timeout=max(timeout, 30))

    raise RuntimeError(f"Erreur SofaScore (status={last_status}) sur {url} | extrait={last_text}")


########################################
# OUTILS POINT-BY-POINT
########################################


def _side_from_serving_value(serving):
    """Convertit la valeur SofaScore `serving` en côté logique.

    Args:
        serving (Any): Valeur provenant de `game["score"]["serving"]`.

    Returns:
        str | None: "home" si 1, "away" si 2, sinon None.
    """
    if serving == 1:
        return "home"
    if serving == 2:
        return "away"
    return None


def _point_to_value(point):
    """Normalise un score de point (jeu ou tie-break) en valeur comparable.

    Args:
        point (Any): Exemple: "0", "15", "30", "40", "A", "7".

    Returns:
        int | None: Valeur ordonnée si reconnue, sinon None.
    """
    if point is None:
        return None

    p = str(point)
    mapping = {"0": 0, "15": 1, "30": 2, "40": 3, "A": 4}
    if p in mapping:
        return mapping[p]
    if p.isdigit():
        return int(p)
    return None


def _infer_point_winner(prev_home, prev_away, cur_home, cur_away):
    """Déduit le gagnant du point en comparant l'état précédent et courant.

    Args:
        prev_home (Any): Score home avant le point
        prev_away (Any): Score away avant le point
        cur_home (Any): Score home après le point
        cur_away (Any): Score away après le point

    Returns:
        str | None: "home" ou "away" si déductible, sinon None.
    """
    ph = _point_to_value(prev_home)
    pa = _point_to_value(prev_away)
    ch = _point_to_value(cur_home)
    ca = _point_to_value(cur_away)

    if ph is None or pa is None or ch is None or ca is None:
        return None

    home_changed = ch != ph
    away_changed = ca != pa

    if home_changed and not away_changed:
        return "home" if ch > ph else "away"

    if away_changed and not home_changed:
        return "away" if ca > pa else "home"

    # Cas atypiques : on tente une heuristique simple.
    if home_changed and away_changed:
        if ch > ph and ca <= pa:
            return "home"
        if ca > pa and ch <= ph:
            return "away"

    return None


def _infer_first_server_side_from_pbp(pbp):
    """Infère le premier serveur du match à partir du point-by-point SofaScore.

    On cherche le set le plus ancien, puis le jeu le plus ancien de ce set, et on
    lit `score.serving` (1=home sert, 2=away sert).

    Args:
        pbp (list[dict]): Contenu `pointByPoint` renvoyé par SofaScore.

    Returns:
        str | None: "home" ou "away" si inférable, sinon None.
    """
    if not pbp:
        return None

    try:
        sets_sorted = sorted(pbp, key=lambda s: s.get("set", 10**9))
        if not sets_sorted:
            return None

        first_set = sets_sorted[0]
        games = first_set.get("games") or []
        if not games:
            return None

        games_sorted = sorted(games, key=lambda g: g.get("game", 10**9))
        first_game = games_sorted[0]
        serving = first_game.get("score", {}).get("serving")
        return _side_from_serving_value(serving)
    except:
        return None


def _a_serves_game(game_index, first_server_side):
    """Retourne True si A sert sur le jeu `game_index`.

    Args:
        game_index (int): Index 0-based du jeu dans le match
        first_server_side (str): "A" ou "B"

    Returns:
        bool: True si A sert, sinon False
    """
    if first_server_side == "B":
        return (game_index % 2) == 1
    return (game_index % 2) == 0


########################################
# API SOFASCORE
########################################

def get_live_matches():
    url = "https://www.sofascore.com/api/v1/sport/tennis/events/live"
    return sofascore_get_json(url)["events"]


def get_scheduled_events_tennis(date_str=None):
    """Récupère les matchs de tennis programmés pour une date donnée.

    Cette logique est la même que dans `ImportTennisPlayers.php` :
    endpoint `/api/v1/sport/tennis/scheduled-events/YYYY-MM-DD`.

    Args:
        date_str (str | None): Date au format YYYY-MM-DD. Si None, utilise la date locale du jour.

    Returns:
        list[dict]: Liste d'objets event (contenus dans "events").
    """
    if date_str is None:
        date_str = datetime.now().strftime("%Y-%m-%d")

    url = f"https://www.sofascore.com/api/v1/sport/tennis/scheduled-events/{date_str}"
    result = sofascore_get_json(url)
    return result.get("events", [])


def get_stats(player_id):
    year = datetime.now().year
    url = f"https://www.sofascore.com/api/v1/team/{player_id}/year-statistics/{year}"
    return sofascore_get_json(url)["statistics"]


def get_last_events(player_id):
    url = f"https://www.sofascore.com/api/v1/team/{player_id}/events/last/0"
    return sofascore_get_json(url)["events"]


def get_point_by_point(event_id):
    url = f"https://www.sofascore.com/api/v1/event/{event_id}/point-by-point"
    return sofascore_get_json(url)["pointByPoint"]


def get_h2h(custom_id):
    url = f"https://www.sofascore.com/api/v1/event/{custom_id}/h2h/events"
    return sofascore_get_json(url)["events"]


########################################
# SERVICE STRENGTH
########################################

def service_strength(stats):
    s = stats[0]

    first_in = s["firstServeTotal"] / s["totalServeAttempts"]
    first_win = s["firstServePointsScored"] / s["firstServePointsTotal"]
    second_win = s["secondServePointsScored"] / s["secondServePointsTotal"]

    return first_in * first_win + (1 - first_in) * second_win


########################################
# RETURN STRENGTH (POINT BY POINT)
########################################
def return_strength(events, player_id):
    """Estime la force en retour d'un joueur via les 5 derniers matchs.

    La métrique retournée est une probabilité : points gagnés quand le joueur est
    relanceur / points joués quand il est relanceur.

    Args:
        events (list[dict]): Liste des matchs SofaScore (via get_last_events)
        player_id (int): Identifiant SofaScore du joueur

    Returns:
        float: Estimation entre 0 et 1
    """
    won = 0
    total = 0

    for e in events[:5]:

        try:

            home_id = e.get("homeTeam", {}).get("id")
            away_id = e.get("awayTeam", {}).get("id")

            if home_id == player_id:
                player_side = "home"
            elif away_id == player_id:
                player_side = "away"
            else:
                continue

            pbp = get_point_by_point(e["id"])

            for s in pbp:
                for g in s.get("games", []):

                    serving_side = _side_from_serving_value(g.get("score", {}).get("serving"))
                    if serving_side is None:
                        continue

                    points = g.get("points") or []
                    prev_home = "0"
                    prev_away = "0"

                    for p in points:

                        cur_home = p.get("homePoint")
                        cur_away = p.get("awayPoint")
                        winner_side = _infer_point_winner(prev_home, prev_away, cur_home, cur_away)

                        # On compte uniquement les points où le joueur est relanceur.
                        if player_side != serving_side:
                            total += 1
                            if winner_side == player_side:
                                won += 1

                        prev_home = cur_home
                        prev_away = cur_away

        except:
            pass

    if total == 0:
        return 0.35

    return won / total


########################################
# FATIGUE
########################################

def fatigue_factor(events):
    last = events[0]["startTimestamp"]
    now = datetime.now().timestamp()

    rest = (now - last) / 86400

    if rest < 1:
        return 0.95

    if rest > 3:
        return 1.02

    return 1


########################################
# H2H (2 ans minimum)
########################################

def h2h_factor(events, player):
    now = datetime.now().timestamp()

    wins = 0
    total = 0

    for e in events:

        if now - e["startTimestamp"] > 2 * 365 * 86400:
            continue

        if "homeScore" not in e or "awayScore" not in e:
            continue

        if "current" not in e["homeScore"] or "current" not in e["awayScore"]:
            continue

        total += 1

        hs = e["homeScore"]["current"]
        ascore = e["awayScore"]["current"]

        if e["homeTeam"]["id"] == player and hs > ascore:
            wins += 1

        if e["awayTeam"]["id"] == player and ascore > hs:
            wins += 1

    if total < 2:
        return 0.5

    return wins / total


########################################
# HISTORIQUE DES JEUX (POINT BY POINT)
########################################

def game_profile(events, player_id=None):
    stats = {
        "15_0": 0,
        "0_15": 0,
        "15_15": 0,
        "30_30": 0,
        "40_40": 0,
        "30_0": 0,
        "0_30": 0,
        "40_0": 0,
        "0_40": 0,
        "40_15": 0,
        "15_40": 0,
        "30_15_3": 0,
        "15_30_3": 0,
        "game4": 0,
        "game5": 0,
        "game6": 0
    }

    games = 0

    for e in events[:5]:

        try:

            pbp = get_point_by_point(e["id"])

            home_id = e.get("homeTeam", {}).get("id")
            away_id = e.get("awayTeam", {}).get("id")

            player_side = None
            if player_id is not None:
                if home_id == player_id:
                    player_side = "home"
                elif away_id == player_id:
                    player_side = "away"
                else:
                    continue

            for s in pbp:
                for g in s["games"]:

                    serving_side = _side_from_serving_value(g.get("score", {}).get("serving"))
                    if player_side is not None:
                        # On ne garde que les jeux où le joueur sert, car les événements
                        # sont définis côté serveur (comme simulate_game).
                        if serving_side != player_side:
                            continue

                    games += 1
                    points = g["points"]

                    pc = len(points)

                    if pc == 4: stats["game4"] += 1
                    if pc == 5: stats["game5"] += 1
                    if pc == 6: stats["game6"] += 1

                    for i, p in enumerate(points):

                        hp = p["homePoint"]
                        ap = p["awayPoint"]

                        # Normalise en "serveur / relanceur".
                        if player_side is not None:
                            # Ici, le serveur est forcément le joueur.
                            if player_side == "home":
                                sp, rp = hp, ap
                            else:
                                sp, rp = ap, hp
                        else:
                            # Mode générique : si on a l'info du serveur, on s'aligne dessus.
                            if serving_side == "home":
                                sp, rp = hp, ap
                            elif serving_side == "away":
                                sp, rp = ap, hp
                            else:
                                sp, rp = hp, ap

                        if sp == "15" and rp == "0":
                            stats["15_0"] += 1

                        if sp == "0" and rp == "15":
                            stats["0_15"] += 1

                        if sp == "15" and rp == "15":
                            stats["15_15"] += 1

                        if sp == "30" and rp == "30":
                            stats["30_30"] += 1

                        if sp == "40" and rp == "40":
                            stats["40_40"] += 1

                        if sp == "30" and rp == "0":
                            stats["30_0"] += 1

                        if sp == "0" and rp == "30":
                            stats["0_30"] += 1

                        if sp == "40" and rp == "0":
                            stats["40_0"] += 1

                        if sp == "0" and rp == "40":
                            stats["0_40"] += 1

                        if sp == "40" and rp == "15":
                            stats["40_15"] += 1

                        if sp == "15" and rp == "40":
                            stats["15_40"] += 1

                        if i == 2:

                            if sp == "30" and rp == "15":
                                stats["30_15_3"] += 1

                            if sp == "15" and rp == "30":
                                stats["15_30_3"] += 1

        except:
            pass

    if games == 0:
        return None

    for k in stats:
        stats[k] /= games

    return stats


########################################
# PROBA POINT SERVEUR
########################################

def point_probability(service, retour, fatigue, h2h):
    p = (
            0.50 * service +
            0.35 * (1 - retour) +
            0.15 * h2h
    )

    p *= fatigue

    if p > 0.75:
        p = 0.75

    if p < 0.45:
        p = 0.45

    return p


########################################
# SIMULATION MARKOV JEU
########################################

def simulate_game(p):
    s = 0
    r = 0
    points = 0
    events = {}

    server_won = None

    while True:

        points += 1

        if random.random() < p:
            s += 1
        else:
            r += 1

        if s == 1 and r == 0: events["15_0"] = True
        if s == 0 and r == 1: events["0_15"] = True

        if s == 1 and r == 1: events["15_15"] = True
        if s == 2 and r == 2: events["30_30"] = True
        if s == 3 and r == 3: events["40_40"] = True

        if s == 2 and r == 0: events["30_0"] = True
        if s == 0 and r == 2: events["0_30"] = True

        if s == 3 and r == 0: events["40_0"] = True
        if s == 0 and r == 3: events["0_40"] = True

        if s == 3 and r == 1: events["40_15"] = True
        if s == 1 and r == 3: events["15_40"] = True

        if points == 3:

            if s == 2 and r == 1:
                events["30_15_3"] = True

            if s == 1 and r == 2:
                events["15_30_3"] = True

        if s >= 4 and s - r >= 2:
            server_won = True
            break

        if r >= 4 and r - s >= 2:
            server_won = False
            break

    if points == 4: events["game4"] = True
    if points == 5: events["game5"] = True
    if points == 6: events["game6"] = True

    # Par sécurité, si on n'a pas déterminé le gagnant, on suppose que le serveur perd.
    if server_won is None:
        server_won = False

    return events, server_won


########################################
# SIMULATION MATCH
########################################

def simulate_match(pA, pB, first_server_side="A"):
    return simulate_match_scoped(pA, pB, first_server_side=first_server_side)["all"]


def simulate_match_scoped(pA, pB, first_n_games_15_0=4, first_n_games_0_15=8, first_n_games_other=10, first_server_side="A"):
    """
    Simule un match complet et remonte :
    - les événements observés au moins une fois sur tout le match
    - les événements observés au moins une fois sur les N premiers jeux du match (pour 15_0)
    - les événements observés au moins une fois sur les N premiers jeux du match (pour 0_15)
    - les événements observés au moins une fois sur les N premiers jeux du match (pour les autres)

    Args:
        pA (float): Probabilité de gagner un point quand A sert
        pB (float): Probabilité de gagner un point quand B sert
        first_n_games_15_0 (int): Fenêtre pour l'événement 15_0 (ex: 4 => jeux 1→4)
        first_n_games_0_15 (int): Fenêtre pour l'événement 0_15 (ex: 8 => jeux 1→8)
        first_n_games_other (int): Fenêtre pour les autres événements (ex: 10 => jeux 1→10)

    Returns:
        dict: {"all": {...}, "first_15_0": {...}, "first_0_15": {...}, "first_other": {...}}
    """
    events_all = {}
    events_first_15_0 = {}
    events_first_0_15 = {}
    events_first_other = {}

    setA = setB = 0
    game_index = 0

    while setA < 2 and setB < 2:

        gA = gB = 0

        while True:

            a_serves = _a_serves_game(game_index, first_server_side)
            p = pA if a_serves else pB

            ev, server_won = simulate_game(p)

            for k in ev:
                events_all[k] = True

            if game_index < first_n_games_15_0:
                for k in ev:
                    events_first_15_0[k] = True

            if game_index < first_n_games_0_15:
                for k in ev:
                    events_first_0_15[k] = True

            if game_index < first_n_games_other:
                for k in ev:
                    events_first_other[k] = True

            game_index += 1
            if server_won:
                if a_serves:
                    gA += 1
                else:
                    gB += 1
            else:
                if a_serves:
                    gB += 1
                else:
                    gA += 1

            if gA >= 6 and gA - gB >= 2:
                setA += 1
                break

            if gB >= 6 and gB - gA >= 2:
                setB += 1
                break

            if gA == 6 and gB == 6:

                if random.random() < 0.5:
                    setA += 1
                else:
                    setB += 1

                break

    return {
        "all": events_all,
        "first_15_0": events_first_15_0,
        "first_0_15": events_first_0_15,
        "first_other": events_first_other,
    }


########################################
# CALCUL PROBABILITÉS
########################################

def compute_probabilities(pA, pB, first_n_games_15_0=4, first_n_games_0_15=8, first_n_games_other=10, first_server_side="A"):
    keys = [
        "15_0", "0_15",
        "15_15", "30_30", "40_40",
        "30_0", "0_30",
        "40_0", "0_40",
        "40_15", "15_40",
        "30_15_3", "15_30_3",
        "game4", "game5", "game6"
    ]

    other_keys = [k for k in keys if k not in ("15_0", "0_15")]
    keys += [f"{k}_first10" for k in other_keys]

    counters = {k: 0 for k in keys}

    for _ in range(SIM_MATCHES):

        scoped = simulate_match_scoped(
            pA,
            pB,
            first_n_games_15_0=first_n_games_15_0,
            first_n_games_0_15=first_n_games_0_15,
            first_n_games_other=first_n_games_other,
            first_server_side=first_server_side,
        )

        # Par défaut, tous les événements sont évalués sur le match complet.
        for k in scoped["all"]:
            if k in ("15_0", "0_15"):
                continue
            counters[k] += 1

        # Exceptions demandées :
        # - "15_0" uniquement sur les 4 premiers jeux
        # - "0_15" uniquement sur les 8 premiers jeux
        if "15_0" in scoped["first_15_0"]:
            counters["15_0"] += 1

        if "0_15" in scoped["first_0_15"]:
            counters["0_15"] += 1

        # Calcul additionnel : autres événements sur les 10 premiers jeux.
        for k in scoped["first_other"]:
            if k in ("15_0", "0_15"):
                continue
            counters[f"{k}_first10"] += 1

    for k in counters:
        counters[k] /= SIM_MATCHES

    return counters


########################################
# ANALYSE MATCH
########################################

def analyze_match(playerA, playerB, custom_id, event_id=None, first_server_side=None):
    statsA = get_stats(playerA)
    statsB = get_stats(playerB)

    eventsA = get_last_events(playerA)
    eventsB = get_last_events(playerB)

    serviceA = service_strength(statsA)
    serviceB = service_strength(statsB)

    returnA = return_strength(eventsA, playerA)
    returnB = return_strength(eventsB, playerB)

    fatigueA = fatigue_factor(eventsA)
    fatigueB = fatigue_factor(eventsB)

    h2h = get_h2h(custom_id)

    h2hA = h2h_factor(h2h, playerA)
    h2hB = h2h_factor(h2h, playerB)

    histA = game_profile(eventsA, player_id=playerA)
    histB = game_profile(eventsB, player_id=playerB)

    pA = point_probability(serviceA, returnB, fatigueA, h2hA)
    pB = point_probability(serviceB, returnA, fatigueB, h2hB)

    # Déduction du premier serveur (si match déjà entamé) :
    inferred_first_server = None
    if first_server_side is None and event_id is not None:
        try:
            pbp_live = get_point_by_point(event_id)
            first_server_home_away = _infer_first_server_side_from_pbp(pbp_live)
            if first_server_home_away == "home":
                inferred_first_server = "A"
            elif first_server_home_away == "away":
                inferred_first_server = "B"
        except:
            inferred_first_server = None

    normalized_first_server = first_server_side
    if isinstance(normalized_first_server, str):
        fs = normalized_first_server.strip().lower()
        if fs in ("a", "home"):
            normalized_first_server = "A"
        elif fs in ("b", "away"):
            normalized_first_server = "B"

    first_server = normalized_first_server or inferred_first_server or "A"

    # Tous les événements sur le match complet, sauf :
    # - "15_0" sur les 4 premiers jeux
    # - "0_15" sur les 8 premiers jeux
    markov = compute_probabilities(
        pA,
        pB,
        first_n_games_15_0=4,
        first_n_games_0_15=8,
        first_server_side=first_server,
    )

    final = {}

    for k in markov:

        base_key = k

        if k.endswith("_first10"):
            base_key = k[:-8]

        if histA and base_key in histA and histB and base_key in histB:

            hist = (histA[base_key] + histB[base_key]) / 2

            final[k] = 0.7 * markov[k] + 0.3 * hist

        else:

            final[k] = markov[k]

    return final


def _find_event_by_player_ids(events, player1_id, player2_id):
    """Retrouve un event SofaScore correspondant à deux joueurs, quel que soit l'ordre home/away."""
    print(f"Recherche du match: player1_id={player1_id}, player2_id={player2_id}")
    print(f"Nombre d'événements à parcourir: {len(events)}")
    for ev in events:
        try:
            home_id = ev.get("homeTeam", {}).get("id")
            away_id = ev.get("awayTeam", {}).get("id")
            if {home_id, away_id} == {int(player1_id), int(player2_id)}:
                print(f"Match trouvé: home={home_id}, away={away_id}")
                return ev
        except:
            continue
    print("Aucun match trouvé avec ces IDs")
    return None


def predict_featured_match(player1_id, player2_id, first_server_player_id, date_str=None):
    """Prédit un match (pré-match ou jour J) via les events programmés du jour.

    Historique : cette fonction utilisait `odds/featured-events/tennis`, qui n'est
    pas fiable pour retrouver *tous* les matchs. Elle est maintenant basée sur
    `sport/tennis/scheduled-events/YYYY-MM-DD`.

    Args:
        player1_id (int): ID SofaScore d'un des joueurs
        player2_id (int): ID SofaScore de l'autre joueur
        first_server_player_id (int): ID SofaScore du joueur qui sert en premier
        date_str (str | None): Date YYYY-MM-DD à interroger. Si None, tente aujourd'hui puis demain.

    Returns:
        dict: {
            "event": {"id":..., "slug":..., "customId":..., "homeTeam":..., "awayTeam":...},
            "first_server_side": "home"|"away",
            "markets": [{"event": str, "prob": float}],
            "raw": dict
        }
    """
    dates_to_try = []
    if date_str:
        dates_to_try = [date_str]
    else:
        today = datetime.now().date()
        dates_to_try = [today.strftime("%Y-%m-%d"), (today + timedelta(days=1)).strftime("%Y-%m-%d")]

    ev = None
    for d in dates_to_try:
        scheduled = get_scheduled_events_tennis(d)

        print(f"Nombre d'événements programmés pour {d}: {len(scheduled)}")
        ev = _find_event_by_player_ids(scheduled, player1_id, player2_id)
        if ev is not None:
            break

    if ev is None:
        raise ValueError("Match introuvable dans scheduled-events pour ces deux IDs joueurs")

    home_id = ev.get("homeTeam", {}).get("id")
    away_id = ev.get("awayTeam", {}).get("id")

    if str(first_server_player_id) == str(home_id):
        first_server_side = "home"
    elif str(first_server_player_id) == str(away_id):
        first_server_side = "away"
    else:
        raise ValueError("first_server_player_id ne correspond ni au home ni au away de l'event")

    probs = analyze_match(
        home_id,
        away_id,
        ev.get("customId"),
        event_id=ev.get("id"),
        first_server_side=first_server_side,
    )

    markets = [{"event": k, "prob": float(v)} for k, v in probs.items()]
    markets.sort(key=lambda x: x["prob"], reverse=True)

    data= {
        "event": {
            "id": ev.get("id"),
            "slug": ev.get("slug"),
            "customId": ev.get("customId"),
            "homeTeam": ev.get("homeTeam"),
            "awayTeam": ev.get("awayTeam"),
            "startTimestamp": ev.get("startTimestamp"),
            "status": ev.get("status"),
        },
        "first_server_side": first_server_side,
        "markets": markets,
        "raw": probs,
    }
    return probs

########################################
# SCANNER MATCHS
########################################

def scan_matches(playerName1, playerName2, first_server_player_id):

    playerA, playerB = get_players_sofascore_id(playerName1, playerName2)
    if first_server_player_id == 1:
        first_server_player_id = playerA
    else:
        first_server_player_id = playerB

    print("IDs des joueurs: {} vs {}, first server: {}".format(playerA, playerB, first_server_player_id))
    print(predict_featured_match(playerA, playerB, first_server_player_id))


def get_players_sofascore_id(playerName1, playerName2):
    print("===== DÉBUT FONCTION proba =====")
    line = 1
    print(f"Joueurs: {playerName1} vs {playerName2}")

    print("Recherche des IDs des joueurs via API...")
    url1 = f"http://datas.sc2vagr6376.universe.wf/api/sports/2/teams/search?search={playerName1.replace(' ', '+')}"
    url2 = f"http://datas.sc2vagr6376.universe.wf/api/sports/2/teams/search?search={playerName2.replace(' ', '+')}"
    try:
        print(f"Requête API pour {playerName1}: {url1}")
        print(f"Requête API pour {playerName2}: {url2}")
        # Définition des headers pour les requêtes HTTP
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        }
        # Désactiver la vérification SSL pour les certificats auto-signés
        def get_json_with_retry(url, playerName, max_attempts=3, delay=5):
            for attempt in range(max_attempts):
                resp = requests.get(url, headers=headers, verify=False)
                print(f"Tentative {attempt+1}/{max_attempts} pour {playerName}, statut: {resp.status_code}")
                if resp.status_code == 429:
                    print(f"429 Too Many Requests pour {playerName}, attente {delay}s...")
                    time.sleep(delay)
                    continue
                try:
                    if resp.ok and resp.text.strip():
                        return resp.json()
                    else:
                        print(f"Réponse vide ou non-JSON pour {playerName}: {resp.text}")
                        return {}
                except Exception as e:
                    print(f"Erreur JSON pour {playerName}: {e}, contenu: {resp.text}")
                    return {}
            print(f"Échec après {max_attempts} tentatives pour {playerName}")
            return {}

        d1 = get_json_with_retry(url1, playerName1)
        d2 = get_json_with_retry(url2, playerName2)


        # Vérifier si des résultats ont été trouvés
        if not d1.get('data') or len(d1['data']) == 0:
            print(f"Aucun résultat trouvé pour {playerName1}")
            print(playerName1, "get_wta_proba_40A_sofascore")
        if not d2.get('data') or len(d2['data']) == 0:
            print(f"Aucun résultat trouvé pour {playerName2}")
            print(playerName2, "get_wta_proba_40A_sofascore")

        # Sélectionner le joueur individuel (non double) si possible
        pid1 = None
        for player in d1['data']:
            # Éviter les équipes de double (contiennent souvent '/')
            if '/' not in player.get('name', ''):
                pid1 = player.get('sofascore_id')
                #pid1 = player.get('id')
                break

        pid2 = None
        for player in d2['data']:
            if '/' not in player.get('name', ''):
                pid2 = player.get('sofascore_id')
                #pid2 = player.get('id')
                break

        # Si aucun joueur individuel n'a été trouvé, utiliser le premier résultat
        if pid1 is None and d1['data']:
            pid1 = d1['data'][0]['sofascore_id']
            #pid1 = d1['data'][0]['id']
            print(f"Aucun joueur individuel trouvé pour {playerName1}, utilisation du premier résultat (ID: {pid1})")

        if pid2 is None and d2['data']:
            pid2 = d2['data'][0]['sofascore_id']
            #pid2 = d2['data'][0]['id']
            print(f"Aucun joueur individuel trouvé pour {playerName2}, utilisation du premier résultat (ID: {pid2})")

        if not pid1 and not pid2:
            print("Impossible de récupérer les IDs des joueurs")
            return False
    except Exception as e:
        print(f"Erreur lors de la récupération des IDs", 'warning')
        # Ajouter un avertissement sur la désactivation de la vérification SSL
        return False
    else:
        return pid1, pid2

########################################
# MAIN
########################################

if __name__ == "__main__":


    playerName1, playerName2, first_server_player_id = "Sebastian Korda", "Francisco Comesana", 1
    scan_matches(playerName1, playerName2, first_server_player_id)
