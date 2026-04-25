# Analyse de la Base de Données GLPI

## Connexion à la base

```bash
docker exec -it glpi_postgres psql -U glpi_user -d glpi
```

---

## Q1 — Les 10 tables principales de GLPI

| Table | Rôle |
|-------|------|
| `glpi_tickets` | Table centrale du système ITSM. Stocke tous les tickets d'incident ou de demande : titre, description, statut, priorité, dates, SLA. C'est la table la plus interrogée dans Grafana. |
| `glpi_users` | Contient tous les utilisateurs GLPI (techniciens, demandeurs, administrateurs). Chaque utilisateur a un identifiant, un rôle et des droits associés. |
| `glpi_computers` | Inventaire des ordinateurs du parc informatique : nom, système d'exploitation, RAM, CPU, utilisateur affecté, entité. |
| `glpi_peripherals` | Inventaire des périphériques (écrans, claviers, imprimantes, etc.) liés ou non à un ordinateur ou utilisateur. |
| `glpi_entities` | Structure organisationnelle hiérarchique (entités/sous-entités). Permet de segmenter les données par département, site ou organisation. |
| `glpi_itilcategories` | Catégories ITIL utilisées pour classifier les tickets (ex. : réseau, matériel, logiciel). Associées aux tickets via `itilcategories_id`. |
| `glpi_groups` | Groupes d'utilisateurs (équipes support, département IT). Servent à l'attribution des tickets à une équipe plutôt qu'un individu. |
| `glpi_slms` | Définitions des SLM (Service Level Management) : objectifs de temps de réponse et de résolution selon la priorité du ticket. |
| `glpi_slas` | Contient les règles SLA spécifiques (temps de réponse cible, temps de résolution cible) rattachées aux SLM. |
| `glpi_tickets_users` | Table de liaison many-to-many entre tickets et utilisateurs. Permet d'associer un demandeur, un technicien assigné ou un observateur à un ticket. |

---

## Q2 — Nombre de tickets par statut

```sql
-- Statuts GLPI :
-- 1 = Nouveau
-- 2 = En cours (attribué)
-- 3 = En cours (planifié)
-- 4 = En attente
-- 5 = Résolu
-- 6 = Clos

SELECT
  CASE status
    WHEN 1 THEN 'Nouveau'
    WHEN 2 THEN 'En cours (attribué)'
    WHEN 3 THEN 'En cours (planifié)'
    WHEN 4 THEN 'En attente'
    WHEN 5 THEN 'Résolu'
    WHEN 6 THEN 'Clos'
    ELSE 'Statut inconnu'
  END AS statut,
  status AS code_statut,
  COUNT(*) AS nombre_tickets
FROM glpi_tickets
GROUP BY status
ORDER BY status;
```

**Explication des statuts :**

| Code | Libellé | Signification |
|------|---------|---------------|
| 1 | Nouveau | Ticket créé, non encore pris en charge |
| 2 | En cours (attribué) | Ticket assigné à un technicien |
| 3 | En cours (planifié) | Une intervention est planifiée |
| 4 | En attente | En attente d'une action externe (utilisateur, tiers) |
| 5 | Résolu | Solution apportée, en attente de validation |
| 6 | Clos | Ticket fermé définitivement |

---

## Q3 — Tickets créés par mois sur les 12 derniers mois

```sql
-- GLPI stocke les dates en timestamp Unix (entier), d'où l'usage de to_timestamp()
SELECT
  TO_CHAR(DATE_TRUNC('month', to_timestamp(date_creation)), 'YYYY-MM') AS mois,
  COUNT(*) AS tickets_crees
FROM glpi_tickets
WHERE date_creation >= EXTRACT(EPOCH FROM NOW() - INTERVAL '12 months')::bigint
GROUP BY DATE_TRUNC('month', to_timestamp(date_creation))
ORDER BY DATE_TRUNC('month', to_timestamp(date_creation));
```

**Note :** `date_creation` est un entier (epoch Unix). `to_timestamp()` convertit en timestamp PostgreSQL natif pour pouvoir utiliser `DATE_TRUNC`.

---

## Q4 — Équipements informatiques associés à un utilisateur

```sql
-- Récupérer les ordinateurs affectés à un utilisateur donné
-- Remplacer 'jean.dupont' par le login de l'utilisateur recherché
SELECT
  u.name         AS login,
  u.firstname    AS prenom,
  u.realname     AS nom,
  c.name         AS ordinateur,
  c.serial       AS numero_serie,
  c.otherserial  AS numero_inventaire,
  c.date_mod     AS derniere_maj
FROM glpi_computers c
JOIN glpi_users u ON u.id = c.users_id
WHERE u.name = 'jean.dupont'
  AND c.is_deleted = 0
ORDER BY c.name;

-- Version plus complète incluant les périphériques associés à cet utilisateur
SELECT
  u.name           AS login,
  'Ordinateur'     AS type_equipement,
  c.name           AS nom_equipement,
  c.serial         AS serie
FROM glpi_computers c
JOIN glpi_users u ON u.id = c.users_id
WHERE u.name = 'jean.dupont' AND c.is_deleted = 0

UNION ALL

SELECT
  u.name           AS login,
  'Périphérique'   AS type_equipement,
  p.name           AS nom_equipement,
  p.serial         AS serie
FROM glpi_peripherals p
JOIN glpi_users u ON u.id = p.users_id
WHERE u.name = 'jean.dupont' AND p.is_deleted = 0

ORDER BY type_equipement, nom_equipement;
```

**Jointures utilisées :**
- `glpi_computers.users_id` → `glpi_users.id` : lien direct entre un ordinateur et son utilisateur affecté
- `glpi_peripherals.users_id` → `glpi_users.id` : même logique pour les périphériques

---

## Q5 — Suivi des SLA : relation entre `glpi_tickets` et `glpi_slms`

### Tables impliquées

| Table | Champs clés | Rôle |
|-------|-------------|------|
| `glpi_slms` | `id`, `name`, `type`, `comment` | Définit un contrat de niveau de service global (SLM). Le champ `type` distingue TTO (Time To Own = prise en charge) et TTR (Time To Resolve = résolution). |
| `glpi_slas` | `id`, `name`, `slms_id`, `resolution_time`, `definition_time` | Règle SLA spécifique rattachée à un SLM, avec les délais cibles en secondes. |
| `glpi_tickets` | `slas_id_tto`, `slas_id_ttr`, `time_to_own`, `time_to_resolve` | Le ticket référence deux SLA (prise en charge et résolution). Les champs `time_to_own` et `time_to_resolve` stockent les échéances calculées (epoch Unix). |

### Requête d'analyse SLA

```sql
-- Tickets avec leurs SLA et respect des délais
SELECT
  t.id                                          AS ticket_id,
  t.name                                        AS titre,
  sla_tto.name                                  AS sla_prise_en_charge,
  sla_ttr.name                                  AS sla_resolution,
  to_timestamp(t.time_to_own)                   AS echeance_prise_en_charge,
  to_timestamp(t.time_to_resolve)               AS echeance_resolution,
  CASE
    WHEN t.time_to_own IS NOT NULL
     AND t.takeintoaccount_delay_stat > 0
     AND t.time_to_own < (t.date_creation + t.takeintoaccount_delay_stat)
    THEN 'SLA TTO dépassé'
    ELSE 'SLA TTO respecté'
  END AS statut_tto
FROM glpi_tickets t
LEFT JOIN glpi_slas sla_tto ON sla_tto.id = t.slas_id_tto
LEFT JOIN glpi_slas sla_ttr ON sla_ttr.id = t.slas_id_ttr
WHERE t.is_deleted = 0
ORDER BY t.date_creation DESC
LIMIT 20;
```

### Schéma de la relation

```
glpi_slms (contrat global)
    ↓  id
glpi_slas (règle avec délai cible)
    ↓  id
glpi_tickets.slas_id_tto  →  SLA de prise en charge (TTO)
glpi_tickets.slas_id_ttr  →  SLA de résolution (TTR)
```

**Points clés :**
- Un ticket peut avoir deux SLA : un pour la prise en charge (TTO) et un pour la résolution (TTR)
- Les délais sont calculés automatiquement par GLPI selon la priorité et les calendriers
- `time_to_own` et `time_to_resolve` sont des timestamps Unix représentant les **échéances absolues**
- Le dépassement de SLA se détecte en comparant ces échéances avec les dates réelles de prise en charge (`takeintoaccount_delay_stat`) et de clôture
