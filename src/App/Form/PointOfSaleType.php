<?php

namespace App\Form;

use App\Entity\PointOfSale;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class PointOfSaleType extends BaseFormType
{
    public static string $modelClass = PointOfSale::class;
    public static string $modelName = 'pointOfSale';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Sisesta nimi'),
                ],
            ])
        ;
    }
}
