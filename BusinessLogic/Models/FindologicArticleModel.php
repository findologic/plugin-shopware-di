<?php

namespace findologicDI\BusinessLogic\Models {


    use Doctrine\ORM\PersistentCollection;
    use FINDOLOGIC\Export\Data\DateAdded;
    use FINDOLOGIC\Export\Data\Description;
    use FINDOLOGIC\Export\Data\Item;
    use FINDOLOGIC\Export\Data\Name;
    use FINDOLOGIC\Export\Data\Ordernumber;
    use FINDOLOGIC\Export\Data\Price;
    use FINDOLOGIC\Export\Data\Summary;
    use FINDOLOGIC\Export\Exporter;
    use FINDOLOGIC\Export\XML\XMLExporter;
    use findologicDI\ShopwareProcess;
    use Shopware\Models\Article\Article;
    use Shopware\Models\Article\Detail;
    use Shopware\Models\Customer\Group;
    use Shopware\Models\Order\Order;

    class FindologicArticleModel
    {

        /**
         * @var XMLExporter
         */
        var $exporter;

        /**
         * @var Item
         */
        var $xmlArticle;

        /**
         * @var Article
         */
        var $baseArticle;

        /**
         * @var \Shopware\Models\Article\Detail
         */
        var $baseVariant;

        /**
         * @var string
         */
        var $shopKey;

        /**
         * @var PersistentCollection
         */
        var $variantArticles;

        /**
         * @var array
         */
        var $allUserGroups;


        /**
         * @var \Shopware\Models\Order\Repository
         */
        var $orderRepository;


        /**
         * FindologicArticleModel constructor.
         * @param Article $shopwareArticle
         * @param string $shopKey
         * @param array $allUserGroups
         */
        public function __construct(Article $shopwareArticle, string $shopKey, array $allUserGroups)
        {
            $this->orderRepository = Shopware()->Container()->get('models')->getRepository(Order::class);
            $this->exporter = Exporter::create(Exporter::TYPE_XML);
            $this->xmlArticle = $this->exporter->createItem($shopwareArticle->getId());
            $this->shopKey = $shopKey;
            $this->baseArticle = $shopwareArticle;
            $this->baseVariant = $this->baseArticle->getMainDetail();

            $this->allUserGroups = $allUserGroups;

            // Load all variants
            $this->variantArticles = $this->baseArticle->getDetails();

            // Fill out the Basedata
            $this->setArticleName($this->baseArticle->getName());

            if (trim($this->baseArticle->getDescription())) {
                $this->setSummary($shopwareArticle->getDescription());
            }

            if (trim($this->baseArticle->getDescriptionLong())) {
                $this->setDescription($shopwareArticle->getDescriptionLong());
            }

            $this->setAddDate();
            //$this->setUrls();
            //$this->setKeywords();
            //$this->setSorts();
            //$this->setImages();
            $this->setSales();
            //$this->setAddProperties();


            $this->setPrices();
            $this->setVariantOrdernumbers();
        }

        public function getXmlRepresentation()
        {
            return $this->xmlArticle;
        }

        protected function setArticleName(string $name, string $userGroup = null)
        {
            $xmlName = new Name();
            $xmlName->setValue($name, $userGroup);
            $this->xmlArticle->setName($xmlName);
        }

        protected function setVariantOrdernumbers()
        {
            $ordernumbers = array();


            /** @var Detail $detail */
            foreach ($this->variantArticles as $detail) {
                $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getNumber()));

                if ($detail->getEan()) {
                    $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getEan()));
                }

                if ($detail->getSupplierNumber()) {
                    $this->xmlArticle->addOrdernumber(new Ordernumber($detail->getSupplierNumber()));
                }

            }

        }

        protected function setSummary(string $description)
        {
            $summary = new Summary();
            $summary->setValue(trim($description));
            $this->xmlArticle->setSummary($summary);
        }

        protected function setDescription(string $descriptionLong)
        {
            $description = new Description();
            $description->setValue($descriptionLong);
            $this->xmlArticle->setDescription($description);
        }

        protected function setPrices()
        {
            $priceArray = array();
            // variant prices per customergroup
            /** @var Detail $detail */
            foreach ($this->variantArticles as $detail) {
                if ($detail->getActive() == 1) {
                    /** @var \Shopware\Models\Article\Price $price */
                    foreach ($detail->getPrices() as $price) {
                        /** @var \Shopware\Models\Customer\Group $customerGroup */
                        $customerGroup = $price->getCustomerGroup();
                        if ($customerGroup) {
                            $priceArray[$customerGroup->getKey()][] = $price->getPrice();
                        }

                    }
                }
            }

            // main prices per customergroup
            foreach ($this->baseVariant->getPrices() as $price) {
                if ($price->getCustomerGroup()) {
                    /** @var \Shopware\Models\Customer\Group $customerGroup */
                    $customerGroup = $price->getCustomerGroup();
                    if ($customerGroup) {
                        $priceArray[$customerGroup->getKey()][] = $price->getPrice();
                    }

                }
            }

            $tax = $this->baseArticle->getTax();

            // searching the loweset price for each customer group
            /** @var Group $userGroup */
            foreach ($this->allUserGroups as $userGroup) {
                if (array_key_exists($userGroup->getKey(), $priceArray)) {
                    $price = min($priceArray[$userGroup->getKey()]);
                } else {
                    $price = min($priceArray['EK']);
                }

                // Add taxes if needed
                if ($userGroup->getTax()) {
                    $price = $price * (1 + (float)$tax->getTax() / 100);
                }

                $xmlPrice = new Price();
                $xmlPrice->setValue(sprintf('%.2f', $price), ShopwareProcess::calculateUsergroupHash($userGroup->getKey(), $this->shopKey));
                $this->xmlArticle->setPrice($xmlPrice);

                if ($userGroup->getKey() == 'EK') {
                    $basePrice = new Price();
                    $xmlPrice->setValue(sprintf('%.2f', $price));
                    $this->xmlArticle->setPrice($basePrice);
                }
            }

        }

        protected function setAddDate()
        {
            $dateAdded = new DateAdded();
            $dateAdded->setDateValue($this->baseArticle->getAdded());
            $this->xmlArticle->setDateAdded($dateAdded);
        }

        protected function setSales()
        {
            $orderQuery = $this->orderRepository->createQueryBuilder('orders')
                ->leftJoin('orders.details', 'details');



        }


    }
}