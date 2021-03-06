<?php

namespace Odiseo\SyliusReportPlugin\Form\Type\DataFetcher;

use Odiseo\SyliusReportPlugin\Form\Builder\QueryFilterFormBuilder;
use Symfony\Component\Form\AbstractType;

abstract class BaseDataFetcherType extends AbstractType
{
    /**
     * @var QueryFilterFormBuilder
     */
    protected $queryFilterFormBuilder;

    public function __construct(QueryFilterFormBuilder $queryFilterFormBuilder)
    {
        $this->queryFilterFormBuilder = $queryFilterFormBuilder;
    }
}
