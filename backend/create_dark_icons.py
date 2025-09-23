#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script pour créer des copies blanches des icônes SVG de sport
Ajoute le suffixe -dark aux noms de fichiers et rend les icônes entièrement blanches
"""

import os
import re
import glob
from pathlib import Path

def convertir_svg_en_blanc(contenu_svg):
    """
    Convertit un SVG en version blanche en remplaçant toutes les couleurs par du blanc
    Préserve la transparence et la forme
    """
    # Remplacer les attributs fill avec des couleurs par fill="white"
    contenu_svg = re.sub(r'fill="[^"]*"(?!\s*=\s*"none")', 'fill="white"', contenu_svg)
    contenu_svg = re.sub(r"fill='[^']*'(?!\s*=\s*'none')", "fill='white'", contenu_svg)
    
    # Remplacer les styles inline avec des couleurs
    contenu_svg = re.sub(r'fill:\s*[^;}\s]+(?!\s*:\s*none)', 'fill:white', contenu_svg)
    
    # Remplacer les attributs stroke avec des couleurs par stroke="white"
    contenu_svg = re.sub(r'stroke="[^"]*"(?!\s*=\s*"none")', 'stroke="white"', contenu_svg)
    contenu_svg = re.sub(r"stroke='[^']*'(?!\s*=\s*'none')", "stroke='white'", contenu_svg)
    
    # Remplacer les styles stroke inline
    contenu_svg = re.sub(r'stroke:\s*[^;}\s]+(?!\s*:\s*none)', 'stroke:white', contenu_svg)
    
    # Ajouter fill="white" aux éléments qui n'ont pas d'attribut fill
    # Rechercher les balises path, circle, rect, polygon, etc. sans fill
    elements_svg = ['path', 'circle', 'rect', 'polygon', 'ellipse', 'line', 'polyline']
    for element in elements_svg:
        # Pattern pour trouver les éléments sans fill
        pattern = f'<{element}(?![^>]*fill=)[^>]*>'
        def ajouter_fill(match):
            balise = match.group(0)
            if balise.endswith('/>'):
                return balise[:-2] + ' fill="white"/>'
            else:
                return balise[:-1] + ' fill="white">'
        contenu_svg = re.sub(pattern, ajouter_fill, contenu_svg)
    
    return contenu_svg

def traiter_icones_sport():
    """
    Traite toutes les icônes SVG dans le dossier sport_icons
    """
    # Chemin vers le dossier des icônes
    dossier_icones = "/Users/steeven/PROJETS/WORKSPACE/NEW BET TRACKER/backend/storage/app/public/sport_icons"
    
    # Vérifier que le dossier existe
    if not os.path.exists(dossier_icones):
        print(f"❌ Le dossier {dossier_icones} n'existe pas")
        return
    
    # Obtenir tous les fichiers SVG
    fichiers_svg = glob.glob(os.path.join(dossier_icones, "*.svg"))
    
    if not fichiers_svg:
        print("❌ Aucun fichier SVG trouvé dans le dossier")
        return
    
    print(f"📁 Traitement de {len(fichiers_svg)} fichiers SVG...")
    
    compteur_succes = 0
    compteur_erreurs = 0
    
    for chemin_fichier in fichiers_svg:
        try:
            # Obtenir le nom du fichier sans extension
            nom_fichier = Path(chemin_fichier).stem
            
            # Vérifier si c'est déjà une version dark
            if nom_fichier.endswith('-dark'):
                print(f"⏭️  Ignoré (déjà une version dark): {nom_fichier}.svg")
                continue
            
            # Lire le contenu du fichier SVG
            with open(chemin_fichier, 'r', encoding='utf-8') as f:
                contenu_original = f.read()
            
            # Convertir en version blanche
            contenu_blanc = convertir_svg_en_blanc(contenu_original)
            
            # Créer le nom du nouveau fichier avec suffixe -dark
            nouveau_nom = f"{nom_fichier}-dark.svg"
            nouveau_chemin = os.path.join(dossier_icones, nouveau_nom)
            
            # Écrire le nouveau fichier
            with open(nouveau_chemin, 'w', encoding='utf-8') as f:
                f.write(contenu_blanc)
            
            print(f"✅ Créé: {nouveau_nom}")
            compteur_succes += 1
            
        except Exception as e:
            print(f"❌ Erreur lors du traitement de {Path(chemin_fichier).name}: {e}")
            compteur_erreurs += 1
    
    print(f"\n📊 Résumé:")
    print(f"   ✅ Fichiers créés avec succès: {compteur_succes}")
    print(f"   ❌ Erreurs: {compteur_erreurs}")
    print(f"   📁 Dossier de destination: {dossier_icones}")

if __name__ == "__main__":
    print("🎨 Création des icônes SVG blanches avec suffixe -dark")
    print("=" * 60)
    traiter_icones_sport()
    print("=" * 60)
    print("✨ Terminé!")