<?php

namespace App\OpenApi\Processors;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\Undefined;

/**
 * Injecte dans le document OpenAPI toutes les routes API de Laravel qui n'ont pas
 * encore d'attribut #[OA\...] dans leur contrôleur.
 *
 * Les endpoints documentés à la main (réponses, schémas, exemples) sont conservés tels quels.
 */
class GenerateRouteOperations
{
    /**
     * @var array<string, class-string<OA\Operation>>
     */
    private const OPERATIONS = [
        'get' => OA\Get::class,
        'post' => OA\Post::class,
        'put' => OA\Put::class,
        'patch' => OA\Patch::class,
        'delete' => OA\Delete::class,
    ];

    /**
     * Libellés français des actions CRUD classiques.
     *
     * @var array<string, string>
     */
    private const VERBS = [
        'index' => 'Liste',
        'store' => 'Création',
        'show' => 'Détails',
        'update' => 'Mise à jour',
        'destroy' => 'Suppression',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'logoutAll' => 'Déconnexion de toutes les sessions',
        'me' => 'Utilisateur courant',
    ];

    /**
     * Paramètres de chemin transmis comme texte (le reste est un identifiant de modèle).
     *
     * @var list<string>
     */
    private const TEXT_PARAMETERS = ['hash', 'key', 'filename', 'path', 'slug', 'token', 'code', 'uuid', 'email', 'name', 'locale', 'language', 'alias'];

    public function __invoke(Analysis $analysis): void
    {
        $openApi = $analysis->openapi;
        $pathItems = [];
        $documented = [];

        if (! Undefined::isDefault($openApi->paths)) {
            foreach ($openApi->paths as $pathItem) {
                $pathItems[$pathItem->path] = $pathItem;

                foreach (array_keys(self::OPERATIONS) as $method) {
                    if (! Undefined::isDefault($pathItem->{$method})) {
                        $documented[$pathItem->path][$method] = true;
                    }
                }
            }
        }

        $added = false;

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_starts_with($route->uri(), 'api/') || str_contains($action, 'L5Swagger\\')) {
                continue;
            }

            $path = '/'.substr($route->uri(), 4);

            foreach (array_keys(self::OPERATIONS) as $method) {
                if (! in_array(strtoupper($method), $route->methods(), true) || isset($documented[$path][$method])) {
                    continue;
                }

                if (! isset($pathItems[$path])) {
                    $pathItems[$path] = new OA\PathItem(['path' => $path]);
                    $analysis->addAnnotation($pathItems[$path], $pathItems[$path]->_context);
                }

                $this->addOperation($analysis, $pathItems[$path], $method, $path, $route);

                $documented[$path][$method] = true;
                $added = true;
            }
        }

        if ($added) {
            $openApi->paths = array_values($pathItems);
        }
    }

    private function addOperation(Analysis $analysis, OA\PathItem $pathItem, string $method, string $path, LaravelRoute $route): void
    {
        $hasBody = in_array($method, ['post', 'put', 'patch'], true);
        $middleware = $this->middleware($route);
        $secured = $this->contains($middleware, 'auth');
        $throttled = $this->contains($middleware, 'throttle');

        $responses = [new OA\Response(['response' => 200, 'description' => 'Succès'])];

        if ($secured) {
            $responses[] = new OA\Response(['response' => 401, 'description' => 'Jeton absent ou invalide']);
        }

        if ($this->contains($middleware, 'role')) {
            $responses[] = new OA\Response(['response' => 403, 'description' => 'Rôle insuffisant']);
        }

        if ($hasBody) {
            $responses[] = new OA\Response(['response' => 422, 'description' => 'Données invalides']);
        }

        if ($throttled) {
            $responses[] = new OA\Response(['response' => 429, 'description' => 'Trop de requêtes']);
        }

        // Les annotations imbriquées doivent partager le contexte du PathItem pour que
        // les processors (MergeJsonContent, etc.) retrouvent leur parent — c'est ce que
        // fait aussi le mode attributs via Generator::$context.
        $previousContext = Generator::$context;
        Generator::$context = $pathItem->_context;

        try {
            $properties = [
                'path' => $path,
                'operationId' => $this->operationId($method, $path),
                'summary' => $this->summary($method, $path, $route),
                'description' => $this->description($route, $middleware),
                'tags' => [$this->tag($route)],
                'responses' => $responses,
            ];

            $parameters = $this->parameters($path);

            if ($parameters !== []) {
                $properties['parameters'] = $parameters;
            }

            if ($hasBody) {
                $properties['requestBody'] = new OA\RequestBody([
                    'description' => 'Corps JSON',
                    'content' => new OA\JsonContent(['type' => 'object']),
                ]);
            }

            if ($secured) {
                $properties['security'] = [['sanctum' => []]];
            }

            /** @var class-string<OA\Operation> $class */
            $class = self::OPERATIONS[$method];
            $operation = new $class($properties);
        } finally {
            Generator::$context = $previousContext;
        }

        $pathItem->merge([$operation]);
        $analysis->addAnnotation($operation, $operation->_context);
    }

    /**
     * @return list<OA\Parameter>
     */
    private function parameters(string $path): array
    {
        if (! preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            return [];
        }

        $parameters = [];

        foreach ($matches[1] as $name) {
            $parameters[] = new OA\Parameter([
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => new OA\Schema([
                    'type' => in_array(strtolower($name), self::TEXT_PARAMETERS, true) ? 'string' : 'integer',
                ]),
            ]);
        }

        return $parameters;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function summary(string $method, string $path, LaravelRoute $route): string
    {
        $action = $route->getActionName();

        if (preg_match('#^(?<class>[\w\\\\]+Controller)@(?<method>\w+)$#', $action, $matches)) {
            $resource = preg_replace('/Controller$/', '', class_basename($matches['class']));

            return $resource.' : '.(self::VERBS[$matches['method']] ?? $this->humanize($matches['method']));
        }

        if (str_ends_with($action, 'Controller')) {
            return preg_replace('/Controller$/', '', class_basename($action));
        }

        return strtoupper($method).' '.$path;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function description(LaravelRoute $route, array $middleware): string
    {
        $lines = [];
        $action = $route->getActionName();

        if (str_contains($action, '@') || str_ends_with($action, 'Controller')) {
            $lines[] = 'Action : '.$action.'.';
        }

        if ($name = $route->getName()) {
            $lines[] = 'Route : '.$name.'.';
        }

        $names = array_values(array_filter($middleware, static fn (string $m): bool => $m !== 'api'));

        if ($names !== []) {
            $lines[] = 'Middleware : '.implode(', ', $names).'.';
        }

        return implode("\n", $lines);
    }

    private function tag(LaravelRoute $route): string
    {
        $action = $route->getActionName();

        if (preg_match('#^Modules\\\\([A-Za-z]+)\\\\#', $action, $matches)) {
            return $matches[1];
        }

        return 'Misc';
    }

    private function operationId(string $method, string $path): string
    {
        return trim(preg_replace('/[^a-z0-9]+/i', '_', strtolower($method.' '.$path)), '_');
    }

    /**
     * @param  list<string>  $middleware
     */
    private function contains(array $middleware, string $prefix): bool
    {
        foreach ($middleware as $value) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function middleware(LaravelRoute $route): array
    {
        return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
    }

    private function humanize(string $value): string
    {
        return ucfirst(implode(' ', preg_split('/(?=[A-Z])/', lcfirst($value)) ?: []));
    }
}
