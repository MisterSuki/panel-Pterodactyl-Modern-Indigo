<div align="center">

# Pterodactyl — Thème Modern Indigo

**Un panel de jeu qui a enfin l'air d'un vrai hébergeur.**
Thème sombre bleu-nuit, accent indigo, cartes en verre dépoli, coins arrondis et animations douces —
pour le dashboard client **et** l'administration.

</div>

## Ce qui est inclus

| Partie | Ce qui change | Comment l'installer |
| --- | --- | --- |
| **Administration** (`/admin`) | Sidebar et en-tête bleu-nuit, cartes arrondies, boutons et formulaires modernes, widgets de stats en dégradé, onglets, barres de progression, alertes lisibles | [Script d'installation](#installation-rapide-administration) — une seule commande |
| **Dashboard client** (liste des serveurs, console, connexion…) | Fond dégradé, barre de navigation en verre dépoli, cartes serveurs avec statut lumineux, boutons en dégradé, modales et dialogues refaits | [Compilation depuis les sources](#installation-du-dashboard-client) |

## Installation rapide (administration)

Sur le serveur qui héberge ton panel, **en root** :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install-theme.sh)
```

Le script :

- vérifie que le panel est bien là (par défaut `/var/www/pterodactyl`) ;
- **sauvegarde** ton `pterodactyl.css` actuel avec un horodatage avant de le remplacer ;
- installe le thème et remet les bons droits sur le fichier ;
- vide le cache Laravel.

### Options

```bash
# Panel installé ailleurs que dans /var/www/pterodactyl
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install-theme.sh) --path=/chemin/vers/pterodactyl

# Sans demande de confirmation
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install-theme.sh) --yes

# Revenir à la dernière sauvegarde
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install-theme.sh) --restore
```

> **Le thème n'apparaît pas ?** L'URL de la feuille de style de l'admin ne change jamais d'une version à l'autre,
> ton navigateur peut donc garder l'ancienne en cache. Fais un rechargement forcé (`Ctrl + Shift + R`)
> ou ouvre le panel dans une fenêtre de navigation privée.

## Installation du dashboard client

Le dashboard client est une application React : il faut recompiler les fichiers pour appliquer le nouveau design.

**Prérequis :** Node.js 22 ou plus, Yarn 1.x et git sur le serveur.

```bash
cd /var/www/pterodactyl

# 1. Sauvegarde
cp -r resources/scripts resources/scripts.bak
cp tailwind.config.js tailwind.config.js.bak

# 2. Récupérer les sources du thème
git clone --depth 1 https://github.com/MisterSuki/panel-ptero-terra.git /tmp/panel-theme
cp -r /tmp/panel-theme/resources/scripts/. resources/scripts/
cp /tmp/panel-theme/tailwind.config.js tailwind.config.js

# 3. Compiler
yarn install
yarn build:production
```

Fais ensuite un rechargement forcé dans ton navigateur. Pour revenir en arrière, remets les dossiers `*.bak`
en place et relance `yarn build:production`.

## Compatibilité

- Testé sur **Pterodactyl Panel 1.15.1**.
- Le thème ne touche à aucun fichier PHP ni à la base de données : seuls le CSS de l'admin, les sources React du
  dashboard client et la configuration Tailwind sont modifiés.
- Si tu as déjà personnalisé ton panel, garde une sauvegarde (le script d'installation en fait une pour le CSS de l'admin).

## Développement

Pour travailler sur le thème en local (mêmes prérequis : Node.js 22+ et Yarn) :

```bash
yarn install
yarn watch   # recompile à chaque modification
```

Les couleurs et les ombres sont définies dans [`tailwind.config.js`](tailwind.config.js) (dashboard client) et
dans les variables CSS `--pd-*` de [`public/themes/pterodactyl/css/pterodactyl.css`](public/themes/pterodactyl/css/pterodactyl.css)
(administration).

## Crédits et licence

Ce projet est un thème construit sur [Pterodactyl Panel](https://github.com/pterodactyl/panel), publié sous
[licence MIT](LICENSE.md). Il n'est ni affilié ni approuvé par le projet Pterodactyl.
Pterodactyl® est une marque de ses propriétaires.
