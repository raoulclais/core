<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\GraphQL\Action;

use ApiPlatform\Core\GraphQL\Schema\SchemaFactory;
use GraphQL\GraphQL;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GraphQL entrypoint.
 *
 * @author Raoul Clais <raoul.clais@gmail.com>
 */
final class GraphQLAction
{
    private $requestStack;
    private $schemaFactory;

    public function __construct(RequestStack $requestStack, SchemaFactory $schemaFactory)
    {
        $this->requestStack = $requestStack;
        $this->schemaFactory = $schemaFactory;
    }

    public function __invoke(): JsonResponse
    {
        list($requestString, $rootValue, $variableValues, $operationName) = $this->getGraphQLRequest();

        return new JsonResponse(GraphQL::execute($this->schemaFactory->create(), $requestString, $rootValue, null, $variableValues, $operationName));
    }

    private function getGraphQLRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return [
            $request->query->get('query'),
            null,
            $request->query->get('variables'),
            $request->query->get('operationName')
        ];
    }
}
