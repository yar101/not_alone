<?php

namespace App\Http\Controllers;

use App\Models\IdolCategoryDescription;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceChangeRequest;
use App\Models\ServicePriceLimit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    /**
     * JSON endpoint — returns full grouped-by-category services for a profile.
     * Used by the frontend to refresh on-the-fly via axios (same as ContentPackController::indexForProfile).
     */
    public function indexForProfile(User $user): JsonResponse
    {
        $authId  = auth()->id();
        $isOwner = $authId === $user->id;

        $allCategories = ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'image_path', 'accent_color', 'sort_order', 'is_active']);

        $query = $user->services()->with(['timeUnit:id,name', 'latestReview', 'pendingChangeRequest.pendingCategory', 'pendingChangeRequest.pendingTimeUnit']);
        if (!$isOwner) {
            $query->where('is_active', true)->where('status', 'approved');
        }
        $services = $query->orderBy('created_at')->get();

        $categoryIds  = $allCategories->pluck('id');
        $descriptions = IdolCategoryDescription::where('user_id', $user->id)
            ->whereIn('category_id', $categoryIds)
            ->pluck('description', 'category_id');

        $servicesByCategory = $services->groupBy('category_id');

        $result = $allCategories->map(function ($cat) use ($servicesByCategory, $descriptions, $isOwner) {
            $group = $servicesByCategory->get($cat->id, collect());
            return [
                'category' => [
                    'id'             => $cat->id,
                    'name'           => $cat->getTranslation('name', 'ru'),
                    'name_ru'        => $cat->getTranslation('name', 'ru'),
                    'name_en'        => $cat->getTranslation('name', 'en', false) ?: null,
                    'description_ru' => $cat->getTranslation('description', 'ru', false) ?: null,
                    'description_en' => $cat->getTranslation('description', 'en', false) ?: null,
                    'image_url'      => $cat->image_path ? Storage::url($cat->image_path) : null,
                    'accent_color'   => $cat->accent_color,
                    'sort_order'     => $cat->sort_order,
                ],
                'idol_description' => $descriptions[$cat->id] ?? null,
                'items'            => $group->map(function (Service $s) use ($isOwner) {
                    $base = [
                        'id'               => $s->id,
                        'name_ru'          => $s->getTranslation('name', 'ru'),
                        'name_en'          => $s->getTranslation('name', 'en', false) ?: null,
                        'price'            => $s->price,
                        'is_active'        => $s->is_active,
                        'status'           => $s->status,
                        'rejection_reason' => $s->rejection_reason,
                        'category_id'      => $s->category_id,
                        'time_unit'        => [
                            'id'      => $s->timeUnit->id,
                            'name_ru' => $s->timeUnit->getTranslation('name', 'ru'),
                            'name_en' => $s->timeUnit->getTranslation('name', 'en', false) ?: null,
                        ],
                    ];

                    if ($isOwner) {
                        $base['latest_review'] = $s->latestReview ? [
                            'decision'       => $s->latestReview->decision,
                            'flagged_fields' => $s->latestReview->flagged_fields ?? [],
                            'field_comments' => $s->latestReview->field_comments ?? [],
                        ] : null;

                        $base['pending_change'] = $s->pendingChangeRequest ? [
                            'changed_fields'    => $s->pendingChangeRequest->changed_fields,
                            'pending_name'      => $s->pendingChangeRequest->pending_name,
                            'pending_price'     => $s->pendingChangeRequest->pending_price,
                            'pending_category'  => $s->pendingChangeRequest->pendingCategory ? [
                                'id'      => $s->pendingChangeRequest->pendingCategory->id,
                                'name_ru' => $s->pendingChangeRequest->pendingCategory->getTranslation('name', 'ru'),
                            ] : null,
                            'pending_time_unit' => $s->pendingChangeRequest->pendingTimeUnit ? [
                                'id'      => $s->pendingChangeRequest->pendingTimeUnit->id,
                                'name_ru' => $s->pendingChangeRequest->pendingTimeUnit->getTranslation('name', 'ru'),
                            ] : null,
                            'status'           => $s->pendingChangeRequest->status,
                            'flagged_fields'   => $s->pendingChangeRequest->flagged_fields ?? [],
                            'field_comments'   => $s->pendingChangeRequest->field_comments ?? [],
                            'admin_comment'    => $s->pendingChangeRequest->admin_comment,
                        ] : null;
                    }

                    return $base;
                })->values(),
            ];
        })->values();

        return response()->json(['groups' => $result]);
    }

    public function forOffer(Request $request): JsonResponse
    {
        $user = $request->user();

        $services = Service::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['category:id,name', 'timeUnit:id,name'])
            ->get();

        if ($services->isEmpty()) {
            return response()->json([]);
        }

        $grouped = $services
            ->groupBy('category_id')
            ->map(fn($items, $categoryId) => [
                'category' => [
                    'id'      => $items->first()->category->id,
                    'name_ru' => $items->first()->category->getTranslation('name', 'ru'),
                    'name_en' => $items->first()->category->getTranslation('name', 'en', false) ?: null,
                ],
                'services' => $items->map(fn($s) => [
                    'id'      => $s->id,
                    'name_ru' => $s->getTranslation('name', 'ru'),
                    'name_en' => $s->getTranslation('name', 'en', false) ?: null,
                    'price'   => $s->price,
                    'time_unit_ru' => $s->timeUnit?->getTranslation('name', 'ru'),
                    'time_unit_en' => $s->timeUnit?->getTranslation('name', 'en', false) ?: null,
                ])->values(),
            ])
            ->values();

        return response()->json($grouped);
    }

    public function store(Request $request): RedirectResponse
    {
        $idol = $request->user();
        abort_if(!$idol->is_idol, 403);

        $data = $request->validate([
            'name_ru'      => ['nullable', 'string', 'max:120'],
            'name_en'      => ['nullable', 'string', 'max:120', 'regex:/^[^\x{0400}-\x{04FF}\x{0500}-\x{052F}]*$/u'],
            'category_id'  => ['required', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['required', 'integer', 'exists:service_time_units,id'],
            'price'        => ['required', 'integer', 'min:1', 'max:999999'],
            'is_trial'     => ['boolean'],
        ]);

        if (empty($data['name_ru']) && empty($data['name_en'])) {
            throw ValidationException::withMessages([
                'name_ru' => 'Укажите хотя бы одно название услуги.',
            ]);
        }

        $this->validatePriceLimit($idol, $data['time_unit_id'], $data['price']);

        Service::create([
            'name'         => array_filter(['ru' => $data['name_ru'] ?? null, 'en' => $data['name_en'] ?? null]),
            'user_id'      => $idol->id,
            'category_id'  => $data['category_id'],
            'time_unit_id' => $data['time_unit_id'],
            'price'        => $data['price'],
            'is_trial'     => $data['is_trial'] ?? false,
            'is_active'    => true,
            'status'       => 'pending',
        ]);

        return back()->with('service_pending', true);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name_ru'      => ['sometimes', 'nullable', 'string', 'max:120'],
            'name_en'      => ['nullable', 'string', 'max:120', 'regex:/^[^\x{0400}-\x{04FF}\x{0500}-\x{052F}]*$/u'],
            'category_id'  => ['sometimes', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['sometimes', 'integer', 'exists:service_time_units,id'],
            'price'        => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $timeUnitId = $data['time_unit_id'] ?? $service->time_unit_id;
        $price      = $data['price'] ?? $service->price;

        if (isset($data['price']) || isset($data['time_unit_id'])) {
            $this->validatePriceLimit($request->user(), $timeUnitId, $price);
        }

        // Check if moderated fields actually changed
        $isModeratedFieldChange = [];
        foreach (['name_ru', 'name_en', 'category_id', 'time_unit_id', 'price'] as $field) {
            if (!array_key_exists($field, $data)) continue;
            
            $val = $data[$field];
            $changed = false;
            
            if ($field === 'name_ru') {
                $oldRu = $service->getTranslation('name', 'ru', false);
                $changed = trim(empty($val) ? '' : $val) !== trim(empty($oldRu) ? '' : $oldRu);
            } elseif ($field === 'name_en') {
                $oldEn = $service->getTranslation('name', 'en', false);
                $changed = trim(empty($val) ? '' : $val) !== trim(empty($oldEn) ? '' : $oldEn);
            } else {
                $changed = (int)$val !== (int)$service->{$field};
            }

            if ($changed) {
                $isModeratedFieldChange[$field] = $val;
            }
        }
        
        if ($service->status === 'approved' && !empty($isModeratedFieldChange)) {
            \Illuminate\Support\Facades\Log::info("False moderation trigger debug", [
                'service_id' => $service->id,
                'changed_fields' => $isModeratedFieldChange,
                'data' => $data,
            ]);
            $this->upsertChangeRequest($service, $isModeratedFieldChange);
            
            // Still allow updating non-moderated fields like is_active
            if (isset($data['is_active'])) {
                $service->update(['is_active' => $data['is_active']]);
            }
            
            return back()->with('success', 'Изменения отправлены на модерацию.');
        }

        if (array_key_exists('name_ru', $data) || array_key_exists('name_en', $data)) {
            $ruAfter = $data['name_ru'] ?? $service->getTranslation('name', 'ru', false);
            $enAfter = $data['name_en'] ?? $service->getTranslation('name', 'en', false);
            if (empty($ruAfter) && empty($enAfter)) {
                throw ValidationException::withMessages([
                    'name_ru' => 'Укажите хотя бы одно название услуги.',
                ]);
            }
        }

        if (isset($data['name_ru'])) {
            $service->setTranslation('name', 'ru', $data['name_ru']);
        }
        if (array_key_exists('name_en', $data)) {
            if (!empty($data['name_en'])) {
                $service->setTranslation('name', 'en', $data['name_en']);
            } else {
                $service->forgetTranslation('name', 'en');
            }
        }

        $rest = array_diff_key($data, array_flip(['name_ru', 'name_en']));
        if (!empty($rest)) {
            $service->fill($rest);
        }

        if ($service->status === 'has_remarks') {
            $service->status = 'pending';
            $service->resubmitted_at = now();
        }

        $service->save();

        return back()->with('success', 'Услуга обновлена.');
    }

    public function fixChangeRequest(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);
        
        $cr = $service->pendingChangeRequest;
        abort_if(!$cr || $cr->status !== 'has_remarks', 422);

        $data = $request->validate([
            'name_ru'      => ['sometimes', 'nullable', 'string', 'max:120'],
            'name_en'      => ['nullable', 'string', 'max:120', 'regex:/^[^\x{0400}-\x{04FF}\x{0500}-\x{052F}]*$/u'],
            'category_id'  => ['sometimes', 'integer', 'exists:service_categories,id'],
            'time_unit_id' => ['sometimes', 'integer', 'exists:service_time_units,id'],
            'price'        => ['sometimes', 'integer', 'min:1', 'max:999999'],
        ]);

        $this->upsertChangeRequest($service, $data);

        return back()->with('success', 'Изменения обновлены и отправлены на повторную модерацию.');
    }

    private function upsertChangeRequest(Service $service, array $data): void
    {
        DB::transaction(function () use ($service, $data) {
            $changeRequest = ServiceChangeRequest::where('service_id', $service->id)
                ->whereIn('status', ['pending', 'has_remarks'])
                ->lockForUpdate()
                ->first();

            $pendingData = $changeRequest ? [
                'name'         => $changeRequest->pending_name,
                'price'        => $changeRequest->pending_price,
                'category_id'  => $changeRequest->pending_category_id,
                'time_unit_id' => $changeRequest->pending_time_unit_id,
            ] : [
                'name'         => $service->getTranslations('name'),
                'price'        => $service->price,
                'category_id'  => $service->category_id,
                'time_unit_id' => $service->time_unit_id,
            ];

            foreach ($data as $key => $value) {
                if ($key === 'name_ru' || $key === 'name_en') {
                    $lang = str_replace('name_', '', $key);
                    if ($key === 'name_en' && empty($value)) {
                        unset($pendingData['name'][$lang]);
                    } else {
                        $pendingData['name'][$lang] = $value;
                    }
                } else {
                    $pendingData[$key] = $value;
                }
            }

            // Filter out fields that are same as current service
            $finalChangedFields = [];
            if (isset($pendingData['name']) && $pendingData['name'] !== $service->getTranslations('name')) $finalChangedFields[] = 'name';
            if (isset($pendingData['price']) && (int)$pendingData['price'] !== (int)$service->price) $finalChangedFields[] = 'price';
            if (isset($pendingData['category_id']) && (int)$pendingData['category_id'] !== (int)$service->category_id) $finalChangedFields[] = 'category_id';
            if (isset($pendingData['time_unit_id']) && (int)$pendingData['time_unit_id'] !== (int)$service->time_unit_id) $finalChangedFields[] = 'time_unit_id';

            if (empty($finalChangedFields)) {
                $changeRequest?->delete();
                return;
            }

            $updateData = [
                'changed_fields'       => $finalChangedFields,
                'pending_name'         => in_array('name', $finalChangedFields) ? $pendingData['name'] : null,
                'pending_price'        => in_array('price', $finalChangedFields) ? $pendingData['price'] : null,
                'pending_category_id'  => in_array('category_id', $finalChangedFields) ? $pendingData['category_id'] : null,
                'pending_time_unit_id' => in_array('time_unit_id', $finalChangedFields) ? $pendingData['time_unit_id'] : null,
                'status'               => 'pending',
            ];

            if ($changeRequest) {
                // Remove flagged status for updated fields
                $flaggedFields = array_diff($changeRequest->flagged_fields ?? [], $finalChangedFields);
                $fieldComments = array_intersect_key($changeRequest->field_comments ?? [], array_flip($flaggedFields));
                
                $updateData['flagged_fields'] = array_values($flaggedFields);
                $updateData['field_comments'] = $fieldComments;
                
                $changeRequest->update($updateData);
            } else {
                ServiceChangeRequest::create(array_merge($updateData, ['service_id' => $service->id]));
            }
        });
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);
        $service->delete();

        return back()->with('success', 'Услуга удалена.');
    }

    public function toggleTrial(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);
        
        $request->validate(['is_trial' => 'required|boolean']);
        
        $service->update(['is_trial' => $request->is_trial]);
        
        return back()->with('success', 'Статус пробной услуги обновлен.');
    }

    public function dismissChangeRequest(Request $request, Service $service): RedirectResponse
    {
        abort_if($service->user_id !== $request->user()->id, 403);
        
        $cr = $service->pendingChangeRequest;
        if ($cr && $cr->status === 'rejected') {
            $cr->delete();
        }

        return back()->with('success', 'Отклоненные изменения скрыты.');
    }

    private function validatePriceLimit($idol, int $timeUnitId, int $price): void
    {
        $threshold = (int) PlatformSetting::get('rating_low_threshold', 30);
        $rating    = (int) ($idol->idol_rating ?? 50);

        if ($rating < $threshold) {
            $limit = ServicePriceLimit::where('time_unit_id', $timeUnitId)->first();
            if ($limit && $price > $limit->max_price) {
                throw ValidationException::withMessages([
                    'price' => 'При вашем рейтинге цена не может превышать ' . $limit->max_price . ' ₽ за эту единицу времени.',
                ]);
            }
        }
    }
}
