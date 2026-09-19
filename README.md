<div align="center">

# Pterodactyl — Thème Modern Indigo

**Un panel de jeu qui a enfin l'air d'un vrai hébergeur.**
Thème sombre bleu-nuit, accent indigo, cartes en verre dépoli, coins arrondis et animations douces —
pour le dashboard client **et** l'administration. Avec en plus l'**inscription** des visiteurs, la
**connexion Discord** et des **rôles de staff** avec permissions.

</div>

## Aperçu

<table>
  <tr>
    <td width="50%" valign="top">
      <a href="docs/images/panel1.jpg"><img src="docs/images/panel1.jpg" alt="Dashboard"></a><br>
      <sub><b>Dashboard</b> — Liste des serveurs : état, jauges CPU, mémoire et disque</sub>
    </td>
    <td width="50%" valign="top">
      <a href="docs/images/panel2.jpg"><img src="docs/images/panel2.jpg" alt="Console d'un serveur"></a><br>
      <sub><b>Console d'un serveur</b> — Console, statistiques et graphiques en direct</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <a href="docs/images/panel3.jpg"><img src="docs/images/panel3.jpg" alt="Bases de données"></a><br>
      <sub><b>Bases de données</b> — Bouton phpMyAdmin : ouvre la base déjà connecté</sub>
    </td>
    <td width="50%" valign="top">
      <a href="docs/images/panel4.jpg"><img src="docs/images/panel4.jpg" alt="Administration"></a><br>
      <sub><b>Administration</b> — Vue d'ensemble de l'administration</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <a href="docs/images/panel5.jpg"><img src="docs/images/panel5.jpg" alt="Réglages du panel"></a><br>
      <sub><b>Réglages du panel</b> — Inscription et connexion Discord</sub>
    </td>
    <td width="50%" valign="top">
      <a href="docs/images/panel6.jpg"><img src="docs/images/panel6.jpg" alt="Node"></a><br>
      <sub><b>Node</b> — Consommation en direct des serveurs du node</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <a href="docs/images/panel7.jpg"><img src="docs/images/panel7.jpg" alt="Rôles de staff"></a><br>
      <sub><b>Rôles de staff</b> — Liste des rôles</sub>
    </td>
    <td width="50%" valign="top">
      <a href="docs/images/panel8.jpg"><img src="docs/images/panel8.jpg" alt="Nouveau rôle"></a><br>
      <sub><b>Nouveau rôle</b> — Permissions section par section</sub>
    </td>
  </tr>
</table>

## Ce qui est inclus

| Partie | Ce qui change |
| --- | --- |
| **Design** | Administration (`/admin`) et dashboard client refaits : sidebar et en-tête bleu-nuit, cartes arrondies, boutons et formulaires modernes, page de connexion en verre dépoli, cartes serveurs avec statut lumineux |
| **[Inscription et Discord](#inscription-et-connexion-discord)** | Page « Create an Account », bouton « Continue with Discord », liaison automatique des comptes existants, réglages dans *Admin → Settings* (le champ « Default Language » est retiré) |
| **[Rôles de staff](#rôles-de-staff)** | Crée des rôles (modérateur, support…), choisis leurs permissions section par section et donne-les à des personnes, sans en faire des administrateurs complets |
| **[Eggs de la communauté](#eggs-de-la-communauté)** | Sur la page *Nests*, tape le nom d'un egg (Valheim, Palworld, Node.js…), choisis le nest et ajoute-le en un clic depuis eggs.pterodactyl.io |
| **[phpMyAdmin](#phpmyadmin)** | Installé automatiquement avec le panel : un bouton sur la page *Databases* d'un serveur ouvre la base directement, déjà connecté |
| **[Installation complète](#installation)** | Un seul script installe tout sur un serveur vierge (serveur web, PHP, base de données, Redis, le panel, SSL), installe Wings, ou met à jour un panel existant |

## Installation

Sur ton serveur, **en root** :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh)
```

Le script te demande ce que tu veux faire :

1. **Installer le panel** sur ce serveur (nouvelle installation complète)
2. **Installer Wings**, le programme qui fait tourner les serveurs de jeu
3. **Les deux** sur la même machine
4. **Mettre à jour** un panel déjà installé avec ce thème
5. **Restaurer** les fichiers d'avant une mise à jour
6. **Désinstaller le thème** et retrouver le panel Pterodactyl d'origine, sans rien perdre
7. **Installer phpMyAdmin** pour ouvrir les bases de données depuis le panel

S'il détecte déjà un panel dans `/var/www/pterodactyl`, il passe directement à la mise à jour.

### Nouveau serveur : le panel, de A à Z

Sur un serveur **vierge** Ubuntu 22.04 / 24.04 ou Debian 11 / 12, avec un nom de domaine qui pointe déjà vers lui :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --panel
```

Il pose quelques questions (domaine, email, compte administrateur) puis fait tout, comme
[pterodactyl-installer](https://pterodactyl-installer.se) :

1. installe **nginx, PHP 8.3, MariaDB et Redis** ;
2. crée la **base de données** avec un mot de passe aléatoire ;
3. installe le **panel** de ce dépôt (design, inscription, Discord, rôles) et ses dépendances ;
4. configure le panel, crée les tables et ton **compte administrateur** ;
5. compile le dashboard (il ajoute un peu de swap temporaire si le serveur a peu de mémoire) ;
6. obtient un **certificat SSL** gratuit Let's Encrypt ;
7. installe la **file d'attente** (`pteroq`) et la **tâche planifiée** (cron) ;
8. installe **[phpMyAdmin](#phpmyadmin)**, relié au panel (`--no-phpmyadmin` pour l'éviter) ;
9. ouvre les ports 80 et 443 si le pare-feu `ufw` est actif.

À la fin, il affiche l'adresse du panel et tes identifiants, et les enregistre dans
`/root/pterodactyl-credentials.txt` (lisible par root seulement, à supprimer ensuite).

Sans interaction :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) \
  --panel --yes --fqdn=panel.example.com --email=toi@example.com --admin-user=toi
```

Sans mot de passe donné, il en génère un aléatoire. Sans domaine (adresse IP), il sert le panel en `http`, sans SSL.

**Il ne touche jamais à l'existant** : il refuse de continuer si un panel est déjà installé, si le dossier n'est pas vide,
ou si une base `panel` ou un utilisateur MySQL `pterodactyl` existent déjà. Si l'installation s'arrête en cours de route,
relance la même commande : elle reprend proprement.

### Wings

Une fois le panel installé, crée une **Location** puis un **Node** dans *Admin*, puis sur la machine qui héberge les
serveurs de jeu :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --wings
```

Il installe Docker, télécharge Wings et crée le service `wings`. Il te reste à coller la configuration du node
(*Admin → Nodes → ton node → Configuration*) dans `/etc/pterodactyl/config.yml`, puis `systemctl enable --now wings`.
Pour tout faire d'un coup, donne-lui l'adresse du panel, un jeton d'API et le numéro du node :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) \
  --wings --panel-url=https://panel.example.com --wings-token=ptla_xxx --node-id=1
```

### phpMyAdmin

Il est installé avec le panel. Sur un panel **déjà installé avec ce thème** (mets-le d'abord à jour avec `--update` si besoin) et qui tourne sous nginx :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --phpmyadmin
```

Il télécharge la dernière version de phpMyAdmin dans `/var/www/phpmyadmin`, la sert à l'adresse
`https://ton-panel/phpmyadmin/` (une ligne `include` est ajoutée au fichier nginx du panel, testée avec `nginx -t` et
annulée si nginx la refuse) et renseigne `PHPMYADMIN_URL` et `PHPMYADMIN_SECRET` dans le `.env` du panel. Tu peux le
relancer à tout moment : ça met phpMyAdmin à jour en gardant le même secret.

Ensuite, sur la page **Databases** d'un serveur, chaque base a un bouton **phpMyAdmin** : un clic ouvre la base dans un
nouvel onglet, **déjà connecté**, sans mot de passe à copier.

Comment c'est protégé :

- le bouton donne un lien à **usage unique, valable une minute**. Il ne contient ni le mot de passe ni le nom
  d'utilisateur : le script de connexion de phpMyAdmin les demande au panel, avec un secret partagé qui reste sur le
  serveur ;
- il faut la permission **View Password** du serveur (la même que pour voir le mot de passe de la base), et l'ouverture
  est notée dans l'activité du serveur ;
- phpMyAdmin n'a **pas de formulaire de connexion** : on ne peut y entrer que par ce lien. Ouvrir `/phpmyadmin/`
  directement renvoie vers le panel. Chaque personne n'accède qu'à sa propre base, avec l'utilisateur limité à celle-ci.

Ce qu'il faut de ton côté : le serveur qui héberge les bases (*Admin → Database Hosts*) doit accepter les connexions
venant de la machine du panel (adresse de MariaDB/MySQL ouverte, et utilisateur de base autorisé depuis `%`, comme pour
Pterodactyl).

### Mettre à jour un panel existant

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --update
```

Il installe tout le contenu de ce dépôt sur ton panel **1.15.1** : le design, l'inscription, Discord et les rôles. Il :

1. télécharge le dépôt et vérifie qu'il est complet, sans rien modifier tant que tout n'est pas en règle ;
2. **sauvegarde** chaque fichier qu'il va remplacer (dans `/var/backups/pterodactyl-theme/`) ;
3. met le panel en maintenance et copie les fichiers ;
4. lance les migrations de la base de données ;
5. installe les dépendances et compile le dashboard ;
6. vide les caches, redémarre la file d'attente et remet le panel en ligne.

Si la migration échoue, il remet les fichiers d'origine. Si la compilation échoue, il remet l'ancien dashboard
compilé pour que le panel continue de fonctionner, et t'indique quoi faire. Dans les deux cas, il te dit pourquoi.
Fais une **sauvegarde de ta base de données** avant : les migrations ajoutent des colonnes à la table `users` et une
table `admin_roles`. Il refuse une version de panel différente de 1.15.1 (sauf avec `--force`).

Pour revenir en arrière :

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --restore
```

Les fichiers remplacés sont remis et ceux que la mise à jour a ajoutés sont supprimés. La base de données n'est pas
modifiée : les nouvelles tables et colonnes restent en place sans gêner. Si tu restaures le dashboard, recompile-le
ensuite avec `yarn build:production`.

> **Le design n'apparaît pas ?** L'URL de la feuille de style de l'admin ne change jamais d'une version à l'autre,
> ton navigateur peut donc garder l'ancienne en cache. Fais un rechargement forcé (`Ctrl + Shift + R`)
> ou ouvre le panel dans une fenêtre de navigation privée.

### Désinstaller le thème (revenir au panel d'origine)

```bash
bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --uninstall
```

Il remet le panel Pterodactyl officiel, avec son design et son dashboard d'origine. **Rien de ce que tu as créé n'est
perdu** : serveurs, utilisateurs, nodes, allocations, sauvegardes, bases de données, plannings, clés d'API et fichier
`.env` restent exactement comme ils sont. Il :

1. télécharge les fichiers officiels de **ta version** de Pterodactyl (dashboard déjà compilé compris) ;
2. **sauvegarde** les fichiers du thème dans `/var/backups/pterodactyl-theme/uninstall-…` ;
3. met le panel en maintenance, remet les fichiers d'origine et supprime ceux que seul le thème ajoutait ;
4. vide les caches, redémarre la file d'attente et remet le panel en ligne.

À savoir :

- l'inscription, la connexion Discord et les rôles de staff ne fonctionnent plus ; les personnes qui n'avaient
  **qu'un rôle de staff** (et ne sont pas administrateurs) perdent leur accès à l'administration, mais leurs comptes
  et leurs serveurs ne bougent pas ;
- la base de données n'est pas modifiée : les colonnes Discord et la table `admin_roles` restent en place sans gêner
  le panel officiel ;
- le dossier `resources/scripts` est remplacé par celui d'origine (une sauvegarde est faite au cas où tu y aurais
  ajouté tes propres fichiers) ;
- phpMyAdmin reste installé mais le bouton disparaît, car le panel d'origine ne le connaît pas ;
- pour **remettre le thème** tel qu'il était : `bash <(curl -s https://raw.githubusercontent.com/MisterSuki/panel-ptero-terra/main/install.sh) --restore`. Pour installer la dernière version du
  thème : `--update`.

Pour vérifier ce qui sera fait sans rien changer, ajoute `--dry-run`.

### Toutes les options

| Option | Effet |
| --- | --- |
| `--panel`, `--wings`, `--update`, `--restore`, `--uninstall`, `--phpmyadmin` | Ce qu'il faut faire (sinon, menu) |
| `-y`, `--yes` | Ne pose aucune question |
| `--dry-run` | Vérifie tout et affiche le plan, sans rien changer |
| `--fqdn=`, `--email=` | Domaine (ou IP) et email, pour un nouveau panel |
| `--admin-user=`, `--admin-password=`, `--admin-first-name=`, `--admin-last-name=` | Compte administrateur créé à l'installation |
| `--timezone=` | Fuseau horaire du panel (celui du serveur par défaut) |
| `--ssl`, `--no-ssl` | Force ou désactive le certificat SSL |
| `--panel-url=`, `--wings-token=`, `--node-id=` | Configure Wings tout de suite |
| `--path=/chemin` | Dossier du panel, si ce n'est pas `/var/www/pterodactyl` |
| `--install-node` | Installe Node.js 22 et Yarn s'ils manquent (Debian/Ubuntu), pour une mise à jour |
| `--enable-settings-ui` | Passe `APP_ENVIRONMENT_ONLY` à `false` dans `.env` (mise à jour) |
| `--skip-build`, `--skip-migrate` | Ne compile pas le dashboard, ne lance pas les migrations |
| `--css-only` | Met à jour seulement le design de l'administration |
| `--force` | Met à jour même si la version du panel est différente (déconseillé) |
| `--no-phpmyadmin` | Avec `--panel` : n'installe pas phpMyAdmin |
| `--pma-source=` | Dossier ou archive `.tar.gz` de phpMyAdmin à utiliser au lieu de télécharger la dernière version |
| `--stock-version=`, `--stock-source=` | Avec `--uninstall` : version officielle à remettre (celle de ton panel par défaut), ou dossier / archive locale à la place du téléchargement |
| `--branch=`, `--repo=`, `--source=` | Installe depuis une autre branche, un autre dépôt ou un dossier local |

## Eggs de la communauté

Dans *Admin → Nests*, la boîte **« Add an egg from the community »** te permet d'ajouter un egg sans télécharger ni
envoyer de fichier :

1. **tape le nom** de l'egg : la recherche se fait pendant que tu écris, sur les quelque 320 eggs publiés sur
   [eggs.pterodactyl.io](https://eggs.pterodactyl.io/) (jeux, applications et eggs génériques). Les majuscules, les
   espaces et les tirets ne comptent pas (`7days` trouve « 7 Days To Die ») ;
2. **choisis le nest** dans la liste « Put it in this nest ». Il propose déjà le bon quand le nom du jeu correspond à un nest
   (un egg Minecraft va vers le nest Minecraft), tu peux le changer ;
3. clique sur **Add** : l'egg est téléchargé et importé, avec ses variables, son script d'installation et ses images Docker,
   puis un bouton **Open** mène à sa page pour le modifier.

Si le nest a déjà un egg du même nom, le panel te le dit et te demande si tu veux l'ajouter quand même.

C'est le même import que le bouton « Import Egg », donc rien de plus dangereux : le panel ne va chercher que les eggs de la liste
du site, et seulement leur fichier dans les dépôts officiels `pterodactyl/game-eggs`, `application-eggs` et
`generic-eggs`. Il faut la permission **Nests → Manage** (un administrateur ou un rôle de staff qui l'a). La liste
est gardée 6 heures pour aller vite ; le serveur du panel doit pouvoir joindre `eggs.pterodactyl.io` et `raw.githubusercontent.com`.

## Serveurs FiveM

Sur la page *Console* d'un serveur FiveM (ou RedM), le panel ajoute :

- un bloc **Players** avec le nombre de joueurs connectés et la limite (`12 / 64`), qui passe au jaune puis au rouge quand
  le serveur se remplit. Il se met à jour toutes les 15 secondes ;
- un lien **txAdmin** à côté du nom du serveur, qui ouvre txAdmin (`http://adresse:port`) dans un nouvel onglet. Le port est
  celui de la variable `TXADMIN_PORT` de l'egg (40120 par défaut) ; le lien est caché si `TXADMIN_ENABLE` est à 0.

Le panel interroge lui-même le serveur (`/players.json` et `/info.json` sur son port de jeu), donc rien à installer ni à
configurer : il faut seulement que le port du serveur soit joignable depuis la machine du panel. Le lien devient jaune
avec un avertissement si le port de txAdmin n'est pas l'une des allocations du serveur (onglet *Network*) : il ne serait
alors pas joignable de l'extérieur.

## Inscription et connexion Discord

### Ce que ça fait

- **Inscription** : une case *Allow Registration* dans *Admin → Settings*. Activée, la page de connexion affiche
  « Create one » et les visiteurs peuvent créer un compte (prénom, nom, pseudo, email, mot de passe). Désactivée,
  le panel reste sur invitation. reCAPTCHA protège l'inscription comme la connexion.
- **Connexion Discord** : un bouton « Continue with Discord » sur les pages de connexion et d'inscription.
- **Synchronisation avec un compte existant** : si l'adresse email **vérifiée** du compte Discord correspond à un
  compte du panel, les deux sont liés automatiquement. Un utilisateur peut aussi lier ou délier Discord depuis la
  page *Account*.
- **Nouveaux comptes** : si l'inscription est ouverte, un utilisateur Discord inconnu obtient un compte créé à partir
  de son profil Discord.

### Garde-fous

- Un email **non vérifié** côté Discord ne lie jamais un compte et ne permet pas d'en créer un.
- Les **administrateurs et le staff** ne sont pas liés automatiquement par email : ils lient Discord depuis leur
  page *Account* une fois connectés avec leur mot de passe.
- La **double authentification** n'est pas contournée : un compte protégé passe toujours par la page de code 2FA.
- Le *Client Secret* est enregistré chiffré et n'est jamais réaffiché.

### Mise en place

1. Sur le [Discord Developer Portal](https://discord.com/developers/applications), crée une application puis, dans
   *OAuth2*, copie le **Client ID** et le **Client Secret**.
2. Dans le panel : *Admin → Settings*, active **Discord Login**, colle le Client ID et le Client Secret, puis copie
   l'URL affichée dans **Discord Redirect URI** vers *OAuth2 → Redirects* sur le portail Discord (l'URL doit être
   identique, y compris `https://`).
3. Dans le fichier `.env`, `APP_ENVIRONMENT_ONLY` doit valoir `false` (une nouvelle installation le fait
   toute seule, la mise à jour avec `--enable-settings-ui`). Avec `true`, le panel ignore les réglages enregistrés depuis l'interface : un bandeau
   rouge le rappelle sur la page Settings.

Les réglages peuvent aussi être fournis par `.env` : `APP_REGISTRATION`, `DISCORD_ENABLED`, `DISCORD_CLIENT_ID`,
`DISCORD_CLIENT_SECRET`.

## Rôles de staff

Jusqu'ici, on était administrateur (accès à tout) ou simple utilisateur. Les rôles ajoutent un niveau intermédiaire.

### Utilisation

1. *Admin → Staff Roles → Create New* : donne un nom (par exemple « Modérateur ») et coche les permissions.
2. Donne le rôle à des personnes : depuis la page du rôle (champ *Add someone*, par pseudo ou email) ou depuis la page
   d'un utilisateur (liste *Staff Role*).
3. Ces personnes voient alors, dans l'administration, uniquement les sections autorisées. Un lien **Admin** apparaît
   aussi dans la barre de navigation du dashboard.

Modifier un rôle s'applique tout de suite à toutes les personnes qui l'ont. Supprimer un rôle les remet simples
utilisateurs, sans rien supprimer d'autre.

### Permissions

Chaque section propose **View** (ouvrir les pages) et **Manage** (modifier). *Manage* inclut toujours *View*.

| Section | View | Manage |
| --- | --- | --- |
| Servers | Voir les serveurs | Créer, modifier, suspendre, réinstaller, transférer, supprimer, voir les accès aux bases |
| Users | Voir les utilisateurs | Créer, modifier, supprimer des utilisateurs simples |
| Nodes | Voir les nodes et allocations | Créer, modifier, supprimer, voir la configuration Wings |
| Locations, Databases, Mounts, Nests & Eggs | Voir | Créer, modifier et supprimer ce que la section permet |
| Panel Settings | — | Voir et modifier les réglages du panel |

### Ce qu'un rôle ne peut jamais donner

- La **clé d'API Application** et la **gestion des rôles** restent réservées aux administrateurs : les deux permettent
  de s'accorder plus de droits.
- Le staff ne peut **pas modifier ni supprimer un administrateur ou un autre membre du staff**, ni donner le statut
  d'administrateur ou un rôle. Sinon, changer l'email ou le mot de passe de quelqu'un reviendrait à prendre son accès.
- Les pages qui affichent des secrets (configuration Wings d'un node, accès aux bases d'un serveur) demandent
  *Manage*, même pour les consulter.
- Si l'authentification à deux facteurs est exigée pour les administrateurs, elle l'est aussi pour le staff.

## Compatibilité

- Conçu et testé sur **Pterodactyl Panel 1.15.1**. La mise à jour refuse une autre version : elle remplace des fichiers
  du cœur (routes, modèle utilisateur, middlewares).
- Nouvelle installation : Ubuntu 22.04 / 24.04 et Debian 11 / 12. Utilise `bash <(curl ...)` et non `curl ... | bash`,
  sinon le script ne peut pas te poser ses questions.
- Si tu as déjà personnalisé ton panel, compare d'abord les fichiers listés dans
  [`install-manifest.txt`](install-manifest.txt) avec les tiens : ce sont ceux que l'installation remplace.

## Développement

Pour travailler sur le thème en local (Node.js 22+ et Yarn) :

```bash
yarn install
yarn watch   # recompile à chaque modification
```

Les couleurs et les ombres sont définies dans [`tailwind.config.js`](tailwind.config.js) (dashboard client) et
dans les variables CSS `--pd-*` de [`public/themes/pterodactyl/css/pterodactyl.css`](public/themes/pterodactyl/css/pterodactyl.css)
(administration).

Quand tu ajoutes ou modifies un fichier, ajoute-le à [`install-manifest.txt`](install-manifest.txt) : l'installateur
ne copie que ce qui est listé.

## Crédits et licence

Ce projet est un thème construit sur [Pterodactyl Panel](https://github.com/pterodactyl/panel), publié sous
[licence MIT](LICENSE.md). Il n'est ni affilié ni approuvé par le projet Pterodactyl.
Pterodactyl® est une marque de ses propriétaires.
