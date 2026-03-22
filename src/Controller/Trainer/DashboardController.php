<?php

namespace App\Controller\Trainer;

use App\Entity\Course;
use App\Entity\CourseMaterial;
use App\Entity\CourseWeek;
use App\Entity\TrainerEvent;
use App\Form\CourseMaterialType;
use App\Form\CourseType;
use App\Repository\AcademicTermRepository;
use App\Repository\CourseRepository;
use App\Repository\CourseWeekRepository;
use App\Repository\TrainerEventRepository;
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
    public function index(CourseRepository $courseRepository, EntityManagerInterface $em, AcademicTermRepository $termRepository): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        $courses = $courseRepository->findBy(['trainer' => $trainer], ['createdAt' => 'DESC']);

        $myStudentsCount = (int) $em->createQuery('
            SELECT COUNT(DISTINCT s.id)
            FROM App\Entity\Course c
            JOIN c.students s
            WHERE c.trainer = :trainer
        ')->setParameter('trainer', $trainer)->getSingleScalarResult();

        return $this->render('trainer/dashboard.html.twig', [
            'user' => $trainer,
            'myCoursesCount' => count($courses),
            'myStudentsCount' => $myStudentsCount,
            'pendingReviewsCount' => 0,
            'courses' => array_slice($courses, 0, 5),
            'currentTerm' => $termRepository->findCurrentTerm(),
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

    #[Route('/courses/new', name: 'app_trainer_courses_new')]
    public function courseNew(
        Request $request,
        EntityManagerInterface $em,
        AcademicTermRepository $termRepository
    ): Response {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        $course = new Course();
        $course->setTrainer($trainer);

        $form = $this->createForm(CourseType::class, $course, [
            'show_trainer_field' => false,
            'allow_term_selection' => false,
        ]);
        $form->handleRequest($request);

        $activeTerm = $termRepository->findCurrentTerm();

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$activeTerm) {
                $this->addFlash('error', 'No active academic term is configured. Please contact the administrator.');
                return $this->redirectToRoute('app_trainer_courses');
            }

            $course->setTerm($activeTerm);
            $em->persist($course);
            $em->flush();

            $this->addFlash('success', sprintf('Course created for %s.', $activeTerm->getDisplayLabel() ?: 'the active term'));

            return $this->redirectToRoute('app_trainer_course_view', ['id' => $course->getId()]);
        }

        return $this->render('trainer/course_form.html.twig', [
            'user' => $trainer,
            'form' => $form,
            'title' => 'Create Course',
            'activeTerm' => $activeTerm,
        ]);
    }

    #[Route('/courses/{id}', name: 'app_trainer_course_view', requirements: ['id' => '\d+'])]
    public function courseView(
        int $id,
        CourseRepository $courseRepository,
        CourseWeekRepository $weekRepository,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $trainer = $this->getUser();
        $course = $courseRepository->find($id);

        if (!$course || $course->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException('Course not found or not assigned to you.');
        }

        // Ensure weeks 1-14 exist for this course
        $weeks = [];
        for ($w = 1; $w <= 14; $w++) {
            $week = $weekRepository->findOneBy(['course' => $course, 'weekNumber' => $w]);
            if (!$week) {
                $week = new CourseWeek();
                $week->setCourse($course)
                     ->setWeekNumber($w)
                     ->setTitle('Week ' . $w);
                $em->persist($week);
            }
            $weeks[$w] = $week;
        }
        $em->flush();

        $uploadForm = $this->createForm(CourseMaterialType::class);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $file    = $uploadForm->get('file')->getData();
            $weekNum = max(1, min(14, (int) $request->request->get('week_number', 1)));

            if ($file) {
                $uploadDir = $this->getParameter('materials_upload_dir');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $originalName = $file->getClientOriginalName();
                $safeBase     = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
                $storedName   = $safeBase . '-' . uniqid() . '.pdf';
                $file->move($uploadDir, $storedName);

                $material = new CourseMaterial();
                $material->setCourse($course)
                         ->setWeek($weeks[$weekNum])
                         ->setFilename($storedName)
                         ->setOriginalName($originalName);
                $em->persist($material);
                $em->flush();

                $this->addFlash('success', 'PDF uploaded to Week ' . $weekNum . ' successfully.');
                return $this->redirectToRoute('app_trainer_course_view', ['id' => $id, '_fragment' => 'week-' . $weekNum]);
            }
        }

        return $this->render('trainer/course_view.html.twig', [
            'user'       => $trainer,
            'course'     => $course,
            'weeks'      => $weeks,
            'uploadForm' => $uploadForm,
        ]);
    }

    #[Route('/courses/{id}/toggle-status', name: 'app_trainer_course_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(int $id, CourseRepository $courseRepository, EntityManagerInterface $em, Request $request): Response
    {
        $trainer = $this->getUser();
        $course  = $courseRepository->find($id);

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
        $trainer  = $this->getUser();

        if (!$material || $material->getCourse()->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-material-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $week = $material->getWeek();
        $weekNum  = $week ? $week->getWeekNumber() : 1;
        $courseId = $material->getCourse()->getId();

        $uploadDir = $this->getParameter('materials_upload_dir');
        $filePath  = $uploadDir . '/' . $material->getFilename();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $em->remove($material);
        $em->flush();

        $this->addFlash('success', 'PDF deleted.');
        return $this->redirectToRoute('app_trainer_course_view', ['id' => $courseId, '_fragment' => 'week-' . $weekNum]);
    }

    #[Route('/weeks/{id}/update', name: 'app_trainer_week_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function weekUpdate(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $week    = $em->find(CourseWeek::class, $id);
        $trainer = $this->getUser();

        if (!$week || $week->getCourse()->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('week-update-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $title = trim($request->request->get('title', ''));
        $week->setTitle($title !== '' ? $title : 'Week ' . $week->getWeekNumber());
        $week->setDescription(trim($request->request->get('description', '')) ?: null);

        $isSubmissionRequired = $request->request->getBoolean('isSubmissionRequired', false);
        $week->setIsSubmissionRequired($isSubmissionRequired);

        $maxFiles = $request->request->getInt('maxFiles', 1);
        $week->setMaxFiles(max(1, $maxFiles));

        $allowedFileTypes = $request->request->all('allowedFileTypes');
        $week->setAllowedFileTypes(is_array($allowedFileTypes) ? $allowedFileTypes : []);

        $em->flush();

        $weekNum  = $week->getWeekNumber();
        $courseId = $week->getCourse()->getId();

        $this->addFlash('success', 'Week ' . $weekNum . ' updated.');
        return $this->redirectToRoute('app_trainer_course_view', ['id' => $courseId, '_fragment' => 'week-' . $weekNum]);
    }

    #[Route('/submissions', name: 'app_trainer_submissions')]
    public function submissions(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        $submissions = $em->createQuery('
            SELECT s 
            FROM App\Entity\StudentSubmission s
            JOIN s.courseWeek w
            JOIN w.course c
            WHERE c.trainer = :trainer
            ORDER BY s.uploadedAt DESC
        ')->setParameter('trainer', $trainer)->getResult();

        return $this->render('trainer/submissions.html.twig', [
            'user' => $trainer,
            'submissions' => $submissions,
        ]);
    }

    #[Route('/submissions/grade', name: 'app_trainer_submission_grade', methods: ['POST'])]
    public function gradeSubmission(Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        $submissionId = $request->request->get('submission_id');
        $grade = $request->request->get('grade');
        $feedback = $request->request->get('feedback');

        $submission = $em->getRepository(\App\Entity\StudentSubmission::class)->find($submissionId);

        if (!$submission || $submission->getCourseWeek()->getCourse()->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException('Invalid submission or access denied.');
        }

        $submission->setGrade((int) $grade);
        $submission->setFeedback($feedback);
        $submission->setStatus('graded');
        
        $em->flush();

        $this->addFlash('success', 'Submission evaluated successfully.');
        
        return $this->redirectToRoute('app_trainer_submissions');
    }

    #[Route('/calendar', name: 'app_trainer_calendar')]
    public function calendar(Request $request, TrainerEventRepository $eventRepository): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        $year = max(2000, min(2100, (int) $request->query->get('year', (int) (new \DateTimeImmutable('now'))->format('Y'))));
        $month = max(1, min(12, (int) $request->query->get('month', (int) (new \DateTimeImmutable('now'))->format('n'))));
        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $calendarStart = $monthStart->modify('-' . $monthStart->format('w') . ' days');
        $calendarEnd = $calendarStart->modify('+41 days')->setTime(23, 59, 59);

        $events = $eventRepository->findForRange($trainer, $calendarStart, $calendarEnd);
        $eventsByDate = [];
        foreach ($events as $event) {
            $startsAt = $event->getStartsAt();
            if (!$startsAt) {
                continue;
            }

            $eventsByDate[$startsAt->format('Y-m-d')][] = $event;
        }

        $days = [];
        $todayKey = (new \DateTimeImmutable('today'))->format('Y-m-d');
        for ($i = 0; $i < 42; $i++) {
            $date = $calendarStart->modify('+' . $i . ' days');
            $dateKey = $date->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'dateKey' => $dateKey,
                'inMonth' => $date->format('n') === $monthStart->format('n'),
                'isToday' => $dateKey === $todayKey,
                'events' => $eventsByDate[$dateKey] ?? [],
            ];
        }

        $prevMonth = $monthStart->modify('-1 month');
        $nextMonth = $monthStart->modify('+1 month');

        return $this->render('trainer/calendar.html.twig', [
            'user' => $trainer,
            'monthLabel' => $monthStart->format('F Y'),
            'currentYear' => (int) $monthStart->format('Y'),
            'currentMonth' => (int) $monthStart->format('n'),
            'prevYear' => (int) $prevMonth->format('Y'),
            'prevMonth' => (int) $prevMonth->format('n'),
            'nextYear' => (int) $nextMonth->format('Y'),
            'nextMonth' => (int) $nextMonth->format('n'),
            'days' => $days,
            'monthEvents' => $events,
        ]);
    }

    #[Route('/calendar/events/create', name: 'app_trainer_calendar_event_create', methods: ['POST'])]
    public function calendarEventCreate(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();

        if (!$this->isCsrfTokenValid('trainer-event-create', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $event = new TrainerEvent();
        $event->setTrainer($trainer);

        if (!$this->applyCalendarEventData($event, $request)) {
            return $this->redirectToRoute('app_trainer_calendar', $this->getCalendarRedirectParams($request));
        }

        $em->persist($event);
        $em->flush();

        $this->addFlash('success', 'Event added to your calendar.');

        return $this->redirectToRoute('app_trainer_calendar', $this->getCalendarRedirectParams($request));
    }

    #[Route('/calendar/events/{id}/edit', name: 'app_trainer_calendar_event_edit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function calendarEventEdit(int $id, Request $request, TrainerEventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();
        $event = $eventRepository->find($id);

        if (!$event || $event->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('trainer-event-edit-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->applyCalendarEventData($event, $request)) {
            return $this->redirectToRoute('app_trainer_calendar', $this->getCalendarRedirectParams($request));
        }

        $em->flush();
        $this->addFlash('success', 'Calendar event updated.');

        return $this->redirectToRoute('app_trainer_calendar', $this->getCalendarRedirectParams($request));
    }

    #[Route('/calendar/events/{id}/delete', name: 'app_trainer_calendar_event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function calendarEventDelete(int $id, Request $request, TrainerEventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $trainer */
        $trainer = $this->getUser();
        $event = $eventRepository->find($id);

        if (!$event || $event->getTrainer() !== $trainer) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('trainer-event-delete-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($event);
        $em->flush();

        $this->addFlash('success', 'Calendar event deleted.');

        return $this->redirectToRoute('app_trainer_calendar', $this->getCalendarRedirectParams($request));
    }

    private function applyCalendarEventData(TrainerEvent $event, Request $request): bool
    {
        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $eventDate = (string) $request->request->get('event_date', '');
        
        $startTime = (string) $request->request->get('start_time', '');
        $endTime = (string) $request->request->get('end_time', '');

        if ($title === '' || $eventDate === '') {
            $this->addFlash('error', 'Title and date are required.');
            return false;
        }

        if ($startTime === '') {
            $startTime = '00:00';
            if ($endTime === '') {
                $endTime = '23:59';
            }
        }

        $startsAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $eventDate . ' ' . $startTime);
        if (!$startsAt) {
            $this->addFlash('error', 'Invalid event date or time.');
            return false;
        }

        $endsAt = null;
        if ($endTime !== '') {
            $endsAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $eventDate . ' ' . $endTime);
            if (!$endsAt) {
                $this->addFlash('error', 'Invalid end time.');
                return false;
            }

            if ($endsAt < $startsAt) {
                $this->addFlash('error', 'End time must be after the start time.');
                return false;
            }
        }

        $event
            ->setTitle($title)
            ->setDescription($description !== '' ? $description : null)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt);

        return true;
    }

    private function getCalendarRedirectParams(Request $request): array
    {
        return [
            'year' => max(2000, min(2100, (int) $request->request->get('year', $request->query->get('year', (int) date('Y'))))),
            'month' => max(1, min(12, (int) $request->request->get('month', $request->query->get('month', (int) date('n'))))),
        ];
    }
}
