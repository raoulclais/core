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

namespace ApiPlatform\Core\GraphQL\Schema;

use GraphQL\Schema;

/**
 * Schema Factory Interface
 *
 * @author Raoul Clais <raoul.clais@gmail.com>
 */
Interface SchemaFactoryInterface
{
    /**
     * Creates the GraphQL Schema
     *
     * @return Schema
     */
    public function create(): Schema;
}