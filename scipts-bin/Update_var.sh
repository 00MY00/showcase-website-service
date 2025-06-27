#!/bin/bash

# Définition des variables
SUPPORT_EMAIL="support@example.com"
ENDSIGN="[Équipe Support]"
WELCOME_COMPANY_NAME="Bienvenue chez MonEntreprise"
FOOTER_CREDIT="MonEntreprise © 2025"

# Répertoire racine à parcourir
ROOT_DIR="./"

# Parcours de tous les fichiers .txt dans le répertoire et ses sous-dossiers
find "$ROOT_DIR" -type f -name "*.txt" | while read -r file; do
  echo "Traitement du fichier : $file"

  # Création d'un fichier temporaire avec les variables remplacées
  sed -e "s/

\[SUPPORT_EMAIL\]

/$SUPPORT_EMAIL/g" \
      -e "s/

\[ENDSIGN\]

/$ENDSIGN/g" \
      -e "s/

\[WELCOME_COMPANY_NAME\]

/$WELCOME_COMPANY_NAME/g" \
      -e "s/

\[FOOTER_CREDIT\]

/$FOOTER_CREDIT/g" \
      "$file" > "${file}.tmp"

  # Remplace l'original par le nouveau contenu
  mv "${file}.tmp" "$file"
done

echo "Remplacement terminé dans tous les fichiers .txt du dossier : $ROOT_DIR"
