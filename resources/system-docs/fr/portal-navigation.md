## Coque du portail

SPIMS utilise une **barre latérale** consciente des rôles sur desktop et une **barre de navigation inférieure** sur téléphone (max cinq éléments). Les pages hub présentent des **tuiles** ; le tableau de bord d’accueil utilise un **bento** asymétrique plutôt qu’une grille uniforme.

La navigation est construite par `NavigationHub` — les éléments n’apparaissent que si l’utilisateur connecté passe la porte correspondante et si la route existe.

---

## Barre latérale (nav principale)

Ordre typique pour un opérateur pleinement privilégié (les étudiants voient un sous-ensemble) :

| Élément | Nom de route | Qui le voit |
|---|---|---|
| **Accueil** | `dashboard` | Tous connectés |
| **Apprentissage** | `hubs.learning` | Tous connectés |
| **Enseigner** | `teach.index` | Qui peut enseigner (instructeur/TA sur offres) |
| **Académique** | `hubs.academic` | Bureau académique (`programs.manage`) |
| **Admin** | `hubs.admin` | Bureau administratif (`users.manage`) |
| **Finance** | `hubs.finance` | Tous connectés (tuiles admin si bureau finance) |
| **Super Admin** | `superadmin.index` | Super Admin seulement |
| **Aide** | `help.index` | Tous connectés |

Apprentissage reste actif sur lecteur de cours, notes, inscriptions, présence, événements, live quiz, projets, enquêtes et routes learn. Enseigner reste actif pour `teach.*` et le conseil lorsqu’il est lié depuis enseigner.

---

## Barre mobile inférieure (max 5)

| Emplacement | Par défaut | Alternative |
|---|---|---|
| 1 | Accueil (`dashboard`) | — |
| 2 | Apprentissage (`hubs.learning`) | — |
| 3 | **Enseigner** si l’utilisateur peut enseigner | Sinon **Catalogue** (`catalog.index`) |
| 4 | Finance (`hubs.finance`) | — |
| 5 | **Super Admin** si Super Admin | Sinon **Plus** → paramètres (`settings.edit`) |

Les destinations en trop (Académique, Admin, Aide, notifications) restent accessibles via le tiroir latéral, les tuiles de hub ou les paramètres.

---

## Tuiles de hubs

Chaque hub liste des tuiles (libellé, icône, courte description). Les routes manquantes sont omises.

### Hub Apprentissage

Catalogue, notes, mes candidatures, inscriptions, projets, sessions live, rejoindre live quiz, événements, présence, enquêtes, raccourci finance, relevé, paramètres, notifications, annonces, préférences de notification, Aide.

### Hub Académique

Conseil, programmes, cours, offres, modèles d’évaluation, semestres, documents, barèmes, traductions, politique de présence, rapport communications, modèles e-mail, modèles de certificats, enquêtes, rapports, Aide.

### Hub Admin

Utilisateurs, conseil, admin inscriptions, thème, formulaires, file de candidatures, Help CMS, communications, événements, rapports, Aide.

### Hub Finance

Finance / portefeuille personnel, don ; pour les admins finance aussi bureau finance, rapports finance, rapports école ; Aide.

### Sections Super Admin

Tuiles groupées : Personnes, Accès (rôles + sécurité), Apparence (thème), École (raccourcis académique/finance/documents/admissions/inscriptions), Preuves (audit, observabilité, santé), Ops (tâches planifiées, tests système, révélations feedback).

---

## Widgets bento du tableau de bord

Le tableau de bord (`dashboard`) est un **bento-grid** asymétrique, pas une grille de hubs plate :

| Widget | Affiche | Action principale |
|---|---|---|
| **Mes cours** | Inscriptions courantes avec % de progression | Ouvrir le lecteur ; lien catalogue si vide |
| **Prochain live** | Prochaine session Zoom planifiée (panneau mis en avant) | Rejoindre / liste live |
| **Bientôt dû** | Évaluations proches de la fermeture | Continuer vers l’évaluation |
| **Portefeuille** | Quatre pastilles : argent EGP, argent USD, points EGP, points USD | Hub Finance |
| **Notifications** | Éléments récents + badge non lus | Index des notifications |

Sous le bento, des tuiles raccourci ouvrent Apprentissage et tout hub admin accessible.

---

## Motif tuile de lien hub

Les pages hub réutilisent le partial partagé `hub-link-tile` (et les cartes `hub-tile` / `app-tile` du dashboard) : icône, titre, description d’une ligne, lien sur toute la tuile. Préférer les destinations de hub existantes plutôt que de nouveaux entrées de barre latérale.

---

## Invité vs connecté

| État | Navigation |
|---|---|
| Invité | Accueil public, catalogue, écrans d’auth — pas de hubs latéraux |
| Connecté | Coque complète selon les portes de rôle |
| Locale | Sélecteur de langue disponible ; l’arabe est RTL-primary |

---

## Conseils pour former le personnel

1. Commencer chaque démo sur **Accueil** pour que le bento corresponde à l’expérience téléphone étudiant.
2. Montrer que **Enseigner** n’apparaît que pour les instructeurs/TA staffés.
3. Montrer que **Finance** est toujours présent pour les étudiants, alors que les tuiles admin finance n’apparaissent que pour les Financial Admins.
4. Utiliser **Aide** pour les articles utilisateurs ; **System Docs** (cet ensemble) pour le briefing produit/ops.

Liés : [Parcours étudiant](student-journey.md), [Parcours personnel](staff-journeys.md), [Guide des rôles](roles-guide.md).
