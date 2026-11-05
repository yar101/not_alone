<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServicePriceLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServicePriceLimitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Services/Index', [
            'limits' => ServicePriceLimit::with('category')->get()->map(fn ($l) => [
                'id' => $l->id,
                'category_id' => $l->category_id,
                'max_price' => $l->max_price,
                'low_rating_max_price' => $l->low_rating_max_price,
                'category' => $l->category ? [
                    'id' => $l->category->id,
                    'name' => $l->category->getTranslation('name', 'ru'),
                    'name_ru' => $l->category->getTranslation('name', 'ru'),
                ] : null,
            ]),
            'categories' => ServiceCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'active_tab' => 'price-limits',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required', 'integer', 'exists:service_categories,id', 'unique:service_price_limits,category_id'],
            'max_price' => ['nullable', 'integer', 'min:1'],
            'low_rating_max_price' => ['nullable', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $maxPrice = $request->input('max_price');
            $lowRatingMaxPrice = $request->input('low_rating_max_price');

            if (empty($maxPrice) && empty($lowRatingMaxPrice)) {
                $validator->errors()->add('max_price', 'Хотя бы один из лимитов должен быть заполнен.');
            }

            if (! empty($maxPrice) && ! empty($lowRatingMaxPrice) && (int) $lowRatingMaxPrice > (int) $maxPrice) {
                $validator->errors()->add('low_rating_max_price', 'Лимит для низкого рейтинга не может превышать общий лимит.');
            }
        });

        $data = $validator->validate();

        ServicePriceLimit::create($data);

        return back()->with('success', 'Лимит цены создан.');
    }

    public function update(Request $request, ServicePriceLimit $priceLimit): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:service_categories,id',
                Rule::unique('service_price_limits', 'category_id')->ignore($priceLimit->id),
            ],
            'max_price' => ['nullable', 'integer', 'min:1'],
            'low_rating_max_price' => ['nullable', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator) use ($request, $priceLimit) {
            $maxPrice = $request->has('max_price') ? $request->input('max_price') : $priceLimit->max_price;
            $lowRatingMaxPrice = $request->has('low_rating_max_price') ? $request->input('low_rating_max_price') : $priceLimit->low_rating_max_price;

            if (empty($maxPrice) && empty($lowRatingMaxPrice)) {
                $validator->errors()->add('max_price', 'Хотя бы один из лимитов должен быть заполнен.');
            }

            if (! empty($maxPrice) && ! empty($lowRatingMaxPrice) && (int) $lowRatingMaxPrice > (int) $maxPrice) {
                $validator->errors()->add('low_rating_max_price', 'Лимит для низкого рейтинга не может превышать общий лимит.');
            }
        });

        $data = $validator->validate();

        $priceLimit->update($data);

        return back()->with('success', 'Лимит цены обновлён.');
    }

    public function destroy(ServicePriceLimit $priceLimit): RedirectResponse
    {
        $priceLimit->delete();

        return back()->with('success', 'Лимит цены удалён.');
    }
}
