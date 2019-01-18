<?php

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Gateway\DBAL;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Components\Test\Plugin\TestCase;

class CustomFacetGatewayTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ActivateFindologic' => true,
            'ShopKey' => 'ABCD0815',
            'ActivateFindologicForCategoryPages' => false,
            'IntegrationType' => Constants::INTEGRATION_TYPE_API
        ]
    ];

    public function tearDown()
    {
        parent::tearDown();
        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
    }

    /**
     * @throws Exception
     */
    public function testUseOriginalServiceWhenShopSearchIsTriggered()
    {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        $mockOriginalService = $this->getMockBuilder(DBAL\CustomFacetGateway::class)
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($this->once())->method('getList');

        $mockUrlBuilder = $this->getMockBuilder(UrlBuilder::class)->getMock();
        $mockUrlBuilder->expects($this->never())->method('setCustomerGroup');
        $mockUrlBuilder->expects($this->never())->method('buildCompleteFilterList');

        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator'),
            $mockUrlBuilder
        );

        $facetGateway->getList([3], $context);
    }

    public function faultyResponseProvider()
    {
        return [
            ['FINDOLOGIC search is triggered and response is null' => null],
            ['FINDOLOGIC search is triggered and response is not OK' => 404]

        ];
    }

    /**
     * @dataProvider faultyResponseProvider
     *
     * @param int|null $responseCode
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testUseOriginalServiceWhenFindologicResponseIsFaulty($responseCode)
    {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Custom request object to trigger findologic search
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend');

        // Create Mock object for Shopware Front Request
        $mockFront = $this->getMockBuilder(Enlight_Controller_Front::class)
            ->disableOriginalConstructor()
            ->setMethods(['Request'])
            ->getMock();

        $mockFront->method('Request')->willReturn($request);

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $mockFront);

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);

        //$originalService = Shopware()->Container()->get('shopware_storefront.custom_facet_gateway');
        $mockOriginalService = $this->getMockBuilder(DBAL\CustomFacetGateway::class)
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($this->once())->method('getList');
        $mockUrlBuilder = $this->getMockBuilder(UrlBuilder::class)
            ->setMethods(['setCustomerGroup', 'buildCompleteFilterList'])
            ->getMock();
        $mockUrlBuilder->expects($this->once())->method('setCustomerGroup');

        if ($responseCode) {
            $request = new Zend_Http_Response($responseCode, []);
        } else {
            $request = null;
        }

        $mockUrlBuilder->expects($this->once())->method('buildCompleteFilterList')->willReturn($request);

        $mockHydrator = $this->getMockBuilder(CustomListingHydrator::class)
            ->setMethods(['hydrateFacet'])
            ->getMock();
        $mockHydrator->expects($this->never())
            ->method('hydrateFacet');
        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            $mockHydrator,
            $mockUrlBuilder
        );

        $facetGateway->getList([3], $context);
    }

    /**
     * @return array
     */
    public function findologicFilterProvider()
    {
        return [
            'No facets are returned' => [
                [],
                []
            ],
            'Single facet' => [
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider']
                ],
                [
                    ProductAttributeFacet::MODE_RANGE_RESULT,
                ]
            ],
            'Two facets' => [
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider'],
                    ['name' => 'color', 'display' => 'Farbe', 'select' => 'multiple', 'type' => 'label'],
                ],
                [
                    ProductAttributeFacet::MODE_RANGE_RESULT,
                    ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                ]
            ],
        ];
    }

    /**
     * @dataProvider findologicFilterProvider
     *
     * @param array $filterData
     * @param array $attributeMode
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testCreatesShopwareFacetsFromFindologicFilters(
        array $filterData,
        array $attributeMode
    ) {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Custom request object to trigger findologic search
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend');

        // Create Mock object for Shopware Front Request
        $mockFront = $this->getMockBuilder(Enlight_Controller_Front::class)
            ->disableOriginalConstructor()
            ->setMethods(['Request'])
            ->getMock();

        $mockFront->method('Request')->willReturn($request);

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $mockFront);

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);

        $mockOriginalService = $this->getMockBuilder(DBAL\CustomFacetGateway::class)
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($this->never())->method('getList');

        $mockUrlBuilder = $this->getMockBuilder(UrlBuilder::class)->getMock();
        $mockUrlBuilder->expects($this->once())->method('setCustomerGroup');
        $xmlResponse = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 2);
        $filters = $xmlResponse->addChild('filters');

        foreach ($filterData as $data) {
            $filter = $filters->addChild('filter');
            foreach ($data as $key => $value) {
                $filter->addChild($key, $value);
            }
        }

        $mockUrlBuilder->expects($this->once())
            ->method('buildCompleteFilterList')
            ->willReturn(new Zend_Http_Response(200, [], $xmlResponse->asXML()));

        $originalHydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            $originalHydrator,
            $mockUrlBuilder
        );

        $customFacets = $facetGateway->getList([3], $context);
        $this->assertCount(
            count($filterData),
            $customFacets,
            'Expected same number of facets to be returned as the number of filters'
        );

        /** @var CustomFacet $customFacet */
        foreach ($customFacets as $key => $customFacet) {
            $this->assertSame(
                $filterData[$key]['name'],
                $customFacet->getName(),
                sprintf("Expected custom facet's name to be %s", $filterData[$key]['name'])
            );
            $this->assertSame(
                $filterData[$key]['name'],
                $customFacet->getUniqueKey(),
                sprintf("Expected custom facet's unique key to be %s", $filterData[$key]['name'])
            );

            /** @var ProductAttributeFacet $productAttributeFacet */
            $productAttributeFacet = $customFacet->getFacet();

            $this->assertInstanceOf(
                ProductAttributeFacet::class,
                $productAttributeFacet,
                "Expected custom facet's facet to be of type ProductAttributeFacet"
            );
            $this->assertSame(
                sprintf('product_attribute_%s', $filterData[$key]['name']),
                $productAttributeFacet->getName(),
                sprintf(
                    "Expected product attribute facet's name to be %s",
                    sprintf('product_attribute_%s', $filterData[$key]['name'])
                )
            );
            $this->assertSame(
                $filterData[$key]['name'],
                $productAttributeFacet->getFormFieldName(),
                sprintf("Expected product attribute facet's form field name to be %s", $filterData[$key]['name'])
            );
            $this->assertSame(
                $filterData[$key]['display'],
                $productAttributeFacet->getLabel(),
                sprintf("Expected product attribute facet's label to be %s", $filterData[$key]['display'])
            );
            $this->assertSame(
                $attributeMode[$key],
                $productAttributeFacet->getMode(),
                sprintf("Expected product attribute facet's mode to be %s", $attributeMode[$key])
            );
        }
    }
}