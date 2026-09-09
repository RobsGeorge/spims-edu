## Qu’est-ce que SPIMS

SPIMS est un **système d’information étudiant (SIS)** et un **LMS** autonomes pour Spims, école orthodoxe copte en ligne. Produit mono-école — pas multi-locataire — accessible comme site web adapté au mobile (pas d’application native séparée dans cette version).

Les étudiants candidatent, s’inscrivent, apprennent semaine par semaine, passent des examens chronométrés, rejoignent des cours Zoom en direct, paient des factures et reçoivent des relevés ou certificats vérifiables publiquement. Le personnel gère admissions, curriculum, enseignement, notes, sessions live et finance, avec une piste d’audit des changements importants.

### Sites en ligne

| Environnement | URL | Chemin serveur |
|---|---|---|
| Production | [https://spims-edu.com](https://spims-edu.com) | `/var/www/spims` |
| Staging (aperçu) | [https://staging.spims-edu.com](https://staging.spims-edu.com) | `/var/www/spims-staging` |

### Langues et apparence

| Capacité | Détail |
|---|---|
| Langues | **Arabe** (principale, RTL), **anglais**, **français** — bascule à tout moment |
| Thème | Clair, sombre, ou selon l’appareil |
| Image de marque | Nom, logos, favicon et couleurs modifiables par les administrateurs |
| Connexion | E-mail + mot de passe, vérification OTP ; mot de passe oublié via le même flux OTP |
| Appareils | Navigateur téléphone, tablette et bureau — barre latérale sur desktop, barre inférieure sur mobile |

---

## En une phrase

Un étudiant peut créer un compte en arabe, candidater à un programme, s’inscrire, étudier semaine par semaine, passer un examen chronométré, rejoindre un Zoom en direct, payer une facture, puis recevoir un relevé ou un certificat que quiconque peut vérifier via un lien public.

---

## Résumé des capacités

| Domaine | Ce que le système prend en charge |
|---|---|
| **Identité** | Auth OTP, multi-rôles, préférence de thème, branding école |
| **Académique** | Programmes, cours, prérequis, drapeaux d’intérêt, barèmes, modèles, traductions |
| **Offres** | Années/semestres, cohorte vs rythme libre, semaines/éléments, verrouillage de contenu, tarifs régionaux |
| **Admissions** | Formulaires dynamiques, file de revue, acceptation / liste d’attente / refus, matriculation |
| **Inscription** | Fenêtres d’inscription, liste d’attente, blocages financiers, abandon, audit de diplôme |
| **Finance** | Factures, portefeuille à quatre soldes, PayPal/Paymob/Cashier, paiement mixte, reçus, dons |
| **Évaluation** | Banques de questions, runner d’examen (autosave, timer serveur), suggestion IA pour dissertations, devoirs |
| **Carnet de notes** | Composantes pondérées, verrouillage instructeur, réouverture académique, GPA, dossiers académiques |
| **Live** | Planification Zoom (un hôte), présence, rappels |
| **Communauté** | Forums, annonces, notifications in-app (+ e-mail) |
| **Documents** | Relevés, certificats programme/cours autonome, vérification publique QR/lien |
| **Aide** | Articles Help in-app (distincts de ces System Docs) |

Les utilisateurs multi-rôles reçoivent l’**union** des permissions. Menus et actions n’apparaissent que s’ils sont autorisés.

---

## Ce qui nécessite une configuration école

Les flux ci-dessus sont dans le produit. Ces intégrations exigent de vrais identifiants en production :

| Domaine | Prêt dans le produit | Configuration école / IT |
|---|---|---|
| **PayPal / Paymob / Cashier** | Branchés ; chemin démo pour le staging | Clés live pour les vrais paiements |
| **Zoom** | Planification, fenêtre de join, présence, rappels | Identifiants d’app Zoom pour de vrais liens |
| **Mail** | OTP, décisions, reçus, rappels | Serveur mail de production pour atteindre les boîtes |
| **Vimeo** | Intégration dans le lecteur de cours | Compte / IDs Vimeo pour la vidéo |
| **S3** | Chemins d’upload documents, devoirs, logos | Stockage compatible S3 en production |
| **IA (Gemini)** | Brouillon de traduction + suggestion de note dissertation | Clé Google/Gemini ; sans elle, le personnel travaille sans aide |

Sans ces secrets, l’école peut tout de même mener admissions, enseignement, notation et paiements **manuels** (espèces, virement, chèque) de bout en bout.

---

## Hors de cette version

Volontairement hors périmètre — à ne pas attendre encore :

- Notifications WhatsApp
- Applications natives iOS / Android (le site est mobile-friendly)
- Comptes parents / tuteurs
- Multi-écoles / multi-campus
- Surveillance d’examen dure (verrouillage caméra) — seulement intégrité douce (perte de focus)
- Plusieurs hôtes Zoom simultanés (le planificateur suppose **un** hôte licencié)
- Site marketing public au-delà de l’accueil et du catalogue in-app

---

## Comment le revoir avec l’école

1. Ouvrir la production ou le staging et basculer arabe, anglais ou français.
2. Parcourir le parcours **étudiant** : catalogue → candidature → inscription → lecteur → examen → portefeuille.
3. Parcourir **Enseigner** : contenu → évaluation → verrouillage du carnet.
4. Parcourir les files **admissions** et **finance** avec les rôles admin correspondants.

Un jeu de démonstration peut être chargé sur le staging. La production doit n’utiliser que de vrais comptes.

Pages liées : [Guide des rôles](roles-guide.md), [Parcours étudiant](student-journey.md), [Parcours personnel](staff-journeys.md), [Navigation du portail](portal-navigation.md).
