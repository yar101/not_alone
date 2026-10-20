<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\IdolApplication;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function users(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Имя', 'Email', 'Пол', 'Айдол', 'Рейтинг', 'Дата регистрации', 'Заблокирован'], ';');

            User::select(['id', 'name', 'email', 'gender', 'is_idol', 'rating', 'created_at', 'is_banned'])
                ->orderBy('id')
                ->chunk(500, function ($users) use ($handle) {
                    foreach ($users as $u) {
                        $row = [
                            $u->id,
                            $u->name,
                            $u->email,
                            $u->gender ?? '',
                            $u->is_idol ? 'Да' : 'Нет',
                            $u->rating ?? '',
                            $u->created_at?->format('d.m.Y H:i') ?? '',
                            $u->is_banned ? 'Да' : 'Нет',
                        ];
                        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ]);
    }

    public function applications(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Имя пользователя', 'Email', 'Статус', 'Дата заявки', 'Дата рассмотрения'], ';');

            IdolApplication::with('user')->orderBy('id')->chunk(500, function ($apps) use ($handle) {
                foreach ($apps as $app) {
                    $row = [
                        $app->id,
                        $app->user?->name ?? '',
                        $app->user?->email ?? '',
                        $app->status,
                        $app->created_at?->format('d.m.Y H:i') ?? '',
                        $app->reviewed_at?->format('d.m.Y H:i') ?? '',
                    ];
                    fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="applications.csv"',
        ]);
    }

    public function orders(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Клиент', 'Email клиента', 'Айдол', 'Email айдола', 'Статус', 'Сумма (₽)', 'Создан', 'Завершен'], ';');

            Order::with(['customer', 'idol', 'items.service'])
                ->orderBy('id', 'desc')
                ->chunk(500, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        $row = [
                            $order->id,
                            $order->customer?->name ?? '—',
                            $order->customer?->email ?? '—',
                            $order->idol?->name ?? '—',
                            $order->idol?->email ?? '—',
                            $order->status?->value ?? $order->status,
                            $order->total_price,
                            $order->created_at?->format('d.m.Y H:i') ?? '',
                            $order->completed_at?->format('d.m.Y H:i') ?? '',
                        ];
                        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ]);
    }

    public function transactions(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Пользователь', 'Email', 'Тип', 'Сумма (₽)', 'Баланс до', 'Баланс после', 'Статус', 'Описание', 'Дата'], ';');

            WalletTransaction::with('user')
                ->orderBy('id', 'desc')
                ->chunk(500, function ($transactions) use ($handle) {
                    foreach ($transactions as $tx) {
                        $row = [
                            $tx->id,
                            $tx->user?->name ?? '—',
                            $tx->user?->email ?? '—',
                            $tx->type?->value ?? $tx->type,
                            $tx->amount,
                            $tx->balance_before,
                            $tx->balance_after,
                            $tx->status?->value ?? $tx->status,
                            $tx->description ?? '',
                            $tx->created_at?->format('d.m.Y H:i') ?? '',
                        ];
                        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transactions.csv"',
        ]);
    }

    public function logs(): StreamedResponse
    {
        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Администратор', 'Email админа', 'Действие', 'Тип сущности', 'ID сущности', 'Детали', 'Дата'], ';');

            AdminLog::with('admin')
                ->orderBy('id', 'desc')
                ->chunk(500, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        $row = [
                            $log->id,
                            $log->admin?->name ?? '—',
                            $log->admin?->email ?? '—',
                            $log->action,
                            $log->target_type ?? '',
                            $log->target_id ?? '',
                            $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '',
                            $log->created_at?->format('d.m.Y H:i') ?? '',
                        ];
                        fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row), ';');
                    }
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="admin_logs.csv"',
        ]);
    }

    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 0 && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'".$value;
        }

        return $value;
    }
}
