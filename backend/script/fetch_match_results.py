#!/usr/bin/env python3
"""
Fetch match results from Sofascore via Chrome headless (bypass TLS fingerprint).
Usage: python3 fetch_match_results.py --event-ids 16385406,16385407
       python3 fetch_match_results.py --from-db   (lit les IDs depuis la DB via stdin JSON)
"""

import argparse
import json
import os
import sys
import time
from datetime import datetime

from selenium import webdriver
from selenium.webdriver.chrome.options import Options


CACHE_DIR = os.path.join(os.path.dirname(__file__), '..', 'storage', 'app', 'sofascore_cache', 'match_results')
SOFASCORE_BASE = 'https://www.sofascore.com'


def build_driver():
    opts = Options()
    opts.add_argument('--headless=new')
    opts.add_argument('--no-sandbox')
    opts.add_argument('--disable-dev-shm-usage')
    opts.add_argument('--disable-web-security')
    opts.add_argument('--disable-blink-features=AutomationControlled')
    opts.add_argument('--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
                      'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36')
    opts.add_experimental_option('excludeSwitches', ['enable-automation'])
    opts.add_experimental_option('useAutomationExtension', False)
    driver = webdriver.Chrome(options=opts)
    driver.execute_cdp_cmd('Network.setUserAgentOverride', {
        'userAgent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
                     'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
    })
    return driver


def fetch_json(driver, url, timeout=15):
    script = """
    var done = arguments[0];
    fetch(arguments[1], {headers: {'Accept': 'application/json'}})
      .then(r => r.json())
      .then(d => done({ok: true, data: d}))
      .catch(e => done({ok: false, error: e.toString()}));
    """
    result = driver.execute_async_script(script, url)
    if result and result.get('ok'):
        return result['data']
    return None


def fetch_json_url(driver, url, timeout=15):
    """Fetch JSON en naviguant directement vers l'URL API (plus fiable qu'execute_async_script)."""
    import json as _json
    try:
        driver.set_page_load_timeout(timeout)
        driver.get(url)
        body = driver.find_element('tag name', 'body').text
        return _json.loads(body)
    except Exception:
        return None


def fetch_event_result(driver, event_id):
    base = f'https://www.sofascore.com/api/v1/event/{event_id}'
    driver.set_script_timeout(20)

    event_data = fetch_json_url(driver, base)
    if not event_data or 'event' not in event_data:
        return None
    stats_data = fetch_json_url(driver, base + '/statistics')
    pbp_data   = fetch_json_url(driver, base + '/point-by-point')

    event_data, stats_data, pbp_data = event_data, stats_data, pbp_data
    if not event_data or 'event' not in event_data:
        return None

    evt = event_data['event']
    return {
        'sofascore_event_id': event_id,
        'status_type': evt.get('status', {}).get('type', 'unknown'),
        'winner_code': evt.get('winnerCode'),
        'home_team_sofascore_id': evt.get('homeTeam', {}).get('id'),
        'away_team_sofascore_id': evt.get('awayTeam', {}).get('id'),
        'home_team_name': evt.get('homeTeam', {}).get('name'),
        'away_team_name': evt.get('awayTeam', {}).get('name'),
        'home_sets': evt.get('homeScore', {}).get('current'),
        'away_sets': evt.get('awayScore', {}).get('current'),
        'score_json': {
            'homeScore': evt.get('homeScore', {}),
            'awayScore': evt.get('awayScore', {}),
        },
        'stats_json': stats_data.get('statistics') if stats_data else None,
        'point_by_point_json': pbp_data.get('pointByPoint') if pbp_data else None,
        'match_timestamp': evt.get('startTimestamp'),
        'tournament': evt.get('tournament', {}).get('name'),
        'round': evt.get('roundInfo', {}).get('name'),
        'fetched_at': datetime.utcnow().isoformat(),
    }


def save_result(result):
    os.makedirs(CACHE_DIR, exist_ok=True)
    path = os.path.join(CACHE_DIR, f"{result['sofascore_event_id']}.json")
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    return path


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--event-ids', help='Comma-separated Sofascore event IDs')
    args = parser.parse_args()

    if args.event_ids:
        event_ids = [int(x.strip()) for x in args.event_ids.split(',') if x.strip()]
    else:
        # Lire depuis stdin (JSON array d'IDs)
        raw = sys.stdin.read().strip()
        event_ids = json.loads(raw) if raw else []

    if not event_ids:
        print('Aucun event ID fourni', file=sys.stderr)
        sys.exit(1)

    print(f'Fetch de {len(event_ids)} match(s)...', flush=True)

    driver = build_driver()
    try:
        # Charger Sofascore pour avoir les cookies/contexte
        driver.get(SOFASCORE_BASE + '/fr/tennis')
        time.sleep(5)

        fetched = []
        errors = []

        for event_id in event_ids:
            print(f'  → event {event_id}...', end=' ', flush=True)
            try:
                result = fetch_event_result(driver, event_id)
                if result:
                    path = save_result(result)
                    status = result.get('status_type', '?')
                    winner = result.get('home_team_name' if result.get('winner_code') == 1 else 'away_team_name', '?')
                    print(f'OK [{status}] {result.get("home_team_name")} {result.get("home_sets")}-{result.get("away_sets")} {result.get("away_team_name")}')
                    fetched.append(event_id)
                else:
                    print('SKIP (pas de données)')
                    errors.append(event_id)
            except Exception as e:
                print(f'ERREUR: {e}')
                errors.append(event_id)
            time.sleep(0.5)

        print(f'\n✓ {len(fetched)} fetchés, {len(errors)} erreurs')
        if errors:
            print(f'  Erreurs: {errors}')

        # Output JSON pour que artisan puisse lire
        output = {'fetched': fetched, 'errors': errors, 'cache_dir': CACHE_DIR}
        print('RESULT_JSON:' + json.dumps(output))

    finally:
        driver.quit()


if __name__ == '__main__':
    main()
