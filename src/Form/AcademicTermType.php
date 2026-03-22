<?php

namespace App\Form;

use App\Entity\AcademicTerm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AcademicTermType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $yearChoices = $this->buildYearChoices();

        $builder
            ->add('schoolYearStart', ChoiceType::class, [
                'mapped'      => false,
                'label'       => 'School Year (From)',
                'placeholder' => 'Select year',
                'choices'     => $yearChoices,
                'constraints' => [
                    new Assert\NotBlank(message: 'Select the starting year.'),
                ],
            ])
            ->add('schoolYearEnd', ChoiceType::class, [
                'mapped'      => false,
                'label'       => 'School Year (To)',
                'placeholder' => 'Select year',
                'choices'     => $yearChoices,
                'constraints' => [
                    new Assert\NotBlank(message: 'Select the ending year.'),
                ],
            ])
            ->add('termLabel', ChoiceType::class, [
                'label'       => 'Term',
                'placeholder' => 'Select term',
                'choices'     => array_combine(
                    AcademicTerm::AVAILABLE_TERMS,
                    AcademicTerm::AVAILABLE_TERMS,
                ),
                'help' => 'Each school year is limited to three terms.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Choose a term label.'),
                ],
            ])
            ->add('startDate', DateType::class, [
                'label'  => 'Start Date',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(message: 'Provide a start date.'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label'  => 'End Date',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(message: 'Provide an end date.'),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Mark as active term',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $term = $event->getData();
            if (!$term instanceof AcademicTerm) {
                return;
            }

            $bounds = $term->getSchoolYearBounds();
            if (!$bounds) {
                return;
            }

            $form = $event->getForm();
            if ($form->has('schoolYearStart')) {
                $form->get('schoolYearStart')->setData((string) $bounds['start']);
            }
            if ($form->has('schoolYearEnd')) {
                $form->get('schoolYearEnd')->setData((string) $bounds['end']);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $term = $event->getData();
            if (!$term instanceof AcademicTerm) {
                return;
            }

            $form  = $event->getForm();
            $start = $form->has('schoolYearStart') ? $form->get('schoolYearStart')->getData() : null;
            $end   = $form->has('schoolYearEnd') ? $form->get('schoolYearEnd')->getData() : null;

            if ($start && $end) {
                $term->setSchoolYear(sprintf('%s-%s', $start, $end));

                $startYearInt = (int) $start;
                $endYearInt   = (int) $end;
                $allowedYears = [$startYearInt, $endYearInt];

                $termStartDate = $term->getStartDate();
                $termEndDate   = $term->getEndDate();

                if ($termStartDate) {
                    $termStartYear = (int) $termStartDate->format('Y');
                    if (!\in_array($termStartYear, $allowedYears, true)) {
                        $form->get('startDate')->addError(new FormError(sprintf('Start date must fall within the %s–%s school year.', $start, $end)));
                    }
                }

                if ($termEndDate) {
                    $termEndYear = (int) $termEndDate->format('Y');
                    if (!\in_array($termEndYear, $allowedYears, true)) {
                        $form->get('endDate')->addError(new FormError(sprintf('End date must fall within the %s–%s school year.', $start, $end)));
                    }
                }

                if ($termStartDate && $termEndDate && $termStartDate > $termEndDate) {
                    $form->get('endDate')->addError(new FormError('End date must be after the start date.'));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AcademicTerm::class,
        ]);
    }

    private function buildYearChoices(): array
    {
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');
        $startYear   = $currentYear - 5;
        $endYear     = $currentYear + 6; // allow planning ahead

        $choices = [];
        for ($year = $endYear; $year >= $startYear; $year--) {
            $choices[(string) $year] = (string) $year;
        }

        return $choices;
    }
}
