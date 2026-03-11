<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps({
    services:           { default: null },
    serviceCategories:  { default: null },
    serviceTimeUnits:   { default: null },
    isOwner:            { type: Boolean, default: false },
    isIdol:             { type: Boolean, default: false },
    wouldBuyCooldownDays: { type: Number, default: 7 },
});

// ── Add / Edit form ────────────────────────────────────────────
const showForm    = ref(false);
const editingId   = ref(null);

const form = useForm({
    name:         '',
    category_id:  null,
    time_unit_id: null,
    price:        '',
});

function openAdd() {
    editingId.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.name         = item.name;
    form.category_id  = item.category_id ?? null;
    form.time_unit_id = item.time_unit?.id ?? null;
    form.price        = item.price;
    showForm.value    = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingId.value) {
        form.patch(route('profile.services.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('profile.services.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function deleteService(id) {
    if (!confirm('Удалить услугу?')) return;
    router.delete(route('profile.services.destroy', id), { preserveScroll: true });
}

function toggleActive(item) {
    router.patch(route('profile.services.update', item.id), {
        is_active: !item.is_active,
    }, { preserveScroll: true });
}

// ── Would-buy ──────────────────────────────────────────────────
function wouldBuy(serviceId) {
    router.post(route('services.would-buy.store', serviceId), {}, { preserveScroll: true });
}

function isCoolingDown(wouldBuyAt) {
    if (!wouldBuyAt) return false;
    const diff = (Date.now() - new Date(wouldBuyAt).getTime()) / 86400000;
    return diff < props.wouldBuyCooldownDays;
}

// ── Computed ───────────────────────────────────────────────────
const isEmpty = computed(() => Array.isArray(props.services) && props.services.length === 0);
const allItems = computed(() => {
    if (!Array.isArray(props.services)) return [];
    return props.services.flatMap(g => g.items);
});
</script>

<template>
    <div class="services-wrap">

        <!-- Loading skeleton -->
        <template v-if="!Array.isArray(services)">
            <div class="svc-skeleton" v-for="n in 3" :key="n">
                <div class="svc-skeleton__row" />
            </div>
        </template>

        <!-- Owner add button -->
        <div v-else-if="isOwner && isIdol" class="svc-owner-bar">
            <button class="svc-add-btn" @click="openAdd">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="6.5" y1="1" x2="6.5" y2="12"/>
                    <line x1="1" y1="6.5" x2="12" y2="6.5"/>
                </svg>
                Добавить услугу
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="Array.isArray(services) && isEmpty" class="svc-empty">
            <p class="svc-empty__title">Айдол пока не добавил услуги</p>
        </div>

        <!-- Service groups -->
        <template v-else-if="Array.isArray(services)">
            <div v-for="group in services" :key="group.category.id" class="svc-group">
                <p class="svc-group__label">{{ group.category.name }}</p>

                <div class="svc-list">
                    <div
                        v-for="item in group.items"
                        :key="item.id"
                        class="svc-card"
                        :class="{ 'svc-card--inactive': !item.is_active }"
                    >
                        <div class="svc-card__main">
                            <span class="svc-card__name">{{ item.name }}</span>
                            <span class="svc-card__unit">{{ item.time_unit.name }}</span>
                        </div>
                        <div class="svc-card__right">
                            <span class="svc-card__price">{{ item.price.toLocaleString('ru') }} ₽</span>

                            <!-- Owner actions -->
                            <template v-if="isOwner">
                                <button
                                    class="svc-icon-btn"
                                    :class="{ 'svc-icon-btn--on': item.is_active, 'svc-icon-btn--off': !item.is_active }"
                                    :title="item.is_active ? 'Отключить' : 'Включить'"
                                    @click="toggleActive(item)"
                                >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line v-if="!item.is_active" x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                    </svg>
                                </button>
                                <button class="svc-icon-btn" title="Редактировать" @click="openEdit(item)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button class="svc-icon-btn svc-icon-btn--danger" title="Удалить" @click="deleteService(item.id)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14H6L5 6"/>
                                        <path d="M10 11v6M14 11v6"/>
                                        <path d="M9 6V4h6v2"/>
                                    </svg>
                                </button>
                            </template>

                            <!-- Encore would-buy -->
                            <template v-else>
                                <button
                                    class="svc-would-buy-btn"
                                    :class="{ 'svc-would-buy-btn--done': isCoolingDown(item.would_buy_at) }"
                                    :disabled="isCoolingDown(item.would_buy_at)"
                                    :title="isCoolingDown(item.would_buy_at) ? 'Вы уже голосовали' : 'Купил бы, если бы было дешевле'"
                                    @click="wouldBuy(item.id)"
                                >
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>{{ isCoolingDown(item.would_buy_at) ? 'Сигнал отправлен' : 'Купил бы дешевле' }}</span>
                                    <span v-if="item.would_buy_count > 0" class="svc-would-buy-count">{{ item.would_buy_count }}</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Add/Edit modal -->
        <Teleport to="body">
            <Transition name="fade-overlay">
                <div v-if="showForm" class="svc-overlay" @click.self="closeForm">
                    <div class="svc-modal">
                        <div class="svc-modal__header">
                            <span>{{ editingId ? 'Редактировать услугу' : 'Новая услуга' }}</span>
                            <button class="svc-modal__close" @click="closeForm">
                                <svg width="14" height="14" viewBox="0 0 14 14" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <line x1="1" y1="1" x2="13" y2="13"/>
                                    <line x1="13" y1="1" x2="1" y2="13"/>
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitForm" class="svc-modal__body">
                            <div class="svc-field">
                                <label class="svc-label">Категория</label>
                                <AppSelect
                                    v-model="form.category_id"
                                    :options="(serviceCategories ?? []).map(c => ({ value: c.id, label: c.name }))"
                                    placeholder="Выберите категорию"
                                    :error="!!form.errors.category_id"
                                />
                                <p v-if="form.errors.category_id" class="svc-err">{{ form.errors.category_id }}</p>
                            </div>

                            <div class="svc-field">
                                <label class="svc-label">Название</label>
                                <input v-model="form.name" class="svc-input" :class="{ 'svc-input--err': form.errors.name }" placeholder="Игра в CS2" maxlength="120" />
                                <p v-if="form.errors.name" class="svc-err">{{ form.errors.name }}</p>
                            </div>

                            <div class="svc-field-row">
                                <div class="svc-field">
                                    <label class="svc-label">Цена (₽)</label>
                                    <input v-model.number="form.price" type="number" min="1" class="svc-input" :class="{ 'svc-input--err': form.errors.price }" placeholder="500" />
                                    <p v-if="form.errors.price" class="svc-err">{{ form.errors.price }}</p>
                                </div>
                                <div class="svc-field">
                                    <label class="svc-label">Единица</label>
                                    <AppSelect
                                        v-model="form.time_unit_id"
                                        :options="(serviceTimeUnits ?? []).map(u => ({ value: u.id, label: u.name }))"
                                        placeholder="За..."
                                        :error="!!form.errors.time_unit_id"
                                    />
                                    <p v-if="form.errors.time_unit_id" class="svc-err">{{ form.errors.time_unit_id }}</p>
                                </div>
                            </div>

                            <div class="svc-modal__actions">
                                <button type="button" class="svc-btn-cancel" @click="closeForm">Отмена</button>
                                <button type="submit" class="svc-btn-submit" :disabled="form.processing">
                                    {{ editingId ? 'Сохранить' : 'Добавить' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* ── Skeleton ─────────────────────────────────────────────── */
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position:  400px 0; }
}
.svc-skeleton { margin-bottom: 0.5rem; }
.svc-skeleton__row {
    height: 52px;
    border-radius: 3px;
    background: linear-gradient(90deg, rgba(255,255,255,0.04) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.04) 75%);
    background-size: 800px 100%;
    animation: shimmer 1.4s infinite linear;
}

/* ── Wrap ─────────────────────────────────────────────────── */
.services-wrap { display: flex; flex-direction: column; gap: 1rem; }

/* ── Owner bar ────────────────────────────────────────────── */
.svc-owner-bar { display: flex; justify-content: flex-end; }
.svc-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.9rem;
    border: 1px solid rgba(190,145,255,0.35);
    border-radius: 3px;
    background: rgba(190,145,255,0.06);
    color: rgba(190,145,255,0.85);
    font-family: inherit;
    font-size: 0.78rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.svc-add-btn:hover {
    border-color: rgba(190,145,255,0.6);
    background: rgba(190,145,255,0.12);
}

/* ── Empty ────────────────────────────────────────────────── */
.svc-empty {
    padding: 3rem 2rem;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
}
.svc-empty__title {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.25);
    margin: 0;
}

/* ── Group ────────────────────────────────────────────────── */
.svc-group { }
.svc-group__label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(190,145,255,0.5);
    margin: 0 0 0.4rem;
    padding: 0 0.1rem;
}

/* ── List & Card ──────────────────────────────────────────── */
.svc-list { display: flex; flex-direction: column; gap: 0; }

.svc-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    background: #06060e;
    border: 1px solid rgba(255,255,255,0.1);
    border-top: none;
    transition: background 0.15s;
}
.svc-card:first-child { border-top: 1px solid rgba(255,255,255,0.1); border-radius: 3px 3px 0 0; }
.svc-card:last-child  { border-radius: 0 0 3px 3px; }
.svc-card:only-child  { border-radius: 3px; }
.svc-card:hover       { background: rgba(255,255,255,0.02); }

.svc-card--inactive { opacity: 0.45; }

.svc-card__main {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.svc-card__name {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.85);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.svc-card__unit {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.3);
}

.svc-card__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.svc-card__price {
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    white-space: nowrap;
}

/* ── Icon buttons ─────────────────────────────────────────── */
.svc-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.svc-icon-btn:hover { border-color: rgba(255,255,255,0.3); color: rgba(255,255,255,0.8); }
.svc-icon-btn--on  { color: rgba(74,222,128,0.7); border-color: rgba(74,222,128,0.25); }
.svc-icon-btn--off { color: rgba(255,255,255,0.2); }
.svc-icon-btn--danger:hover { border-color: rgba(239,68,68,0.5); color: rgba(239,68,68,0.8); }

/* ── Would-buy button ─────────────────────────────────────── */
.svc-would-buy-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border: 1px solid rgba(190,145,255,0.3);
    border-radius: 3px;
    background: rgba(190,145,255,0.05);
    color: rgba(190,145,255,0.75);
    font-family: inherit;
    font-size: 0.72rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
    white-space: nowrap;
}
.svc-would-buy-btn:hover:not(:disabled) {
    border-color: rgba(190,145,255,0.6);
    background: rgba(190,145,255,0.12);
    color: rgba(190,145,255,1);
}
.svc-would-buy-btn--done {
    border-color: rgba(74,222,128,0.25);
    background: rgba(74,222,128,0.05);
    color: rgba(74,222,128,0.6);
    cursor: default;
}
.svc-would-buy-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 99px;
    background: rgba(190,145,255,0.15);
    font-size: 0.65rem;
    font-weight: 700;
}

/* ── Modal overlay ────────────────────────────────────────── */
.svc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.svc-modal {
    background: #0a0a0f;
    border: 1px solid rgba(190,145,255,0.25);
    border-radius: 3px;
    width: 100%;
    max-width: 420px;
    margin: 1rem;
}

.svc-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
}
.svc-modal__close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    border-radius: 3px;
    transition: color 0.15s;
}
.svc-modal__close:hover { color: rgba(255,255,255,0.7); }

.svc-modal__body {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* ── Form fields ──────────────────────────────────────────── */
.svc-field { display: flex; flex-direction: column; gap: 0.3rem; }
.svc-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.svc-label {
    font-size: 0.72rem;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
}

.svc-input {
    padding: 0.5rem 0.7rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    color: rgba(255,255,255,0.85);
    font-family: inherit;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.15s;
    appearance: none;
    width: 100%;
    box-sizing: border-box;
}
.svc-input:focus { border-color: rgba(190,145,255,0.45); }
.svc-input--err  { border-color: rgba(239,68,68,0.5); }

.svc-err { font-size: 0.75rem; color: rgba(239,68,68,0.8); margin: 0; }

/* ── Modal actions ────────────────────────────────────────── */
.svc-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding-top: 0.25rem;
}
.svc-btn-cancel {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 3px;
    background: transparent;
    color: rgba(255,255,255,0.4);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.svc-btn-cancel:hover { border-color: rgba(255,255,255,0.25); color: rgba(255,255,255,0.7); }

.svc-btn-submit {
    padding: 0.5rem 1.2rem;
    border: 1px solid rgba(190,145,255,0.45);
    border-radius: 3px;
    background: linear-gradient(135deg, rgba(155,110,232,0.3) 0%, rgba(124,45,126,0.2) 100%);
    color: rgba(255,255,255,0.9);
    font-family: inherit;
    font-size: 0.82rem;
    cursor: pointer;
    transition: box-shadow 0.15s, border-color 0.15s;
}
.svc-btn-submit:hover:not(:disabled) {
    border-color: rgba(190,145,255,0.7);
    box-shadow: 0 0 12px rgba(155,110,232,0.25);
}
.svc-btn-submit:disabled { opacity: 0.5; cursor: default; }

/* ── Transition ───────────────────────────────────────────── */
.fade-overlay-enter-active,
.fade-overlay-leave-active { transition: opacity 0.18s ease; }
.fade-overlay-enter-from,
.fade-overlay-leave-to     { opacity: 0; }
</style>
