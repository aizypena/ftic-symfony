<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'       => 'First Name',
                'constraints' => [new NotBlank(['message' => 'Please enter your first name.'])],
                'attr'        => ['placeholder' => 'e.g. Juan'],
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Last Name',
                'constraints' => [new NotBlank(['message' => 'Please enter your last name.'])],
                'attr'        => ['placeholder' => 'e.g. dela Cruz'],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Email Address',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your email.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
                'attr' => ['placeholder' => 'you@example.com'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'          => PasswordType::class,
                'mapped'        => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr'  => ['placeholder' => '••••••••'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr'  => ['placeholder' => '••••••••'],
                ],
                'invalid_message' => 'Passwords do not match.',
                'constraints'     => [
                    new NotBlank(['message' => 'Please enter a password.']),
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least {{ limit }} characters.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
