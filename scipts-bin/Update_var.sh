#!/bin/bash

cd '/var/www/showcase-website-service/'

# Vérification du nombre d'arguments
if [ "$#" -ne 4 ]; then
  echo "Usage: $0 SUPPORT_EMAIL ENDSIGN WELCOME_COMPANY_NAME FOOTER_CREDIT"
  exit 1
fi

# Récupération des paramètres
SUPPORT_EMAIL="$1"
ENDSIGN="$2"
WELCOME_COMPANY_NAME="$3"
FOOTER_CREDIT="$4"

# Répertoire racine à parcourir
ROOT_DIR="./"

# Parcours de tous les fichiers .txt dans le répertoire et ses sous-dossiers
find "$ROOT_DIR" -type f -name "*.txt" | while read -r file; do
  echo "Traitement du fichier : $file"

  # Création d'un fichier temporaire avec les variables remplacées
  sed -e "s/\[SUPPORT_EMAIL\]/$SUPPORT_EMAIL/g" \
      -e "s/\[ENDSIGN\]/$ENDSIGN/g" \
      -e "s/\[WELCOME_COMPANY_NAME\]/$WELCOME_COMPANY_NAME/g" \
      -e "s/\[FOOTER_CREDIT\]/$FOOTER_CREDIT/g" \
      "$file" > "${file}.tmp"

  # Remplace l'original par le nouveau contenu
  mv "${file}.tmp" "$file"
done

echo "✅ Remplacement terminé dans tous les fichiers .txt du dossier : $ROOT_DIR"
