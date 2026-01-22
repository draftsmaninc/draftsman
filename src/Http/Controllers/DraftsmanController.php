<?php

namespace Draftsman\Draftsman\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DraftsmanController extends Controller
{
    protected $index_file = 'resources/front/index.html';

    protected $front_dir = 'resources/front/';

    protected $package_root_path = '/../../../';

    /**
     * Display the front index page.
     */
    public function index()
    {
        $file = __DIR__.$this->osSafe($this->package_root_path.$this->index_file);
        $front = file_get_contents($file);

        return $front;
    }

    /**
     * Pass the front end stuff resources.
     */
    public function front(Request $request)
    {
        $uri = $request->path();
        $prefix = 'draftsman/';
        if (substr($uri, 0, strlen($prefix)) === $prefix) {
            $uri = $this->front_dir.substr($uri, strlen($prefix));
            $file = __DIR__.$this->osSafe($this->package_root_path.$uri);
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $mime = mime_content_type($file);
                if ($mime === 'text/plain') {
                    switch ($ext) {
                        case 'css':
                            $mime = 'text/css';
                            break;
                        case 'html':
                            $mime = 'text/html';
                            break;
                        case 'js':
                            $mime = 'application/javascript';
                            break;
                        case 'json':
                            $mime = 'application/json';
                            break;
                        case 'svg':
                            $mime = 'image/svg+xml';
                            break;
                    }
                }

                return response()->file($file, ['Content-Type' => $mime]);
            }
            abort(404);
        }
    }

    protected function osSafe(string $path)
    {
        return implode(DIRECTORY_SEPARATOR, explode('/', $path));
    }
}
