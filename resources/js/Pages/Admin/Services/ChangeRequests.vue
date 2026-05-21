<script setup>
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    requests: Object,
});

function switchTab(tab) {
    if (tab === 'categories')   router.visit(route('admin.services.categories.index'),   { preserveState: false });
    if (tab === 'time-units')   router.visit(route('admin.services.time-units.index'),   { preserveState: false });
    if (tab === 'price-limits') router.visit(route('admin.services.price-limits.index'), { preserveState: false });
    if (tab === 'moderation')   router.visit(route('admin.services.moderation.index'),   { preserveState: false });
    if (tab === 'change-requests') router.visit(route('admin.services.change-requests.index'), { preserveState: false });
}
</script>

<template>
    <div>
        <!-- Tabs -->
        <div class="tabs-nav">
            <button class="tab-btn" @click="switchTab('categories')">Категории</button>
            <button class="tab-btn" @click="switchTab('time-units')">Ед. времени</button>
            <button class="tab-btn" @click="switchTab('price-limits')">Лимиты цен</button>
            <button class="tab-btn" @click="switchTab('moderation')">Модерация</button>
            <button class="tab-btn tab-btn--active" @click="switchTab('change-requests')">Изменения</button>
        </div>

        <div class="page-header">
            <h1 class="page-title">Запросы на изменение услуг</h1>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Айдол</th>
                        <th>Услуга</th>
                        <th>Поля</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests.data" :key="r.id">
                        <td>
                            <div class="mod-user-cell">
                                <img v-if="r.service?.user?.avatar_url" :src="r.service.user.avatar_url" class="mod-avatar" alt="" />
                                <div>
                                    <div class="mod-name">{{ r.service?.user?.name || '—' }}</div>
                                    <div class="mod-email">{{ r.service?.user?.email || '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ r.service?.name || '—' }}</td>
                        <td>
                            <div class="changed-fields">
                                <span v-for="f in r.changed_fields" :key="f" class="field-tag">{{ f }}</span>
                            </div>
                        </td>
                        <td>
                            <span :class="['badge', `badge--${r.status}`]">
                                {{ r.status === 'pending' ? 'Ожидает' : r.status === 'has_remarks' ? 'Замечания' : r.status === 'approved' ? 'Одобрено' : 'Отклонено' }}
                            </span>
                        </td>
                        <td>{{ new Date(r.created_at).toLocaleDateString('ru') }}</td>
                        <td>
                            <div class="actions">
                                <Link :href="route('admin.services.change-requests.show', r.id)" class="btn-edit">Проверить</Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!requests.data.length">
                        <td colspan="6" class="empty-row">Запросов нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="requests.last_page > 1">
            <Link
                v-for="link in requests.links"
                :key="link.label"
                :href="link.url || '#'"
                class="page-link"
                :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                v-html="link.label"
            />
        </div>
    </div>
</template>

<style scoped>
/* Tabs */
.tabs-nav {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 1.5rem;
}
.tab-btn {
    padding: 0.6rem 1.25rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: rgba(255,255,255,0.4);
    font-size: 0.88rem;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.15s;
    margin-bottom: -1px;
}
.tab-btn:hover { color: rgba(255,255,255,0.75); }
.tab-btn--active { color: #9B6EE8; border-bottom-color: #9B6EE8; }

/* Header */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }

/* Table */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    text-align: left;
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.data-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    color: rgba(255,255,255,0.8);
    font-size: 0.93rem;
}
.data-table tr:hover td { background: rgba(255,255,255,0.03); }
.empty-row { text-align: center; color: rgba(255,255,255,0.25); padding: 2rem; }

.badge { padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
.badge--pending  { background: rgba(251,146,60,0.12); color: rgba(251,146,60,0.9); }
.badge--approved { background: rgba(74,222,128,0.1);  color: rgba(74,222,128,0.8); }
.badge--rejected { background: rgba(239,68,68,0.1);   color: rgba(239,68,68,0.7); }
.badge--has_remarks { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }

.actions { display: flex; gap: 0.5rem; }
.btn-edit { padding: 0.3rem 0.7rem; border-radius: 3px; font-size: 0.78rem; cursor: pointer; font-family: inherit; transition: background 0.15s; border: 1px solid rgba(255,255,255,0.15); background: transparent; color: rgba(255,255,255,0.6); text-decoration: none; }
.btn-edit:hover { background: rgba(255,255,255,0.08); }

.mod-user-cell { display: flex; align-items: center; gap: 0.6rem; }
.mod-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; background: rgba(155,110,232,0.15); flex-shrink: 0; }
.mod-name  { font-size: 0.85rem; color: rgba(255,255,255,0.85); font-weight: 500; }
.mod-email { font-size: 0.75rem; color: rgba(255,255,255,0.3); }

.changed-fields { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.field-tag { font-size: 0.7rem; background: rgba(155,110,232,0.1); color: #9B6EE8; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: uppercase; border: 1px solid rgba(155,110,232,0.2); }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link { padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer; }
.page-link--active   { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
