<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- AUTHENTICATION ROUTES (Will be handled by Laravel Breeze/UI later) ---
// For now, we will just test the middleware manually.

// --- ROLE-BASED DASHBOARD ROUTES ---

// 1. Super Administrator Routes
Route::middleware(['auth', 'role:super-administrator'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return "<h1>Welcome, Super Admin!</h1>";
    })->name('admin.dashboard');
});

// 2. Accountant Routes
Route::middleware(['auth', 'role:accountant'])->group(function () {
    Route::get('/accountant/dashboard', function () {
        return "<h1>Welcome, Accountant!</h1>";
    })->name('accountant.dashboard');
});

// 3. Teacher Routes
Route::middleware(['auth', 'role:teacher'])->group(function () {
    Route::get('/teacher/dashboard', function () {
        return "<h1>Welcome, Teacher!</h1>";
    })->name('teacher.dashboard');
});

// 4. Student Routes
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/student/dashboard', function () {
        return "<h1>Welcome, Student!</h1>";
    })->name('student.dashboard');
});