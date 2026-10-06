#!/usr/bin/env python3
"""
Backfill ponctuel : récupère event/{id}/point-by-point pour une liste de
matchs terminés (déjà en base, avec une prédiction tightness enregistrée)
qui n'ont pas encore de fichier point-by-point en cache — pour agrandir
l'échantillon exploitable par tennis:backtest-scores.

Usage:
    python3 backfill_point_by_point.py pbp_fetch_list.csv
Format du CSV: event_id,team1_sofascore_id,team2_sofascore_id (pas d'en-tête)
"""
import csv
import json
import sys
import time
from pathlib import Path

from sofascore_extension_bridge import ExtensionBridge

OUT_DIR = Path(__file__).parent.parent / "storage" / "app" / "sofascore_cache" / "tennis_players" / "players" / "point_by_point"
OUT_DIR.mkdir(parents=True, exist_ok=True)


def main():
    csv_path = sys.argv[1] if len(sys.argv) > 1 else "pbp_fetch_list.csv"
    rows = []
    with open(csv_path, newline="") as f:
        for line in csv.reader(f):
            if len(line) >= 3 and line[0]:
                rows.append((line[0].strip(), line[1].strip(), line[2].strip()))

    print(f"🚀 Backfill point-by-point pour {len(rows)} match(s)")

    bridge = ExtensionBridge()
    print("⏳ En attente de connexion de l'extension Chrome...")
    bridge.wait_for_extension(timeout=120)
    print("✅ Extension connectée, fetch en cours...")

    ok, ko, skipped = 0, 0, 0
    for i, (event_id, home_id, away_id) in enumerate(rows, 1):
        out_file = OUT_DIR / f"pbp_{event_id}.json"
        if out_file.exists():
            skipped += 1
            continue

        url = f"https://www.sofascore.com/api/v1/event/{event_id}/point-by-point"
        data = bridge.fetch_json(url)
        if data and "pointByPoint" in data:
            data["_home_team_id"] = int(home_id) if home_id else None
            data["_away_team_id"] = int(away_id) if away_id else None
            out_file.write_text(json.dumps(data, ensure_ascii=False))
            ok += 1
        else:
            ko += 1

        if i % 25 == 0:
            print(f"  [{i}/{len(rows)}] traités... (ok={ok}, ko={ko}, skip={skipped})")
        time.sleep(0.3)

    print(f"\n📊 Terminé — ok={ok} ko={ko} skip(déjà présents)={skipped}")


if __name__ == "__main__":
    main()
