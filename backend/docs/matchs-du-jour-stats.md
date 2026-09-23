# Stats "Matchs du jour" (tennis) — guide de référence

Ce document décrit chaque statistique affichée sur la page **Matchs du jour**
(`/mes-outils/matchs-du-jour`, composant `frontend/src/components/tools/TodayMatches.vue`),
d'où elle vient et comment elle est calculée. Ces stats sont pensées pour
repérer les matchs/joueurs propices à une stratégie de martingale sur le
premier set.

Toutes les probabilités "par jeu" (et non par set ou par match) sont
calculées soit à partir de l'historique réel point-by-point des 2 joueurs
(si l'échantillon est suffisant), soit via un modèle théorique basé sur leur
% de points gagnés au service.

## Score match serré (🔥 /100)

Indice global 0-100 estimant à quel point un match est disputé/équilibré.
Basé sur :
- proximité de classement / rang UTR entre les 2 joueurs
- cotes bookmaker proches
- % de points gagnés au service similaire
- activité de breaks et taux de conversion des balles de break
- historique des confrontations directes (H2H)
- fréquence de tie-breaks
- forme récente

Plus le score est haut, plus le match est potentiellement disputé.
Calculé par `ComputeTennisTightnessScores::computeForMatch()`.

## 15A / 30A / 40A

Probabilité qu'**un jeu donné** du 1er set atteigne 15-15 / 30-30 / 40-40
(deuce). C'est une proba **par jeu**, pas "au moins une fois dans le set" —
ce calcul cumulatif saturait à ~100% et était inexploitable.

- Empirique si échantillon ≥ 15 jeux (moyenne des 2 joueurs), sinon modèle
  théorique binomial à partir de `servicePointsWonPct()`.
- Formules théoriques : 15-15 = `2p(1-p)` ; 30-30 = `6p²(1-p)²` ;
  40-40 = `20p³(1-p)³` (p = % points gagnés au service).

Colonnes API : `prob_15a_set1`, `prob_30a_set1`, `prob_40a_set1`.

## 💰 Value bet / Edge

Écart entre la proba calculée et le seuil de rentabilité implicite des cotes
de référence : `edge = proba(%) − 100/cote`. **Vert = edge positif** (pari
théoriquement rentable selon notre modèle).

Cotes de référence utilisées (`ComputeTennisTightnessScores::MARKET_ODDS`) :

| Marché | Cote réf. |
|---|---|
| 15A | 1.85 |
| 30A | 2.40 |
| 40A | 3.00 |
| 30-0 (30love) | 2.20 |
| Jeu 40-0 | 3.00 |
| Jeu 40-15 | 3.00 |
| Jeu 40-30 | 3.00 |
| Mène 15-0 (leads) | 1.40 |
| Perd 1er point sur son service (lost_serve) | 2.00 |

Colonnes API : `edge_15a`, `edge_30a`, `edge_40a`, `edge_30love`,
`edge_g40_0`, `edge_g40_15`, `edge_g40_30`, `edge_leads1/2`,
`edge_lost_serve1/2`.

## n=XX (fiabilité de l'échantillon)

Taille de l'échantillon (jeux ou matchs) derrière les probas ci-dessus, le
plus petit des 2 joueurs (`min(sample_games_set1 des 2 joueurs)`).

- 🔵 ≥ 30 jeux : fiable
- 🟡 15-29 jeux : moyen
- 🔴 < 15 jeux : peu fiable (bascule vers le modèle théorique)

Colonne API : `sample_size`.

## Patterns de jeu : 30-0 / G:40-0 / G:40-15 / G:40-30

Probabilité moyenne (2 joueurs) de patterns spécifiques dans un jeu du 1er
set, calculée depuis l'historique réel point-by-point (`score.serving` /
`score.scoring` Sofascore) ou, à défaut, via un modèle théorique.

- **30-0** : un des deux joueurs mène 2 points à 0 à un moment du jeu.
  Théorique : `p² + (1-p)²`.
- **G:40-0** : le jeu se termine 40-0 (à zéro, sans deuce).
  Théorique : `p⁴ + (1-p)⁴`.
- **G:40-15** : le jeu se termine 40-15 (sans deuce).
  Théorique : `4p⁴(1-p) + 4(1-p)⁴p`.
- **G:40-30** : le jeu se termine 40-30 (sans deuce).
  Théorique : `10p⁴(1-p)² + 10(1-p)⁴p²`.

Colonnes API : `prob_30love_set1`, `prob_game_40_0`, `prob_game_40_15`,
`prob_game_40_30`.

## ≥1pt srv (points gagnés au service)

Reformulation positive de la proba de service à 0 : proba moyenne (2
joueurs) de gagner **au moins un point** sur son propre jeu de service =
`100 − prob_server_loss_to_love`. Purement informatif, aucune cote de
référence associée.

Colonne API source : `prob_server_loss_to_love` (le complément est calculé
côté frontend).

## 🥇 Nom mène 15-0 : X%

Probabilité que **ce joueur précis** (pas une moyenne) gagne le tout premier
point du jeu, qu'il serve ou relance. Asymétrique et par joueur — un joueur
faible face au n°1 mondial mènera rarement 15-0. Aucune formule théorique de
repli (uniquement empirique, `count_led_15_0 / sample_games_set1`).

Colonnes API : `prob_team1_leads_15_0`, `prob_team2_leads_15_0`,
`edge_leads1`, `edge_leads2`.

## 🎾 Nom perd 1er pt sur son service : X%

Probabilité que **ce joueur précis** perde le tout premier point **quand il
sert** — dénominateur = ses jeux servis uniquement (`sample_service_games_set1`),
contrairement à "mène 15-0" qui mélange service et retour. Utile pour repérer
les joueurs à éviter en martingale sur leur propre service (un joueur qui
perd souvent son 1er point de service à 40-0 est un mauvais candidat).
Uniquement empirique (`count_lost_first_point_on_serve / sample_service_games_set1`),
aucune formule théorique de repli.

Colonnes API : `prob_lost_serve1`, `prob_lost_serve2`, `edge_lost_serve1`,
`edge_lost_serve2`.

## Filtre "Value bets uniquement"

Coche `valueBetsOnly` → paramètre `value_bets=1` sur `/api/matches/today` →
ne garde que les matchs avec au moins un edge positif parmi tous les marchés
ci-dessus (`MatchController::today()`).

## Tri disponibles

Chaque proba/edge ci-dessus est disponible comme option de tri dans le
sélecteur (`sortOptions` de `TodayMatches.vue` / `$sortMap` de
`MatchController.php`), y compris les tris asymétriques par joueur
(`leads_15_0_desc`, `lost_serve_desc`) qui trient sur le meilleur des deux
joueurs du match via `GREATEST(...)`.

## Route API par joueur

`GET /api/stats/tennis/player/{teamId}/tension` expose individuellement
toutes ces stats brutes pour un joueur donné (indépendamment d'un match),
utile pour une analyse ciblée. Voir
`app/Http/Controllers/TennisPlayerTensionStatController.php`.

## Récapitulatif des seuils de fiabilité

| Seuil | Comportement |
|---|---|
| `sample_games_set1` ≥ 15 | Bascule sur les probas empiriques (historique réel) plutôt que le modèle théorique — voir `TennisPlayerGameTensionStat::MIN_SAMPLE_GAMES` |
| Pas de données empiriques et modèle théorique indisponible (`leads_15_0`, `lost_first_point_on_serve`) | Champ `null`, badge non affiché |
