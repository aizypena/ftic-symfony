<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Course Name',
                'attr'  => ['placeholder' => 'e.g. Web Development Fundamentals'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Brief course description…', 'rows' => 4],
            ])
            ->add('trainer', EntityType::class, [
                'class'        => User::class,
                'label'        => 'Assign Trainer',
                'required'     => false,
                'placeholder'  => '— No trainer assigned —',
                'choice_label' => fn(User $u) => $u->getDisplayName() . ' (' . $u->getEmail() . ')',
                'query_builder' => fn(UserRepository $repo) => $repo->createQueryBuilder('u')
                    ->where('u.role = :role')
                    ->setParameter('role', 'trainer')
                    ->orderBy('u.fullName', 'ASC'),
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Status',
                'choices' => [
                    'Active'   => 'active',
                    'Inactive' => 'inactive',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Course::class]);
    }
}
