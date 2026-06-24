<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomizationController extends Controller
{
    public function index()
    {
        return Inertia::render('Profile/Customization');
    }
}
