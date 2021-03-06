<?php

namespace spec\Odiseo\SyliusReportPlugin\Form\Type\DataFetcher;

use Odiseo\SyliusReportPlugin\Form\Type\DataFetcher\UserRegistrationType;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilder;

class UserRegistrationTypeSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(UserRegistrationType::class);
    }

    function it_should_be_abstract_type_object()
    {
        $this->shouldHaveType(AbstractType::class);
    }

    function it_has_block_prefix()
    {
        $this->getBlockPrefix()->shouldReturn('odiseo_sylius_report_data_fetcher_user_registration');
    }

    function it_builds_the_form(FormBuilder $builder)
    {
        $builder->add('start', DateType::class, Argument::any())->willReturn($builder);
        $builder->add('end', DateType::class, Argument::any())->willReturn($builder);
        $builder->add('period', ChoiceType::class, Argument::any())->willReturn($builder);
        $builder->add('empty_records', CheckboxType::class, Argument::any())->willReturn($builder);

        $this->buildForm($builder, []);
    }
}
