<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use Exception;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilderFactory;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Zend_Cache_Exception;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    /**
     * @var QueryBuilderFactory
     */
    protected $queryBuilderFactory;

    /**
     * @param CustomListingHydrator $hydrator
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        CustomListingHydrator $hydrator,
        QueryBuilderFactoryInterface $queryBuilderFactory
    ) {
        $this->hydrator = $hydrator;
        $this->queryBuilderFactory = $queryBuilderFactory;
    }

    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return CustomFacet[]
     * @throws Zend_Cache_Exception
     * @throws Exception
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createSearchNavigationQueryWithoutAdditionalFilters($criteria, $context);

        try {
            /** @var Xml21Response $response */
            $response = $query->execute();
        } catch (ServiceNotAliveException $e) {
            return [];
        }
        $responseParser = ResponseParser::getInstance($response);

        $filters = $responseParser->getFilters();

        return $this->hydrate($filters);
    }

    /**
     * @param array $categoryIds
     * @param ShopContextInterface $context
     *
     * @return array
     * @throws Zend_Cache_Exception
     * @throws Exception
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
        $categoryId = $categoryIds[0];

        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);
        $criteria->addCondition(new CategoryCondition($categoryIds));

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);

        try {
            /** @var Xml21Response $response */
            $response = $query->execute();
        } catch (ServiceNotAliveException $e) {
            return [];
        }
        $responseParser = ResponseParser::getInstance($response);

        $filters = $responseParser->getFilters();
        if (!StaticHelper::isEmpty($filters)) {
            $categoryFacets = [];
            $categoryFacets[$categoryId] = $this->hydrate($filters);

            return $categoryFacets;
        }

        return [];
    }

    /**
     * @param ShopContextInterface $context
     *
     * @return array
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
        return [];
    }

    /**
     * @param Filter[] $filters
     *
     * @return CustomFacet[]
     * @throws Zend_Cache_Exception
     */
    private function hydrate(array $filters)
    {
        $facets = [];
        $hasCategoryFacet = false;
        $hasVendorFacet = false;

        foreach ($filters as $filter) {
            $facet = $this->hydrator->hydrateFacet($filter);
            $facetName = $facet->getName();

            if ($facetName === 'vendor') {
                $hasVendorFacet = true;
            }
            if ($facetName === 'cat') {
                $hasCategoryFacet = true;
            }

            $facets[] = $facet;
        }

        if (!$hasCategoryFacet) {
            $facets[] = $this->hydrator->hydrateDefaultCategoryFacet();
        }
        if (!$hasVendorFacet) {
            $facets[] = $this->hydrator->hydrateDefaultVendorFacet();
        }

        return $facets;
    }
}
