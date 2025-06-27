#!/bin/bash

# Fichier SQL de création des tables
SCHEMA_FILE="mes-services-db.sql"

ADMIN_MAIL="admin@example.com"

cd '/var/www/showcase-website-service/assets/SQL' || exit 1


echo "🔍 Recherche de mes-services-db.sql dans tout le système..."
find / -type f -name "mes-services-db.sql" 2>/dev/null
ls -l mes-services-db.sql

# Nom du fichier de base de données
DB_FILE="mes-services-db.db"

# Supprimer l’ancienne base si elle existe
if [ -f "$DB_FILE" ]; then
    echo "⚠️ Base de données existante détectée. Suppression..."
    rm "$DB_FILE"
fi







# Création de la base de données avec les tables
sqlite3 "$DB_FILE" < "$SCHEMA_FILE"

# Vérification de l'existence du fichier SQL
if [ ! -f "$SCHEMA_FILE" ]; then
    echo "❌ Fichier de schéma introuvable: $SCHEMA_FILE"
    exit 1
fi

# Création de la base de données avec les tables
sqlite3 "$DB_FILE" < "$SCHEMA_FILE"

# Insertion de l'utilisateur administrateur
sqlite3 "$DB_FILE" <<EOF
INSERT INTO "GROUP" (NAME, LVL) VALUES ('Admin', 10);
INSERT INTO "USER" (
    GROUP_ID, NOM, PRENOM, EMAIL, NUMEROMOBIL, LANGUE, MDP, ACTIVER, TOKEN
) VALUES (
    1, 'admin', 'admin', '$ADMIN_MAIL', '', 'fr', '\$2b\$12\$ITXPmD6iJuNH2QdrAzWDE.L1O/DNyy6qV/W7dvDNyTh5J71lHktb6', 1, NULL
);
EOF

echo "🎉 Base de données créée et utilisateur admin ajouté avec succès."
