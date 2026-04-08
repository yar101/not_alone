<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    logs:   Object,
    idols:  Array,
    filter: Object,
});

const EVENT_LABELS = {
    review_5star:            'Отзыв 5★',
    review_4star:            'Отзыв 4★',
    review_3star:            'Отзыв 3★',
    review_2star:            'Отзыв 2★',
    review_1star:            'Отзыв 1★',
    order_completed:         'Заказ выполнен',
    report_accepted:         'Репорт принят',
    review_dispute_approved: 'Спор выигран',
    admin_manual:            'Ручное изменение',
};

const idolId   = ref(props.filter?.idol_id   ?? '');
const dateFrom = ref(props.filter?.date_from ?? '');
const dateTo   = ref(props.filter?.date_to   ?? '');

function applyFilter() {
    router.get(route('admin.rating-logs.index'), {
        idol_id:   idolId.value   || undefined,
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value   || undefined,
    }, { preserveState: false });
}

function resetFilter() {
    idolId.value   = '';
    dateFrom.value = '';
    dateTo.value   = '';
    router.get(route('admin.rating-logs.index'), {}, { preserveState: false });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div>
        <h1 class="page-title">Логи рейтинга айдолов</h1>

        <!-- Filters -->
        <div class="filters">
            <select v-model="idolId" class="input input--select">
                <option value="">Все айдолы</option>
                <option v-for="idol in idols" :key="idol.id" :value="idol.id">{{ idol.name }}</option>
            </select>

            <input v-model="dateFrom" type="date" class="input" placeholder="От" />
            <input v-model="dateTo"   type="date" class="input" placeholder="До" />

            <button class="btn-apply" @click="applyFilter">Применить</button>
            <button class="btn-reset" @click="resetFilter">Сбросить</button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Айдол</th>
                        <th>Событие</th>
                        <th>Дельта</th>
                        <th>Примечание</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td>
                            <Link v-if="log.idol" :href="route('admin.users.show', log.idol.id)" class="user-link">
                                {{ log.idol.name }}
                            </Link>
                            <span v-else class="muted">—</span>
                        </td>
                        <td class="event-cell">{{ EVENT_LABELS[log.event] ?? log.event }}</td>
                        <td>
                            <span :class="['delta-badge', log.delta > 0 ? 'delta--pos' : 'delta--neg']">
                                {{ log.delta > 0 ? '+' : '' }}{{ log.delta }}
                            </span>
                        </td>
                        <td class="note-cell">{{ log.note ?? '—' }}</td>
                        <td class="muted">{{ formatDate(log.created_at) }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="5" class="empty-row">Записей нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="logs.last_page > 1">
            <a
                v-for="link in logs.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
                @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
            />
        </div>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.25rem; }

.filters { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: center; }
.input { padding: 0.4rem 0.7rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.85); font-family: inherit; font-size: 0.88rem; outline: none; }
.input--select { min-width: 180px; }
.input:focus { border-color: rgba(190,145,255,0.45); }
.btn-apply { padding: 0.4rem 0.9rem; border: 1px solid rgba(190,145,255,0.45); background: rgba(190,145,255,0.1); color: rgba(255,255,255,0.9); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-apply:hover { background: rgba(190,145,255,0.2); }
.btn-reset { padding: 0.4rem 0.9rem; border: 1px solid rgba(255,255,255,0.12); background: transparent; color: rgba(255,255,255,0.4); font-family: inherit; font-size: 0.82rem; cursor: pointer; }
.btn-reset:hover { color: rgba(255,255,255,0.7); }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 0.75rem 1.25rem; font-size: 0.82rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; border-bottom: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.025); }
.data-table td { padding: 0.9rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.93rem; color: rgba(255,255,255,0.75); vertical-align: middle; }
.data-table tr:hover td { background: rgba(255,255,255,0.03); }

.user-link { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.88rem; }
.user-link:hover { color: #9B6EE8; }
.event-cell { color: rgba(255,255,255,0.55); font-size: 0.88rem; }
.note-cell { color: rgba(255,255,255,0.4); font-size: 0.82rem; max-width: 260px; }
.muted { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2.5rem; }

.delta-badge { font-size: 0.82rem; font-weight: 700; padding: 0.1rem 0.4rem; }
.delta--pos { color: #4ade80; background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.2); }
.delta--neg { color: #f87171; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
