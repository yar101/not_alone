<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            abort(403, 'Доступ запрещен.');
        }

        if ($user->trashed()) {
            return back()->withErrors(['impersonate' => 'Невозможно войти под удаленным пользователем.']);
        }

        session(['impersonator_admin_id' => $admin->id]);

        AdminLogService::log(
            $admin->id,
            'impersonate_user',
            'user',
            $user->id,
            ['target_user_name' => $user->name, 'target_user_email' => $user->email]
        );

        auth('web')->login($user);

        return redirect('/')->with('success', "Вы успешно вошли в систему как {$user->name}.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = session('impersonator_admin_id');
        if (! $adminId) {
            return redirect('/');
        }

        $user = auth('web')->user();
        auth('web')->logout();
        session()->forget('impersonator_admin_id');

        if ($user) {
            AdminLogService::log(
                $adminId,
                'leave_impersonation',
                'user',
                $user->id,
                ['user_name' => $user->name]
            );

            return redirect()->route('admin.users.show', $user->id)->with('success', 'Вы вернулись в панель администратора.');
        }

        return redirect()->route('admin.users.index')->with('success', 'Вы вернулись в панель администратора.');
    }
}
