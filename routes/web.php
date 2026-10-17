<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\MeetingContentController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/profile', 'profile')->name('profile.show');
    Route::get('/meetings/{meeting}/chat/messages', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/meetings/{meeting}/chat/messages', [ChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.store');
    Route::get('/negotiations', [NegotiationController::class, 'index'])->name('negotiations.index');
    Route::get('/meetings/{meeting}/negotiation', [NegotiationController::class, 'show'])->name('negotiations.show');
    Route::post('/meetings/{meeting}/proposals', [NegotiationController::class, 'store'])->name('negotiations.store');
    Route::patch('/meetings/{meeting}/proposals/{proposal}/accept', [NegotiationController::class, 'accept'])->name('negotiations.accept');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/history', [MeetingController::class, 'index'])->name('history');
    Route::get('/reports/meetings', [MeetingController::class, 'report'])->name('reports.meetings');
    Route::resource('meetings', MeetingController::class)->except('destroy');
    Route::patch('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
    Route::patch('/meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
    Route::delete('/meetings/{meeting}/agenda', [MeetingContentController::class, 'destroyAgenda'])->name('meetings.agenda.destroy');
    Route::put('/meetings/{meeting}/minutes', [MeetingContentController::class, 'minutes'])->name('meetings.minutes');
    Route::patch('/meetings/{meeting}/participants/{participant}/confirmation', [MeetingContentController::class, 'confirmation'])->name('participants.confirmation');
    Route::put('/meetings/{meeting}/participants/{participant}/reminder', [MeetingContentController::class, 'preference'])->name('participants.reminder');
    Route::get('/my-tasks', [FollowUpController::class, 'index'])->name('tasks.index');
    Route::post('/meetings/{meeting}/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('/meetings/{meeting}/follow-ups/{followUp}', [FollowUpController::class, 'update'])->name('follow-ups.update');
    Route::patch('/meetings/{meeting}/follow-ups/{followUp}/status', [FollowUpController::class, 'status'])->name('follow-ups.status');
    Route::delete('/meetings/{meeting}/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('clients', ClientController::class)->except('show');
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::patch('/reminders/{notification}/read', [ReminderController::class, 'read'])->name('reminders.read');
});
