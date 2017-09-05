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

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\ContainNonResource;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;
use phpDocumentor\Reflection\Types\Resource;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Schema Factory
 *
 * @author Raoul Clais <raoul.clais@gmail.com>
 */
class SchemaFactory implements SchemaFactoryInterface
{
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $resourceNameCollectionFactory;
    private $resourceMetadataFactory;
    private $collectionDataProvider;
    private $itemDataProvider;
    private $normalizer;
    private $queryFields = [];
    private $mutationFields = [];
    private $resourceTypes = [];

    /**
     * @param PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory
     * @param PropertyMetadataFactoryInterface       $propertyMetadataFactory
     * @param ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory
     * @param ResourceMetadataFactoryInterface       $resourceMetadataFactory
     * @param CollectionDataProviderInterface        $collectionDataProvider
     * @param ItemDataProviderInterface              $itemDataProvider
     * @param NormalizerInterface                    $normalizer
     */
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory, ResourceMetadataFactoryInterface $resourceMetadataFactory, CollectionDataProviderInterface $collectionDataProvider, ItemDataProviderInterface $itemDataProvider, NormalizerInterface $normalizer)
    {
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->resourceNameCollectionFactory = $resourceNameCollectionFactory;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->collectionDataProvider = $collectionDataProvider;
        $this->itemDataProvider = $itemDataProvider;
        $this->normalizer = $normalizer;
    }

    /**
     * @return Schema
     */
    public function create(): Schema
    {
        foreach ($this->resourceNameCollectionFactory->create() as $resource) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resource);

            $this->createQueryFields($resource, $resourceMetadata);
            $this->createMutationFields($resource, $resourceMetadata);
        }

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => $this->queryFields,
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => $this->mutationFields,
            ])
        ]);
    }

    /**
     * @param string           $resource
     * @param ResourceMetadata $resourceMetadata
     */
    private function createQueryFields(string $resource, ResourceMetadata $resourceMetadata)
    {
        $shortName = $resourceMetadata->getShortName();
        $resourceType = $this->getResourceType($resource, $shortName, $resourceMetadata->getDescription());

        foreach ($this->getOperations($resourceMetadata, true, true) as $operationName => $queryItemOperation) {
            $this->queryFields['item_'.$operationName.'_'.$shortName] = [
                'type' => $resourceType,
                'args' => ['id' => $this->getResourceIdentifier($resource)],
                'resolve' => function ($root, $args) use ($resource) {
                    return $this->normalizer->normalize($this->itemDataProvider->getItem($resource, $args['id'], 'get'));
                }
            ];
        }

        foreach ($this->getOperations($resourceMetadata, true, false) as $operationName => $queryCollectionOperation) {
            $this->queryFields['collection_'.$operationName.'_'.$shortName] = [
                'type' => GraphQLType::listOf($resourceType),
                'resolve' => function ($root, $args) use ($resource) {
                    return $this->normalizer->normalize($this->collectionDataProvider->getCollection($resource, 'get'));
                }
            ];
        }
    }

    /**
     * @param string           $resource
     * @param ResourceMetadata $resourceMetadata
     */
    private function createMutationFields(string $resource, ResourceMetadata $resourceMetadata)
    {
        $shortName = $resourceMetadata->getShortName();
        $resourceType = $this->getResourceType($resource, $shortName, $resourceMetadata->getDescription());

        foreach ($this->getOperations($resourceMetadata, false, false) as $operationName => $mutationOperation) {
            $this->mutationFields['collection_'.$operationName.'_'.$shortName] = [
                'type' => $resourceType,
                'args' => $this->getResourceArgs($resourceMetadata),
                'resolve' => function ($val, $args) {
                
                }
            ];
        }

        foreach ($this->getOperations($resourceMetadata, false, true) as $operationName => $mutationOperation) {
            $this->mutationFields['item_'.$operationName.'_'.$shortName] = [
                'type' => $resourceType,
            ];
        }
    }

    /**
     * @param string $resource
     * @param string $shortName
     * @param string $description
     *
     * @return ObjectType
     */
    private function getResourceType(string $resource, string $shortName, string $description): ObjectType
    {
        if (!isset($this->resourceTypes[$shortName])) {
            $this->resourceTypes[$shortName] = new ObjectType([
                'name' => $shortName,
                'description' => $description,
                'fields' => function () use ($resource) {
                    return $this->getResourceFields($resource);
                }
            ]);
        }

        return $this->resourceTypes[$shortName];
    }

    /**
     * @param string $resource
     *
     * @return array
     */
    private function getResourceIdentifier(string $resource): array
    {
        foreach ($this->propertyNameCollectionFactory->create($resource) as $property) {
            $propertyMetadata = $this->propertyMetadataFactory->create($resource, $property);

            if (!$propertyMetadata->isIdentifier()) {
                continue;
            }

            return [
                'name' => $property,
                'description' => $propertyMetadata->getDescription(),
                'type' => GraphQLType::nonNull($this->convertType($propertyMetadata->getType()))
            ];
        }

        throw new \LogicException(sprintf('Missing identifier field for resource "%s"', $resource));
    }

    /**
     * @param string $resource
     *
     * @return array
     */
    private function getResourceFields(string $resource): array
    {
        $fields = [];

        foreach ($this->propertyNameCollectionFactory->create($resource) as $property) {
            $propertyMetadata = $this->propertyMetadataFactory->create($resource, $property);

            if (null !== $propertyMetadata->getType()) {
                try {
                    $fields[$property] = [
                        'type' => $this->convertType($propertyMetadata->getType()),
                        'description' => $propertyMetadata->getDescription()
                    ];
                } catch (InvalidTypeException $e) {
                    continue;
                }
            }
        }

        return $fields;
    }

    /**
     * @param ResourceMetadata $resourceMetadata
     *
     * @return array
     */
    private function getResourceArgs(ResourceMetadata $resourceMetadata): array
    {
        $args = [];

        foreach( as $arg) {
            $args[] = [
                'type' => $this->convertType($proper)
            ]
        }

        return $args;
    }

    /**
     * @param Type $type
     *
     * @return GraphQLType
     */
    private function convertType(Type $type): GraphQLType
    {
        switch ($type->getBuiltinType()) {
            case Type::BUILTIN_TYPE_BOOL:
                $graphqlType = GraphQLType::boolean();
                break;

            case Type::BUILTIN_TYPE_INT:
                $graphqlType = GraphQLType::int();
                break;

            case Type::BUILTIN_TYPE_FLOAT:
                $graphqlType = GraphQLType::float();
                break;

            case Type::BUILTIN_TYPE_STRING:
                $graphqlType = GraphQLType::string();
                break;

            case Type::BUILTIN_TYPE_OBJECT:
                if (\DateTime::class === $type->getClassName()) {
                    $graphqlType = GraphQLType::string();
                    break;
                }

                try {
                    $className = $type->isCollection() ? $type->getCollectionValueType()->getClassName() : $type->getClassName();
                    $shortName = $this->resourceMetadataFactory->create($className)->getShortName();
                } catch (ResourceClassNotFoundException $e) {
                    throw new InvalidTypeException();
                }

                $graphqlType = $this->resourceTypes[$shortName];
                break;

            case Type::BUILTIN_TYPE_ARRAY:
            case Type::BUILTIN_TYPE_RESOURCE:
            case Type::BUILTIN_TYPE_NULL:
            case Type::BUILTIN_TYPE_CALLABLE:
            default:
                throw new InvalidTypeException();
        }

        return $type->isCollection() ? GraphQLType::listOf($graphqlType) : $graphqlType;
    }

    /**
     * @param ResourceMetadata $resourceMetadata
     * @param bool             $query
     * @param bool             $item
     *
     * @return array
     */
    private function getOperations(ResourceMetadata $resourceMetadata, bool $query, bool $item): array
    {
        return array_filter($item ? $resourceMetadata->getItemOperations() : $resourceMetadata->getCollectionOperations(), function ($operation) use ($query) {
            if (isset($operation['controller']) || !isset($operation['method'])) {
                return false;
            }

            $isGetMethod = Request::METHOD_GET === $operation['method'];

            return $query ? $isGetMethod : !$isGetMethod;
        });
    }
}
