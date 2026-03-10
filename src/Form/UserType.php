<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('fullName', TextType::class, [
                'label'    => 'Full Name',
                'required' => false,
                'attr'     => ['placeholder' => 'Enter full name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['placeholder' => 'Enter email'],
            ])
            ->add('role', ChoiceType::class, [
                'label'   => 'Role',
                'choices' => [
                    'Admin'   => 'admin',
                    'Trainer' => 'trainer',
                    'Student' => 'student',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label'    => $isEdit ? 'New Password (leave blank to keep current)' : 'Password',
                'mapped'   => false,
                'required' => !$isEdit,
                'attr'     => ['placeholder' => $isEdit ? 'Leave blank to keep unchanged' : 'Enter password'],
                'constraints' => $isEdit ? [] : [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least {{ limit }} characters.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false,
        ]);
    }
}
