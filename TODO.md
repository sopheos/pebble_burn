# TODO — pebble_burn

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **`{date}` passe tous ses groupes imbriqués comme arguments.** `src/Router.php:34`.
  - Le regex contient une trentaine de groupes capturants. `/d/{date}` sur `2024-02-29` appelle le handler avec 17 arguments, et sur `2023-01-15` avec 28. Le nombre dépend de la branche qui matche.
  - Correctif : passer les groupes internes en non-capturants `(?:…)` et ne garder qu'un groupe externe.
  - Test : `tests/RouterTest.php::testDateWildcardPassesEveryNestedGroupAsAnArgument`.
- [ ] **Un nom de wildcard contenant `-` ou `.` n'est jamais substitué.** `src/Router.php:203`.
  - La clé de recherche est `'\{' . $k . '\}'`, sans `preg_quote()`, alors que l'URI est passée par `preg_quote()`. `{my-id}` devient `\{my\-id\}` dans l'URI, la clé ne la retrouve pas, et la route ne matche jamais, sans erreur.
  - Correctif : `$search[] = preg_quote('{' . $k . '}', '#');` (c'est le correctif retenu dans bredala-router).
  - Test : `tests/RouterTest.php::testWildcardNameWithRegexCharactersIsNeverSubstituted`.
- [ ] **`run()` renvoie l'objet route stocké et le modifie sur place.** `src/Router.php:81-83, 213`.
  - `setArguments()` modifie l'instance partagée. Deux `run()` successifs sur le même pattern renvoient le même objet, et le second écrase les arguments du premier. C'est risqué en process long (workers) ou si une route est conservée.
  - Correctif : stocker `[$controller, $method]` et construire la route dans `run()`, comme bredala-router, ou bien `clone`.
  - Test : `tests/RouterTest.php::testRunReturnsTheSharedRouteInstance`.

## Dette / qualité

- [ ] `src/RouteAbstract.php:48` : `return null;` est inatteignable après le `throw`.
- [ ] `src/Services.php:16` : `ENV_DEV = 'developpement'` (orthographe française). Changer la valeur casserait les configs existantes, donc il faut au minimum la documenter (fait dans le skill).
- [ ] `src/Services.php:34` : `destroy()` n'a pas de type de retour (`: void`).
- [ ] `src/Router.php` : `if` d'une ligne sans accolades (`run()`), et `@return static` alors que la signature le déclare déjà.
- [ ] Pas de 405. Une méthode inconnue et une URI inconnue lèvent la même `RouteException`.
