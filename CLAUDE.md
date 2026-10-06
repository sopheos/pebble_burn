# CLAUDE.md — pebble_burn

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet, voir le skill [`skills/pebble-burn/`](skills/pebble-burn/SKILL.md).

## Rôle

`sopheos/pebble_burn`, namespace `Pebble\Burn\`, PHP >= 8.1, aucune dépendance runtime. La lib fournit :
- un routeur HTTP/CLI minimal, avec des patterns `{wildcard}` ;
- une classe abstraite `Services`, singleton par sous-classe qui porte la config, l'environnement et les hooks `start()`/`stop()`.

Pas de middleware, pas de conteneur DI, pas de lecture des superglobales.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter RouterTest
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `Router.php` | Registre des routes, table des wildcards, `run()` (match exact puis regex), `export()`, singleton `getInstance()` |
| `RouteInterface.php` | Contrat d'une route résolue |
| `RouteAbstract.php` | Stocke la méthode, l'URI, le callback et les arguments. `execute()` lève `RouteException` |
| `CallableRoute.php` | Route dont le handler est un callable |
| `ControllerRoute.php` | Route `[Classe, méthode]`, instanciée sans argument à `execute()` |
| `RouteException.php` | « Route not found » et « is not callable » |
| `Services.php` | Singleton par sous-classe : config, env (`ENV_PROD`/`ENV_TEST`/`ENV_DEV`), `path()` |

## Tests

- PHPUnit 9.5. `tests/bootstrap.php` charge aussi `tests/ressources/*.php` (contrôleurs factices).
- Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.
- Les tests `Router` utilisent `new Router()`, jamais `Router::getInstance()` : le singleton ne se réinitialise pas.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- constantes de classe sans visibilité ;
- `if` d'une ligne sans accolades tolérés ;
- docblocks `@return static`.

Une modification de comportement doit être répercutée dans `skills/pebble-burn/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » de `tests/RouterTest.php`.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-burn/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
