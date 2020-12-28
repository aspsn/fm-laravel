<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Middleware\IsAdmin;

class DashboardController extends Controller
{
    Public function index()
    {
        return view('dashboard');
    }
}
