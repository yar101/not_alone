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
        return Inertia::render('Admin/Services/Categories', [
            'categories' => ServiceCategory::orderBy('sort_order')->get(['id', 'name', 'description', 'image_path', 'sort_order', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        ServiceCategory::create($data);

        return back()->with('success', 'Категория создана.');
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        if ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
            // Delete old image if exists
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
