<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@element-plus/icons-vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    pack: Object,
});

// ── Flag state ───────────────────────────────────────────
const flaggedFields  = reactive({
    title:       false,
    description: false,
    price:       false,
});
const fieldComments  = reactive({ title: '', description: '', price: '' });
const flaggedPhotos  = reactive({}); // { [photo_id]: boolean }
const photoComments  = reactive({}); // { [photo_id]: string }

const PRESETS = {
    title: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Опечатки или некорректный регистр',
    ],
    description: [
        'Содержит ссылки / контактную информацию',
        'Нецензурная лексика или оскорбления',
        'Слишком короткое описание',
        'Грамматические и стилистические ошибки',
    ],
    price: [
        'Некорректная цена пака',
        'Цена не соответствует правилам платформы',
    ],
    photo: [
        'Низкое качество изображения',
        'Наличие водяных знаков / чужих логотипов',
        'Неприемлемый или нарушающий правила контент',
    ],
};

// Initialize photo flags
if (props.pack?.photos) {
    props.pack.photos.forEach(p => {
        flaggedPhotos[p.id]  = false;
        photoComments[p.id]  = '';
    });
}

// ── Decision ─────────────────────────────────────────────
const isApproved  = ref(false);
const submitting  = ref(false);
const errors      = ref({});

const hasAnyFlag = computed(() => {
    const flagged = Object.values(flaggedFields).some(v => v) ||
                    Object.values(flaggedPhotos).some(v => v);
    if (flagged) isApproved.value = false;
    return flagged;
});

const canSubmit = computed(() => isApproved.value || hasAnyFlag.value);

function reject() {
    if (!confirm('Полностью отклонить пак? Все фотографии будут удалены, айдол получит уведомление.')) return;
    submitting.value = true;
    const fd = new FormData();
    fd.append('decision', 'rejected');
    router.post(route('admin.content-packs.decide', props.pack.id), fd, {
        forceFormData:  true,
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const fd = new FormData();
    fd.append('decision', isApproved.value ? 'approved' : 'has_remarks');

    const fFields = Object.keys(flaggedFields).filter(f => flaggedFields[f]);
    fFields.forEach(f => fd.append('flagged_fields[]', f));

    const fComments = {};
    fFields.forEach(f => { if (fieldComments[f]) fComments[f] = fieldComments[f]; });
    if (Object.keys(fComments).length) fd.append('field_comments', JSON.stringify(fComments));

    const fPhotoIds = Object.keys(flaggedPhotos).filter(id => flaggedPhotos[id]);
    fPhotoIds.forEach(id => fd.append('flagged_photo_ids[]', id));

    const fPhotoComments = {};
    fPhotoIds.forEach(id => { if (photoComments[id]) fPhotoComments[id] = photoComments[id]; });
    if (Object.keys(fPhotoComments).length) fd.append('photo_comments', JSON.stringify(fPhotoComments));

    submitting.value = true;
    router.post(route('admin.content-packs.decide', props.pack.id), fd, {
        forceFormData:  true,
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

// ── Lightbox ─────────────────────────────────────────────
const lightboxIndex = ref(null);
const photos = computed(() => props.pack?.photos ?? []);

function openLightbox(idx) { lightboxIndex.value = idx; }
function closeLightbox()   { lightboxIndex.value = null; }
function prevPhoto() { lightboxIndex.value = (lightboxIndex.value - 1 + photos.value.length) % photos.value.length; }
function nextPhoto() { lightboxIndex.value = (lightboxIndex.value + 1) % photos.value.length; }

function handleLbKey(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'ArrowLeft')  prevPhoto();
    else if (e.key === 'ArrowRight') nextPhoto();
    else if (e.key === 'Escape') closeLightbox();
}
onMounted(() => window.addEventListener('keydown', handleLbKey));
onUnmounted(() => window.removeEventListener('keydown', handleLbKey));

const STATUS_LABELS = {
    pending_review: 'На рассмотрении',
    approved:       'Одобрен',
    published:      'Опубликован',
    has_remarks:    'Есть замечания',
};
</script>

<template>
    <div class="cps-wrap">
        <!-- Back link -->
        <Link :href="route('admin.content-packs.index')" class="cps-back">← К списку</Link>

        <div class="cps-layout">
            <!-- LEFT: Idol info + Pack metadata + Photos -->
            <div class="cps-left">

                <!-- Idol info -->
                <div class="cps-card">
                    <h2 class="cps-card__title">Айдол</h2>
                    <a :href="route('profile.show', pack.user.id)" target="_blank" class="cps-idol-link">
                        <div class="cps-idol">
                            <img v-if="pack.user.avatar_url" :src="pack.user.avatar_url" class="cps-idol__avatar" alt="" />
                            <div v-else class="cps-idol__avatar cps-idol__avatar--empty">{{ pack.user.name?.charAt(0) }}</div>
                            <div>
                                <div class="cps-idol__name">{{ pack.user.name }}</div>
                                <div class="cps-idol__email">{{ pack.user.email }}</div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Pack metadata -->
                <div class="cps-card">
                    <h2 class="cps-card__title">Пак</h2>
                    <div class="cps-meta">
                        <div class="cps-meta__row">
                            <span class="cps-meta__label">Статус</span>
                            <span class="cps-status" :class="'cps-status--' + pack.status">
                                {{ STATUS_LABELS[pack.status] || pack.status }}
                            </span>
                        </div>
                        <div class="cps-meta__row">
                            <span class="cps-meta__label">Название</span>
                            <span>{{ pack.title }}</span>
                        </div>
                        <div class="cps-meta__row" v-if="pack.description">
                            <span class="cps-meta__label">Описание</span>
                            <span class="cps-meta__desc">{{ pack.description }}</span>
                        </div>
                        <div class="cps-meta__row">
                            <span class="cps-meta__label">Цена</span>
                            <span>{{ pack.price }} ₽</span>
                        </div>
                        <div class="cps-meta__row">
                            <span class="cps-meta__label">Фото</span>
                            <span>{{ pack.photos?.length ?? 0 }} шт.</span>
                        </div>
                    </div>
                </div>

                <!-- Review history -->
                <div v-if="pack.reviews?.length" class="cps-card">
                    <h2 class="cps-card__title">История проверок</h2>
                    <div v-for="r in pack.reviews" :key="r.id" class="cps-review-item">
                        <div class="cps-review-item__header">
                            <span :class="r.decision === 'approved' ? 'cps-dec--ok' : 'cps-dec--bad'">
                                {{ r.decision === 'approved' ? '✓ Одобрено' : '✗ Замечания' }}
                            </span>
                            <span class="cps-review-item__date">
                                {{ new Date(r.created_at).toLocaleDateString('ru-RU') }}
                            </span>
                            <span v-if="r.admin" class="cps-review-item__admin">{{ r.admin.name }}</span>
                        </div>
                        <div v-if="r.flagged_fields?.length" class="cps-review-item__detail">
                            Помечено: {{ r.flagged_fields.join(', ') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Review form -->
            <div class="cps-right">

                <!-- Field flags -->
                <div class="cps-card">
                    <h2 class="cps-card__title">Поля</h2>
                    <div v-for="field in ['title','description','price']" :key="field" class="cps-flag-row" :class="{ 'cps-flag-row--flagged': flaggedFields[field] }">
                        <div class="cps-flag-header">
                            <label class="cps-flag-label">
                                <input type="checkbox" v-model="flaggedFields[field]" class="cps-checkbox" />
                                <span class="cps-flag-name" :class="{ 'cps-flag-name--flagged': flaggedFields[field] }">
                                    {{ { title: 'Название', description: 'Описание', price: 'Цена' }[field] }}
                                </span>
                                <svg v-if="flaggedFields[field]" class="cps-warn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ff7b7b" stroke-width="2.5">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </label>
                            <span class="cps-flag-value">
                                <template v-if="field === 'title'">{{ pack.title }}</template>
                                <template v-else-if="field === 'price'">{{ pack.price }} ₽</template>
                                <template v-else-if="field === 'description'">
                                    {{ pack.description ? pack.description.slice(0, 60) + (pack.description.length > 60 ? '…' : '') : '—' }}
                                </template>
                            </span>
                        </div>
                        <div v-if="flaggedFields[field]" class="cps-comment-wrapper">
                            <textarea
                                v-model="fieldComments[field]"
                                class="cps-comment"
                                rows="2"
                                placeholder="Комментарий к замечанию..."
                            />
                            <div class="cps-presets">
                                <button v-for="preset in PRESETS[field]" :key="preset" type="button" class="cps-preset-btn" @click="fieldComments[field] = preset">
                                    + {{ preset }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div class="cps-card">
                    <h2 class="cps-card__title">Фотографии</h2>
                    <div class="cps-photos">
                        <div v-for="(photo, idx) in pack.photos" :key="photo.id" class="cps-photo" :class="{ 'cps-photo--flagged': flaggedPhotos[photo.id] }">
                            <div class="cps-photo-img-wrapper">
                                <img :src="photo.url" class="cps-photo__img" alt="" @click="openLightbox(idx)" />
                                <span v-if="flaggedPhotos[photo.id]" class="cps-photo-badge cps-photo-badge--flagged">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                </span>
                            </div>
                            <div class="cps-photo__controls">
                                <label class="cps-photo__flag">
                                    <input type="checkbox" v-model="flaggedPhotos[photo.id]" />
                                    <span :class="{ 'cps-photo__flag--flagged': flaggedPhotos[photo.id] }">Пометить</span>
                                </label>
                            </div>
                            <div v-if="flaggedPhotos[photo.id]" class="cps-comment-wrapper">
                                <textarea
                                    v-model="photoComments[photo.id]"
                                    class="cps-comment"
                                    rows="2"
                                    placeholder="Что не так с этим фото..."
                                />
                                <div class="cps-presets">
                                    <button v-for="preset in PRESETS.photo" :key="preset" type="button" class="cps-preset-btn" @click="photoComments[photo.id] = preset">
                                        + {{ preset }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decision -->
                <div class="cps-card cps-card--sticky">
                    <h2 class="cps-card__title">Решение</h2>

                    <label class="cps-approved-label" :class="{ 'cps-approved-label--disabled': hasAnyFlag }">
                        <input type="checkbox" v-model="isApproved" :disabled="hasAnyFlag" class="cps-checkbox" />
                        <span>Одобрено</span>
                    </label>

                    <p v-if="hasAnyFlag" class="cps-decision-hint">
                        Есть пометки — будет отправлено решение «Есть замечания».
                    </p>
                    <p v-else-if="isApproved" class="cps-decision-hint cps-decision-hint--ok">
                        Пак будет одобрен и айдол сможет опубликовать его.
                    </p>

                    <div v-if="errors._" class="cps-err">{{ errors._ }}</div>

                    <div class="cps-actions">
                        <button
                            class="cps-submit"
                            :disabled="!canSubmit || submitting"
                            @click="submit"
                        >
                            {{ submitting ? 'Отправка…' : 'Отправить решение' }}
                        </button>
                        <button
                            class="cps-reject"
                            :disabled="submitting"
                            @click="reject"
                        >
                            Отклонить полностью
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition name="lb">
            <div v-if="lightboxIndex !== null" class="lb-overlay" @click.self="closeLightbox">
                <button class="lb-close" @click="closeLightbox">✕</button>
                <button class="lb-arrow lb-arrow--prev" @click="prevPhoto"><el-icon><ArrowLeft /></el-icon></button>
                <div class="lb-img-wrap">
                    <img :src="photos[lightboxIndex]?.url" alt="" class="lb-img" />
                </div>
                <button class="lb-arrow lb-arrow--next" @click="nextPhoto"><el-icon><ArrowRight /></el-icon></button>
                <div class="lb-counter">{{ lightboxIndex + 1 }} / {{ photos.length }}</div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cps-wrap { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

.cps-back { color: rgba(255, 178, 239,0.7); text-decoration: none; font-size: 0.88rem; display: inline-block; margin-bottom: 1.25rem; }
.cps-back:hover { color: #ffb2ef; }

.cps-warn-icon {
    display: inline-block;
    vertical-align: middle;
    margin-left: 0.4rem;
}

.cps-idol-link {
    text-decoration: none;
    display: block;
}
.cps-idol-link:hover .cps-idol__name {
    color: #ffb2ef;
}

.cps-layout { display: grid; grid-template-columns: 320px 1fr; gap: 1.25rem; }
@media (max-width: 900px) { .cps-layout { grid-template-columns: 1fr; } }

.cps-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 1.1rem;
    margin-bottom: 1rem;
}

.cps-card__title { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.85rem; }
.cps-card__hint  { font-size: 0.83rem; color: rgba(255,255,255,0.35); margin: 0 0 0.75rem; }

.cps-idol { display: flex; align-items: center; gap: 0.75rem; }
.cps-idol__avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cps-idol__avatar--empty { background: rgba(255, 178, 239,0.15); display: flex; align-items: center; justify-content: center; color: rgba(255, 178, 239,0.7); font-weight: 600; }
.cps-idol__name  { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.cps-idol__email { font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

.cps-meta { display: flex; flex-direction: column; gap: 0.55rem; }
.cps-meta__row { display: flex; gap: 0.75rem; font-size: 0.88rem; }
.cps-meta__label { color: rgba(255,255,255,0.35); flex-shrink: 0; width: 80px; }
.cps-meta__desc { color: rgba(255,255,255,0.65); white-space: pre-wrap; line-height: 1.4; }

.cps-status { padding: 2px 8px; border-radius: 10px; font-size: 0.78rem; font-weight: 500; }
.cps-status--pending_review { background: rgba(255, 178, 239,0.15); color: #ffb2ef; }
.cps-status--approved       { background: rgba(100,210,160,0.15); color: #64d2a0; }
.cps-status--published      { background: rgba(100,210,255,0.15); color: #64d2ff; }
.cps-status--has_remarks    { background: rgba(255,123,123,0.15); color: #ff7b7b; }

.cps-review-item { padding: 0.6rem 0; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; }
.cps-review-item:first-child { border-top: none; }
.cps-review-item__header { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.cps-review-item__date { color: rgba(255,255,255,0.3); font-size: 0.8rem; }
.cps-review-item__admin { color: rgba(255,255,255,0.35); font-size: 0.8rem; }
.cps-review-item__detail { margin-top: 0.3rem; color: rgba(255,255,255,0.4); font-size: 0.8rem; }
.cps-dec--ok  { color: #64d2a0; }
.cps-dec--bad { color: #ff7b7b; }

.cps-flag-row { margin-bottom: 0.75rem; }
.cps-flag-header { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.cps-flag-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
.cps-flag-name { font-size: 0.88rem; color: rgba(255,255,255,0.65); font-weight: 500; transition: color 0.15s; }
.cps-flag-name--flagged { color: #ff7b7b; }
.cps-flag-value { font-size: 0.83rem; color: rgba(255,255,255,0.35); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.cps-checkbox { accent-color: #ffb2ef; width: 15px; height: 15px; }

.cps-comment {
    width: 100%;
    box-sizing: border-box;
    margin-top: 0.5rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,100,100,0.25);
    border-radius: 6px;
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    font-family: inherit;
    padding: 0.45rem 0.65rem;
    resize: vertical;
    outline: none;
}

.cps-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.6rem; }

.cps-photo { display: flex; flex-direction: column; gap: 0.35rem; }
.cps-photo__img { width: 100%; aspect-ratio: 3/4; object-fit: cover; border-radius: 6px; display: block; border: 1px solid rgba(255,255,255,0.08); cursor: pointer; transition: opacity 0.15s; }
.cps-photo__img:hover { opacity: 0.85; }
.cps-photo__controls { display: flex; gap: 0.4rem; font-size: 0.78rem; flex-wrap: wrap; }
.cps-photo__flag { display: flex; align-items: center; gap: 3px; cursor: pointer; }
.cps-photo__flag--flagged { color: #ff7b7b !important; }
.cps-photo__flag span { color: rgba(255,255,255,0.45); }

.cps-approved-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.92rem; color: rgba(255,255,255,0.7); }
.cps-approved-label--disabled { opacity: 0.35; cursor: not-allowed; }
.cps-decision-hint { font-size: 0.83rem; color: rgba(255,123,123,0.8); margin: 0.5rem 0 0; }
.cps-decision-hint--ok { color: rgba(100,210,160,0.8); }

.cps-err { font-size: 0.83rem; color: #ff7b7b; margin-top: 0.4rem; }

.cps-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }

.cps-submit {
    width: 100%;
    padding: 0.65rem;
    border-radius: 8px;
    background: rgba(255, 178, 239,0.12);
    border: 1px solid rgba(255, 178, 239,0.35);
    color: #ffb2ef;
    font-size: 0.95rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.cps-submit:hover:not(:disabled) { background: rgba(255, 178, 239,0.22); }
.cps-submit:disabled { opacity: 0.4; cursor: not-allowed; }

.cps-reject {
    width: 100%;
    padding: 0.55rem;
    border-radius: 8px;
    background: rgba(180,50,50,0.1);
    border: 1px solid rgba(200,60,60,0.3);
    color: rgba(255,110,110,0.8);
    font-size: 0.88rem;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
}
.cps-reject:hover:not(:disabled) { background: rgba(180,50,50,0.2); }
.cps-reject:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Lightbox ── */
.lb-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,0.92);
    display: flex;
    align-items: center;
    justify-content: center;
}
.lb-img-wrap { max-width: 90vw; max-height: 90vh; display: flex; align-items: center; justify-content: center; }
.lb-img { max-width: 90vw; max-height: 90vh; object-fit: contain; display: block; border-radius: 4px; box-shadow: 0 8px 60px rgba(0,0,0,0.8); }
.lb-close {
    position: absolute; top: 18px; right: 22px;
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.7); width: 36px; height: 36px; border-radius: 50%;
    font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1;
}
.lb-close:hover { background: rgba(255,255,255,0.15); color: #fff; }
.lb-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.7); width: 46px; height: 46px; border-radius: 50%;
    font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background 0.15s; z-index: 1;
}
.lb-arrow:hover { background: rgba(255,255,255,0.15); color: #fff; }
.lb-arrow--prev { left: 18px; }
.lb-arrow--next { right: 18px; }
.lb-counter {
    position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%);
    background: rgba(0,0,0,0.5); backdrop-filter: blur(6px);
    color: rgba(255,255,255,0.7); font-size: 0.85rem; padding: 4px 14px; border-radius: 20px;
}
.lb-enter-active, .lb-leave-active { transition: opacity 0.18s; }
.lb-enter-from, .lb-leave-to { opacity: 0; }

/* ── Enhanced UI/UX Styles ── */
.cps-flag-row {
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.5rem;
    transition: all 0.2s ease;
}
.cps-flag-row--flagged {
    border-color: rgba(255,100,100,0.15);
    background: rgba(255,100,100,0.02);
}
.cps-indicator {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}
.cps-indicator--ok {
    color: #64d2a0;
    background: rgba(100,210,160,0.1);
}
.cps-indicator--flagged {
    color: #ff7b7b;
    background: rgba(255,123,123,0.1);
}
.cps-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.35rem;
}
.cps-preset-btn {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    color: rgba(255,255,255,0.5);
    font-size: 0.72rem;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.cps-preset-btn:hover {
    background: rgba(255, 178, 239, 0.1);
    color: #ffb2ef;
    border-color: rgba(255, 178, 239, 0.25);
}
.cps-photo-img-wrapper {
    position: relative;
    border-radius: 6px;
    overflow: hidden;
}
.cps-photo-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 4px;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
}
.cps-photo-badge--ok {
    background: rgba(80, 210, 140, 0.85);
    color: #fff;
}
.cps-photo-badge--flagged {
    background: rgba(220, 60, 60, 0.85);
    color: #fff;
}
.cps-photo--flagged {
    border: 1px solid rgba(255, 100, 100, 0.25);
    border-radius: 8px;
    padding: 0.4rem;
    background: rgba(255,100,100,0.01);
}
.cps-card--sticky {
    position: sticky;
    bottom: 1rem;
    z-index: 100;
    background: rgba(30, 30, 42, 0.96) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.25) !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.6);
}
</style>
