<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/applications', name: 'app_admin_applications')]
    public function applications(): Response
    {
        return $this->render('admin/applications.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    // ── Courses CRUD ────────────────────────────────────────────────────────

    #[Route('/courses', name: 'app_admin_courses')]
    public function courses(CourseRepository $repo): Response
    {
        return $this->render('admin/courses.html.twig', [
            'user'    => $this->getUser(),
            'courses' => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/courses/new', name: 'app_admin_courses_new')]
    public function courseNew(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form   = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();
            $this->addFlash('success', 'Course created successfully.');
            return $this->redirectToRoute('app_admin_courses');
        }

        return $this->render('admin/course_form.html.twig', [
            'user'  => $this->getUser(),
            'form'  => $form,
            'title' => 'New Course',
        ]);
    }

    #[Route('/courses/{id}/edit', name: 'app_admin_courses_edit')]
    public function courseEdit(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Course updated successfully.');
            return $this->redirectToRoute('app_admin_courses');
        }

        return $this->render('admin/course_form.html.twig', [
            'user'   => $this->getUser(),
            'form'   => $form,
            'title'  => 'Edit Course',
            'course' => $course,
        ]);
    }

    #[Route('/courses/{id}/delete', name: 'app_admin_courses_delete', methods: ['POST'])]
    public function courseDelete(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-course-' . $course->getId(), $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Course deleted.');
        }
        return $this->redirectToRoute('app_admin_courses');
    }

    // ── Users ────────────────────────────────────────────────────────────────

    #[Route('/users', name: 'app_admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}

