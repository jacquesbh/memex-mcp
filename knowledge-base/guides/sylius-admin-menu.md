---
name: Sylius Admin Menu Item
element_types: [admin-menu, menu-item, sylius-menu]
frameworks: [sylius, symfony]
difficulty: beginner
category: administration
version: 2.0
---

# Sylius Admin Menu - Ajouter un élément

## Description

Guide pour ajouter un nouvel élément au menu de l'administration Sylius. Le menu admin est le menu latéral gauche extensible accessible sur l'URL `/admin/`.

## Prérequis

- Sylius installé et fonctionnel
- Connaissance de base de Symfony (services, événements)
- Accès aux fichiers de configuration `config/services.yaml`

## Architecture

### Composants nécessaires

1. **Event Listener** - Classe PHP qui écoute l'événement `sylius.menu.admin.main`
2. **Service Configuration** - Enregistrement du listener dans `config/services.yaml`
3. **Route** (optionnelle) - Si le menu doit pointer vers une page spécifique

### Structure des fichiers

```
src/
  └── Menu/
      └── AdminMenuListener.php
config/
  └── services.yaml
```

## Implémentation

### Étape 1: Créer le Listener

Créer le fichier `src/Menu/AdminMenuListener.php`:

```php
<?php

namespace App\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        // Ajouter un item de menu principal
        $menu
            ->addChild('mon_item')
            ->setLabel('Mon Menu Item')
        ;

        // OU: Ajouter un sous-menu avec des items
        $newSubmenu = $menu
            ->addChild('mon_sous_menu')
            ->setLabel('Mon Sous-Menu')
        ;

        $newSubmenu
            ->addChild('mon_item_1')
            ->setLabel('Item 1')
        ;
    }
}
```

### Étape 2: Enregistrer le Listener

Ajouter dans `config/services.yaml`:

```yaml
services:
    app.listener.admin.menu_builder:
        class: App\Menu\AdminMenuListener
        tags:
            - { name: kernel.event_listener, event: sylius.menu.admin.main, method: addAdminMenuItems }
```

### Étape 3: Lier à une route existante

Pour pointer vers une page existante (ex: liste des utilisateurs):

```php
$menu
    ->addChild('users', ['route' => 'sylius_admin_admin_user_index'])
    ->setLabel('Utilisateurs')
;
```

### Étape 4: Vider le cache

```bash
php bin/console cache:clear
```

## Options avancées

### Ajouter une icône

Sylius utilise Semantic UI. Pour ajouter une icône:

```php
$menu
    ->addChild('mon_item')
    ->setLabel('Mon Item')
    ->setLabelAttribute('icon', 'users') // Icône Semantic UI
;
```

### Positionnement dans le menu

Par défaut, les nouveaux items apparaissent en bas du menu. Pour contrôler la position:

```php
// Ajouter au début
$menu
    ->addChild('mon_item', ['extras' => ['position' => 0]])
    ->setLabel('Premier Item')
;

// Ajouter après un item spécifique
$menu
    ->addChild('mon_item')
    ->setLabel('Mon Item')
    ->setAttribute('position', 'after', 'catalog') // Après le menu Catalog
;
```

### Ajouter des sous-items multiples

```php
public function addAdminMenuItems(MenuBuilderEvent $event): void
{
    $menu = $event->getMenu();

    $customMenu = $menu
        ->addChild('custom')
        ->setLabel('Menu Personnalisé')
    ;

    $customMenu
        ->addChild('item_1', ['route' => 'app_custom_index'])
        ->setLabel('Item 1')
    ;

    $customMenu
        ->addChild('item_2', ['route' => 'app_custom_show'])
        ->setLabel('Item 2')
    ;
}
```

## Événements disponibles

Sylius expose plusieurs événements de menu:

- `sylius.menu.admin.main` - Menu principal de l'admin
- `sylius.menu.shop.account` - Menu du compte utilisateur (shop)
- `sylius.menu.admin.customer.show` - Boutons sur la page client
- `sylius.menu.admin.order.show` - Boutons sur la page commande
- `sylius.menu.admin.product.form` - Tabs du formulaire produit
- `sylius.menu.admin.product.update` - Boutons page produit
- `sylius.menu.admin.product_variant.form` - Tabs du formulaire variante
- `sylius.menu.admin.promotion.update` - Boutons page promotion

## Contraintes

### Limitations

- Les items apparaissent par défaut en bas du menu (sauf si position spécifiée)
- Le label doit être défini sinon le nom technique s'affiche
- Les icônes utilisent la syntaxe Semantic UI
- Cache Symfony doit être vidé après modification

### Bonnes pratiques

- Utiliser des noms de services préfixés par `app.listener.admin.`
- Créer des listeners séparés pour différents menus
- Utiliser des routes nommées plutôt que des URLs en dur
- Documenter les routes personnalisées

## Validation

### Checklist de vérification

- [ ] Classe `AdminMenuListener` créée dans `src/Menu/`
- [ ] Namespace correct: `App\Menu`
- [ ] Méthode `addAdminMenuItems(MenuBuilderEvent $event)` implémentée
- [ ] Service enregistré dans `config/services.yaml`
- [ ] Tag `kernel.event_listener` avec événement `sylius.menu.admin.main`
- [ ] Label défini avec `setLabel()`
- [ ] Cache vidé après modification
- [ ] Menu visible dans l'admin `/admin/`
- [ ] Clic sur le menu fonctionne (si route définie)

## Dépannage

### Le menu n'apparaît pas

1. Vérifier que le cache est vidé: `php bin/console cache:clear`
2. Vérifier le namespace de la classe
3. Vérifier l'enregistrement du service dans `services.yaml`
4. Vérifier les logs: `var/log/dev.log` ou `var/log/prod.log`

### Erreur "Service not found"

- Vérifier que le service est bien dans la section `services:` de `services.yaml`
- Vérifier l'indentation YAML
- Vérifier le chemin de la classe

### Le label ne s'affiche pas

- Utiliser `setLabel()` et non `setName()`
- Le label peut nécessiter une traduction si le système de traduction est activé

## Exemple complet

Cas d'usage: Ajouter un menu "Toto" qui pointe vers la liste des utilisateurs admin:

```php
<?php

namespace App\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild('toto', ['route' => 'sylius_admin_admin_user_index'])
            ->setLabel('Toto')
            ->setLabelAttribute('icon', 'user')
        ;
    }
}
```

```yaml
# config/services.yaml
services:
    app.listener.admin.menu_builder:
        class: App\Menu\AdminMenuListener
        tags:
            - { name: kernel.event_listener, event: sylius.menu.admin.main, method: addAdminMenuItems }
```

Résultat: Un nouvel item "Toto" avec icône utilisateur apparaît dans le menu admin et redirige vers `/admin/admin-users/`.
