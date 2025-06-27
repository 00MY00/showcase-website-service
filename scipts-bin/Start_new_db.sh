#!/bin/bash

# Nom du fichier de base de données
DB_FILE="mes-services-db.db"

# Fichier SQL de création des tables
SCHEMA_FILE="schema.sql"

ADMIN_MAIL="admin@example.com"

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
