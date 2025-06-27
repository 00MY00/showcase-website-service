#!/bin/sh

echo "🔧 Initialisation du conteneur..."

echo ls -l '/var/www/showcase-website-service'
ls -l "/var/www/showcase-website-service"




# Valeurs par défaut
PORT="${PORT:-8089}"

echo "🔢 Port d'écoute NGINX : $PORT"
echo "📧 SUPPORT_EMAIL=${SUPPORT_EMAIL}"
echo "✍️  ENDSIGN=${ENDSIGN}"
echo "🏢 WELCOME_COMPANY_NAME=${WELCOME_COMPANY_NAME}"
echo "📎 FOOTER_CREDIT=${FOOTER_CREDIT}"
echo "👤 ADMIN_MAIL=${ADMIN_MAIL}"

# Modification du port dans la configuration NGINX
if [ -n "$PORT" ]; then 
    echo "🔄 Mise à jour du port dans la configuration NGINX..."
    sh /opt/scripts/set-nginx-port.sh "$PORT"
fi

# Remplacement des variables dans les .txt
if [ -n "$SUPPORT_EMAIL" ] && [ -n "$ENDSIGN" ] && [ -n "$WELCOME_COMPANY_NAME" ] && [ -n "$FOOTER_CREDIT" ]; then
    echo "🔄 Remplacement des variables dans les fichiers..."
    sh /opt/scripts/Update_var.sh "$SUPPORT_EMAIL" "$ENDSIGN" "$WELCOME_COMPANY_NAME" "$FOOTER_CREDIT"
else
    echo "⚠️  Variables manquantes pour Update_var.sh — passage ignoré"
fi

# Création base de données
if [ -n "$ADMIN_MAIL" ]; then
    echo "🗄️  Création de la base de données avec l'admin $ADMIN_MAIL..."
    export ADMIN_MAIL
    sh /opt/scripts/Start_new_db.sh
else
    echo "⚠️  ADMIN_MAIL manquant — la base de données ne sera pas créée"
fi

# 🔐 Sécurisation post-exécution
echo "🔐 Sécurisation des fichiers modifiés..."

chmod 400 /etc/nginx/nginx.conf || true

