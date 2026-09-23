<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
date_default_timezone_set('America/Lima');

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
