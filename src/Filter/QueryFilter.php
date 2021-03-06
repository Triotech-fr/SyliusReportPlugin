<?php

namespace Odiseo\SyliusReportPlugin\Filter;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Customer;

class QueryFilter
{
    /** @var EntityManager */
    protected $em;

    /** @var QueryBuilder */
    protected $qb;

    /** @var array */
    protected $joins = [];

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;

        $this->qb = $this->em->createQueryBuilder();
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    public function reset()
    {
        $this->qb = $this->em->createQueryBuilder();
        $this->joins = [];
    }

    /**
     * @param QueryBuilder $qb
     * @param array $configuration
     * @param string $dateField
     *
     * @return array
     */
    protected function getGroupByParts(QueryBuilder $qb, array $configuration = [], $dateField = 'checkoutCompletedAt')
    {
        if (false === strpos($dateField, '.')) {
            $rootAlias = $qb->getRootAliases()[0];
            $dateF = $rootAlias.'.'.$dateField;
        } else {
            $dateF = $dateField;
        }

        $selectPeriod = '';
        $selectGroupBy = '';
        foreach ($configuration['groupBy'] as $groupByElement) {
            if (strlen($selectPeriod) > 0) {
                $selectPeriod .= ', ';
                $selectGroupBy .= ',';
            }
            $salias = ucfirst(strtolower($groupByElement)).'Date';
            $selectPeriod .= $groupByElement.'('.$dateF.') as '.$salias;

            $selectGroupBy .= $salias;
        }

        return [$selectPeriod, $selectGroupBy];
    }

    /**
     * @param $join
     * @param $alias
     *
     * @return string
     */
    public function addLeftJoin($join, $alias): string
    {
        if (!isset($this->joins[$join])) {
            $this->joins[$join] = $alias;

            $this->qb->leftJoin($join, $alias);
        }

        return $this->joins[$join];
    }

    /**
     * @param array $configuration
     * @param string $dateField
     */
    public function addTimePeriod(array $configuration = [], $dateField = 'checkoutCompletedAt'): void
    {
        if (false === strpos($dateField, '.')) {
            $rootAlias = $this->qb->getRootAliases()[0];
            $dateF = $rootAlias.'.'.$dateField;
        } else {
            $dateF = $dateField;
        }

        $groupByParts = $this->getGroupByParts($this->qb, $configuration, $dateField);

        /** @var \DateTime $startDateTime */
        $startDateTime = $configuration['timePeriod']['start'];
        /** @var \DateTime $endDateTime */
        $endDateTime = $configuration['timePeriod']['end'];

        $this->qb
            ->addSelect($groupByParts[0])
            ->andWhere($this->qb->expr()->gte($dateF, ':from'))
            ->andWhere($this->qb->expr()->lte($dateF, ':to'))
            ->setParameter('from', $startDateTime->format('Y-m-d H:i:s'))
            ->setParameter('to', $endDateTime->format('Y-m-d H:i:s'))
            ->groupBy($groupByParts[1])
            ->orderBy('date,'.$groupByParts[1])
        ;
    }

    /**
     * @param array $configuration
     * @param null $field
     */
    public function addChannel(array $configuration = [], $field = null): void
    {
        if (isset($configuration['channel']) && $configuration['channel'] != null) {
            $storeIds = [];

            if ($configuration['channel'] instanceof ChannelInterface) {
                $storeIds[] = $configuration['channel']->getId();
            } elseif (is_array($configuration['channel']) && !in_array(0, $configuration['channel'])) {
                $storeIds = $configuration['channel'];
            }

            if (!(count($storeIds) > 0)) {
                return;
            }

            if (!$field) {
                $alias = $this->qb->getRootAliases()[0];
                $field = $alias.'.channel';
            }

            $this->qb
                ->andWhere($this->qb->expr()->in($field, $storeIds))
            ;
        }
    }

    /**
     * @param array $configuration
     */
    public function addUserGender(array $configuration = []): void
    {
        if (isset($configuration['userGender']) && $configuration['userGender'] != null) {
            $rootAlias = $cAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $cAlias = $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $this->qb
                ->andWhere($this->qb->expr()->in($cAlias.'.gender', $configuration['userGender']))
            ;
        }
    }

    /**
     * @param array $configuration
     */
    public function addUserBuyer(array $configuration = []): void
    {
        if (isset($configuration['userBuyer']) && $configuration['userBuyer'] != null && $configuration['userBuyer'] != 'both') {
            $rootAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $oAlias = $this->addLeftJoin('c.orders', 'o');

            if ($configuration['userBuyer'] == 'yes') {
                $this->qb
                    ->andWhere($this->qb->expr()->isNotNull($oAlias.'.checkoutCompletedAt'))
                    ->andWhere($oAlias.'.itemsTotal > 0')
                ;
            } else {
                $this->qb
                    ->andWhere($this->qb->expr()->isNull($oAlias.'.checkoutCompletedAt'))
                ;
            }
        }
    }

    /**
     * @param array $configuration
     * @param string $addressType
     */
    public function addUserCountry(array $configuration = [], string $addressType = 'shipping'): void
    {
        $type = 'user'.ucfirst($addressType).'Country';
        if (isset($configuration[$type]) && $configuration[$type] != null) {
            $rootAlias = $cAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $cAlias = $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $caAlias = $this->addLeftJoin($cAlias.'.addresses', 'c'.substr($addressType, 0, 1).'a');

            $this->qb
                ->andWhere($this->qb->expr()->in($caAlias.'.countryCode', $configuration[$type]))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $addressType
     */
    public function addUserProvince(array $configuration = [], string $addressType = 'shipping'): void
    {
        $type = 'user'.ucfirst($addressType).'Province';
        if (isset($configuration[$type]) && $configuration[$type] != null) {
            $rootAlias = $cAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $cAlias = $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $caAlias = $this->addLeftJoin($cAlias.'.addresses', 'c'.substr($addressType, 0, 1).'a');

            $this->qb
                ->andWhere($this->qb->expr()->in($caAlias.'.provinceCode', $configuration[$type]))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $addressType
     */
    public function addUserCity(array $configuration = [], string $addressType = 'shipping'): void
    {
        $type = 'user'.ucfirst($addressType).'City';
        if (isset($configuration[$type]) && $configuration[$type] != null) {
            $rootAlias = $cAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $cAlias = $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $caAlias = $this->addLeftJoin($cAlias.'.addresses', 'c'.substr($addressType, 0, 1).'a');

            $this->qb
                ->andWhere($this->qb->expr()->in($caAlias.'.city', $configuration[$type]))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $addressType
     */
    public function addUserPostcode(array $configuration = [], string $addressType = 'shipping'): void
    {
        $type = 'user'.ucfirst($addressType).'Postcode';
        if (isset($configuration[$type]) && $configuration[$type] != null) {
            $rootAlias = $cAlias = $this->qb->getRootAliases()[0];

            if (!$this->hasRootEntity(Customer::class)) {
                $cAlias = $this->addLeftJoin($rootAlias.'.customer', 'c');
            }

            $caAlias = $this->addLeftJoin($cAlias.'.addresses', 'c'.substr($addressType, 0, 1).'2a');

            $this->qb
                ->andWhere($this->qb->expr()->in($caAlias.'.postcode', $configuration[$type]))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $field
     */
    public function addProduct(array $configuration = [], string $field = 'p.id'): void
    {
        if (isset($configuration['product']) && $configuration['product'] != null) {
            $this->qb
                ->andWhere($this->qb->expr()->in($field, $configuration['product']))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $field
     */
    public function addProductBrand(array $configuration = [], string $field = 'p.vendor'): void
    {
        if (isset($configuration['productBrand']) && $configuration['productBrand'] != null) {
            $this->qb
                ->andWhere($this->qb->expr()->in($field, $configuration['productBrand']))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $field
     */
    public function addProductCategory(array $configuration = [], string $field = 'pt.id'): void
    {
        if (isset($configuration['productCategory']) && $configuration['productCategory'] != null) {
            $this->qb
                ->andWhere($this->qb->expr()->in($field, $configuration['productCategory']))
            ;
        }
    }

    /**
     * @param array $configuration
     * @param string $field
     */
    public function addOrderNumbers(array $configuration = [], string $field = 'o.id'): void
    {
        if (isset($configuration['orderNumbers']) && $configuration['orderNumbers'] != null) {
            $this->qb
                ->andWhere($this->qb->expr()->in($field, $configuration['orderNumbers']))
            ;
        }
    }

    /**
     * @param $rootEntityClassname
     *
     * @return bool
     */
    protected function hasRootEntity($rootEntityClassname): bool
    {
        return in_array($rootEntityClassname, $this->qb->getRootEntities());
    }
}
