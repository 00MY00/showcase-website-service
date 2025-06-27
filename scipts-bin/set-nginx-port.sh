#!/bin/bash

# Vérification d’un paramètre
if [ -z "$1" ]; then
  echo "Usage: $0 <port>"
  exit 1
fi

PORT="$1"
CONF_FILE="/etc/nginx/nginx.conf"  # Chemin réel dans le conteneur

# Vérification que le fichier existe
if [ ! -f "$CONF_FILE" ]; then
  echo "❌ Erreur : fichier '$CONF_FILE' introuvable"
  exit 1
fi

# Modification des lignes listen
sed -i "s/^ *listen [0-9]*;/    listen $PORT;/" "$CONF_FILE"
sed -i "s/^ *listen \[::\]:[0-9]*;/    listen [::]:$PORT;/" "$CONF_FILE"

echo "✅ Port changé avec succès dans '$CONF_FILE' → $PORT"
