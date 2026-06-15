<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    changeRequest: Object,
    pack:          Object,
    // fields: { title: { current, pending, flagged, admin_comment }, ... }
    fields:        Object,
});

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

// ── Decision state ────────────────────────────────────────
const isApproved = ref(false);
const submitting = ref(false);
const errors     = ref({});
const showReject = ref(false);
const rejectComment = ref('');

// Per-field flag + comment (pre-fill from existing remarks)
const flagged  = reactive({});
const comments = reactive({});
for (const field of Object.keys(props.fields)) {
    flagged[field]  = props.fields[field].flagged ?? false;
    comments[field] = props.fields[field].admin_comment ?? '';
}

const hasAnyFlag = computed(() => Object.values(flagged).some(v => v));
const canSubmit  = computed(() => isApproved.value || hasAnyFlag.value);

function submit() {
    if (!canSubmit.value) return;
    errors.value = {};

    const fd = new FormData();
    fd.append('decision', isApproved.value ? 'approved' : 'has_remarks');

    if (!isApproved.value) {
        const flaggedList = Object.keys(flagged).filter(f => flagged[f]);
        flaggedList.forEach(f => fd.append('flagged_fields[]', f));
        flaggedList.forEach(f => {
            if (comments[f]) fd.append(`field_comments[${f}]`, comments[f]);
        });
    }

    submitting.value = true;
    router.post(route('admin.content-packs.change-requests.decide', props.changeRequest.id), fd, {
        forceFormData:  true,
        preserveScroll: true,
        onError: (errs) => { errors.value = errs; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}

function reject() {
    submitting.value = true;
    router.post(
        route('admin.content-packs.change-requests.decide', props.changeRequest.id),
        { decision: 'rejected', admin_comment: rejectComment.value || null },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        }
    );
}

function formatPrice(val) {
    return val != null ? val + ' ₽' : '—';
}

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
    rejection: [
        'Пак нарушает правила платформы',
        'Спам / Дубликат существующего пака',
    ],
};

// ── Lightbox ─────────────────────────────────────────────
const lightboxIndex = ref(null);
const lightboxPhotos = computed(() => {
    const list = [];
    if (props.pack?.cover_url) {
        list.push({ id: 'cover', url: props.pack.cover_url });
    }
    if (props.pack?.photos) {
        list.push(...props.pack.photos);
    }
    return list;
});

function openLightboxByUrl(url) {
    const idx = lightboxPhotos.value.findIndex(p => p.url === url);
    if (idx !== -1) {
        lightboxIndex.value = idx;
    }
}
function closeLightbox()   { lightboxIndex.value = null; }
function prevPhoto() { lightboxIndex.value = (lightboxIndex.value - 1 + lightboxPhotos.value.length) % lightboxPhotos.value.length; }
function nextPhoto() { lightboxIndex.value = (lightboxIndex.value + 1) % lightboxPhotos.value.length; }

function handleLbKey(e) {
    if (lightboxIndex.value === null) return;
    if (e.key === 'ArrowLeft')  prevPhoto();
    else if (e.key === 'ArrowRight') nextPhoto();
    else if (e.key === 'Escape') closeLightbox();
}
onMounted(() => window.addEventListener('keydown', handleLbKey));
onUnmounted(() => window.removeEventListener('keydown', handleLbKey));
</script>

<template>
    <div class="crs-wrap">
        <Link :href="route('admin.content-packs.change-requests.index')" class="crs-back">← К списку изменений</Link>

        <div class="crs-layout">
            <!-- LEFT: Pack info -->
            <div class="crs-left">
                <div class="crs-card">
                    <h2 class="crs-card__title">Айдол</h2>
                    <a :href="route('profile.show', pack.user.id)" target="_blank" class="crs-idol-link">
                        <div class="crs-idol">
                            <img v-if="pack.user?.avatar_url" :src="pack.user.avatar_url" class="crs-idol__avatar" alt="" />
                            <div v-else class="crs-idol__avatar crs-idol__avatar--empty">{{ pack.user?.name?.charAt(0) }}</div>
                            <div class="crs-idol__name">{{ pack.user?.name }}</div>
                        </div>
                    </a>
                </div>

                <div class="crs-card">
                    <h2 class="crs-card__title">Пак</h2>
                    <img
                        v-if="pack.cover_url"
                        :src="pack.cover_url"
                        class="crs-cover"
                        style="cursor: pointer;"
                        @click="openLightboxByUrl(pack.cover_url)"
                        alt=""
                    />
                    <div v-else class="crs-cover crs-cover--empty"></div>
                    <p class="crs-pack-title">{{ pack.title }}</p>
                </div>

                <div class="crs-card" v-if="pack.photos && pack.photos.length">
                    <h2 class="crs-card__title">Фотографии в паке</h2>
                    <div class="crs-photos">
                        <img
                            v-for="photo in pack.photos"
                            :key="photo.id"
                            :src="photo.url"
                            class="crs-photo-thumb"
                            @click="openLightboxByUrl(photo.url)"
                            alt=""
                        />
                    </div>
                </div>

                <!-- Reject (fully) -->
                <div class="crs-card crs-card--danger">
                    <h2 class="crs-card__title">Отклонить полностью</h2>
                    <p class="crs-hint">Все изменения будут отклонены, пак останется без изменений.</p>
                    <div v-if="!showReject">
                        <button class="crs-btn crs-btn--reject-toggle" :disabled="submitting" @click="showReject = true">
                            Отклонить
                        </button>
                    </div>
                    <div v-else>
                        <label class="crs-label">Комментарий (необязательно)</label>
                        <textarea v-model="rejectComment" class="crs-textarea" rows="3" placeholder="Причина отклонения..." />
                        <div class="crs-presets crs-presets--block">
                            <button v-for="preset in PRESETS.rejection" :key="preset" type="button" class="crs-preset-btn" @click="rejectComment = preset">
                                + {{ preset }}
                            </button>
                        </div>
                        <div class="crs-reject-btns">
                            <button class="crs-btn crs-btn--reject" :disabled="submitting" @click="reject">Подтвердить</button>
                            <button class="crs-btn crs-btn--cancel" :disabled="submitting" @click="showReject = false">Отмена</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Fields diff + decision -->
            <div class="crs-right">
                <div v-if="Object.keys(errors).length" class="crs-err-banner">
                    <div v-for="(msg, key) in errors" :key="key">{{ msg }}</div>
                </div>

                <!-- Fields -->
                <div v-for="(vals, field) in fields" :key="field" class="crs-field-card" :class="{ 'crs-field-card--flagged': flagged[field] }">
                    <div class="crs-field-header">
                        <span class="crs-field-name" :class="{ 'crs-field-name--flagged': flagged[field] }">
                            {{ FIELD_LABELS[field] ?? field }}
                            <svg v-if="flagged[field]" class="crs-warn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ff7b7b" stroke-width="2.5">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </span>
                        <label class="crs-flag-toggle">
                            <input type="checkbox" v-model="flagged[field]" @change="isApproved = false" />
                            <span>Замечание</span>
                        </label>
                    </div>

                    <!-- Diff (current vs requested side-by-side) -->
                    <div class="crs-diff">
                        <div class="crs-diff-col crs-diff-col--current">
                            <p class="crs-diff-label">Текущее</p>
                            <p class="crs-diff-value">{{ field === 'price' ? formatPrice(vals.current) : (vals.current || '—') }}</p>
                        </div>
                        <div class="crs-diff-arrow">→</div>
                        <div class="crs-diff-col crs-diff-col--pending">
                            <p class="crs-diff-label">Запрошено</p>
                            <p class="crs-diff-value crs-diff-value--new">
                                {{ field === 'price' ? formatPrice(vals.pending) : (vals.pending || '—') }}
                            </p>
                        </div>
                    </div>

                    <!-- Comment when flagged -->
                    <div v-if="flagged[field]" class="crs-field-comment">
                        <label class="crs-label">Комментарий к «{{ FIELD_LABELS[field] ?? field }}»</label>
                        <input
                            v-model="comments[field]"
                            class="crs-input"
                            type="text"
                            :placeholder="'Укажите что не так с ' + (FIELD_LABELS[field] ?? field).toLowerCase() + '...'"
                        />
                        <div class="crs-presets">
                            <button v-for="preset in PRESETS[field]" :key="preset" type="button" class="crs-preset-btn" @click="comments[field] = preset">
                                + {{ preset }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Decision buttons -->
                <div class="crs-decision crs-decision--sticky">
                    <button
                        class="crs-btn crs-btn--approve"
                        :disabled="submitting || hasAnyFlag"
                        @click="isApproved = true; submit()"
                    >
                        Одобрить все изменения
                    </button>
                    <button
                        class="crs-btn crs-btn--remarks"
                        :disabled="submitting || !hasAnyFlag"
                        @click="isApproved = false; submit()"
                    >
                        Отправить замечания
                    </button>
                </div>
                <p v-if="!hasAnyFlag && !isApproved" class="crs-hint crs-hint--center">
                    Пометьте поля с замечаниями или нажмите «Одобрить»
                </p>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition name="lb">
            <div v-if="lightboxIndex !== null" class="lb-overlay" @click.self="closeLightbox">
                <button class="lb-close" @click="closeLightbox">✕</button>
                <button class="lb-arrow lb-arrow--prev" @click="prevPhoto">&#8249;</button>
                <div class="lb-img-wrap">
                    <img :src="lightboxPhotos[lightboxIndex]?.url" alt="" class="lb-img" />
                </div>
                <button class="lb-arrow lb-arrow--next" @click="nextPhoto">&#8250;</button>
                <div class="lb-counter">{{ lightboxIndex + 1 }} / {{ lightboxPhotos.length }}</div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.crs-wrap { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

.crs-back {
    display: inline-block;
    margin-bottom: 1.25rem;
    color: rgba(255, 178, 239,0.7);
    text-decoration: none;
    font-size: 0.88rem;
}
.crs-back:hover { color: rgba(200,200,255,0.95); }

.crs-warn-icon {
    display: inline-block;
    vertical-align: middle;
    margin-left: 0.4rem;
}

.crs-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 1.25rem;
    align-items: start;
}

.crs-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 1.1rem;
    margin-bottom: 1rem;
}
.crs-card--danger { border-color: rgba(255,80,80,0.15); }
.crs-card__title {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(255,255,255,0.3);
    margin: 0 0 0.75rem;
    font-weight: 600;
}

.crs-idol { display: flex; align-items: center; gap: 0.6rem; }
.crs-idol__avatar {
    width: 34px; height: 34px;
    border-radius: 50%; object-fit: cover; flex-shrink: 0;
}
.crs-idol__avatar--empty {
    background: rgba(255, 178, 239,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; color: rgba(255, 178, 239,0.7);
}
.crs-idol__name { font-size: 0.9rem; color: rgba(255,255,255,0.85); }

.crs-cover {
    width: 100%; aspect-ratio: 3/4; object-fit: cover;
    border-radius: 7px; display: block; margin-bottom: 0.75rem;
}
.crs-cover--empty { background: rgba(255,255,255,0.05); }
.crs-pack-title { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0; }

.crs-hint {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.35);
    margin: 0 0 0.75rem;
    line-height: 1.4;
}
.crs-hint--center { text-align: center; margin-top: 0.5rem; }

/* Fields */
.crs-field-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 9px;
    padding: 1rem 1.1rem;
    margin-bottom: 0.75rem;
    transition: border-color 0.15s;
}
.crs-field-card--flagged { border-color: rgba(255,200,80,0.3); }

.crs-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.crs-field-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.crs-flag-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.35);
    user-select: none;
}
.crs-flag-toggle input[type=checkbox] { accent-color: #ffc850; cursor: pointer; }
.crs-field-card--flagged .crs-flag-toggle { color: #ffc850; }

/* Diff */
.crs-diff { display: flex; align-items: flex-start; gap: 0.75rem; }
.crs-diff-col { flex: 1; min-width: 0; }
.crs-diff-label { font-size: 0.72rem; color: rgba(255,255,255,0.3); margin: 0 0 0.25rem; }
.crs-diff-value {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.5);
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}
.crs-diff-value--new { color: rgba(255,255,255,0.92); }
.crs-diff-arrow { color: rgba(255,255,255,0.2); padding-top: 1.35rem; flex-shrink: 0; }

/* Comment field */
.crs-field-comment { margin-top: 0.75rem; }
.crs-label { display: block; font-size: 0.78rem; color: rgba(255,255,255,0.4); margin-bottom: 0.35rem; }
.crs-input {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: rgba(255,255,255,0.85);
    font-size: 0.88rem;
    font-family: inherit;
    padding: 0.4rem 0.7rem;
    outline: none;
}

/* Decision */
.crs-decision { display: flex; gap: 0.75rem; margin-top: 1rem; }

.crs-err-banner {
    background: rgba(255,80,80,0.1);
    border: 1px solid rgba(255,80,80,0.25);
    border-radius: 7px;
    padding: 0.6rem 0.9rem;
    color: #ff8080;
    font-size: 0.88rem;
    margin-bottom: 1rem;
}

/* Buttons */
.crs-btn {
    padding: 0.6rem 1rem;
    border-radius: 7px;
    border: none;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.15s, background 0.15s;
    flex: 1;
}
.crs-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.crs-btn--approve {
    background: rgba(100,210,160,0.2);
    border: 1px solid rgba(100,210,160,0.35);
    color: #64d2a0;
}
.crs-btn--approve:hover:not(:disabled) { background: rgba(100,210,160,0.28); }

.crs-btn--remarks {
    background: rgba(255,200,80,0.15);
    border: 1px solid rgba(255,200,80,0.3);
    color: #ffc850;
}
.crs-btn--remarks:hover:not(:disabled) { background: rgba(255,200,80,0.22); }

.crs-btn--reject-toggle {
    width: 100%;
    background: rgba(255,80,80,0.1);
    border: 1px solid rgba(255,80,80,0.2);
    color: rgba(255,120,120,0.8);
}
.crs-btn--reject-toggle:hover:not(:disabled) { background: rgba(255,80,80,0.16); }

.crs-textarea {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 7px;
    color: rgba(255,255,255,0.85);
    font-size: 0.88rem;
    font-family: inherit;
    padding: 0.5rem 0.75rem;
    outline: none;
    resize: vertical;
    margin-bottom: 0.5rem;
}
.crs-reject-btns { display: flex; gap: 0.5rem; }

.crs-btn--reject {
    flex: 1;
    background: rgba(255,80,80,0.2);
    border: 1px solid rgba(255,80,80,0.35);
    color: #ff8080;
}
.crs-btn--reject:hover:not(:disabled) { background: rgba(255,80,80,0.28); }

.crs-btn--cancel {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.5);
}
.crs-btn--cancel:hover:not(:disabled) { background: rgba(255,255,255,0.09); }

/* ── Enhanced UI/UX Styles ── */
.crs-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.4rem;
}
.crs-presets--block {
    margin-bottom: 0.75rem;
}
.crs-preset-btn {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    color: rgba(255,255,255,0.5);
    font-size: 0.72rem;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.crs-preset-btn:hover {
    background: rgba(255, 178, 239, 0.1);
    color: #ffb2ef;
    border-color: rgba(255, 178, 239, 0.25);
}
.crs-indicator {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 4px;
}
.crs-indicator--ok {
    color: #64d2a0;
    background: rgba(100,210,160,0.1);
}
.crs-indicator--flagged {
    color: #ffc850;
    background: rgba(255,200,80,0.1);
}
.crs-field-card {
    transition: all 0.25s ease;
}
.crs-field-card--flagged {
    border-color: rgba(255,200,80,0.2) !important;
    background: rgba(255,200,80,0.01) !important;
}
.crs-decision--sticky {
    position: sticky;
    bottom: 1rem;
    z-index: 100;
    background: rgba(30, 30, 42, 0.96) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 178, 239, 0.25) !important;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.6);
}
.crs-idol-link {
    text-decoration: none;
    display: block;
}
.crs-idol-link:hover .crs-idol__name {
    color: #ffb2ef;
}

.crs-photos {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.crs-photo-thumb {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.15s;
    border: 1px solid rgba(255,255,255,0.08);
}
.crs-photo-thumb:hover {
    opacity: 0.85;
    transform: scale(1.02);
}

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
    font-size: 2rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
    padding-bottom: 2px; z-index: 1;
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
</style>
