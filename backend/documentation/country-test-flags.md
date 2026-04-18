# `country:test-flags` — Tester les drapeaux des pays

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan country:test-flags` |
| **Classe** | `App\Console\Commands\TestCountryFlags` |
| **Fichier** | `app/Console/Commands/TestCountryFlags.php` |
| **Catégorie** | Test / Assets |

## Description

Commande de diagnostic qui teste la fonctionnalité de téléchargement des drapeaux des pays. Affiche des statistiques générales, vérifie l'existence des drapeaux pour chaque pays, et inspecte le répertoire de stockage.

---

## Signature

```bash
php artisan country:test-flags
```

## Options

Aucune option disponible.

---

## Fonctionnement

1. **Statistiques générales** :
   - Total des pays en base
   - Pays avec un code pays défini
   - Pays avec un drapeau enregistré
2. **Test par pays** :
   - Pour chaque pays ayant un code, vérifie si le drapeau existe via `CountryFlagService::flagExists()`
   - Affiche l'URL du drapeau si disponible
3. **Vérification du stockage** :
   - Vérifie l'existence du répertoire `storage/app/public/country_flags/`
   - Compte le nombre de fichiers `.png`

---

## Dépendances

- `App\Models\Country`
- `App\Services\CountryFlagService`

---

## Exemples d'utilisation

```bash
php artisan country:test-flags
```

---

## Notes

- Commande en lecture seule : ne modifie aucune donnée.
- Utile pour diagnostiquer les problèmes de drapeaux manquants avant d'exécuter `country:download-flags --all`.
