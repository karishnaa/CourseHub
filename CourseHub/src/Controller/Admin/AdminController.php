<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;
use App\Model\ProgrammeModel;
use App\Model\InterestModel;
use App\Model\RegisterModel;
use App\Middleware\SessionMiddleware;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Csrf\Guard;
use App\Model\AdminModel;
use App\Model\StudentInterestModel;
use Slim\Routing\RouteContext;




class AdminController
{
    public function __construct(
        private PDO    $db,
        private Twig   $view,
        private Guard  $csrf,
        private \Slim\App $app // <-- Use Slim\App here
    ) {}



    public function loginPage(Request $request, Response $response): Response
    {
        $this->clearAdminSession();
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        // Always define both keys so Twig never trips on missing ones:
        $oldInputRaw = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
        $oldInput = array_merge(
            ['username' => '', 'remember' => false],
            $oldInputRaw
        );

        return $this->view->render($response, 'admin/admin-login.html.twig', [
            'error_message' => $error,
            'csrf'          => $this->getCsrfFields(),
            'old_input'     => $oldInput,
        ]);

    }


    public function handleLogin(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        // 1) Required fields
        if (!$this->validateLoginInput($data)) {
            return $this->redirectToLogin(
                $response,
                'Username and password are required',
                $data
            );
        }

        // 2) CSRF
        $nameKey  = $this->csrf->getTokenNameKey();
        $valueKey = $this->csrf->getTokenValueKey();
        if (!$this->csrf->validateToken(
            $data[$nameKey]  ?? '',
            $data[$valueKey] ?? ''
        )) {
            return $this->redirectToLogin(
                $response,
                'Invalid security token',
                $data
            );
        }

        // 3) Credentials
        $user = $this->getAdminUser($data['username']);
        if (!$this->validateUserCredentials($user, $data['password'])) {
            return $this->view->render($response, 'admin/admin-login.html.twig', [
                'error_message' => 'Incorrect Username or Password',
                'csrf' => $this->getCsrfFields(),
                'old_input' => [
                    'username' => $data['username'] ?? '',
                    'remember' => !empty($data['remember']),
                ],
            ]);

        }

        // 4) Success!
        $this->createAdminSession($user);
        return $this->redirectToDashboard($response);
    }
    private function getAdminUser(string $username): ?array
    {
        // Fetch via your AdminModel
        return AdminModel::getAdminByUsername($this->db, $username);
    }


    public function dashboard(Request $request, Response $response): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        $stats = $this->getDashboardStats();
        $currentAdmin = $_SESSION[SessionMiddleware::ADMIN_SESSION_KEY]['username'] ?? '';

        return $this->view->render($response, 'admin/admin-dashboard.html.twig', array_merge(
            $stats,
            ['currentAdmin' => $currentAdmin]
        ));
    }



    private function getDashboardStats(): array
    {
        $adminId = $_SESSION['admin']['id'] ?? null;
        // 1) Totals
        $totalStudents  = AdminModel::getTotalInterestedStudents($this->db);
        $totalPrograms  = AdminModel::getTotalProgrammes($this->db);
        $totalInterests = AdminModel::getTotalInterests($this->db);

        // 2) Breakdown stats for chart & table
        $stats = AdminModel::getInterestCounts($this->db);
        // $stats is an array of ['program_name'=>..., 'total'=>...]

        return [
            'adminId' => $adminId,
            'totalStudents'  => $totalStudents,
            'totalPrograms'  => $totalPrograms,
            'totalInterests' => $totalInterests,
            'stats'          => $stats,
        ];
    }




    private function clearAdminSession(): void
    {
        unset($_SESSION[SessionMiddleware::ADMIN_SESSION_KEY]);
    }

    private function validateLoginInput(array $data): bool
    {
        return !empty($data['username']) && !empty($data['password']);
    }



    private function validateUserCredentials(?array $user, string $password): bool
    {
        return $user && password_verify($password, $user['password_hash']);
    }

    private function createAdminSession(array $user): void
    {
        $_SESSION[SessionMiddleware::ADMIN_SESSION_KEY] = [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'logged_in_at' => time(),
            'ip_address'   => $_SERVER['REMOTE_ADDR']    ?? '',
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
    }

    private function redirectToLogin(Response $response, string $message = null, array $data = []): Response
    {
        if ($message) {
            $_SESSION['login_error'] = $message;
        }

        // Preserve only what you want the user to re-enter
        $_SESSION['old_input'] = [
            'username' => $data['username'] ?? '',
            'remember' => !empty($data['remember']),
        ];

        return $response
            ->withHeader('Location', '/admin/login')
            ->withStatus(302);
    }

    private function redirectToDashboard(Response $response): Response
    {
        return $response
            ->withHeader('Location', '/CourseHub/public/admin/dashboard')
            ->withStatus(302);
    }




    private function getCsrfFields(): array
    {
        return [
            'name_key'  => $this->csrf->getTokenNameKey(),
            'value_key' => $this->csrf->getTokenValueKey(),
            'name'      => $this->csrf->getTokenName(),
            'value'     => $this->csrf->getTokenValue(),
        ];
    }


    private function ensureAdminIsAuthenticated(Request $request): void
    {
        $session = $_SESSION[SessionMiddleware::ADMIN_SESSION_KEY] ?? null;
        if (
            !$session
            || $session['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')
            || $session['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
        ) {
            throw new HttpUnauthorizedException($request);
        }
    }
    public function managePrograms(Request $request, Response $response): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        // cast the 'edit' query param to int before passing to the model
        $queryParams = $request->getQueryParams();
        $edit_id     = isset($queryParams['edit']) ? (int)$queryParams['edit'] : null;
        $edit_program = $edit_id
            ? AdminModel::getProgramById($this->db, $edit_id)
            : null;

        $programs = AdminModel::getProgrammes($this->db);

        return $this->view->render($response, 'admin/manage-programs.html.twig', [
            'programs'     => $programs,
            'edit_program' => $edit_program,
            'edit_id'      => $edit_id,
            'csrf'         => $this->getCsrfFields()
        ]);
    }




    public function processAddProgramme(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $level = $data['level'] ?? '';
        $imageUrl = $data['image_url'] ?? null;

        error_log("⚠️ Adding Program w/ URL image");
        error_log("POST data: " . print_r($data, true));

        // Save to DB with URL directly
        $result = AdminModel::addProgram($this->db, $name, $description, $level, $imageUrl);
        error_log("📥 DB Insert result: " . ($result ? 'Success' : 'Fail'));

        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.programmes.list');
        return $response->withHeader('Location', $url)->withStatus(302);
    }




    public function processEditProgramme(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $level = trim($data['level'] ?? '');
        $imageUrl = trim($data['image_url'] ?? '');

        if (empty($name) || empty($level)) {
            // you can add error handling here if needed
            return $response->withStatus(400)->write('Name and level are required.');
        }

        AdminModel::updateProgram($this->db, $id, $name, $description, $level, $imageUrl);

        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.programmes.list');
        return $response->withHeader('Location', $url)->withStatus(302);
    }


    public function deleteProgramme(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        // Delete the program (your existing logic)
        AdminModel::deleteProgram($this->db, $id);

        // Get the route parser from the request
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        // Generate URL for redirect
        $url = $routeParser->urlFor('admin.programmes.list');

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }


    public function manageModules(Request $request, Response $response): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        // Fetch all programmes for the dropdown/select
        $programmes = AdminModel::getProgrammes($this->db);

        $queryParams = $request->getQueryParams();
        $selectedProgrammeId = $queryParams['programme_id'] ?? null;
        $editId = isset($queryParams['edit']) ? (int)$queryParams['edit'] : null;


        $selectedProgramme = null;
        $modules = [];
        $editModule = null;

        if ($selectedProgrammeId) {
            $selectedProgramme = AdminModel::getProgrammeById($this->db, (int)$selectedProgrammeId);
            $modules = AdminModel::getModulesByProgrammeId($this->db, (int)$selectedProgrammeId);
        }

        if ($editId) {
            $editModule = AdminModel::getModuleById($this->db, (int)$editId);
        }

        // Fetch staff list for module leader dropdown
        $staffList = AdminModel::fetchAllStaff($this->db);

        return $this->view->render($response, 'admin/manage-modules.html.twig', [
            'programmes' => $programmes,
            'selectedProgramme' => $selectedProgramme,
            'modules' => $modules,
            'editModule' => $editModule,
            'editId' => $editId,
            'staffList' => $staffList,
            'csrf' => $this->getCsrfFields(),
            'success_message' => $_SESSION['success_message'] ?? null,
            'error_message' => $_SESSION['error_message'] ?? null,
        ]);
    }

    // Method for adding a new module
    // In AdminController.php
    public function processAddModule(Request $request, Response $response): Response
    {
        // Get POST data
        $moduleName = $request->getParsedBody()['name'] ?? null;
        $moduleDescription = $request->getParsedBody()['description'] ?? null;
        $moduleYear = (int)($request->getParsedBody()['year'] ?? 0);
        $moduleLeaderId = $request->getParsedBody()['module_leader_id'] ?? null;
        $programmeId = (int)($request->getParsedBody()['programme_id'] ?? 0);

        // Insert the module into the database
        $moduleId = AdminModel::addModule($this->db, $moduleName, $moduleDescription, $moduleYear);

        // Link the module to the selected programme
        AdminModel::linkModuleToProgramme($this->db, $moduleId, $programmeId);

        // Optionally, link the module leader
        if ($moduleLeaderId) {
            AdminModel::assignModuleLeader($this->db, $moduleId, $moduleLeaderId);
        }

        // Set success message in session
        $_SESSION['success_message'] = "Module successfully added!";

        // Use the App instance to get the URL
        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('admin.modules.list', [], ['programme_id' => $programmeId]);

        // Redirect to the module list page for the selected programme
        return $response->withHeader('Location', $url)
            ->withStatus(302); // Temporary redirect
    }




    // Method for editing an existing module
    public function processEditModule(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $year = isset($data['year']) ? (int)$data['year'] : 1;  // <== FIXED HERE
        $leaderId = $data['module_leader_id'] !== '' ? (int)$data['module_leader_id'] : null;

        AdminModel::updateModule($this->db, $id, $name, $description, $year, $leaderId);

        $programmeId = $data['programme_id'] ?? null;
        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $programmeId
            ? $routeParser->urlFor('admin.modules.list', [], ['programme_id' => $programmeId])
            : $routeParser->urlFor('admin.modules.list');

        return $response->withHeader('Location', $url)->withStatus(302);
    }




    // Method for deleting a module
    public function deleteModule(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        // Delete it from DB
        AdminModel::deleteModule($this->db, $id);

        // Redirect back to module list with programme_id in query
        $programmeId = $request->getQueryParams()['programme_id'] ?? null;

        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $programmeId
            ? $routeParser->urlFor('admin.modules.list', [], ['programme_id' => $programmeId])
            : $routeParser->urlFor('admin.modules.list');

        return $response->withHeader('Location', $url)->withStatus(302);
    }








    public function showStaffList(Request $request, Response $response): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        $staff = AdminModel::fetchAllStaff($this->db);

        $editId = $request->getQueryParams()['edit'] ?? null;

        return $this->view->render($response, 'admin/manage-staff.html.twig', [
            'staff' => $staff,
            'csrf' => $this->getCsrfFields(),
            'editId' => $editId,
            'request' => $request,
        ]);
    }


    public function showAddStaffForm(Request $request, Response $response): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        return $this->view->render($response, 'admin/add-staff.html.twig', [
            'csrf' => $this->getCsrfFields(),
        ]);
    }

    public function processAddStaff(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        error_log('processAddStaff called');
        error_log(print_r($data, true));

        // Set the password to 'password' and hash it
        $plainPassword = 'password'; // hardcoded default


        $success = AdminModel::addStaff(
            $this->db,
            $data['name'],
            $data['email'],
            $data['role'],
            $plainPassword // this is the fifth argument!
        );
        if (!$success) {
            error_log('Failed to add staff');
            // Add error handling here
        }

        return $response->withHeader('Location', '/CourseHub/public/admin/staff')->withStatus(302);
    }




    public function processEditStaff(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();

        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? 'module_leader';

        AdminModel::updateStaff($this->db, $id, $name, $email, $role);

        // 💡 Fix: Get routeParser from request context
        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('admin.staff.list');

        return $response->withHeader('Location', $url)->withStatus(302);
    }



    public function deleteStaff(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        AdminModel::deleteStaff($this->db, $id);

        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('admin.staff.list');

        // Redirect back to staff list
        return $response->withHeader('Location', $url)->withStatus(302);
    }



    public function viewStudentInterest(Request $request, Response $response): Response
    {
        $interests = AdminModel::getInterestedStudents($this->db);
        $stats = AdminModel::getInterestCounts($this->db);
        $programmes = AdminModel::getAllProgrammes($this->db);

        return $this->view->render($response, 'admin/manage-student-interests.html.twig', [
            'interests' => $interests,
            'stats' => $stats,
            'programmes' => $programmes,
            'csrf' => $this->getCsrfFields()
        ]);

    }


    public function deleteStudentInterest(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        AdminModel::deleteStudentInterestById($this->db, $id);

        $routeParser = \Slim\Routing\RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor('admin.interests.list');

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }




    public function exportMailingList(Request $request, Response $response, array $args): Response
    {
        $programmeId = (int) $args['programmeId'];

        $students = AdminModel::getInterestedStudentsByProgramme($this->db, $programmeId);

        $csvFile = fopen('php://temp', 'r+');
        fputcsv($csvFile, ['Student Name', 'Email', 'Programme', 'Date Registered']);

        foreach ($students as $student) {
            fputcsv($csvFile, [
                $student['student_name'],
                $student['email'],
                $student['program_name'],
                $student['created_at'],
            ]);
        }

        rewind($csvFile);
        $csvContent = stream_get_contents($csvFile);
        fclose($csvFile);

        $response->getBody()->write($csvContent);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="programme_' . $programmeId . '_interests.csv"');
    }

    public function exportAllInterests(Request $request, Response $response): Response
    {
        $interests = AdminModel::getInterestedStudents($this->db);

        // Convert $interests to CSV
        $csvData = "ID,Student Name,Email,Programme,Date Registered\n";

        foreach ($interests as $row) {
            $csvData .= "{$row['id']},\"{$row['student_name']}\",\"{$row['email']}\",\"{$row['program_name']}\",{$row['created_at']}\n";
        }

        $response->getBody()->write($csvData);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="student_interests.csv"');
    }
    public function togglePublish(Request $request, Response $response, array $args): Response
    {
        $this->ensureAdminIsAuthenticated($request);

        $programmeId = (int)$args['id'];
        $data        = (array)$request->getParsedBody();
        $publish     = ($data['action_type'] ?? '') === 'publish';

        AdminModel::setProgrammePublished($this->db, $programmeId, $publish);

        // grab the Slim route parser from the current request
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url         = $routeParser->urlFor('admin.programmes.list');

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }





























}
