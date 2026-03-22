<?php

namespace App\Form;

use App\Entity\AcademicTerm;
use App\Entity\Course;
use App\Entity\User;
use App\Repository\AcademicTermRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CourseType extends AbstractType
{
    private const PLACEHOLDER_SCHOOL_YEAR = 'Default';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Course|null $course */
        $course = $builder->getData();
        $yearChoices = $this->buildYearChoices();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Course Name',
                'attr'  => ['placeholder' => 'e.g. Web Development Fundamentals'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Give this course a name.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Brief course description…', 'rows' => 4],
            ]);

        if ($options['show_trainer_field']) {
            $builder->add('trainer', EntityType::class, [
                'class'        => User::class,
                'label'        => 'Assign Trainer',
                'required'     => false,
                'placeholder'  => '— No trainer assigned —',
                'choice_label' => fn(User $u) => $u->getDisplayName() . ' (' . $u->getEmail() . ')',
                'query_builder' => fn(UserRepository $repo) => $repo->createQueryBuilder('u')
                    ->where('u.role = :role')
                    ->setParameter('role', 'trainer')
                    ->orderBy('u.lastName', 'ASC'),
            ]);
        }

        if ($options['allow_term_selection']) {
            $builder
                ->add('termMode', ChoiceType::class, [
                    'mapped'    => false,
                    'expanded'  => true,
                    'multiple'  => false,
                    'label'     => 'Schedule Mode',
                    'data'      => 'existing',
                    'choices'   => [
                        'Use existing school year & term' => 'existing',
                        'Create a new school year & term' => 'new',
                    ],
                    'choice_attr' => [
                        'existing' => ['data-description' => 'Pick from previously created school years and terms.'],
                        'new'      => ['data-description' => 'Define a fresh school year and term without leaving this form.'],
                    ],
                ])
                ->add('term', EntityType::class, [
                    'class'        => AcademicTerm::class,
                    'label'        => 'School Year & Term',
                    'placeholder'  => 'Select school year & term',
                    'choice_label' => fn(AcademicTerm $term) => $term->getDisplayLabel(),
                    'required'     => false,
                    'query_builder' => function (AcademicTermRepository $repo) use ($course) {
                        $qb = $repo->createQueryBuilder('t')
                            ->orderBy('t.startDate', 'DESC');

                        $shouldHidePlaceholder = !$course || !$course->getTerm() || $course->getTerm()->getSchoolYear() !== self::PLACEHOLDER_SCHOOL_YEAR;

                        if ($shouldHidePlaceholder) {
                            $qb->where('t.schoolYear != :placeholder')
                               ->setParameter('placeholder', self::PLACEHOLDER_SCHOOL_YEAR);
                        }

                        return $qb;
                    },
                ])
                ->add('newTermSchoolYearStart', ChoiceType::class, [
                    'mapped'      => false,
                    'required'    => false,
                    'label'       => 'School Year (From)',
                    'placeholder' => 'Select year',
                    'choices'     => $yearChoices,
                ])
                ->add('newTermSchoolYearEnd', ChoiceType::class, [
                    'mapped'      => false,
                    'required'    => false,
                    'label'       => 'School Year (To)',
                    'placeholder' => 'Select year',
                    'choices'     => $yearChoices,
                ])
                ->add('newTermLabel', ChoiceType::class, [
                    'mapped'      => false,
                    'required'    => false,
                    'label'       => 'Term',
                    'placeholder' => 'Select term',
                    'choices'     => array_combine(
                        AcademicTerm::AVAILABLE_TERMS,
                        AcademicTerm::AVAILABLE_TERMS,
                    ),
                    'help' => 'Each school year is limited to the three defined terms.',
                ])
                ->add('newTermStartDate', DateType::class, [
                    'mapped'   => false,
                    'required' => false,
                    'label'    => 'Start Date',
                    'widget'   => 'single_text',
                ])
                ->add('newTermEndDate', DateType::class, [
                    'mapped'   => false,
                    'required' => false,
                    'label'    => 'End Date',
                    'widget'   => 'single_text',
                ])
                ->add('newTermIsActive', CheckboxType::class, [
                    'mapped'   => false,
                    'required' => false,
                    'label'    => 'Mark this term as active for students',
                    'help'     => 'Activating a term hides courses from other school years for students.',
                ]);
        }

        $builder->add('status', ChoiceType::class, [
            'label'   => 'Status',
            'choices' => [
                'Active'   => 'active',
                'Inactive' => 'inactive',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'show_trainer_field' => true,
            'allow_term_selection' => false,
        ]);

        $resolver->setAllowedTypes('show_trainer_field', 'bool');
        $resolver->setAllowedTypes('allow_term_selection', 'bool');
    }

    private function buildYearChoices(): array
    {
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');
        $startYear   = $currentYear - 5;
        $endYear     = $currentYear + 6;

        $choices = [];
        for ($year = $endYear; $year >= $startYear; $year--) {
            $choices[(string) $year] = (string) $year;
        }

        return $choices;
    }
}
