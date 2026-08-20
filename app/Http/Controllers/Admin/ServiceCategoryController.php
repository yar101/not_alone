<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Services/Index', [
            'categories' => ServiceCategory::orderBy('sort_order')->get()->map(fn ($c) => array_merge(
                $c->toArray(),
                [
                    'name_ru' => $c->getTranslation('name', 'ru'),
                    'name_en' => $c->getTranslation('name', 'en', false) ?: '',
                    'description_ru' => $c->getTranslation('description', 'ru', false) ?: '',
                    'description_en' => $c->getTranslation('description', 'en', false) ?: '',
                    'name_suggestions_ru' => $c->name_suggestions['ru'] ?? [],
                    'name_suggestions_en' => $c->name_suggestions['en'] ?? [],
                ]
            )),
            'active_tab' => 'categories',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'name_suggestions_ru' => ['nullable', 'array'],
            'name_suggestions_ru.*' => ['string', 'max:120'],
            'name_suggestions_en' => ['nullable', 'array'],
            'name_suggestions_en.*' => ['string', 'max:120'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
        ]);

        $data['sort_order'] = (ServiceCategory::max('sort_order') ?? -1) + 1;
        $data['name'] = array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]);
        $data['description'] = array_filter(['ru' => $data['description_ru'] ?? null, 'en' => $data['description_en'] ?? null]) ?: null;
        $data['name_suggestions'] = ['ru' => $data['name_suggestions_ru'] ?? [], 'en' => $data['name_suggestions_en'] ?? []];
        unset($data['name_ru'], $data['name_en'], $data['description_ru'], $data['description_en'], $data['name_suggestions_ru'], $data['name_suggestions_en']);

        ServiceCategory::create($data);

        return back()->with('success', 'Категория создана.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        foreach ($ids as $order => $id) {
            ServiceCategory::where('id', $id)->update(['sort_order' => $order]);
        }

        ServiceCategory::clearCache();

        return back();

    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name_ru' => ['sometimes', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'name_suggestions_ru' => ['nullable', 'array'],
            'name_suggestions_ru.*' => ['string', 'max:120'],
            'name_suggestions_en' => ['nullable', 'array'],
            'name_suggestions_en.*' => ['string', 'max:120'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name_ru']) || array_key_exists('name_en', $data)) {
            if (isset($data['name_ru'])) {
                $category->setTranslation('name', 'ru', $data['name_ru']);
            }
            if (! empty($data['name_en'])) {
                $category->setTranslation('name', 'en', $data['name_en']);
            }
            unset($data['name_ru'], $data['name_en']);
        }

        if (array_key_exists('description_ru', $data) || array_key_exists('description_en', $data)) {
            if (! empty($data['description_ru'])) {
                $category->setTranslation('description', 'ru', $data['description_ru']);
            }
            if (! empty($data['description_en'])) {
                $category->setTranslation('description', 'en', $data['description_en']);
            }
            unset($data['description_ru'], $data['description_en']);
        }

        if (array_key_exists('name_suggestions_ru', $data) || array_key_exists('name_suggestions_en', $data)) {
            $existing = $category->name_suggestions ?? [];
            $data['name_suggestions'] = [
                'ru' => $data['name_suggestions_ru'] ?? $existing['ru'] ?? [],
                'en' => $data['name_suggestions_en'] ?? $existing['en'] ?? [],
            ];
            unset($data['name_suggestions_ru'], $data['name_suggestions_en']);
        }

        $category->save();
        $category->update($data);

        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            if ($category->image_path) {
                Storage::delete($category->image_path);
            }
            $category->update(['image_path' => null]);
        } elseif ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
            if ($category->image_path) {
                Storage::delete($category->image_path);
            }
            $ext = $request->file('image')->getClientOriginalExtension() ?: 'jpg';
            $path = $request->file('image')->storeAs('service-categories', "{$category->id}.{$ext}");
            $category->update(['image_path' => $path]);
        }

        return back()->with('success', 'Категория обновлена.');
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        if ($category->image_path) {
            Storage::delete($category->image_path);
        }
        $category->delete();

        return back()->with('success', 'Категория удалена.');
    }
}
