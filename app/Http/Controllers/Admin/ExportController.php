<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdolApplication;
use App\Models\User;
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
                            $u->created_at->format('d.m.Y H:i'),
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
                        $app->created_at->format('d.m.Y H:i'),
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

    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 0 && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'".$value;
        }

        return $value;
    }
}
