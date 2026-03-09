<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CourseMaterialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'PDF File',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'maxSize' => '50M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/x-pdf',
                        'application/acrobat',
                        'application/vnd.pdf',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid PDF file.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
