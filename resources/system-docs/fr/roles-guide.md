## Sept rôles en un coup d’œil

SPIMS a exactement sept rôles. Une personne peut en porter plusieurs. Les permissions effectives sont l’**union** de tous les rôles — par exemple un instructeur aussi étudiant voit Enseigner et Apprentissage.

| Rôle | Propriété en langage clair |
|---|---|
| **Super Admin** | Accès plateforme complet : sécurité, audit, observabilité, matrice des rôles, santé système. Contourne les contrôles de permission. |
| **Administrative Admin** | Personnes, formulaires d’admission, revue des candidatures, dérogations d’inscription, blocages/listes d’attente, thème/branding, Help CMS. |
| **Academic Admin** | Programmes, cours, offres, semestres, barèmes, modèles d’évaluation, traductions, émission de documents, **réouverture du carnet**. |
| **Financial Admin** | Prix des offres, file de factures, paiements manuels, remboursements, recharge portefeuille/points, rapports finance, dons. |
| **Instructor** | Offres dont il est staff : contenu, évaluations, notation, annonces, discussions, planning live, présence, **verrouillage du carnet**. |
| **TA** | Même bureau d’enseignement pour contenu, notation, discussions, annonces — **ne peut pas** verrouiller les notes finales. |
| **Student** | Candidater, s’inscrire, apprendre, rendre, examens, payer, notes publiées, relevé, portefeuille, sessions live. |

L’autorisation passe toujours par des **clés de permission**, jamais par des comparaisons de noms de rôle dans l’UI. Ce que l’on voit suit ce que l’on a le droit de faire.

---

## Tableau de propriété (qui possède quoi)

| Domaine | Super Admin | Admin Admin | Academic Admin | Financial Admin | Instructor | TA | Student |
|---|---|---|---|---|---|---|---|
| Utilisateurs & attribution de rôles | Oui | Créer / suspendre / attribuer | — | — | — | — | Profil propre |
| Thème / branding | Oui | Complet | — | — | — | — | Préférence seule |
| Programmes / cours / offres | Oui | Lecture | Gestion complète | Tarification | Vue staffée | Vue staffée | Catalogue / inscription |
| Formulaires & décisions d’admission | Oui | Complet | — | — | — | — | Candidater / suivre |
| Dérogation inscription / blocages | Oui | Complet | Vues conseil | Blocage finance | Liste d’attente (portée) | Liste d’attente (portée) | S’inscrire / abandonner |
| Contenu & évaluations | Oui | — | École entière si accordé | — | Ses offres | Ses offres | Passer / rendre |
| Verrouillage carnet | Oui | — | Réouverture seule | — | **Verrouiller** | Non | Voir le publié |
| Factures / portefeuille / remboursements | Oui | — | — | Complet | — | — | Payer / donner |
| Zoom live / présence | Oui | — | Politiques / rapports | — | Planifier / saisir | Planifier / saisir | Rejoindre / présence propre |
| Émission de documents | Oui | Émettre | Émettre | — | — | — | Voir / vérifier public |
| Audit / ops / matrice des rôles | Complet | Lecture audit | Lecture audit | Lecture audit | — | — | — |

---

## Union multi-rôles

- Les permissions de tous les rôles assignés sont combinées.
- Un Academic Admin qui enseigne aussi n’est **pas** limité aux offres qu’il enseigne pour les clés académiques école — une grant admin non scopée l’emporte.
- Les grants Instructor/TA sur les clés d’enseignement sont **scopées à l’offre** : uniquement les offres où ils sont staffés.
- Le Super Admin l’emporte toujours et ignore la matrice.

---

## Ce que chaque rôle voit typiquement

### Étudiant

- Tableau de bord bento : mes cours, prochain live, bientôt dû, portefeuille, notifications
- Hub Apprentissage : catalogue, inscriptions, notes, candidatures, live, relevé, paramètres
- Hub Finance : portefeuille, factures, paiement, don
- Articles d’aide étudiants

### Instructor / TA

- **Enseigner** dans la barre latérale (souvent 3ᵉ élément de la barre mobile)
- Hub Enseigner listant les offres staffées → contenu, évaluations, carnet, live, discussions, roster
- Instructor seul : verrouiller le carnet après soumission
- TA : noter et enseigner sans verrouiller

### Academic Admin

- Hub **Académique** : programmes, cours, offres, modèles, barèmes, traductions, documents, rapports
- Réouverture des notes quand un instructeur a verrouillé un carnet à corriger

### Administrative Admin

- Hub **Admin** : utilisateurs, formulaires, file de candidatures, admin inscriptions, éditeur de thème, Help CMS
- Matriculer les acceptés ; poser ou lever les blocages financiers avec la finance

### Financial Admin

- Tuiles admin du hub **Finance** : file de factures, paiement manuel, remboursements, grants portefeuille, rapports bi-devise
- Les étudiants utilisent le même hub Finance pour leur portefeuille et paiements

### Super Admin

- Plan de contrôle **Super Admin** : raccourcis personnes, hub rôles, sécurité, thème, bureaux école, audit, observabilité, santé, tâches planifiées, tests système
- Le hub Rôles peut surcharger la matrice de permissions par défaut (lignes DB `RolePermission`)

---

## Notes pratiques pour la direction

1. Donner le **plus petit** ensemble de rôles qui couvre le poste.
2. Préférer Administrative Admin pour le greffe et Academic Admin pour le curriculum — ne pas les confondre.
3. Garder peu de comptes Super Admin ; modifier la matrice avec prudence.
4. Les comptes démo staging doivent refléter les combinaisons de rôles réelles.

Voir aussi : [Parcours personnel](staff-journeys.md), [Navigation du portail](portal-navigation.md).
