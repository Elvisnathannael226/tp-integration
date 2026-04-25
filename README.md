# TP Final — Intégration Logicielle

**Groupe 5 :** Kabore Cedric, 
                                Korogo Gaetan, 
                                                    Tiendrebeogo Elvis Nathannael

---

## Présentation du projet

Ce projet déploie une infrastructure complète de gestion de parc informatique (ITSM) et de monitoring via Docker Compose, démarrable en une seule commande.

### Architecture globale

```
┌────────────────────────────────────────────────────────────┐
│                        glpi_network                        │
│                                                            │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │  PostgreSQL  │    │   MariaDB    │    │     GLPI     │  │
│  │  (port 5432) │    │  (port 3306) │◄───│  (port 8080) │  │
│  └──────┬───────┘    └──────┬───────┘    └──────────────┘  │
│         │                  │                               │
│         ▼                  ▼                               │
│  ┌──────────────────────────────────┐                      │
│  │            Grafana               │                      │
│  │           (port 3000)            │                      │
│  │  datasource: PostgreSQL (défaut) │                      │
│  │  datasource: MySQL (GLPI data)   │                      │
│  └──────────────────────────────────┘                      │
│                                                            │
│  ┌──────────────┐    ┌──────────────┐                      │
│  │  Prometheus  │◄───│   cAdvisor   │                      │
│  │  (port 9090) │    │  (port 8081) │                      │
│  └──────────────┘    └──────────────┘                      │
└────────────────────────────────────────────────────────────┘
```

### Rôle de chaque service

|    Service        |               Image               |                           Rôle                                            |
|-------------------|-----------------------------------|---------------------------------------------------------------------------|
| **PostgreSQL 15** | `postgres:15`                     | Base de données relationnelle (datasource Grafana, requis par le TP)      |
| **MariaDB 10.11** | `mariadb:10.11`                   | Base de données GLPI (GLPI nécessite MySQL/MariaDB)                       |
| **GLPI**          | `diouxx/glpi`                     | Système ITSM — gestion des tickets et du parc informatique                |
| **Grafana**       | `grafana/grafana:latest`          | Tableaux de bord — visualisation des données GLPI et métriques infra      |
| **Prometheus**    | `prom/prometheus:latest`          | Collecte et stockage des métriques de monitoring                          |
| **cAdvisor**      | `gcr.io/cadvisor/cadvisor:latest` | Exposition des métriques des conteneurs Docker                            |

---

## Prérequis

|       Outil    |       Version minimale         |       Vérification       |
|----------------|--------------------------------|--------------------------|
| Docker         | 24.0+                          | `docker --version`       |
| Docker Compose | 2.20+                          | `docker compose version` |
| RAM disponible | 4 Go minimum, 6 Go recommandés |          —               |
| Espace disque  | 5 Go minimum                   |          —               |

**Systèmes testés :**
- Windows 11 Pro avec Docker Desktop (WSL2 backend)
- Ubuntu 22.04 LTS
- macOS 13+

> **Windows :** Docker Desktop doit être démarré et utiliser le backend WSL2 avant tout.
> **macOS :** les montages cAdvisor (`/var/lib/docker`, `/dev/disk`) peuvent ne pas exister — commenter ces lignes dans `docker-compose.yml` si nécessaire.

---

## Instructions de démarrage (pas à pas)

### Étape 1 — Récupérer le projet

```bash
git clone https://github.com/Elvisnathannael226/tp-integration.git
cd tp-integration
```

### Étape 2 — Configurer les variables d'environnement

```bash
cp .env.example .env
# Les valeurs par défaut fonctionnent pour un test local
# Modifier les mots de passe pour un environnement de production
```

### Étape 3 — Lancer toute la stack

```bash
docker compose up -d
```

Cette commande télécharge les images et démarre tous les conteneurs. Premier lancement : 2 à 5 minutes selon la connexion.

### Étape 4 — Vérifier que tout est UP

```bash
docker compose ps
```

Tous les services doivent afficher `running` ou `healthy`.

```bash
# Surveiller les logs en temps réel
docker compose logs -f glpi
```

### Étape 5 — Configurer GLPI (premier démarrage uniquement)

1. Ouvrir http://localhost:8080
2. Passer l'étape de compatibilité (cliquer **Continuer**)
3. À l'étape "Connexion à la base de données", entrer :

|       Champ      |     Valeur     |
|------------------|----------------|
| Serveur SQL      | `mariadb:3306` |
| Utilisateur SQL  | `glpi_user`    |
| Mot de passe SQL | `Gl9xP@ssw0rd` |

4. Sélectionner la base existante `glpi` → **Continuer**
5. Suivre l'assistant jusqu'à la fin (télémétrie : ignorer → **Continuer**)
6. Se connecter avec `glpi` / `glpi`

### Étape 6 — Accéder aux autres services

|       Service      |            URL                |
|--------------------|-------------------------------|
| GLPI               | http://localhost:8080         |
| Grafana            | http://localhost:3000         |
| Prometheus         | http://localhost:9090         |
| Prometheus Targets | http://localhost:9090/targets |
| cAdvisor métriques | http://localhost:8081/metrics |

---

## Identifiants par défaut

|    Service |    Utilisateur |    Mot de passe     |
|------------|----------------|---------------------|
| GLPI       | `glpi`         | `glpi`              |
| GLPI tech  | `tech`         | `tech`              |
| Grafana    | `admin`        | `Admin@Grafana2024` |
| PostgreSQL | `grafana_user` | voir `.env`         |
| MariaDB    | `glpi_user`    | voir `.env`         |

---

## Dashboards Grafana

Deux dashboards sont provisionnés **automatiquement** au démarrage de Grafana :

|               Dashboard              |    Datasource   |                 Description                    |
|--------------------------------------|-----------------|------------------------------------------------|
| **GLPI - Gestion des Tickets**       | MySQL (MariaDB) | Tickets, statuts, catégories, équipements, SLA |
| **Monitoring Infrastructure Docker** | Prometheus      | CPU, mémoire, réseau, statut des conteneurs    |

Accès : Grafana → **Dashboards** → Browse

---

## Procédure d'arrêt et nettoyage

```bash
# Arrêter les conteneurs (données conservées dans les volumes)
docker compose down

# Arrêter ET supprimer toutes les données (remise à zéro complète)
docker compose down -v

# Supprimer également les images téléchargées
docker compose down -v --rmi all

# Vérifier qu'il ne reste aucun conteneur
docker compose ps
```

---

## Réponses aux questions PromQL (Partie 4)

### Targets Prometheus configurés

Accéder à http://localhost:9090/targets :

|    Job       |      Target      |  Statut  |                        Explication                                    |
|--------------|------------------|----------|-----------------------------------------------------------------------|
| `prometheus` | `localhost:9090` | **UP**   | Prometheus se scrape lui-même                                         |
| `cadvisor`   | `cadvisor:8080`  | **UP**   | Métriques Docker exposées par cAdvisor                                |
| `glpi`       | `glpi:80`        | **DOWN** | GLPI n'expose pas de endpoint `/metrics` natif (comportement attendu) |

### Différence entre `scrape_interval` et `evaluation_interval`

- **`scrape_interval: 15s`** — fréquence à laquelle Prometheus envoie des requêtes HTTP GET `/metrics` à chaque target pour collecter les métriques. Toutes les 15 secondes, Prometheus interroge cAdvisor, lui-même, etc.

- **`evaluation_interval: 15s`** — fréquence à laquelle Prometheus évalue les règles d'alerting et les recording rules définies dans des fichiers de règles. Ces intervalles sont indépendants : on peut scraper toutes les 15s mais n'évaluer les alertes que toutes les 60s.

### Explication de la requête PromQL

```promql
rate(container_cpu_usage_seconds_total{name!=""}[5m])
```

**Décomposition :**

|               Partie                |                                           Rôle                                |
|-------------------------------------|-------------------------------------------------------------------------------|
| `container_cpu_usage_seconds_total` | Compteur cumulatif (counter) du temps CPU consommé par conteneur, en secondes |
| `{name!=""}`                        | Filtre : exclut les conteneurs sans nom (processus système)                   |
| `[5m]`                              | Fenêtre glissante de 5 minutes pour le calcul du taux                         |
| `rate(...)`                         | Calcule le taux de variation moyen par seconde sur la fenêtre                 |

**Résultat :** la valeur représente le **nombre de secondes CPU consommées par seconde** pour chaque conteneur. Une valeur de `0.5` = 50% d'un cœur CPU utilisé en moyenne sur les 5 dernières minutes. Multiplié par 100, on obtient le pourcentage d'utilisation CPU affiché dans le dashboard.

---

## Difficultés rencontrées et solutions

### 1. GLPI incompatible avec PostgreSQL

**Problème :** Le TP demande d'utiliser PostgreSQL comme base de données pour GLPI. Cependant, après plusieurs tentatives (image `diouxx/glpi`, image custom avec `pdo_pgsql` installé, image `elestio/glpi`), aucune ne permettait à GLPI de se connecter à PostgreSQL.

L'erreur obtenue était systématiquement `Connection refused` ou `No such file or directory`, car l'installeur web de GLPI utilise toujours le protocole MySQL/PDO MySQL, même si le driver `pdo_pgsql` est installé en PHP.

**Explication technique :** GLPI est architecturalement conçu pour MySQL/MariaDB. Son code source utilise la classe `DBmysql` et des fonctions SQL spécifiques à MySQL (`GROUP_CONCAT`, syntaxe de DATETIME...). Le support PostgreSQL a été partiellement tenté dans les versions 9.x mais **officiellement abandonné**. La documentation officielle de GLPI (https://glpi-install.readthedocs.io) mentionne explicitement MySQL 8.0+ et MariaDB 10.x comme seuls moteurs supportés.

**Solution adoptée :** Utilisation de **MariaDB 10.11** pour GLPI (moteur officiellement supporté). PostgreSQL 15 est conservé dans la stack comme datasource Grafana, conformément aux exigences du TP.

### 2. Port 8081 déjà occupé

**Problème :** Au premier lancement, Docker ne pouvait pas exposer le port 8081 pour cAdvisor car un autre processus l'utilisait (PID 22384 identifié avec `netstat -ano | findstr :8081`).

**Solution :** Libération du port avec `taskkill /PID 22384 /F` avant de relancer `docker compose up -d`.

### 3. Docker Desktop non démarré

**Problème :** La première tentative de `docker compose up -d` a échoué avec l'erreur `open //./pipe/dockerDesktopLinuxEngine: The system cannot find the file specified` car Docker Desktop n'était pas lancé.

**Solution :** Lancer Docker Desktop depuis le menu Démarrer et attendre que l'icône soit verte avant de relancer la commande.

### 4. Timestamps et type de données dans Grafana

**Problème :** Les requêtes SQL initialement écrites utilisaient `to_timestamp()` et `EXTRACT(EPOCH FROM ...)` (syntaxe PostgreSQL) pour convertir les dates. Or dans MariaDB, `date_creation` est de type `TIMESTAMP` natif — ces fonctions PostgreSQL n'existent pas en MySQL.

**Solution :** Adaptation de toutes les requêtes Grafana en syntaxe MySQL : `DATE(date_creation)`, `CURDATE()`, `DATE_SUB(NOW(), INTERVAL 30 DAY)` au lieu des équivalents PostgreSQL.

### 5. Datasource UIDs en file-provisioning Grafana

**Problème :** Les dashboards Grafana exportés via l'UI contiennent des variables `${DS_...}` (section `__inputs`) pour référencer les datasources. Ces variables sont résolues uniquement lors d'un import UI, pas en provisioning par fichier — tous les panels restaient sans datasource.

**Solution :** Définir des `uid` explicites dans les fichiers de provisioning YAML (`uid: glpi-postgres`, `uid: glpi-mysql`, `uid: prometheus-ds`) et utiliser ces UIDs directement dans les JSON des dashboards. Suppression de la section `__inputs` inutile.

---

## Structure du projet

```
tp-integration/
├── docker-compose.yml          # Orchestration des 6 services
├── .env                        # Variables sensibles (non commité)
├── .env.example                # Template des variables
├── .gitignore
├── README.md
├── glpi/
│   └── config_db.php           # Référence de config BDD GLPI
├── prometheus/
│   └── prometheus.yml          # Configuration des scrape jobs
├── grafana/
│   ├── provisioning/
│   │   ├── datasources/
│   │   │   ├── postgres.yml    # Datasource PostgreSQL (défaut)
│   │   │   ├── mysql.yml       # Datasource MySQL/MariaDB (GLPI)
│   │   │   └── prometheus.yml  # Datasource Prometheus
│   │   └── dashboards/
│   │       └── dashboards.yml  # Configuration du provider
│   └── dashboards/
│       ├── glpi_dashboard.json         # Dashboard GLPI (6 panels)
│       └── monitoring_dashboard.json   # Dashboard monitoring (4 panels)
└── analyse/
    └── analyse_bdd_glpi.md     # Analyse et requêtes SQL GLPI
```
