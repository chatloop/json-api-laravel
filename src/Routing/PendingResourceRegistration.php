<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Laravel\Routing;

use Closure;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use function is_string;

class PendingResourceRegistration
{

    /**
     * @var ResourceRegistrar
     */
    private ResourceRegistrar $registrar;

    /**
     * @var string
     */
    private string $resourceType;

    /**
     * @var string
     */
    private string $controller;

    /**
     * @var array
     */
    private array $options;

    /**
     * @var bool
     */
    private bool $registered = false;

    /**
     * @var Closure|null
     */
    private ?Closure $relationships = null;

    /**
     * @var string|null
     */
    private ?string $actionsPrefix = null;

    /**
     * @var Closure|null
     */
    private ?Closure $actions = null;

    /**
     * @var array|string[]
     */
    private array $map = [
        'create' => 'store',
        'read' => 'show',
        'delete' => 'destroy',
    ];

    /**
     * PendingResourceRegistration constructor.
     *
     * @param ResourceRegistrar $registrar
     * @param string $resourceType
     * @param string $controller
     */
    public function __construct(
        ResourceRegistrar $registrar,
        string $resourceType,
        string $controller
    ) {
        $this->registrar = $registrar;
        $this->resourceType = $resourceType;
        $this->controller = $controller;
        $this->options = [];
    }

    /**
     * Set the methods the controller should apply to.
     *
     * @param string ...$actions
     * @return $this
     */
    public function only(string ...$actions): self
    {
        $this->options['only'] = $this->normalizeActions($actions);

        return $this;
    }

    /**
     * Set the methods the controller should exclude.
     *
     * @param string ...$actions
     * @return $this
     */
    public function except(string ...$actions): self
    {
        $this->options['except'] = $this->normalizeActions($actions);

        return $this;
    }

    /**
     * Only register read-only actions.
     *
     * @return $this
     */
    public function readOnly(): self
    {
        return $this->only('index', 'show');
    }

    /**
     * Set the route names for controller actions.
     *
     * @param array $names
     * @return $this
     */
    public function names(array $names): self
    {
        foreach ($names as $method => $name) {
            $this->name($method, $name);
        }

        return $this;
    }

    /**
     * Set the route name for a controller action.
     *
     * @param string $method
     * @param string $name
     * @return $this
     */
    public function name(string $method, string $name): self
    {
        if (!isset($this->options['names'])) {
            $this->options['names'] = [];
        }

        $method = $this->map[$method] ?? $method;
        $this->options['names'][$method] = $name;

        return $this;
    }

    /**
     * Override the route parameter name.
     *
     * @param string $parameter
     * @return $this
     */
    public function parameter(string $parameter): self
    {
        $this->options['parameter'] = $parameter;

        return $this;
    }

    /**
     * Add middleware to the resource routes.
     *
     * @param mixed ...$middleware
     * @return $this
     */
    public function middleware(...$middleware): self
    {
        if (count($middleware) === 1) {
            $middleware = Arr::wrap($middleware[0]);
        }

        if (array_is_list($middleware)) {
            $this->options['middleware'] = $middleware;
            return $this;
        }

        $this->options['middleware'] = Arr::wrap($middleware['*'] ?? null);
        $this->options['action_middleware'] = $middleware;

        return $this;
    }

    /**
     * Specify middleware that should be removed from the resource routes.
     *
     * @param string ...$middleware
     * @return $this
     */
    public function withoutMiddleware(string ...$middleware): self
    {
        $this->options['excluded_middleware'] = array_merge(
            (array) ($this->options['excluded_middleware'] ?? []),
            $middleware
        );

        return $this;
    }

    /**
     * Register resource relationship routes.
     *
     * @param Closure $callback
     * @return $this
     */
    public function relationships(Closure $callback): self
    {
        $this->relationships = $callback;

        return $this;
    }

    /**
     * Register custom actions for the resource.
     *
     * @param string|Closure $prefixOrCallback
     * @param Closure|null $callback
     * @return $this
     */
    public function actions($prefixOrCallback, ?Closure $callback = null): self
    {
        if ($prefixOrCallback instanceof Closure && null === $callback) {
            $this->actionsPrefix = null;
            $this->actions = $prefixOrCallback;
            return $this;
        }

        if (is_string($prefixOrCallback) && !empty($prefixOrCallback) && $callback instanceof Closure) {
            $this->actionsPrefix = $prefixOrCallback;
            $this->actions = $callback;
            return $this;
        }

        throw new InvalidArgumentException('Invalid arguments when registering custom resource actions.');
    }

    /**
     * Register the resource routes.
     *
     * @return RouteCollection
     */
    public function register(): RouteCollection
    {
        $this->registered = true;

        $routes = $this->registrar->register(
            $this->resourceType,
            $this->controller,
            $this->options
        );

        if ($this->relationships) {
            $relations = $this->registrar->relationships(
                $this->resourceType,
                $this->controller,
                $this->options,
                $this->relationships
            );

            foreach ($relations as $route) {
                $routes->add($route);
            }
        }

        if ($this->actions) {
            $actions = $this->registrar->actions(
                $this->resourceType,
                $this->controller,
                $this->options,
                $this->actionsPrefix,
                $this->actions
            );

            foreach ($actions as $route) {
                $routes->add($route);
            }
        }

        return $routes;
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        if (!$this->registered) {
            $this->register();
        }
    }

    /**
     * @param array $actions
     * @return array
     */
    private function normalizeActions(array $actions): array
    {
        return collect($actions)
            ->map(fn($action) => $this->map[$action] ?? $action)
            ->all();
    }
}
