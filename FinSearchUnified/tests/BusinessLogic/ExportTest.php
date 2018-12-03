<?php

namespace FinSearchUnified\BusinessLogic;

use FinSearchUnified\tests\Helper\Utility;
use FinSearchUnified\XmlInformation;
use Shopware\Components\Api\Manager;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Article\Article;

class ExportTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCD0815'
        ],
    ];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Utility::sResetArticles();
    }

    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    /**
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProvider()
    {
        $shopCategoryId = Shopware()->Shop()->getCategory()->getId();

        return [
            'All active articles' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 2,
                'Two articles were expected'
            ],
            '1 active and 1 inactive article' => [
                'Active' => [true, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 1,
                'Only one article was expected'
            ],
            'All inactive articles' => [
                'Active' => [false, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 0,
                'No articles were expected but %d were returned'
            ],
            'Invalid shopkey' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => null,
                'UnexpectedValueException was expected but was not thrown'
            ],
            'Return only first article' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => 1,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 1,
                '1 out of 2 valid articles were expected to be exported'
            ],
            'Skip first article and return all others' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 1,
                'All except the first article were expected to be exported'
            ],
            'Skip first article and return the next one' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => 1,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 1,
                'Only one article was expected to be exported'
            ],
            'Last stock is false and hideNoInStock is true' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'categories' => [$shopCategoryId, true],
                'expected' => 2,
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is greater than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'categories' => [$shopCategoryId, true],
                'expected' => 2,
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is less than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'categories' => [$shopCategoryId, true],
                'expected' => 0,
                'Expected that variation is not returned'
            ],
            'Last stock is true and hideNoInStock is false and stock is less than min purchase' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => false,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'categories' => [$shopCategoryId, true],
                'expected' => 2,
                'Expected that variation is returned'
            ],
            'Only current shop article are considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'categories' => [$shopCategoryId, true],
                'expected' => 2,
                'Expected that variation is returned'
            ],
            'Other shop articles are not considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'categories' => [39, true],
                'expected' => 0,
                'Expected that variation are not returned'
            ],
            'Only article with active category is considered' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'categories' => [$shopCategoryId, false],
                'expected' => 0,
                'Expected that variation is not returned'
            ]
        ];
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider articleProvider
     *
     * @param array $isActive
     * @param string $shopkey
     * @param int $start
     * @param int $count
     * @param bool $hideNoInStock
     * @param int $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param array $category
     * @param int|null $expected
     * @param string $errorMessage
     *
     * @throws \Exception
     */
    public function testArticleExport(
        $isActive,
        $shopkey,
        $start,
        $count,
        $hideNoInStock,
        $laststock,
        $instock,
        $minpurchase,
        $category,
        $expected,
        $errorMessage
    ) {
        $categoryRepository = Shopware()->Models()->getRepository('Shopware\Models\Category\Category');

        /** @var Category $categoryModel */
        $categoryModel = $categoryRepository->find($category[0]);
        $this->assertInstanceOf(
            'Shopware\Models\Category\Category',
            $categoryModel,
            sprintf('Could not find category for given ID %d', $category[0])
        );

        $categoryModel->setActive($category[1]);
        Shopware()->Models()->persist($categoryModel);
        Shopware()->Models()->flush();

        // Create articles with the provided data to test the export functionality
        for ($i = 0; $i < count($isActive); $i++) {
            $this->createTestProduct($i, $isActive[$i], $laststock, $instock, $minpurchase, $categoryModel->getId());
        }

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap([
                ['hideNoInStock', $hideNoInStock],
                ['ShopKey', 'ABCD0815']
            ]);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        /** @var Export $exportService */
        $exportService = Shopware()->Container()->get('fin_search_unified.business_logic.export');

        if (!isset($expected)) {
            $this->expectException(\UnexpectedValueException::class);
        }

        /** @var XmlInformation $result */
        $result = $exportService->getXml($shopkey, $start, $count);

        if (isset($expected)) {
            $this->assertSame($expected, $result->count, $errorMessage);
        }
    }

    /**
     * Method to create test products for the export
     *
     * @param int $number
     * @param bool $isActive
     * @param int $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param int $categoryId
     *
     * @return Article|null
     */
    private function createTestProduct($number, $isActive, $laststock, $instock, $minpurchase, $categoryId)
    {
        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => $categoryId]
            ],
            'mainDetail' => [
                'number' => 'FINDOLOGIC' . $number,
                'active' => $isActive,
                'inStock' => $instock,
                'lastStock' => $laststock,
                'minPurchase' => $minpurchase,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ],
                ]
            ],
        ];

        try {
            $manger = new Manager();
            $resource = $manger->getResource('Article');
            $article = $resource->create($testArticle);

            return $article;
        } catch (\Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return null;
    }
}
