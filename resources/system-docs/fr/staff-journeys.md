## Parcours du personnel

Le travail du personnel est organisé par hubs. Les portes de rôle contrôlent quels hubs apparaissent ; dans un hub, les clés de permission contrôlent tuiles et actions.

---

## Hub Enseigner (Instructor et TA)

Instructeurs et TA ouvrent **Enseigner** depuis la barre latérale (ou la barre mobile s’ils peuvent enseigner). Le hub liste les offres où ils sont staffés.

Pour chaque offre ils peuvent :

| Domaine | Ce qu’ils font |
|---|---|
| **Contenu** | Construire semaines et éléments (vidéo, lecture, texte, devoir, quiz, examen, discussion) |
| **Évaluations** | Banques de questions, attacher des questions, créer quizzes/examens, publier les résultats, surcharger les scores |
| **Carnet** | Composantes pondérées ; amorcer depuis un modèle ; saisir ; **soumettre** ; **verrouiller** (instructeur seulement) |
| **Live** | Planifier Zoom (le système bloque les chevauchements — une licence hôte) ; importer ou surcharger la présence |
| **Discussions** | Configurer le forum, modérer, noter la participation |
| **Annonces** | Publier des mises à jour visibles dans le lecteur |
| **Roster** | Voir les inscrits ; exporter / annoncer si permis |
| **Projets / feedback / live quiz** | Lorsque activés pour l’offre (surfaces S6E) |

Le **verrouillage** publie des dossiers officiels au relevé. Un TA ne verrouille pas. Seul un **Academic Admin** peut **rouvrir** un carnet verrouillé.

### Flux enseigner typique

1. Ouvrir Enseigner → choisir une offre.
2. Construire ou ajuster le contenu et le verrouillage des semaines.
3. Configurer évaluations et poids du carnet (total 100 %).
4. Planifier les sessions live ; animer ; importer la présence.
5. Noter les rendus ; éventuellement suggestions IA pour dissertations.
6. L’instructeur soumet puis **verrouille** le carnet.
7. Correction après verrouillage → Academic Admin rouvre → instructeur re-verrouille.

---

## Academic Admin — bureau curriculum

Les Academic Admins utilisent le hub **Académique** (porte `programs.manage`).

| Tuile / domaine | Objectif |
|---|---|
| Programmes | Diplôme / certificat / grade ; plafonds crédits & semestres ; options ; seuil de réussite ; signataire certificat |
| Cours | Crédits, prix USD/EGP par défaut, flags gratuit/autonome, prérequis, compteurs d’intérêt |
| Offres | Attacher un cours à un semestre (cohorte) ou rythme libre ; places ; seuil de présence ; staff (instructeur/TA) ; prix régionaux |
| Semestres / années | Calendrier et fenêtres d’inscription (souvent partagé avec l’admin) |
| Modèles d’évaluation | Blueprints de carnet par défaut |
| Barèmes | Bandes de lettres, pourcentages, points GPA, flag de réussite |
| Traductions | Texte humain ou assisté IA ; vérifier avant usage |
| Documents | Émettre ou régénérer relevés et certificats |
| Conseil / rapports / enquêtes | Bureau de conseil, rapports académiques, enquêtes staff |

---

## Admin école — admissions, utilisateurs, thème

Les Administrative Admins utilisent le hub **Admin** (porte `users.manage`).

| Domaine | Ce qu’ils font |
|---|---|
| **Utilisateurs** | Créer et suspendre des comptes ; attribuer des rôles |
| **Formulaires** | Types de champs, obligatoires, documents |
| **File de candidatures** | Filtrer par statut ; accepter / refuser / liste d’attente avec notes ; **matriculer** les acceptés |
| **Admin inscriptions** | Déroger, gérer listes d’attente, poser ou lever des **blocages financiers** |
| **Éditeur de thème** | Nom d’école, logos clair/sombre, favicon, tokens couleur, aperçu live |
| **Help CMS** | Catégories et articles d’aide in-app (pas ces System Docs) |
| **Événements / communications** | Événements école et rapports de communication si activés |

### Flux admissions typique

1. Construire ou mettre à jour le formulaire pour la campagne.
2. Les étudiants soumettent → file « en revue ».
3. Décider acceptation / liste d’attente / refus avec notes.
4. Matriculer les acceptés dans le programme.
5. L’étudiant s’inscrit aux offres selon fenêtres, places et blocages.

---

## Administration finance

Les Financial Admins (porte `offerings.pricing` pour le bureau finance) travaillent via les tuiles admin du hub **Finance** ; tous les utilisateurs voient toujours les outils de portefeuille personnel.

| Tâche | Détail |
|---|---|
| Tarification | Prix d’offre et surcharges régionales (pays) |
| File de factures | Factures d’inscription ; factures manuelles |
| Paiements manuels | Enregistrer et vérifier espèces / virement / chèque |
| Remboursements | Approuver ; crédit vers le portefeuille étudiant |
| Portefeuille | Accorder des points ou recharger l’argent (seaux EGP/USD séparés) |
| Rapports | Soldes dus et revenus encaissés, séparés USD et EGP |
| Dons | Qui a la permission peut donner ; la finance supervise |

L’argent est toujours en **unités mineures entières** (cents / piastres). Pas de flottants ; pas de conversion FX automatique.

---

## Super Admin — plan de contrôle

Les Super Admins ouvrent **Super Admin** pour les opérations plateforme :

| Section | Contenu |
|---|---|
| **Personnes** | Raccourci utilisateurs |
| **Accès** | Hub rôles (surcharges de matrice), sécurité (ex. flush sessions) |
| **Apparence** | Éditeur de thème |
| **École** | Raccourcis académique, finance, documents, admissions, inscriptions, rapports |
| **Preuves** | Journal d’audit, observabilité (files, jobs échoués, sauvegardes), santé |
| **Ops** | Tâches planifiées, tests système, révélations d’identité feedback |

Toute écriture importante (inscription, paiement, verrouillage de notes, décision d’admission, etc.) est enregistrée via `AuditLogWriter`. Le Super Admin contourne les contrôles ; les clés à carte vide (ex. `features.manage`, `roles.manage_matrix`) restent SA seulement sauf grant via le hub Rôles.

---

## Exemples de collaboration inter-rôles

| Scénario | Qui agit |
|---|---|
| Étudiant bloqué par un hold financier | Financial ou Administrative Admin lève → inscription/paiement |
| Note verrouillée à corriger | Academic Admin rouvre → Instructor corrige → re-verrouille |
| Nouveau trimestre | Academic : offres & staff ; Administrative : formulaires & fenêtres ; Financial : prix |
| Certificat après achèvement | Academic ou Administrative Admin émet → l’étudiant partage le lien public |

Voir [Guide des rôles](roles-guide.md) et [Navigation du portail](portal-navigation.md).
