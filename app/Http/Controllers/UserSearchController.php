<?php

namespace App\Http\Controllers;

use App\Models\InterestCategory;
use App\Models\PersonalityTrait;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()->where('is_banned', false);

        // Текстовые фильтры
        if ($v = $request->name)  $query->where('name',  'ilike', "%{$v}%");

        // Пол
        if ($v = $request->gender) $query->where('gender', $v);

        // Возраст (через birth_date)
        if ($v = $request->age_from)
            $query->whereDate('birth_date', '<=', now()->subYears((int)$v));
        if ($v = $request->age_to)
            $query->whereDate('birth_date', '>=', now()->subYears((int)$v + 1)->addDay());

        // Айдол
        if ($request->filled('is_idol') && $request->is_idol !== '')
            $query->where('is_idol', (bool)(int)$request->is_idol);

        // Рейтинг
        if ($v = $request->rating_from) $query->where('rating', '>=', (int)$v);
        if ($v = $request->rating_to)   $query->where('rating', '<=', (int)$v);

        // Черты (OR)
        if ($v = $request->traits)
            $query->whereHas('traits', fn($q) => $q->whereIn('traits.id', (array)$v));

        // Интересы (OR)
        if ($v = $request->interests)
            $query->whereHas('interests', fn($q) => $q->whereIn('interests.id', (array)$v));

        // Языки (OR)
        if ($v = $request->languages)
            $query->whereHas('languages', fn($q) => $q->whereIn('language_code', (array)$v));

        // Часовой пояс
        if ($v = $request->timezone) $query->where('timezone', $v);

        // Категории услуг
        if ($v = $request->service_categories)
            $query->whereHas('services', fn($q) => $q
                ->whereIn('category_id', (array)$v)
                ->where('status', 'approved')
                ->where('is_active', true)
            );

        // Сортировка
        $sortBy  = in_array($request->sort_by, ['rating', 'created_at']) ? $request->sort_by : 'rating';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $users = $query
            ->select(['id','name','avatar_path','gender','birth_date','is_idol','rating','about','timezone','created_at'])
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Search/Index', [
            'users'              => $users,
            'traits'             => PersonalityTrait::orderBy('sort_order')->get(['id','name_ru']),
            'interestCategories' => InterestCategory::with(['interests' => fn($q) => $q->orderBy('sort_order')])
                                        ->orderBy('sort_order')->get(),
            'serviceCategories'  => ServiceCategory::where('is_active', true)->orderBy('sort_order')->get(['id','name']),
            'filters'            => $request->only([
                'name','gender','age_from','age_to','is_idol',
                'rating_from','rating_to','traits','interests','languages',
                'timezone','service_categories','sort_by','sort_dir',
            ]),
        ]);
    }
}
