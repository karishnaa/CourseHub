<?php
namespace App\Controller\Staff;

use App\Model\StaffModel;
use Slim\Views\Twig;
use Psr\Http\Message\RequestInterface  as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;

class StaffController {
    protected $view;
    protected $db;

    public function __construct(Twig $view, \PDO $db) {
        $this->view = $view;
        $this->db   = $db;
    }
    private function getCsrfFields(Request $request): array
    {
        $nameKey  = 'csrf_name';
        $valueKey = 'csrf_value';

        return [
            'name_key'  => $nameKey,
            'value_key' => $valueKey,
            'name'      => $request->getAttribute($nameKey),
            'value'     => $request->getAttribute($valueKey),
        ];
    }
    public function showLoginForm(Request $request, Response $response, array $args)
    {
        return $this->view->render($response, 'staff/staff-login.html.twig', [
            'csrf' => $this->getCsrfFields($request),
            'error' => '',
            'old_input' => [
                'email' => '',
                'remember' => false,
            ],
        ]);
    }








    public function handleLogin(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $staff = StaffModel::getByEmail($this->db, $data['email'] ?? '');

        if ($staff && password_verify($data['password'], $staff['password_hash'])) {
            $_SESSION['staff_id'] = (int)$staff['staff_id'];

            // this will respect whatever basePath you've set in index.php
            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $url = $routeParser->urlFor('staff.dashboard');

            return $response
                ->withHeader('Location', $url)
                ->withStatus(302);
        }

        // on failure, re-render with error
        return $this->view->render($response, 'staff/staff-login.html.twig', [
            'error' => 'Invalid email or password',
            'old_input' => ['email' => $data['email'] ?? '', 'remember' => isset($data['remember'])],
            'csrf' => $this->getCsrfFields($request),
        ]);
    }



    public function dashboard(Request $request, Response $response): Response
    {
        if (empty($_SESSION['staff_id'])) {
            return $response
                ->withHeader('Location', '/staff/login')
                ->withStatus(302);
        }

        $sid = (int) $_SESSION['staff_id'];
        $modules    = StaffModel::getModulesForStaff($this->db, $sid);
        $programmes = StaffModel::getProgrammesForStaff($this->db, $sid);

        return $this->view->render($response, 'staff/staff-dashboard.html.twig', [
            'modules'    => $modules,
            'programmes' => $programmes,
        ]);
    }

    public function viewProgramme(Request $request, Response $response, array $args): Response
    {
        $programmeId = (int)$args['id'];

        $programme = StaffModel::getProgrammeById($this->db, $programmeId);
        if (!$programme) {
            throw new HttpNotFoundException($request, "Programme not found");
        }

        $modules = StaffModel::getModulesForProgramme($this->db, $programmeId);

        return $this->view->render($response, 'staff/staff-programme-view.html.twig', [
            'programme' => $programme,
            'modules' => $modules
        ]);

    }
    public function editProfile(Request $request, Response $response): Response {
        $staffId = $_SESSION['staff_id'];
        $staff = StaffModel::getById($this->db, $staffId);

        return $this->view->render($response, 'staff/staff-profile.html.twig', [
            'staff' => $staff,
            'csrf'  => $this->getCsrfFields($request),
            'success_message' => null // <- 💥 this stops Twig from complaining
        ]);

    }


    public function updateProfile(Request $request, Response $response): Response {
        $data = (array)$request->getParsedBody();
        $staffId = $_SESSION['staff_id'];

        StaffModel::updateProfile($this->db, $staffId, $data);

        // Get updated data to show again
        $staff = StaffModel::getById($this->db, $staffId);

        return $this->view->render($response, 'staff/staff-profile.html.twig', [
            'staff' => $staff,
            'success_message' => 'Profile updated successfully!',
            'csrf' => $this->getCsrfFields($request)
        ]);
    }









}
