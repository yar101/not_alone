<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    blocks:      Object,
    top_blocked: Array,
});

function formatDate(dt) {
    if (!dt) return 'Навсегда';
    return new Date(dt).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function goToBan(userId) {
    router.visit(route('admin.users.show', userId));
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Блокировки в чате</h1>
        </div>

        <!-- Топ блокируемых -->
        <section class="section">
            <h2 class="section-title">Топ блокируемых</h2>
            <div v-if="top_blocked.length === 0" class="empty-state">Нет данных</div>
            <div v-else class="top-list">
                <div v-for="(row, i) in top_blocked" :key="row.user.id" class="top-item">
                    <span class="top-rank">#{{ i + 1 }}</span>
                    <div class="top-avatar">
                        <img v-if="row.user.avatar_url" :src="row.user.avatar_url" alt="" />
                        <span v-else>{{ row.user.name?.charAt(0) ?? '?' }}</span>
                    </div>
                    <div class="top-info">
                        <Link :href="route('admin.users.show', row.user.id)" class="top-name">{{ row.user.name }}</Link>
                        <span class="top-count">{{ row.block_count }} блок.</span>
                    </div>
                    <button class="ban-btn" @click="goToBan(row.user.id)">Забанить</button>
                </div>
            </div>
        </section>

        <!-- Таблица блокировок -->
        <section class="section">
            <h2 class="section-title">Активные блокировки</h2>
            <div v-if="blocks.data.length === 0" class="empty-state">Нет активных блокировок</div>
            <div v-else class="table-wrap">
                <table class="blocks-table">
                    <thead>
                        <tr>
                            <th>Заблокировал</th>
                            <th>Заблокировал кого</th>
                            <th>Причина</th>
                            <th>До</th>
                            <th>Когда</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="block in blocks.data" :key="block.id">
                            <td>
                                <div class="user-cell">
                                    <div class="cell-avatar">
                                        <img v-if="block.blocker?.avatar_url" :src="block.blocker.avatar_url" alt="" />
                                        <span v-else>{{ block.blocker?.name?.charAt(0) ?? '?' }}</span>
                                    </div>
                                    <Link :href="route('admin.users.show', block.blocker_id)" class="cell-name">
                                        {{ block.blocker?.name ?? '—' }}
                                    </Link>
                                </div>
                            </td>
                            <td>
                                <div class="user-cell">
                                    <div class="cell-avatar">
                                        <img v-if="block.blocked?.avatar_url" :src="block.blocked.avatar_url" alt="" />
                                        <span v-else>{{ block.blocked?.name?.charAt(0) ?? '?' }}</span>
                                    </div>
                                    <Link :href="route('admin.users.show', block.blocked_id)" class="cell-name">
                                        {{ block.blocked?.name ?? '—' }}
                                    </Link>
                                </div>
                            </td>
                            <td class="reason-cell">{{ block.reason }}</td>
                            <td class="until-cell">{{ formatDate(block.blocked_until) }}</td>
                            <td class="date-cell">{{ formatDate(block.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <div v-if="blocks.last_page > 1" class="pagination">
                <Link
                    v-for="link in blocks.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="page-link"
                    :class="{
                        'page-link--active': link.active,
                        'page-link--disabled': !link.url,
                    }"
                    v-html="link.label"
                />
            </div>
        </section>
    </div>
</template>

<style scoped>
.page-header {
    margin-bottom: 2rem;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
    margin: 0;
}

.section {
    margin-bottom: 2.5rem;
}
.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    margin: 0 0 1rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.8rem;
}

.empty-state {
    color: rgba(255, 255, 255, 0.28);
    font-size: 0.9rem;
    padding: 1.5rem 0;
}

/* Top list */
.top-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-width: 520px;
}
.top-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 0.65rem 1rem;
}
.top-rank {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.25);
    width: 22px;
    text-align: right;
    flex-shrink: 0;
}
.top-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(110, 110, 210, 0.15);
    border: 1.5px solid rgba(110, 110, 210, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #7070d8;
    font-weight: 700;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.top-avatar img { width: 100%; height: 100%; object-fit: cover; }
.top-info {
    flex: 1;
    min-width: 0;
}
.top-name {
    display: block;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.82);
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.top-name:hover { color: #be91ff; }
.top-count {
    font-size: 0.78rem;
    color: rgba(255, 140, 80, 0.8);
}
.ban-btn {
    background: rgba(220, 50, 80, 0.15);
    border: 1px solid rgba(220, 80, 100, 0.35);
    color: rgba(255, 120, 140, 0.85);
    font-size: 0.8rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s;
    flex-shrink: 0;
}
.ban-btn:hover {
    background: rgba(220, 50, 80, 0.3);
    border-color: rgba(220, 80, 100, 0.6);
}

/* Table */
.table-wrap {
    overflow-x: auto;
}
.blocks-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.blocks-table th {
    text-align: left;
    padding: 0.6rem 0.85rem;
    color: rgba(255, 255, 255, 0.35);
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    white-space: nowrap;
}
.blocks-table td {
    padding: 0.7rem 0.85rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.75);
    vertical-align: middle;
}
.blocks-table tr:last-child td { border-bottom: none; }
.blocks-table tr:hover td { background: rgba(255, 255, 255, 0.02); }

.user-cell {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.cell-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(110, 110, 210, 0.15);
    border: 1px solid rgba(110, 110, 210, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #7070d8;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.cell-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cell-name {
    text-decoration: none;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.88rem;
    white-space: nowrap;
}
.cell-name:hover { color: #be91ff; }

.reason-cell { color: rgba(255, 200, 120, 0.85); }
.until-cell  { color: rgba(255, 255, 255, 0.45); white-space: nowrap; }
.date-cell   { color: rgba(255, 255, 255, 0.3);  white-space: nowrap; }

/* Pagination */
.pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 1.5rem;
}
.page-link {
    padding: 0.3rem 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: rgba(255, 255, 255, 0.45);
    text-decoration: none;
    font-size: 0.82rem;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.page-link:hover:not(.page-link--disabled):not(.page-link--active) {
    border-color: rgba(155, 110, 232, 0.4);
    color: rgba(255, 255, 255, 0.75);
}
.page-link--active {
    border-color: rgba(155, 110, 232, 0.6);
    background: rgba(155, 110, 232, 0.15);
    color: #be91ff;
}
.page-link--disabled {
    opacity: 0.3;
    cursor: default;
    pointer-events: none;
}
</style>
