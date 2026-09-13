<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LegacyPageRenderer;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(private readonly LegacyPageRenderer $renderer) {}

    public function index(Request $request)
    {
        $request->validate(['page' => 'sometimes|string']);
        $page = $request->query('page', 'landing');

        return $this->renderer->render($request, $page);
    }

    public function page(Request $request, $page = 'landing')
    {
        return $this->renderer->render($request, (string) $page);
    }
}
