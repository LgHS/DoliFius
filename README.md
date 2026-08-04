# DoliFius

![DoliFius](img/dolifiuslogo.png)

**DoliFius** Dolibarr Module for Belfius CSV import.

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

## Ce que le module fait déjà

- [x] Activation du module sur Dolibarr 22.0.2 (permissions "Consulter" / "Importer" dédiées)
- [x] Formulaire d'upload d'un fichier CSV Belfius
- [x] Conversion ISO-8859-1 → UTF-8 et détection stricte de la ligne d'en-tête (abandon si format non reconnu)
- [x] Validation ligne par ligne (nombre de colonnes, format de date, format de montant) avec raison du rejet
- [x] Calcul du solde recalculé et comparaison au solde annoncé dans le préambule (avertissement non bloquant)
- [x] Rapport complet à l'écran : compteurs, avertissements, lignes rejetées (toujours affichées, même vide), liste détaillée des lignes qui seront importées
- [x] Page de configuration (`admin/setup.php`) : choix du compte bancaire Dolibarr cible pour l'import
- [x] Création effective des écritures bancaires (`Account::addline()`) après confirmation humaine explicite — aucune écriture pendant l'analyse
- [x] Déduplication : clé naturelle n° d'extrait + n° de transaction stockée dans le champ technique "N° chèque" de l'écriture, vérifiée avant toute création (pas de doublon si un export chevauchant est réimporté)
- [x] Import atomique : tout ou rien, annulation complète si une ligne échoue en cours de route
- [x] Protection contre un double-clic / une confirmation concurrente (verrou de session)
- [x] Protection basique (fichiers `index.php` anti-listing, droits Dolibarr requis pour uploader/confirmer)
- [x] Rapport d'analyse testé avec succès sur un export de production réel (269 lignes, 0 rejet)

## Prochaines étapes

- [ ] Test complet de bout en bout de la création d'écritures : import réel + réimport du même export pour valider la déduplication en conditions réelles
- [ ] Décider si les imports doivent être journalisés dans une table dédiée (audit) ou rester sans persistance au-delà du rapport affiché
- [ ] Rapprochement automatique avec les factures ouvertes (V2), toujours avec confirmation humaine obligatoire avant toute création de règlement — jamais rien en automatique
- [ ] Tester l'activation/le fonctionnement une fois la migration Dolibarr 22 → 23.x effectuée

## Installation

Copier ce dossier dans `custom/` de l'instance Dolibarr cible, puis activer le module depuis l'administration des modules.

## Compatibilité

- Dolibarr 22.x+
- PHP 7.2+
