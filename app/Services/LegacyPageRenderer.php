<?php

namespace App\Services;

use App\Legacy\ResponseSignal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compatibility boundary for the legacy PHP/Blade page renderer.
 *
 * Legacy templates are kept isolated here while the rest of the application
 * follows Laravel's controller/service boundaries. New pages should use
 * regular Blade views or the frontend entrypoint. Procedural backend code is
 * quarantined under app/Legacy until it can be migrated feature by feature.
 */
final class LegacyPageRenderer
{
    private string $pagesPath;

    public function __construct()
    {
        $this->pagesPath = resource_path('views/pages');
    }

    public function render(Request $request, string $page = 'landing'): Response|RedirectResponse
    {
        $bufferLevel = ob_get_level();
        $previousGet = $_GET;
        $previousPost = $_POST;
        $previousRequest = $_REQUEST;
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $hadPdo = array_key_exists('pdo', $GLOBALS);
        $previousPdo = $GLOBALS['pdo'] ?? null;

        try {
            // Symfony requests (including feature tests) need not populate
            // PHP superglobals. Keep the bridge scoped to this render only.
            $_GET = $request->query->all();
            $_GET['page'] = $page;
            $_POST = $request->request->all();
            $_REQUEST = array_replace($_GET, $_POST);
            $_SERVER['REQUEST_METHOD'] = $request->getMethod();

            return $this->renderPage($request, $page);
        } catch (ResponseSignal $signal) {
            return $signal->response();
        } finally {
            app(SessionLifecycle::class)->persist($request);
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $_GET = $previousGet;
            $_POST = $previousPost;
            $_REQUEST = $previousRequest;
            if ($previousMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousMethod;
            }
            if ($hadPdo) {
                $GLOBALS['pdo'] = $previousPdo;
            } else {
                unset($GLOBALS['pdo']);
            }
        }
    }

    private function renderPage(Request $request, string $page): Response|RedirectResponse
    {
        ob_start();

        // Both the handler and legacy helper functions need this connection.
        // Includes must share this scope; loading core in a separate method
        // loses its local $pdo and causes AJAX requests to return HTTP 500.
        global $pdo;
        require_once app_path('Legacy/core.php');
        $pdo = getPdo();

        if ($request->has('ajax') || $request->has('action')) {
            require app_path('Legacy/ajax_handler.php');

            return response(ob_get_clean());
        }

        require $this->pagesPath.'/layout_header.blade.php';

        if ($page === 'logout') {
            ob_end_clean();

            app(SessionLifecycle::class)->logout($request);

            return redirect('/');
        }

        if (in_array($page, [
            'landing', 'login', 'register', 'forgot-password',
            'verify-otp', 'reset-password', 'presensi-masuk', 'presensi-pulang',
        ], true)) {
            require $this->pagesPath.'/layout_html_header.blade.php';
            require $this->pagesPath.'/'.$this->pageTemplate($page);
        } else {
            if (! isset($_SESSION['user'])) {
                ob_end_clean();

                return redirect('/login');
            }

            require $this->pagesPath.'/layout_html_header.blade.php';
            $template = ($_SESSION['user']['role'] ?? null) === 'admin'
                ? 'admin.blade.php'
                : 'pegawai.blade.php';
            require $this->pagesPath.'/'.$template;
        }

        require $this->pagesPath.'/layout_footer.blade.php';

        return response(ob_get_clean());
    }

    private function pageTemplate(string $page): string
    {
        return $page === 'presensi-masuk' || $page === 'presensi-pulang'
            ? 'presensi.blade.php'
            : $page.'.blade.php';
    }
}
