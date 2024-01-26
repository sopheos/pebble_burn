# Pebble/Burn

## Services

Gestion des dépendances entre les services d'une application

## Router


Le système de route permet d'associer un traitement à une requête (HTTP / CLI).

Le routeur `\Pebble\Burn\Router` est un singleton.

## Ajouter une route 

La méthode `add($http_method, $uri, $controller, $method = null) : Router` permet
d'ajouter une nouvelle route. Cette méthode est chainable.

* *`$http_method`* Méthode de la requête HTTP (GET, POST, ...) ou CLI.
* *`$uri`* URI correspondant à la route.
* *`$controller`* Nom du contrôleur OU un élément `callable`.
* *`$method`* Nom de la méthode du contrôleur si celui-ci n'est pas de type `callable`.

Raccourcis : 

* `get($uri, $controller, $method = null) : Router`
* `post($uri, $controller, $method = null) : Router`
* `put($uri, $controller, $method = null) : Router`
* `patch($uri, $controller, $method = null) : Router`
* `delete($uri, $controller, $method = null) : Router`
* `options($uri, $controller, $method = null) : Router`
* `cli($uri, $controller, $method = null) : Router`

## Les URIs

Les URIs peuvent être des expressions régulières.
Les segments capturés par des parenthèses sont passés comme paramètres à la fonction de rappel.
Pour les routes dynamiques, les `expressions régulières` et `wildcards` doivent être entourées pas des parenthèses.

Exemples : 

````php
Router::getInstance()->get('/user/{any}', function($num) {
    echo 'User n°' . $num;
});
````

## Wildcards

Les `wildcards` sont des snippets pour écrire les expressions régulières plus simplement.

* `{all}` Entre 0 et n caractères non obliques (`/`)
* `{any}` Entre 1 et n caractères non obliques (`/`)
* `{num}` Entre 1 et n caractères numériques
* `{hex}` Entre 1 et n caractères hexadécimaux
* `{uuid}` Un UUID tel que définit dans la RFC 4122

Ajouter un wildcard :

````php
Router::getInstance()->wildcard('date', '[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}');
````

## Execution d'une route

````php
$http_method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url('http://dummy' . $_SERVER['REQUEST_URI'], PHP_URL_PATH);
Router::getInstance()->run($http_method, $uri)->execute();
````

# Exception

Si une route n'est pas trouvée, ou que sa fonction de rappel n'est pas 
appelable, une erreur de type `\Pebble\Burn\RouteException` est déclenchée.
