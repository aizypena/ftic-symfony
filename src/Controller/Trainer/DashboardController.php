<?php

namespace App\Controller\Trainer;

use App\Entity\CourseMaterial;
use App\Form\CourseMaterialType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/trainer')]
#[IsGranted('ROLE_TRAINER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_trainer_dashboard')]
    public function index(): Response
    {
        return $this->render('trainer/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/courses', name: 'app_trainer_courses')]
    public function courses(CourseRepository $courseRepository): Response
    {
        $trainer = $this->getUser();
        $courses = $courseRepository->findBy(['trainer' => $trainer], ['createdAt' => 'DESC']);

        return $this->render('trainer/courses.html.twig', [
            'user' => $trainer,
            'courses' => $courses,
        ]);
    }

    #[Route('/courses/{id}', name: 'app_trainer_course_view', requirements: ['id' => '\d+'])]
    public function courseView(
        int $id,
        CourseRepository $courseRepository,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $trainer = $this->getUser();
        $course = $courseRepository->find($id);

        if (!$course || $course->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException('Course not found or not assigned to you.');
        }

        $uploadForm = $this->createForm(CourseMaterialType::class);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $file = $uploadForm->get('file')->getData();
            if ($file) {
                $uploadDir = $this->getParameter('materials_upload_dir');

                // Ensure directory exists
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $originalName = $file->getClientOriginalName();
                $safeBase = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
                $storedName = $safeBase . '-' . uniqid() . '.pdf';
                $file->move($uploadDir, $storedName);

                $material = new CourseMaterial();
                $material->setCourse($course);
                $material->setFilename($storedName);
                $material->setOriginalName($originalName);
                $em->persist($material);
                $em->flush();

                $this->addFlash('success', 'PDF uploaded successfully.');
                return $this->redirectToRoute('app_trainer_course_view', ['id' => $id]);
            }
        }

        return $this->render('trainer/course_view.html.twig', [
            'user' => $trainer,
            'course' => $course,
            'uploadForm' => $uploadForm,
        ]);
    }

    #[Route('/courses/{id}/toggle-status', name: 'app_trainer_course_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(int $id, CourseRepository $courseRepository, EntityManagerInterface $em, Request $request): Response
    {
        $trainer = $this->getUser();
        $course = $courseRepository->find($id);

        if (!$course || $course->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('toggle-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $course->setStatus($course->getStatus() === 'active' ? 'inactive' : 'active');
        $em->flush();

        $this->addFlash('success', 'Course status updated.');
        return $this->redirectToRoute('app_trainer_course_view', ['id' => $id]);
    }

    #[Route('/materials/{id}/delete', name: 'app_trainer_material_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function materialDelete(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $material = $em->find(CourseMaterial::class, $id);
        $trainer = $this->getUser();

        if (!$material || $material->getCourse()->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-material-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $courseId = $material->getCourse()->getId();

        // Remove file from disk
        $uploadDir = $this->getParameter('materials_upload_dir');
        $filePath = $uploadDir . '/' . $material->getFilename();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $em->remove($material);
        $em->flush();

        $this->addFlash('success', 'PDF deleted.');
        return $this->redirectToRoute('app_trainer_course_view', ['id' => $courseId]);
    }

    #[Route('/submissions', name: 'app_trainer_submissions')]
    public function submissions(): Response
    {
        return $this->render('trainer/submissions.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/calendar', name: 'app_trainer_calendar')]
    public function calendar(): Response
    {
        return $this->render('trainer/calendar.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
