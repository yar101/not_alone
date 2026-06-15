<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    requests: Object,
});

const FIELD_LABELS = {
    title:       'Название',
    description: 'Описание',
    price:       'Цена',
};

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <div class="cr-index">
        <h1 class="cr-heading">Изменения в паках</h1>

        <div class="cr-table-wrap">
            <table class="cr-table">
                <thead>
                    <tr>
                        <th>Айдол</th>
                        <th>Пак</th>
                        <th>Изменяемые поля</th>
                        <th>Дата запроса</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in requests.data" :key="req.id">
                        <td>
                            <div class="cr-idol-info">
                                <img v-if="req.content_pack?.user?.avatar_url" :src="req.content_pack.user.avatar_url" class="cr-avatar" alt="" />
                                <div v-else class="cr-avatar cr-avatar--empty">
                                    {{ req.content_pack?.user?.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span>{{ req.content_pack?.user?.name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="cr-td-title">{{ req.content_pack?.title ?? '—' }}</td>
                        <td>
                            <span
                                v-for="field in req.changed_fields"
                                :key="field"
                                class="cr-field-chip"
                            >{{ FIELD_LABELS[field] ?? field }}</span>
                        </td>
                        <td>{{ formatDate(req.created_at) }}</td>
                        <td>
                            <Link :href="route('admin.content-packs.change-requests.show', req.id)" class="cr-link">
                                Рассмотреть →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="5" class="cr-empty">Нет ожидающих запросов</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="requests.last_page > 1" class="cr-pagination">
            <Link
                v-for="page in requests.last_page"
                :key="page"
                :href="requests.links?.find(l => l.label == page)?.url"
                class="cr-page-btn"
                :class="{ 'cr-page-btn--active': page === requests.current_page }"
            >{{ page }}</Link>
        </div>
    </div>
</template>

<style scoped>
.cr-index { padding: 1.5rem; }

.cr-heading { font-size: 1.4rem; font-weight: 700; color: rgba(255,255,255,0.9); margin: 0 0 1.25rem; }

.cr-table-wrap { overflow-x: auto; }

.cr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.cr-table th {
    text-align: left;
    padding: 0.6rem 0.75rem;
    color: rgba(255,255,255,0.35);
    font-weight: 500;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.cr-table td {
    padding: 0.7rem 0.75rem;
    vertical-align: middle;
    color: rgba(255,255,255,0.75);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.cr-idol-info { display: flex; align-items: center; gap: 0.5rem; }
.cr-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cr-avatar--empty {
    background: rgba(255, 178, 239,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: rgba(255, 178, 239,0.7);
}

.cr-td-title { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.cr-field-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 500;
    background: rgba(255,200,80,0.15);
    color: #ffc850;
    margin-right: 0.3rem;
}

.cr-link {
    color: rgba(255, 178, 239,0.8);
    text-decoration: none;
    font-size: 0.85rem;
    white-space: nowrap;
}
.cr-link:hover { color: rgba(200,200,255,0.95); }

.cr-empty { text-align: center; color: rgba(255,255,255,0.3); padding: 2rem 0; }

.cr-pagination { display: flex; gap: 0.35rem; margin-top: 1rem; flex-wrap: wrap; }
.cr-page-btn {
    padding: 0.3rem 0.65rem;
    border-radius: 5px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.5);
    font-size: 0.82rem;
    text-decoration: none;
    transition: background 0.15s;
}
.cr-page-btn:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.8); }
.cr-page-btn--active { background: rgba(255, 178, 239,0.15); border-color: rgba(255, 178, 239,0.4); color: rgba(200,200,255,0.9); }
</style>
