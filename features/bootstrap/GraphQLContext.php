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

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\HttpFoundation\Request;

class GraphQLContext implements Context
{
    /**
     * @var \Behatch\Context\RestContext
     */
    private $restContext;

    /**
     * Gives access to the Behatch context.
     *
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        /** @var InitializedContextEnvironment $environment */
        $environment = $scope->getEnvironment();
        $this->restContext = $environment->getContext('Behatch\Context\RestContext');
    }

    /**
     * @When I send the following graphql request:
     */
    public function ISendTheFollowingGraphqlRequest(PyStringNode $request)
    {
        $this->restContext->iSendARequestTo(Request::METHOD_GET, '/graphql?query='.str_replace("\n", "", $request->getRaw()));
    }
}
