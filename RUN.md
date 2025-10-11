# Comment utiliser MEMEX

## 🎯 C'est quoi ?

**MEMEX** (**MEM**ory + ind**EX**) est un serveur MCP (Model Context Protocol) qui gère une base de connaissances pour l'IA :
- **Guides** : Documentation technique et guides d'implémentation
- **Contexts** : Personas, prompts et conventions réutilisables

Inspiré du [Memex de Vannevar Bush (1945)](https://en.wikipedia.org/wiki/Memex), MEMEX augmente la mémoire de l'IA avec un accès persistant à vos connaissances.

## 📋 Prérequis

- PHP 8.3+
- Composer installé
- Un client MCP compatible :
  - **Claude Desktop** (recommandé)
  - **Cline** (VS Code extension)
  - Tout client compatible MCP

## ⚙️ Configuration initiale

### 1. Installer les dépendances

```bash
cd /Users/jacques/Sites/memex-mcp
composer install
```

### 2. (Optionnel) Configurer un dossier de knowledge base personnalisé

Par défaut, le serveur utilise le dossier `memex/` du projet. Vous pouvez le changer :

#### Via argument CLI

```bash
# Chemin absolu
php bin/server.php --memex=/shared/company-knowledge

# Chemin relatif (résolu depuis le répertoire courant)
php bin/server.php --memex=./custom-kb
```

**Cas d'usage** :
- Partager une knowledge base entre plusieurs projets
- Bibliothèque de guides et contextes à l'échelle de l'entreprise
- Tester avec différents ensembles de contenus

**Note** : Le dossier doit exister et contenir les sous-dossiers `guides/` et `contexts/`.

### 3. Tester le serveur manuellement

```bash
php bin/server.php
```

Le serveur attend des commandes JSON-RPC sur STDIN. Pour un test rapide :

```bash
(echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","method":"notifications/initialized"}' && \
 sleep 0.5 && \
 echo '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' && \
 sleep 1) | php bin/server.php
```

Si vous voyez la liste des 8 tools disponibles, le serveur fonctionne ! ✅

---

## 🖥️ Utilisation avec Claude Desktop

### 1. Localiser le fichier de configuration

**macOS** :
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Windows** :
```
%APPDATA%\Claude\claude_desktop_config.json
```

**Linux** :
```
~/.config/Claude/claude_desktop_config.json
```

### 2. Ajouter la configuration MCP

Éditer `claude_desktop_config.json` :

**Configuration de base** :

```json
{
  "mcpServers": {
    "memex": {
      "command": "php",
      "args": ["/Users/jacques/Sites/memex-mcp/bin/server.php"]
    }
  }
}
```

**Avec knowledge base personnalisée** :

```json
{
  "mcpServers": {
    "memex": {
      "command": "php",
      "args": [
        "/Users/jacques/Sites/memex-mcp/bin/server.php",
        "--memex=/shared/company-kb"
      ]
    }
  }
}
```

⚠️ **Important** : 
- Remplacer `/Users/jacques/Sites/memex-mcp` par le chemin **absolu** vers votre projet

### 3. Redémarrer Claude Desktop

Quitter complètement Claude Desktop et le relancer.

### 4. Vérifier la connexion

Dans Claude Desktop, chercher l'icône 🔌 (plug) en bas à gauche ou dans les paramètres. Vous devriez voir `memex` connecté avec 8 tools disponibles.

### 5. Utiliser le serveur

Dans Claude Desktop, vous pouvez maintenant utiliser les tools :

**Exemples de prompts** :
```
Liste les guides disponibles
```

```
Charge le guide pour ajouter un menu dans l'admin Sylius
```

```
Écris un guide pour créer un custom repository dans Sylius
```

```
Écris un contexte "Expert Sylius" avec les bonnes pratiques
```

Claude va automatiquement détecter et utiliser les tools appropriés (`list_guides`, `get_guide`, `write_guide`, `write_context`, etc.)

---

## 🔧 Utilisation avec Cline (VS Code)

### 1. Installer Cline

Extension VS Code : [Cline](https://marketplace.visualstudio.com/items?itemName=saoudrizwan.claude-dev)

### 2. Configurer MCP

Dans VS Code, ouvrir les paramètres Cline et ajouter le serveur MCP :

```json
{
  "mcpServers": {
    "memex": {
      "command": "php",
      "args": ["/Users/jacques/Sites/memex-mcp/bin/server.php"]
    }
  }
}
```

### 3. Utiliser dans Cline

Ouvrir Cline et demander :
```
Liste les guides disponibles
```

Ou :
```
Charge le guide pour ajouter un champ custom dans le formulaire produit Sylius
```

---

## 📝 Exemples d'utilisation

### Exemple 1 : Récupérer un guide existant

**Prompt** :
```
Charge le guide pour ajouter un menu dans l'admin Sylius
```

Claude utilisera le tool `get_guide` pour récupérer le guide depuis la knowledge base.

### Exemple 3 : Créer un guide personnalisé

**Prompt** :
```
Écris un guide pour implémenter un custom repository dans Sylius avec Doctrine
```

Claude utilisera `write_guide` pour créer le fichier dans `memex/guides/`.

### Exemple 4 : Charger un contexte + guide

**Prompt** :
```
Charge le contexte "Sylius Expert" puis donne-moi le guide pour créer un plugin
```

Claude chargera d'abord le contexte avec `get_context`, puis récupérera le guide avec `get_guide`.

### Exemple 5 : Nettoyage de la knowledge base

**Prompt** :
```
Liste les guides disponibles
```

Puis après analyse :
```
Supprime le guide "old-deprecated-guide"
```

Claude utilisera `delete_guide` pour nettoyer la base.

---

## 🧪 Test des services manuellement

Pour tester la compilation :

```bash
cd /Users/jacques/Sites/memex-mcp

# Compiler les guides
php bin/compile-guides.php

# Compiler les contexts
php bin/compile-contexts.php

# Voir les guides disponibles
php -r "
require 'vendor/autoload.php';
use App\Service\GuideService;
use App\Service\PatternCompilerService;

\$compiler = new PatternCompilerService();
\$guideService = new GuideService(__DIR__ . '/memex', \$compiler);
\$guides = \$guideService->list();

foreach (\$guides as \$guide) {
    echo \$guide['slug'] . ' - ' . \$guide['title'] . PHP_EOL;
}
"
```

---

## 📚 La Knowledge Base : Guides et Contextes

### Qu'est-ce qu'un Guide ?

Un **guide** est un document technique qui explique **COMMENT** faire quelque chose :
- Étapes d'implémentation
- Exemples de code
- Architecture
- Best practices

**Emplacement** : `memex/guides/*.md`

**Exemple** : `memex/guides/sylius-admin-menu.md`

```markdown
---
title: "Sylius Admin Menu Item"
type: guide
tags: [sylius, admin, menu]
created: 2025-01-10
---

# Ajouter un menu dans l'admin Sylius

## Description
Guide pour ajouter un nouvel élément au menu admin Sylius.

## Implémentation

### Étape 1: Créer le listener
...
```

### Qu'est-ce qu'un Contexte ?

Un **contexte** est un prompt/persona qui définit **COMMENT** l'IA doit penser/répondre :
- Rôle/expertise (ex: "Tu es un expert Sylius")
- Contraintes (ex: "Toujours utiliser l'injection de dépendances")
- Conventions (ex: "Suivre PSR-12")
- Tone of voice

**Emplacement** : `memex/contexts/*.md`

**Exemple** : `memex/contexts/sylius-expert.md`

```markdown
---
name: "Sylius Expert"
type: context
tags: [sylius, expert, e-commerce]
created: 2025-01-10
---

Tu es un expert Sylius avec une connaissance approfondie de :
- Architecture Symfony/Doctrine
- Patterns Sylius (Resources, Grids, State Machine)
- Best practices e-commerce

## Contraintes
- Toujours utiliser l'injection de dépendances
- Suivre les conventions Sylius
- Code PSR-12 compliant
```

### Ajouter du contenu manuellement

**Via Claude (recommandé)** :
```
Écris un guide pour créer un custom repository Sylius
```

**Ou manuellement** :
1. Créer `memex/guides/mon-guide.md` ou `memex/contexts/mon-contexte.md`
2. Ajouter le frontmatter YAML
3. Écrire le contenu en Markdown
4. Recompiler :
   ```bash
   php bin/compile-guides.php
   php bin/compile-contexts.php
   ```

### Recompilation

Après ajout manuel de fichiers :

```bash
# Recompiler les guides
php bin/compile-guides.php

# Recompiler les contextes
php bin/compile-contexts.php

# Ou supprimer les fichiers compilés (recompilation auto au prochain appel)
rm memex/compiled/guides.json
rm memex/compiled/contexts.json
```

---

## 🐛 Dépannage

### Le serveur ne démarre pas

**Erreur** : `Class not found`

**Solution** :
```bash
composer dump-autoload
```

### Claude Desktop ne voit pas le serveur

1. Vérifier le chemin absolu dans `claude_desktop_config.json`
2. Vérifier les permissions d'exécution :
   ```bash
   chmod +x bin/server.php
   ```
3. Tester manuellement :
   ```bash
   php bin/server.php
   ```
4. Vérifier les logs Claude Desktop (Menu > View > Developer > Toggle Developer Tools)

### Aucun guide/context trouvé

**Problème** : `list_guides` ou `list_contexts` retourne une liste vide.

**Solution** : 
1. Vérifier que le dossier `memex/guides/` ou `memex/contexts/` contient des fichiers `.md`
2. Recompiler : `php bin/compile-guides.php` ou `php bin/compile-contexts.php`
3. Vérifier les fichiers compilés : `cat memex/compiled/guides.json`

---

## 📊 Monitoring

### Vérifier les guides/contexts compilés

```bash
# Voir les guides compilés
cat memex/compiled/guides.json | jq

# Voir les contexts compilés
cat memex/compiled/contexts.json | jq
```

### Voir les guides disponibles

```bash
php -r "
require 'vendor/autoload.php';
use App\Service\GuideService;
use App\Service\PatternCompilerService;

\$compiler = new PatternCompilerService();
\$guideService = new GuideService(__DIR__ . '/memex', \$compiler);
\$guides = \$guideService->list();

foreach (\$guides as \$g) {
    echo \$g['slug'] . ' - ' . \$g['title'] . PHP_EOL;
}
"
```

---

## 🚀 Workflows recommandés

### Workflow 1 : Utiliser un guide existant

1. **Charger le guide** : `Charge le guide pour ajouter un menu Sylius`
2. **Analyser le guide** retourné
3. **Demander le code** : `Génère le code basé sur ce guide`
4. **Implémenter** le code
5. **Valider** avec la checklist du guide

### Workflow 2 : Créer et partager la connaissance

1. **Créer un guide** : `Écris un guide pour X`
2. Le guide est stocké dans `memex/guides/`
3. **Partager** avec l'équipe via `--memex=/shared/kb`
4. Toute l'équipe peut maintenant utiliser ce guide

### Workflow 3 : Charger un contexte pour des réponses spécialisées

1. **Charger le contexte** : `Charge le contexte Sylius Expert`
2. Claude adopte le persona/contraintes du contexte
3. **Poser des questions** : `Comment implémenter X ?`
4. Les réponses sont contextualisées avec l'expertise Sylius

### Workflow 4 : Contexte + Guide = Réponse optimale

1. **Charger contexte** : `Charge le contexte MonsieurBiz Code Standards`
2. **Charger guide** : `Puis charge le guide custom repository`
3. **Demander le code** : `Génère le code en suivant nos standards`
4. Code généré conforme aux standards ET au guide

---

## 💡 Astuces

### Guides vs Contextes

- **Guide** = Instructions techniques (COMMENT faire)
- **Contexte** = Persona/contraintes (COMMENT penser)
- Les deux sont complémentaires !

### MCP Tools disponibles (8 tools)

- `get_guide` / `get_context` - Récupérer du contenu
- `list_guides` / `list_contexts` - Lister le contenu
- `write_guide` / `write_context` - Créer/mettre à jour
- `delete_guide` / `delete_context` - Supprimer

### Performance

- Les fichiers sont compilés en JSON pour un accès rapide
- La compilation est automatique au besoin
- Utilisez `list_guides` / `list_contexts` pour voir ce qui est disponible

### Partage

- Utilisez `--memex=/shared/path` pour partager entre projets
- Créez une bibliothèque d'entreprise de guides et contextes
- Versionner votre knowledge base avec Git

### Bonnes pratiques

- Nommez vos guides de façon descriptive
- Utilisez des tags pour catégoriser
- Créez des contextes réutilisables (ex: "Expert Sylius", "Code Reviewer")
- Mettez à jour les guides quand les pratiques évoluent (overwrite: true)

---

## 📞 Support

- Documentation Sylius : https://docs.sylius.com/
- MCP Protocol : https://modelcontextprotocol.io/
- Claude API : https://docs.anthropic.com/

---

**Enjoy! 🎉**
