# Comment utiliser le serveur MCP UI Element

## 🎯 C'est quoi ?

Un serveur MCP (Model Context Protocol) qui génère des **guides d'implémentation** pour des éléments UI/features en utilisant Claude AI.

**Important**: Ce serveur génère des GUIDES textuels, pas du code. Ces guides sont conçus pour être ensuite utilisés par un LLM pour générer le code.

## 📋 Prérequis

- PHP 8.3+
- Composer installé
- Une clé API Claude (Anthropic)
- Un client MCP compatible :
  - **Claude Desktop** (recommandé)
  - **Cline** (VS Code extension)
  - Tout client compatible MCP

## ⚙️ Configuration initiale

### 1. Installer les dépendances

```bash
cd /Users/jacques/Sites/mcp-ui-element
composer install
```

### 2. Configurer la clé API Claude

Éditer le fichier `.env` :

```bash
CLAUDE_API_KEY=sk-ant-xxxxxxxxxxxxx
```

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

Si vous voyez la liste des tools disponibles, le serveur fonctionne ! ✅

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

Éditer `claude_desktop_config.json` et ajouter :

```json
{
  "mcpServers": {
    "mcp-ui-element": {
      "command": "php",
      "args": ["/Users/jacques/Sites/mcp-ui-element/bin/server.php"]
    }
  }
}
```

⚠️ **Important** : Remplacer `/Users/jacques/Sites/mcp-ui-element` par le chemin **absolu** vers votre projet.

### 3. Redémarrer Claude Desktop

Quitter complètement Claude Desktop et le relancer.

### 4. Vérifier la connexion

Dans Claude Desktop, chercher l'icône 🔌 (plug) en bas à gauche ou dans les paramètres. Vous devriez voir `mcp-ui-element` connecté.

### 5. Utiliser le serveur

Dans Claude Desktop, vous pouvez maintenant utiliser le tool :

**Exemple de prompt** :
```
Génère-moi un guide d'implémentation pour ajouter un menu "Configuration" 
dans le menu admin de Sylius qui pointe vers une page de settings.
```

Claude va automatiquement :
1. Détecter que vous parlez d'un élément Sylius
2. Appeler le tool `generate-implementation-guide`
3. Vous retourner un guide structuré avec :
   - Analyse du besoin
   - Architecture recommandée
   - Étapes d'implémentation détaillées
   - Patterns applicables
   - Contraintes techniques
   - Checklist de validation

---

## 🔧 Utilisation avec Cline (VS Code)

### 1. Installer Cline

Extension VS Code : [Cline](https://marketplace.visualstudio.com/items?itemName=saoudrizwan.claude-dev)

### 2. Configurer MCP

Dans VS Code, ouvrir les paramètres Cline et ajouter le serveur MCP :

```json
{
  "mcpServers": {
    "mcp-ui-element": {
      "command": "php",
      "args": ["/Users/jacques/Sites/mcp-ui-element/bin/server.php"]
    }
  }
}
```

### 3. Utiliser dans Cline

Ouvrir Cline et demander :
```
Génère un guide pour ajouter un champ custom dans le formulaire produit Sylius
```

---

## 📝 Exemples d'utilisation

### Exemple 1 : Menu admin Sylius

**Prompt** :
```
J'ai besoin d'ajouter un menu "Statistiques" dans le menu admin de Sylius 
qui pointe vers la route app_stats_dashboard. Génère-moi le guide.
```

**Résultat** : Guide avec 5 étapes, code examples, checklist de validation.

### Exemple 2 : Feature Sylius générique

**Prompt** :
```
Comment ajouter un champ "note interne" sur les commandes Sylius ?
Type: custom-field
Framework: Sylius
```

**Résultat** : Guide d'implémentation complet.

---

## 🧪 Test du tool manuellement

Pour tester sans client MCP :

```bash
cd /Users/jacques/Sites/mcp-ui-element

php -r "
require 'vendor/autoload.php';
use App\Service\GuideGeneratorService;
use App\Service\ClaudeApiService;
use App\Service\KnowledgeBaseService;
use App\Service\PatternCompilerService;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

\$apiKey = \$_ENV['CLAUDE_API_KEY'];
\$compiler = new PatternCompilerService();
\$claudeApi = new ClaudeApiService(\$apiKey);
\$knowledgeBase = new KnowledgeBaseService(__DIR__ . '/knowledge-base', \$compiler);
\$generator = new GuideGeneratorService(\$claudeApi, \$knowledgeBase);

\$result = \$generator->generateGuide(
    elementType: 'admin-menu',
    requirements: 'Ajouter un menu Test dans admin Sylius',
    framework: 'Sylius'
);

echo json_encode(\$result['guide'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"
```

---

## 📚 Ajouter des patterns personnalisés

### Structure d'un pattern

Les patterns sont des fichiers Markdown dans `knowledge-base/patterns/`.

Exemple : `knowledge-base/patterns/sylius-custom-field.md`

```markdown
---
name: Sylius Custom Field
element_types: [custom-field, entity-extension]
frameworks: [sylius, symfony]
difficulty: intermediate
category: customization
---

# Sylius - Ajouter un champ personnalisé

## Description

Guide pour ajouter un champ personnalisé à une entité Sylius...

## Architecture

...

## Implémentation

### Étape 1: Étendre l'entité

...
```

### Recompiler les patterns

Après ajout d'un pattern :

```bash
rm knowledge-base/compiled/patterns.json
```

Le fichier sera recompilé automatiquement au prochain appel.

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

### Erreur API Claude

**Erreur** : `CLAUDE_API_KEY not configured`

**Solution** : Vérifier que `.env` contient votre clé API.

**Erreur** : `401 Unauthorized`

**Solution** : Clé API invalide ou expirée. Vérifier sur https://console.anthropic.com/

### Le guide généré est vide

**Problème** : Pas de pattern correspondant trouvé.

**Solution** : Ajouter un pattern dans `knowledge-base/patterns/` qui correspond à votre `element_type`.

---

## 📊 Monitoring

### Vérifier les patterns compilés

```bash
cat knowledge-base/compiled/patterns.json | jq
```

### Voir les patterns disponibles

```bash
php -r "
require 'vendor/autoload.php';
use App\Service\KnowledgeBaseService;
use App\Service\PatternCompilerService;

\$compiler = new PatternCompilerService();
\$kb = new KnowledgeBaseService(__DIR__ . '/knowledge-base', \$compiler);
\$patterns = \$kb->getAllPatterns();

foreach (\$patterns as \$p) {
    echo \$p['name'] . ' - ' . implode(', ', \$p['metadata']['element_types'] ?? []) . PHP_EOL;
}
"
```

---

## 🚀 Workflow recommandé

1. **Demander un guide** via Claude Desktop/Cline
2. **Analyser le guide** généré
3. **Demander au LLM de générer le code** basé sur le guide
4. **Implémenter** le code
5. **Valider** avec la checklist du guide

---

## 💡 Astuces

- Le serveur utilise **Claude 3.7 Sonnet** pour la génération
- Les guides sont **en français** par défaut
- Les patterns peuvent définir des **contraintes** et **checklists**
- Un **fallback** existe si l'API Claude échoue
- Les guides sont **contextuels** aux patterns disponibles

---

## 📞 Support

- Documentation Sylius : https://docs.sylius.com/
- MCP Protocol : https://modelcontextprotocol.io/
- Claude API : https://docs.anthropic.com/

---

**Enjoy! 🎉**
