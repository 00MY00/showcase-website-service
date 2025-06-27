
---

### 📦 Showcase Website Service

Ce dépôt contient le code source d’une plateforme web de présentation de services, dotée de fonctionnalités avancées pour la gestion des utilisateurs, la messagerie interne, et l’affichage personnalisé des services proposés.

#### ✅ Fonctionnalités principales :

* Authentification des utilisateurs avec gestion de session.
* Récupération dynamique des informations utilisateur (nom, e-mail, groupe, niveau).
* Affichage du nombre de messages non lus.
* Intégration avec une base de données SQLite locale (`mes-services-db.db`).
* Interface personnalisée en fonction du rôle et du niveau de l’utilisateur.

#### 🔧 Technologies :

* PHP (backend)
* SQLite (base de données embarquée)
* HTML/CSS

---

### 🐳 Exécution via Docker
Ce projet a été pensé dès le départ pour être facilement déployé et exécuté dans un conteneur Docker. Cela permet une configuration simplifiée, une portabilité maximale et un déploiement cohérent sur n’importe quel environnement compatible Docker.

* ⚙️ Avantages de l'approche conteneurisée :
Aucune installation complexe ni dépendance système à gérer.

* Isolation complète du projet par rapport au système hôte.

* Déploiement facilité sur un serveur distant ou en local.

* Intégration transparente avec d'autres services (base de données, proxy, etc.).

* 🚀 Lancement rapide :
Une image Docker personnalisée peut être construite à partir du Dockerfile fourni, ou bien intégrée à une configuration plus globale via docker-compose.

---

### 🔁 Reverse Proxy

Il est fortement conseillé de mettre en place un proxy inverse pour sécuriser l’accès au site.  
Vous pouvez, par exemple, utiliser **NGINX Proxy Manager**.

---

### 📄 Liste des variables

* `SUPPORT_EMAIL` — Adresse e-mail du support affichée dans les e-mails automatiques.  
* `ENDSIGN` — Texte affiché à la fin de l'e-mail en tant que signature (ex. : [Équipe Support]).  
* `WELCOME_COMPANY_NAME` — Texte affiché dans le message de bienvenue de la page d’accueil.  
* `FOOTER_CREDIT` — Nom affiché dans les crédits du pied de page.

---

### 🔐 Niveaux de privilèges

* `10` → Super administrateur  
* `5 à 9` → L'utilisateur peut être contacté par message  
* `4` → Peut voir le bouton CV et accéder à son contenu  
* `1 à 3` → Aucun droit particulier  
* `1++` → Inscrit (notation à préciser si nécessaire)  
* `0` → Visiteur non inscrit

---

### ID Compt Admin par défault

* `user = admin`
* `mdp  = showcase-website-service`

---

© Tous droits réservés — **00MY00**

---
