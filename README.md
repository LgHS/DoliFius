# DoliFius

Module Dolibarr custom pour importer les extraits de compte **Belfius** (export CSV) et faciliter le rapprochement bancaire.

## Pourquoi ce module

Dolibarr ne propose nativement aucun import de relevé bancaire (uniquement la saisie et le rapprochement manuel, écriture par écriture). Belfius ne fournissant pas d'accès API bancaire, les relevés sont téléchargés manuellement en CSV depuis le site de la banque.

Plutôt qu'une solution générique du Dolistore (multi-banques, connexion à un service tiers payant...), ce module est volontairement minimaliste : **zéro dépendance externe**, uniquement le cœur Dolibarr, pour un seul besoin — importer les CSV Belfius.

## Fonctionnement (V1)

1. Upload du fichier CSV exporté depuis Belfius.
2. Analyse stricte du fichier : détection de l'en-tête, validation de chaque ligne, vérification de cohérence globale.
3. Affichage d'un rapport (lignes lues / acceptées / rejetées) à valider par l'utilisateur.
4. Création des écritures bancaires **uniquement après confirmation explicite**.

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

> Toutes les valeurs ci-dessus (comptes, noms, adresses, montants, BIC) sont inventées à des fins d'illustration uniquement.

## Installation

Copier ce dossier dans `custom/` de l'instance Dolibarr cible, puis activer le module depuis l'administration des modules.

## Compatibilité

- Dolibarr 23.x
- PHP 7.2+
