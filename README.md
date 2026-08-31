# DoliFius

![DoliFius](img/dolifiuslogo.png)

**Module Dolibarr pour importer les extraits de compte Belfius (export CSV) et faciliter le rapprochement bancaire.**

Dolibarr ne propose nativement aucun import de relevé bancaire — uniquement la saisie et le rapprochement manuel, écriture par écriture. Belfius ne fournissant pas d'accès API bancaire, les relevés sont téléchargés manuellement en CSV depuis le site de la banque : DoliFius les importe, les valide strictement, et crée les écritures bancaires correspondantes après validation humaine.

Zéro dépendance externe : pas de librairie tierce, pas de service payant, uniquement le cœur Dolibarr (`Account`, `AccountLine`).

## Fonctionnalités

- Upload d'un export CSV Belfius et analyse stricte avant toute écriture : détection de l'en-tête, validation ligne par ligne (colonnes, dates, montants), calcul de cohérence du solde.
- Rapport détaillé à l'écran (lignes acceptées / rejetées avec raison, solde recalculé vs annoncé) à valider avant toute création en base — **aucune écriture n'est jamais créée automatiquement**, la confirmation humaine est obligatoire à chaque import.
- Création des écritures bancaires dans le compte Dolibarr de votre choix.
- Déduplication automatique : réimporter un export qui chevauche un import précédent ne crée pas de doublons (clé n° d'extrait + n° de transaction).
- Import atomique : en cas d'erreur en cours de route, rien n'est enregistré plutôt qu'un import à moitié fait.

## Ce que le module ne fait pas (V1)

- **Pas de rapprochement automatique avec les factures** — les écritures bancaires sont créées, mais leur lettrage avec les factures Dolibarr reste manuel, comme aujourd'hui avec le rapprochement natif.
- **Pas de connexion bancaire directe** — l'export CSV reste à télécharger manuellement depuis le site Belfius, il n'y a pas d'accès API.
- **Belfius uniquement** — le format CSV attendu est spécifique à Belfius, ce module ne gère pas d'autres banques.

## Prérequis

- Dolibarr 22.x ou supérieur.
- PHP 7.2 ou supérieur.
- Module **Banque & Caisse** de Dolibarr activé, avec au moins un compte bancaire déjà créé.

## Installation

1. Téléchargez le zip du module et déployez-le via **Configuration → Modules/Applications → Déployer un module externe**, ou copiez le dossier manuellement dans `custom/` de votre instance Dolibarr.
   > ⚠️ Le dossier doit impérativement s'appeler `importbancairebelfius` (le nom technique utilisé partout dans le code). Si vous déployez un zip dont le dossier racine porte un autre nom (ex. le nom du dépôt), Dolibarr créera les liens de menu et de configuration vers un chemin qui n'existe pas, et vous aurez des erreurs 404.
2. Activez le module depuis la liste des modules.
3. **Donnez les permissions** : l'activation d'un module ne donne aucun droit automatiquement, même à un administrateur. Allez dans votre profil utilisateur → onglet Permissions → section "Import bancaire Belfius", cochez "Consulter" et "Importer", puis déconnectez-vous/reconnectez-vous.

## Configuration

Depuis la liste des modules, ouvrez la configuration de DoliFius (icône clé à molette) et sélectionnez le compte bancaire Dolibarr cible pour l'import. C'est ce compte qui recevra les écritures créées.

## Utilisation

1. Sur le site Belfius, exportez vos mouvements au format CSV (filtre par date depuis votre espace "Comptes").
2. Dans Dolibarr, menu **Banque → Import Belfius**, uploadez le fichier.
3. Vérifiez le rapport : nombre de lignes valides/rejetées (avec la raison de chaque rejet), avertissement de cohérence de solde le cas échéant, et le détail des lignes qui seront importées.
4. Cliquez **Confirmer l'import** pour créer les écritures, ou **Annuler** pour tout abandonner sans rien écrire.

Réimporter un fichier déjà traité (en partie ou en totalité) ne crée pas de doublons : les lignes déjà présentes sont automatiquement ignorées et comptabilisées comme telles dans le message de résultat.

## Format du fichier CSV attendu

- Encodage ISO-8859-1, séparateur `;`, fins de ligne CRLF.
- Le fichier commence par un bloc préambule (critères du filtre d'export + solde du compte), suivi de la ligne d'en-tête puis des lignes de transaction.

### Exemple de bloc préambule (données fictives)

| Clé | Valeur |
|---|---|
| Date de comptabilisation à partir de | 01/01/2024 |
| Date de comptabilisation jusqu'au | 31/01/2024 |
| Dernier solde | 1.234,56 EUR |
| Date/heure du dernier solde | 31/01/2024 18:00:00 |

### Exemple de lignes de transaction (données 100 % fictives)

| Compte | Date de comptabilisation | N° d'extrait | N° de transaction | Compte contrepartie | Nom contrepartie | Rue et numéro | Code postal et localité | Transaction | Date valeur | Montant | Devise | BIC | Code pays | Communications |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| BE00 0000 0000 0001 | 03/01/2024 | 00001 | 1 | BE00 1111 1111 1111 | DUPONT Jean | Rue de l'Exemple 12 | 1000 Bruxelles | VIREMENT EN VOTRE FAVEUR | 03/01/2024 | 25,00 | EUR | EXEMBEBB | BE | COTISATION 2024 |
| BE00 0000 0000 0001 | 05/01/2024 | 00001 | 2 |  | MAGASIN EXEMPLE |  | 1000 Bruxelles | PAIEMENT CARTE - MAGASIN EXEMPLE | 05/01/2024 | -14,90 | EUR |  | BE | PAIEMENT CARTE - MAGASIN EXEMPLE |
| BE00 0000 0000 0001 | 10/01/2024 | 00001 | 3 | BE00 2222 2222 2222 | ASSOCIATION EXEMPLE ASBL | Avenue Fictive 5 | 4000 Liège | VIREMENT INSTANTANE VERS ASSOCIATION EXEMPLE | 10/01/2024 | -50,00 | EUR | EXEMBEBB | BE | +++000/0000/00000+++ |

Ces trois lignes illustrent la variété réelle du champ **Communications** : une référence de cotisation en texte libre, un texte dupliqué de la colonne "Transaction" (paiement par carte), et une référence structurée de virement.

## Sécurité et robustesse

- **Confirmation humaine obligatoire** avant toute écriture en base — jamais d'import automatique, quel que soit le niveau de confiance de l'analyse.
- Le fichier CSV est analysé et rejeté ligne par ligne si le format ne correspond pas (jamais d'insertion à l'aveugle).
- Un changement de format côté Belfius (en-tête modifié) bloque l'import avec un message explicite plutôt que d'importer des données mal interprétées.
- Les dossiers du module ne sont pas listables directement (protection contre l'exposition accidentelle des fichiers source).

## Compatibilité

- Dolibarr 22.x et supérieur.
- PHP 7.2 et supérieur.

## Licence

GPL v3 ou supérieure — voir [LICENSE](LICENSE).

## Feuille de route

- [ ] Rapprochement automatique avec les factures ouvertes (toujours avec confirmation humaine — voir plus haut)
- [ ] Rapprochement automatique des lignes aux cotisations d'un adhérent
- [ ] Décision sur la journalisation des imports dans une table dédiée (audit)
- [ ] Validation sur Dolibarr 23.x une fois la migration effectuée

## Support

Ouvrez une issue sur le dépôt GitHub du projet.
