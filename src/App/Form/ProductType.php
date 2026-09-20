<?php

namespace App\Form;

use App\Entity\Enum\ProductAmountType;
use App\Entity\Product;
use App\Entity\Enum\ProductResourceType;
use App\Entity\Enum\ProductStatus;
use App\Entity\ProductGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class ProductType extends BaseFormType
{
    public static string $modelClass = Product::class;
    public static string $modelName = 'product';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Sisesta nimi'),
                ],
            ])
            ->add('price', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Sisesta hind'),
                    new Range(minMessage: 'Sisesta positiivne number', min: 0),
                ],
            ])
            ->add('amount', NumberType::class, [
                'constraints' => [
                    new NotBlank(message: 'Sisesta kogus'),
                    new Range(minMessage: 'Sisesta positiivne number', min: 0),
                ],
            ])
            ->add('amountType', ChoiceType::class, [
                'choices' => ProductAmountType::values(),
                'constraints' => [
                    new NotBlank(message: 'Sisesta koguse liik'),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => ProductStatus::values(),
                'constraints' => [
                    new NotBlank(message: 'Vali staatus'),
                ],
            ])
            ->add('resourceType', ChoiceType::class, [
                'choices' => ProductResourceType::values(),
            ])
            ->add('productCode', TextType::class)
            // TODO: fix relation not saved
            ->add('productGroup', EntityType::class, [
                'class' => ProductGroup::class,
                'constraints' => [
                    new NotBlank(message: 'Vali tootegrupp'),
                ],
            ])
            ->add('warehouseCount', NumberType::class)
            ->add('storageCount', NumberType::class)
            ->add('seq', IntegerType::class);
    }
}
