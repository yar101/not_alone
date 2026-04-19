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
                ['name_ru' => $c->getTranslation('name', 'ru'), 'name_en' => $c->getTranslation('name', 'en', false) ?: '']
            )),
            'active_tab' => 'categories',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'            => ['required', 'string', 'max:100'],
            'name_en'            => ['nullable', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'name_suggestions'   => ['nullable', 'array'],
            'name_suggestions.*' => ['string', 'max:120'],
            'accent_color'       => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active'          => ['boolean'],
        ]);

        $data['sort_order'] = (ServiceCategory::max('sort_order') ?? -1) + 1;
        $data['name'] = array_filter(['ru' => $data['name_ru'], 'en' => $data['name_en'] ?? null]);
        unset($data['name_ru'], $data['name_en']);

        ServiceCategory::create($data);

        return back()->with('success', 'Категория создана.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        foreach ($ids as $order => $id) {
            ServiceCategory::where('id', $id)->update(['sort_order' => $order]);
        }

        return back();
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name_ru'            => ['sometimes', 'string', 'max:100'],
            'name_en'            => ['nullable', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'name_suggestions'   => ['nullable', 'array'],
            'name_suggestions.*' => ['string', 'max:120'],
            'accent_color'       => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order'         => ['sometimes', 'integer', 'min:0'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name_ru']) || array_key_exists('name_en', $data)) {
            if (isset($data['name_ru'])) {
                $category->setTranslation('name', 'ru', $data['name_ru']);
            }
            if (!empty($data['name_en'])) {
                $category->setTranslation('name', 'en', $data['name_en']);
            }
            $category->save();
            unset($data['name_ru'], $data['name_en']);
        }

        $category->update($data);

        if ($request->boolean('remove_image') && !$request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $category->update(['image_path' => null]);
        } elseif ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $ext  = $request->file('image')->getClientOriginalExtension() ?: 'jpg';
            $path = $request->file('image')->storeAs('service-categories', "{$category->id}.{$ext}", 'public');
            $category->update(['image_path' => $path]);
        }

        return back()->with('success', 'Категория обновлена.');
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
        $category->delete();

        return back()->with('success', 'Категория удалена.');
    }
}
