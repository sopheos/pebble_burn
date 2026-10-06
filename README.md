# Pebble/Burn

Routeur HTTP/CLI minimal et classe de base `Services` pour une application PHP 8.1+.

La lib ne fournit ni middleware, ni conteneur d'injection, ni lecture des superglobales : c'est au point d'entrée de l'application de passer la méthode et l'URI au routeur.

## Installation

```bash
composer require sopheos/pebble_burn
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-burn/`](skills/pebble-burn/). Il documente les patterns d'usage et les pièges de la librairie : placeholders `{nom}` et non regex, `{all}` qui se déploie en plusieurs arguments, `{date}` cassé, `run()` qui résout sans exécuter, etc.

Dans un projet qui dépend de `sopheos/pebble_burn`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_burn/skills/pebble-burn .claude/skills/pebble-burn
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## Services

`\Pebble\Burn\Services` est une classe abstraite : chaque sous-classe obtient son propre singleton via `getInstance()`.

* `getInstance() : static` Instance unique de la sous-classe.
* `destroy()` Supprime l'instance. `stop()` est appelé par le destructeur.
* `start()` / `stop()` Hooks vides à surcharger. `start()` n'est jamais appelé automatiquement.
* `setConfig(array $config) : static` Remplace toute la configuration.
* `config(string $name) : mixed` Une clé de configuration, ou `null`.
* `setEnv(string $env) : static` / `env() : string` Environnement. `env()` lève `InvalidArgumentException` s'il n'est pas défini.
* `isProd()` / `isTest()` / `isDev()` Comparent avec `ENV_PROD` (`production`), `ENV_TEST` (`testing`) et `ENV_DEV` (`developpement`).
* `path(string $path = '') : string` Concatène `config['path']` et `$path`.

```php
class App extends \Pebble\Burn\Services {}

App::getInstance()
    ->setEnv(App::ENV_PROD)
    ->setConfig(['path' => '/var/www/app']);
```

## Router

Le système de route permet d'associer un traitement à une requête (HTTP / CLI).

Le routeur `\Pebble\Burn\Router` s'instancie avec `new Router()` ou s'utilise comme singleton via `Router::getInstance()`.

### Ajouter une route

La méthode `add($http_method, $uri, $controller, $method = null) : Router` ajoute une nouvelle route. Elle est chaînable.

* *`$http_method`* Méthode de la requête HTTP (GET, POST, ...) ou CLI. Sensible à la casse.
* *`$uri`* URI de la route, avec éventuellement des wildcards `{nom}`.
* *`$controller`* Nom du contrôleur OU un élément `callable`.
* *`$method`* Nom de la méthode du contrôleur si celui-ci n'est pas de type `callable`. Le contrôleur est instancié **sans argument** au moment de `execute()`.

Raccourcis :

* `get($uri, $controller, $method = null) : Router`
* `post($uri, $controller, $method = null) : Router`
* `getpost($uri, $controller, $method = null) : Router`
* `put($uri, $controller, $method = null) : Router`
* `patch($uri, $controller, $method = null) : Router`
* `delete($uri, $controller, $method = null) : Router`
* `options($uri, $controller, $method = null) : Router`
* `cli($uri, $controller, $method = null) : Router`

Enregistrer deux fois la même méthode et la même URI écrase silencieusement la première route.

### Les URIs

Les URIs **ne sont pas** des expressions régulières : elles sont échappées par `preg_quote()`. Seuls les wildcards `{nom}` sont dynamiques. Les segments capturés sont passés, sous forme de chaînes, comme paramètres à la fonction de rappel.

```php
Router::getInstance()->get('/user/{id}', function ($id) {
    echo 'User n°' . $id;
});
```

Les patterns sont ancrés au début et à la fin. Il faut passer au routeur le chemin seul, sans query string.

### Wildcards

Les `wildcards` sont des raccourcis vers des expressions régulières.

* `{all}` Entre 0 et n caractères, y compris `/`. Chaque `/` produit un argument supplémentaire.
* `{any}` Entre 1 et n caractères non obliques (`/`)
* `{id}` Entier positif
* `{num}` Entier, éventuellement négatif
* `{hex}` Entre 1 et n caractères hexadécimaux
* `{size}` `xs`, `sm`, `md` ou `lg`
* `{uuid}` Un UUID tel que défini dans la RFC 4122
* `{date}` Date `AAAA-MM-JJ` valide. **Attention** : ce wildcard passe un nombre variable d'arguments parasites au handler (voir [`TODO.md`](TODO.md)).

Ajouter un wildcard : un seul groupe capturant, et un nom limité à `[A-Za-z0-9_]` (un `-` ou un `.` dans le nom l'empêche de fonctionner).

```php
Router::getInstance()->wildcard('ymd', '([0-9]{4}-[0-9]{2}-[0-9]{2})');
Router::getInstance()->wildcards(['slug' => '([a-z0-9-]+)']);
```

### Exécution d'une route

`run()` résout la route mais ne l'exécute pas : il faut appeler `execute()`.

```php
$http_method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url('http://dummy' . $_SERVER['REQUEST_URI'], PHP_URL_PATH);
Router::getInstance()->run($http_method, $uri)->execute();
```

Une route exacte l'emporte toujours sur une route dynamique. Entre routes dynamiques, la première enregistrée gagne.

`export() : array` renvoie la liste des routes, triée par URI : `['/uri' => ['GET', 'POST']]`.

### Exception

Si une route n'est pas trouvée, ou si sa fonction de rappel n'est pas appelable, une erreur de type `\Pebble\Burn\RouteException` est déclenchée.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
