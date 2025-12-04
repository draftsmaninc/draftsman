<?php

namespace Draftsman\Draftsman\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DraftsmanController extends Controller
{
    protected $index_file = 'resources/front/index.html';
    protected $package_root_path = '/../../../';

    /**
     * Display the front index page.
     */
    public function index()
    {
        $file = __DIR__.$this->package_root_path.$this->index_file;
        $front = file_get_contents($file);
        $pattern = '/(href|src)=(["\'])(\/)(_next)\//im';
        $replacement = '/$1=$2$3draftsman$3$4/';
        $front = preg_replace($pattern, $replacement, $front);
        return $front;
    }
}
