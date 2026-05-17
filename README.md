# HEIG-VD DévProdMéd Course - Mini-projet

Ce dépôt contient le mini-projet réalisé dans le cadre du cours
_"[Développement de produit média (DévProdMéd)](https://github.com/heig-vd-devprodmed-course/heig-vd-devprodmed-course)"_
enseigné à la
[Haute Ecole d'Ingénierie et de Gestion du Canton de Vaud (HEIG-VD)](https://heig-vd.ch),
Suisse.

## Objectif du mini-projet

L'objectif de ce mini-projet est d'étendre un réseau social existant (Laravel) avec un système de
sondage complet, consommé par un frontend Vue.js via une API JSON versionnée.

Un utilisateur connecté peut créer et gérer ses sondages, les partager via un lien public contenant
un token, et consulter les résultats en temps réel. Une personne non connectée peut voter (si elle
possède le lien) ou consulter les résultats (si ceux-ci sont publics).

## Fonctionnalités

- Tableau de bord des sondages : liste, création, édition, suppression
- Gestion des options de réponse (ajout, modification, suppression)
- Paramètres : choix simple ou multiple, résultats publics, durée en minutes
- Démarrage d'un sondage en mode brouillon, immédiatement ou manuellement
- Lien de partage par token copiable en un clic
- Page de vote publique accessible via le lien
- Vote pour les utilisateurs connectés, avec unicité garantie côté frontend et API
- Affichage conditionnel selon l'état du sondage (brouillon, actif, expiré) et les droits de l'utilisateur
- Résultats en temps réel via polling toutes les 5 secondes
- Aperçu graphique des résultats sous forme de barres CSS

## Stack technique

- **Backend** : Laravel 12, PHP 8.4, SQLite
- **Frontend** : Vue.js 3 (Composition API, `<script setup>`), Vite
- **Authentification** : Laravel Sanctum (cookie de session SPA)

## Pré-requis

- PHP >= 8.0
- Composer
- Node.js et npm

## Installation et lancement

1. Cloner le dépôt

2. Installer les dépendances :

    ```bash
    composer install
    npm install
    ```

3. Copier le fichier d'environnement :

    ```bash
    cp .env.example .env
    ```

4. Générer la clé d'application :

    ```bash
    php artisan key:generate
    ```

5. Créer le lien symbolique pour les fichiers téléversés :

    ```bash
    php artisan storage:link
    ```

6. Créer la base de données et exécuter les migrations :

    ```bash
    php artisan migrate
    ```

7. (Optionnel) Peupler la base de données avec des données fictives :

    ```bash
    php artisan db:seed
    ```

8. Démarrer les serveurs de développement :

    ```bash
    composer run dev
    ```

L'application est accessible à l'adresse <http://127.0.0.1:8000>.

## Architecture

### Deux applications Vue distinctes

Le frontend est divisé en deux applications Vue indépendantes :

- **`AppPollDashboard.vue`** — montée sur `/polls/dashboard`, accessible uniquement aux utilisateurs
  connectés. Gère la liste des sondages, le formulaire de création/édition et les actions (démarrer,
  supprimer, copier le lien).

- **`AppPollVote.vue`** — montée sur `/polls/{token}`, accessible publiquement. Affiche le sondage,
  le formulaire de vote, et les résultats.

Ce découpage est intentionnel : les deux contextes d'usage (gérer vs voter) ont des besoins et des
données différents. Les regrouper dans une seule app aurait alourdi inutilement chaque page.

Chaque app a son propre entrypoint Vite (`poll-dashboard.js`, `poll-vote.js`) déclaré dans
`vite.config.js`, ce qui génère des bundles séparés — chaque page ne charge que son JS.

### Store singleton (`usePollStore`)

Le store Vue est déclaré avec le ref `polls` **en dehors** de la fonction exportée. En Vue 3, cela
crée un état partagé entre toutes les instances du composable — équivalent d'un store global, sans
Pinia. Toutes les mutations (créer, modifier, supprimer un sondage ou une option) passent par ce
store et mettent à jour le tableau réactif localement, sans recharger toute la liste depuis l'API.

### Composable `usePolling`

La page de vote rafraîchit les résultats toutes les 5 secondes via `usePolling(refreshResults, 5000)`.
Ce composable utilise `setInterval` dans `onMounted` et `clearInterval` dans `onUnmounted` — le
polling s'arrête proprement quand le composant est détruit.

### Authentification Sanctum SPA

Les composants Vue appellent l'API via `useFetchApi`, un composable centralisé qui injecte
automatiquement le header `X-XSRF-TOKEN` (lu depuis le cookie de session Laravel) sur toutes les
requêtes mutantes. Pas de Bearer token nécessaire : l'auth se fait par cookie de session, car le
frontend est servi depuis le même domaine que le backend.

### Sérialisation des dates

Le modèle `Poll` déclare `ends_at` et `started_at` dans `$casts` avec le type `datetime`. Laravel
sérialise alors ces champs en ISO 8601 avec timezone (`2026-05-17T15:20:00.000000Z`). Sans ce cast,
les dates seraient retournées sans timezone et interprétées comme heure locale par le navigateur,
provoquant des erreurs d'expiration décalées selon le fuseau horaire de l'utilisateur.

### API versionnée

Toutes les routes API sont préfixées `/api/v1/` et déclarées dans `routes/api.php`. Les routes
publiques (`GET /polls/{token}`, `POST /polls/{token}/vote`) sont accessibles sans authentification.
Les routes de gestion (créer, modifier, démarrer, supprimer) exigent `auth:sanctum`.

## Endpoints API

| Méthode | Route | Auth | Description |
|---|---|---|---|
| GET | `/api/v1/polls` | Oui | Liste des sondages de l'utilisateur |
| POST | `/api/v1/polls` | Oui | Créer un sondage |
| PUT | `/api/v1/polls/{id}` | Oui | Modifier un sondage |
| POST | `/api/v1/polls/{id}/start` | Oui | Démarrer un sondage |
| DELETE | `/api/v1/polls/{id}` | Oui | Supprimer un sondage |
| POST | `/api/v1/polls/{id}/options` | Oui | Ajouter une option |
| PUT | `/api/v1/polls/{id}/options/{optionId}` | Oui | Modifier une option |
| DELETE | `/api/v1/polls/{id}/options/{optionId}` | Oui | Supprimer une option |
| GET | `/api/v1/polls/{token}` | Non | Afficher un sondage par token |
| POST | `/api/v1/polls/{token}/vote` | Oui | Voter |
