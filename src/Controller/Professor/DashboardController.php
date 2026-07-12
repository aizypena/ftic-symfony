<?php

namespace App\Controller\Professor;

use App\Entity\Course;
use App\Entity\CourseMaterial;
use App\Entity\CourseWeek;
use App\Entity\ProfessorEvent;
use App\Form\CourseMaterialType;
use App\Form\CourseType;
use App\Repository\AcademicTermRepository;
use App\Repository\CourseRepository;
use App\Repository\CourseWeekRepository;
use App\Repository\ProfessorEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/professor')]
#[IsGranted('ROLE_PROFESSOR')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_professor_dashboard')]
    public function index(CourseRepository $courseRepository, EntityManagerInterface $em, AcademicTermRepository $termRepository): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        $courses = $courseRepository->findBy(['professor' => $professor], ['createdAt' => 'DESC']);

        $myStudentsCount = (int) $em->createQuery('
            SELECT COUNT(DISTINCT s.id)
            FROM App\Entity\Course c
            JOIN c.students s
            WHERE c.professor = :professor
        ')->setParameter('professor', $professor)->getSingleScalarResult();

        return $this->render('trainer/dashboard.html.twig', [
            'user' => $professor,
            'myCoursesCount' => count($courses),
            'myStudentsCount' => $myStudentsCount,
            'pendingReviewsCount' => 0,
            'courses' => array_slice($courses, 0, 5),
            'currentTerm' => $termRepository->findActiveTerm(),
        ]);
    }

    #[Route('/courses', name: 'app_professor_courses')]
    public function courses(CourseRepository $courseRepository): Response
    {
        $professor = $this->getUser();
        $courses = $courseRepository->findBy(['professor' => $professor], ['createdAt' => 'DESC']);

        return $this->render('trainer/courses.html.twig', [
            'user' => $professor,
            'courses' => $courses,
        ]);
    }

    #[Route('/courses/new', name: 'app_professor_courses_new')]
    public function courseNew(
        Request $request,
        EntityManagerInterface $em,
        AcademicTermRepository $termRepository
    ): Response {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        $course = new Course();
        $course->setProfessor($professor);

        $form = $this->createForm(CourseType::class, $course, [
            'show_professor_field' => false,
            'allow_term_selection' => false,
        ]);
        $form->handleRequest($request);

        $activeTerm = $termRepository->findActiveTerm();

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$activeTerm) {
                $this->addFlash('error', 'No active academic term is configured. Please contact the administrator.');
                return $this->redirectToRoute('app_professor_courses');
            }

            $course->setTerm($activeTerm);
            $em->persist($course);
            $em->flush();

            $this->addFlash('success', sprintf('Course created for %s.', $activeTerm->getDisplayLabel() ?: 'the active term'));

            return $this->redirectToRoute('app_professor_course_view', ['id' => $course->getId()]);
        }

        return $this->render('trainer/course_form.html.twig', [
            'user' => $professor,
            'form' => $form,
            'title' => 'Create Course',
            'activeTerm' => $activeTerm,
        ]);
    }

    #[Route('/courses/{id}', name: 'app_professor_course_view', requirements: ['id' => '\\d+'])]
    public function courseView(
        int $id,
        CourseRepository $courseRepository,
        CourseWeekRepository $weekRepository,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $professor = $this->getUser();
        $course = $courseRepository->find($id);

        if (!$course || $course->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException('Course not found or not assigned to you.');
        }

        // Ensure weeks 1-14 exist for this course
        $weeksByNumber = [];
        for ($w = 1; $w <= 14; $w++) {
            $week = $weekRepository->findOneBy(['course' => $course, 'weekNumber' => $w]);
            if (!$week) {
                $week = new CourseWeek();
                $week->setCourse($course)
                     ->setWeekNumber($w)
                     ->setDisplayOrder($w)
                     ->setTitle('Week ' . $w);
                $em->persist($week);
            }
            $weeksByNumber[$w] = $week;
        }
        $em->flush();

        $orderedWeeks = $weekRepository->findBy(
            ['course' => $course],
            ['displayOrder' => 'ASC', 'weekNumber' => 'ASC']
        );
        $orderedMaterials = $this->getOrderedMaterialsForCourse($course);

        $materialRanks = [];
        foreach ($orderedMaterials as $index => $orderedMaterial) {
            $materialRanks[$orderedMaterial->getId()] = $index;
        }

        $materialPrerequisiteChoices = [];
        foreach ($orderedMaterials as $materialItem) {
            $materialId = (int) $materialItem->getId();
            $materialPrerequisiteChoices[$materialId] = [];

            foreach ($orderedMaterials as $candidate) {
                if ($materialRanks[$candidate->getId()] < $materialRanks[$materialId]) {
                    $materialPrerequisiteChoices[$materialId][] = $candidate;
                }
            }
        }

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
                
                // ── FIX 1: Dynamically extract real extension instead of forcing .pdf ──
                $extension    = $file->guessExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION);
                $storedName   = $safeBase . '-' . uniqid() . '.' . strtolower($extension);
                
                $file->move($uploadDir, $storedName);

                $material = new CourseMaterial();

                $maxOrder = (int) $em->createQuery('SELECT COALESCE(MAX(m.displayOrder), 0) FROM App\\Entity\\CourseMaterial m WHERE m.week = :week')
                    ->setParameter('week', $weeksByNumber[$weekNum])
                    ->getSingleScalarResult();
                $nextOrder = $maxOrder + 1;

                $material->setCourse($course)
                         ->setWeek($weeksByNumber[$weekNum])
                         ->setDisplayOrder($nextOrder)
                         ->setFilename($storedName)
                         ->setOriginalName($originalName);
                $em->persist($material);
                $em->flush();

                // ── FIX 2: Dynamic confirmation notice messages ──
                $isVideo = in_array(strtolower($extension), ['mp4', 'webm', 'mov', 'mkv', 'avi']);
                $fileTypeName = $isVideo ? 'Video' : 'Material';
                
                $this->addFlash('success', $fileTypeName . ' uploaded to Week ' . $weekNum . ' successfully.');
                return $this->redirectToRoute('app_professor_course_view', ['id' => $id, '_fragment' => 'week-' . $weekNum]);
            }
        }

        return $this->render('trainer/course_view.html.twig', [
            'user'       => $professor,
            'course'     => $course,
            'weeks'      => $weeksByNumber,
            'orderedWeeks' => $orderedWeeks,
            'materialPrerequisiteChoices' => $materialPrerequisiteChoices,
            'uploadForm' => $uploadForm,
        ]);
    }

    #[Route('/courses/{id}/weeks/reorder', name: 'app_professor_course_weeks_reorder', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function reorderWeeks(int $id, Request $request, CourseRepository $courseRepository, CourseWeekRepository $weekRepository, EntityManagerInterface $em): JsonResponse
    {
        $professor = $this->getUser();
        $course = $courseRepository->find($id);

        if (!$course || $course->getProfessor() !== $professor) {
            return $this->json(['error' => 'Course not found.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['token'] ?? '');
        if (!$this->isCsrfTokenValid('reorder-weeks-' . $course->getId(), $token)) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $orderedWeekIds = $payload['orderedWeekIds'] ?? null;
        if (!is_array($orderedWeekIds) || $orderedWeekIds === []) {
            return $this->json(['error' => 'No order provided.'], Response::HTTP_BAD_REQUEST);
        }

        $courseWeeks = $weekRepository->findBy(['course' => $course]);
        $courseWeekIds = array_map(static fn (CourseWeek $week) => $week->getId(), $courseWeeks);
        sort($courseWeekIds);

        $normalizedIds = array_values(array_map('intval', $orderedWeekIds));
        sort($normalizedIds);

        if ($normalizedIds !== $courseWeekIds) {
            return $this->json(['error' => 'Order list does not match course modules.'], Response::HTTP_BAD_REQUEST);
        }

        $weekById = [];
        foreach ($courseWeeks as $week) {
            $weekById[$week->getId()] = $week;
        }

        $position = 1;
        foreach ($orderedWeekIds as $weekId) {
            $weekId = (int) $weekId;
            if (!isset($weekById[$weekId])) {
                return $this->json(['error' => 'Invalid module id.'], Response::HTTP_BAD_REQUEST);
            }

            $weekById[$weekId]->setDisplayOrder($position);
            $position++;
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/weeks/{id}/materials/reorder', name: 'app_professor_week_materials_reorder', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function reorderWeekMaterials(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $professor = $this->getUser();
        $week = $em->find(CourseWeek::class, $id);

        if (!$week || $week->getCourse()->getProfessor() !== $professor) {
            return $this->json(['error' => 'Week not found.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['token'] ?? '');
        if (!$this->isCsrfTokenValid('reorder-materials-week-' . $week->getId(), $token)) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $orderedMaterialIds = $payload['orderedMaterialIds'] ?? null;
        if (!is_array($orderedMaterialIds) || $orderedMaterialIds === []) {
            return $this->json(['error' => 'No order provided.'], Response::HTTP_BAD_REQUEST);
        }

        $materials = $em->getRepository(CourseMaterial::class)->findBy(
            ['week' => $week],
            ['displayOrder' => 'ASC', 'uploadedAt' => 'ASC']
        );

        $materialIds = array_map(static fn (CourseMaterial $material) => $material->getId(), $materials);
        sort($materialIds);

        $normalizedIds = array_values(array_map('intval', $orderedMaterialIds));
        sort($normalizedIds);

        if ($normalizedIds !== $materialIds) {
            return $this->json(['error' => 'Order list does not match modules.'], Response::HTTP_BAD_REQUEST);
        }

        $materialById = [];
        foreach ($materials as $material) {
            $materialById[$material->getId()] = $material;
        }

        $position = 1;
        foreach ($orderedMaterialIds as $materialId) {
            $materialId = (int) $materialId;
            if (!isset($materialById[$materialId])) {
                return $this->json(['error' => 'Invalid module id.'], Response::HTTP_BAD_REQUEST);
            }

            $materialById[$materialId]->setDisplayOrder($position);
            $position++;
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/courses/{id}/toggle-status', name: 'app_professor_course_toggle', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function toggleStatus(int $id, CourseRepository $courseRepository, EntityManagerInterface $em, Request $request): Response
    {
        $professor = $this->getUser();
        $course  = $courseRepository->find($id);

        if (!$course || $course->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('toggle-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $course->setStatus($course->getStatus() === 'active' ? 'inactive' : 'active');
        $em->flush();

        $this->addFlash('success', 'Course status updated.');
        return $this->redirectToRoute('app_professor_course_view', ['id' => $id]);
    }

    #[Route('/materials/{id}/delete', name: 'app_professor_material_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function materialDelete(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $material = $em->find(CourseMaterial::class, $id);
        $professor  = $this->getUser();

        if (!$material || $material->getCourse()->getProfessor() !== $professor) {
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
        return $this->redirectToRoute('app_professor_course_view', ['id' => $courseId, '_fragment' => 'week-' . $weekNum]);
    }

    #[Route('/materials/{id}/prerequisite', name: 'app_professor_material_prerequisite_update', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function materialPrerequisiteUpdate(int $id, EntityManagerInterface $em, Request $request): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();
        $material = $em->find(CourseMaterial::class, $id);

        if (!$material || $material->getCourse()->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('material-prerequisite-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $course = $material->getCourse();
        $selectedPrerequisiteId = max(0, (int) $request->request->get('prerequisite_id', 0));

        if ($selectedPrerequisiteId === 0) {
            $material->setPrerequisiteMaterial(null);
            $em->flush();

            $this->addFlash('success', 'Module prerequisite cleared.');

            $week = $material->getWeek();
            $weekNumber = $week ? $week->getWeekNumber() : 1;

            return $this->redirectToRoute('app_professor_course_view', [
                'id' => $course->getId(),
                '_fragment' => 'week-' . $weekNumber,
            ]);
        }

        $selectedPrerequisite = $em->find(CourseMaterial::class, $selectedPrerequisiteId);
        if (!$selectedPrerequisite || $selectedPrerequisite->getCourse() !== $course) {
            $this->addFlash('error', 'Invalid prerequisite module selected.');

            $week = $material->getWeek();
            $weekNumber = $week ? $week->getWeekNumber() : 1;

            return $this->redirectToRoute('app_professor_course_view', [
                'id' => $course->getId(),
                '_fragment' => 'week-' . $weekNumber,
            ]);
        }

        $orderedMaterials = $this->getOrderedMaterialsForCourse($course);
        $materialRanks = [];
        foreach ($orderedMaterials as $index => $orderedMaterial) {
            $materialRanks[$orderedMaterial->getId()] = $index;
        }

        $materialRank = $materialRanks[$material->getId()] ?? null;
        $selectedPrerequisiteRank = $materialRanks[$selectedPrerequisite->getId()] ?? null;

        if ($materialRank === null || $selectedPrerequisiteRank === null || $selectedPrerequisiteRank >= $materialRank) {
            $this->addFlash('error', 'Prerequisite must come before this module in course order.');

            $week = $material->getWeek();
            $weekNumber = $week ? $week->getWeekNumber() : 1;

            return $this->redirectToRoute('app_professor_course_view', [
                'id' => $course->getId(),
                '_fragment' => 'week-' . $weekNumber,
            ]);
        }

        $material->setPrerequisiteMaterial($selectedPrerequisite);
        $em->flush();

        $this->addFlash('success', 'Module prerequisite updated.');

        $week = $material->getWeek();
        $weekNumber = $week ? $week->getWeekNumber() : 1;

        return $this->redirectToRoute('app_professor_course_view', [
            'id' => $course->getId(),
            '_fragment' => 'week-' . $weekNumber,
        ]);
    }

    #[Route('/weeks/{id}/update', name: 'app_professor_week_update', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function weekUpdate(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $week    = $em->find(CourseWeek::class, $id);
        $professor = $this->getUser();

        if (!$week || $week->getCourse()->getProfessor() !== $professor) {
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
        return $this->redirectToRoute('app_professor_course_view', ['id' => $courseId, '_fragment' => 'week-' . $weekNum]);
    }

    #[Route('/submissions', name: 'app_professor_submissions')]
    public function submissions(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        $submissions = $em->createQuery('
            SELECT s
            FROM App\Entity\StudentSubmission s
            JOIN s.courseWeek w
            JOIN w.course c
            WHERE c.professor = :professor
            ORDER BY s.uploadedAt DESC
        ')->setParameter('professor', $professor)->getResult();

        return $this->render('trainer/submissions.html.twig', [
            'user' => $professor,
            'submissions' => $submissions,
        ]);
    }

    #[Route('/submissions/grade', name: 'app_professor_submission_grade', methods: ['POST'])]
    public function gradeSubmission(Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        $submissionId = $request->request->get('submission_id');
        $grade = $request->request->get('grade');
        $feedback = $request->request->get('feedback');

        $submission = $em->getRepository(\App\Entity\StudentSubmission::class)->find($submissionId);

        if (!$submission || $submission->getCourseWeek()->getCourse()->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException('Invalid submission or access denied.');
        }

        $submission->setGrade((int) $grade);
        $submission->setFeedback($feedback);
        $submission->setStatus('graded');

        $em->flush();

        $this->addFlash('success', 'Submission evaluated successfully.');

        return $this->redirectToRoute('app_professor_submissions');
    }

    #[Route('/calendar', name: 'app_professor_calendar')]
    public function calendar(Request $request, ProfessorEventRepository $eventRepository): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        $year = max(2000, min(2100, (int) $request->query->get('year', (int) (new \DateTimeImmutable('now'))->format('Y'))));
        $month = max(1, min(12, (int) $request->query->get('month', (int) (new \DateTimeImmutable('now'))->format('n'))));
        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $calendarStart = $monthStart->modify('-' . $monthStart->format('w') . ' days');
        $calendarEnd = $calendarStart->modify('+41 days')->setTime(23, 59, 59);

        $events = $eventRepository->findForRange($professor, $calendarStart, $calendarEnd);
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
            'user' => $professor,
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

    #[Route('/calendar/events/create', name: 'app_professor_calendar_event_create', methods: ['POST'])]
    public function calendarEventCreate(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();

        if (!$this->isCsrfTokenValid('professor-event-create', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $event = new ProfessorEvent();
        $event->setProfessor($professor);

        if (!$this->applyCalendarEventData($event, $request)) {
            return $this->redirectToRoute('app_professor_calendar', $this->getCalendarRedirectParams($request));
        }

        $em->persist($event);
        $em->flush();

        $this->addFlash('success', 'Event added to your calendar.');

        return $this->redirectToRoute('app_professor_calendar', $this->getCalendarRedirectParams($request));
    }

    #[Route('/calendar/events/{id}/edit', name: 'app_professor_calendar_event_edit', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function calendarEventEdit(int $id, Request $request, ProfessorEventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();
        $event = $eventRepository->find($id);

        if (!$event || $event->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('professor-event-edit-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->applyCalendarEventData($event, $request)) {
            return $this->redirectToRoute('app_professor_calendar', $this->getCalendarRedirectParams($request));
        }

        $em->flush();
        $this->addFlash('success', 'Calendar event updated.');

        return $this->redirectToRoute('app_professor_calendar', $this->getCalendarRedirectParams($request));
    }

    #[Route('/calendar/events/{id}/delete', name: 'app_professor_calendar_event_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function calendarEventDelete(int $id, Request $request, ProfessorEventRepository $eventRepository, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $professor */
        $professor = $this->getUser();
        $event = $eventRepository->find($id);

        if (!$event || $event->getProfessor() !== $professor) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('professor-event-delete-' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($event);
        $em->flush();

        $this->addFlash('success', 'Calendar event deleted.');

        return $this->redirectToRoute('app_professor_calendar', $this->getCalendarRedirectParams($request));
    }

    private function applyCalendarEventData(ProfessorEvent $event, Request $request): bool
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

    /**
     * @return list<CourseMaterial>
     */
    private function getOrderedMaterialsForCourse(Course $course): array
    {
        $orderedMaterials = [];

        foreach ($course->getWeeks() as $week) {
            foreach ($week->getMaterials() as $material) {
                $orderedMaterials[] = $material;
            }
        }

        return $orderedMaterials;
    }
}
