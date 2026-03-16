<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    metrics:               Object,
    recent_applications:   Array,
    recent_broadcasts:     Array,
    registrations_by_day:  Array,
    top_idols:             Array,
    quiz_stats:            Object,
});

const maxReg = computed(() => {
    if (!props.registrations_by_day?.length) return 1;
    return Math.max(1, ...props.registrations_by_day.map(d => d.count));
});

function barHeight(count) {
    return Math.max(4, Math.round((count / maxReg.value) * 100));
}

function formatDate(str) {
    return new Date(str).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

const statusLabel = {
    pending:  { text: 'На рассмотрении', cls: 'status--pending' },
    approved: { text: 'Одобрена',        cls: 'status--approved' },
    rejected: { text: 'Отклонена',       cls: 'status--rejected' },
};

const targetLabel = {
    all:      'Все',
    user:     'Пользователь',
    filtered: 'По фильтру',
};
</script>

<template>
    <div>
        <h1 class="page-title">Дашборд</h1>

        <!-- Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value metric-value--accent">{{ metrics.pending_applications_count }}</div>
                <div class="metric-label">Заявки на рассмотрении</div>
                <Link :href="route('admin.applications.index')" class="metric-link">Перейти →</Link>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ metrics.total_users.toLocaleString('ru') }}</div>
                <div class="metric-label">Всего пользователей</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ metrics.total_idols }}</div>
                <div class="metric-label">Всего айдолов</div>
                <Link :href="route('admin.users.index', { is_idol: 1 })" class="metric-link">Управлять →</Link>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ metrics.users_with_cooldown }}</div>
                <div class="metric-label">Пользователей с кулдауном</div>
                <Link :href="route('admin.users.index')" class="metric-link">Управлять →</Link>
            </div>
        </div>

        <div class="tables-row">
            <!-- Recent applications -->
            <div class="table-section">
                <div class="section-header">
                    <h2 class="section-title">Последние заявки</h2>
                    <Link :href="route('admin.applications.index')" class="section-link">Все →</Link>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="app in recent_applications" :key="app.id">
                            <td>
                                <Link :href="route('admin.applications.show', app.id)" class="app-link">
                                    {{ app.user.name }}
                                </Link>
                            </td>
                            <td>
                                <span :class="['status-badge', statusLabel[app.status]?.cls]">
                                    {{ statusLabel[app.status]?.text ?? app.status }}
                                </span>
                            </td>
                            <td class="muted">{{ formatDate(app.created_at) }}</td>
                        </tr>
                        <tr v-if="!recent_applications.length">
                            <td colspan="3" class="empty-row">Заявок нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Recent broadcasts -->
            <div class="table-section">
                <div class="section-header">
                    <h2 class="section-title">Последние рассылки</h2>
                    <Link :href="route('admin.messages.index')" class="section-link">Все →</Link>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Заголовок</th>
                            <th>Аудитория</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in recent_broadcasts" :key="b.id">
                            <td class="broadcast-title">{{ b.title }}</td>
                            <td class="muted">{{ targetLabel[b.target] ?? b.target }}</td>
                            <td class="muted">{{ formatDate(b.created_at) }}</td>
                        </tr>
                        <tr v-if="!recent_broadcasts.length">
                            <td colspan="3" class="empty-row">Рассылок нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analytics sections -->
        <div class="analytics-row">
            <!-- Registrations chart -->
            <div class="analytics-card">
                <h2 class="section-title">Регистрации за 14 дней</h2>
                <div class="bar-chart" v-if="registrations_by_day?.length">
                    <div
                        v-for="day in registrations_by_day"
                        :key="day.date"
                        class="bar-col"
                        :title="`${day.date}: ${day.count}`"
                    >
                        <div class="bar-fill" :style="{ height: barHeight(day.count) + '%' }"></div>
                        <div class="bar-label">{{ new Date(day.date).getDate() }}</div>
                    </div>
                </div>
                <div v-else class="empty-chart">Нет данных</div>
            </div>

            <!-- Quiz funnel -->
            <div class="analytics-card" v-if="quiz_stats">
                <h2 class="section-title">Воронка квиза</h2>
                <div class="quiz-stats">
                    <div class="quiz-stat">
                        <div class="quiz-stat__value">{{ quiz_stats.total }}</div>
                        <div class="quiz-stat__label">Всего сессий</div>
                    </div>
                    <div class="quiz-stat">
                        <div class="quiz-stat__value quiz-stat__value--pass">{{ quiz_stats.passed }}</div>
                        <div class="quiz-stat__label">Пройдено</div>
                    </div>
                    <div class="quiz-stat">
                        <div class="quiz-stat__value quiz-stat__value--fail">{{ quiz_stats.failed }}</div>
                        <div class="quiz-stat__label">Провалено</div>
                    </div>
                </div>
                <div class="quiz-rate">Процент прохождения: <strong>{{ quiz_stats.pass_rate }}%</strong></div>
            </div>
        </div>

        <!-- Top idols -->
        <div class="table-section" v-if="top_idols?.length" style="margin-top: 1.5rem;">
            <div class="section-header">
                <h2 class="section-title">Топ-5 айдолов по рейтингу</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Айдол</th>
                        <th>Рейтинг</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(idol, i) in top_idols" :key="i">
                        <td class="muted">{{ i + 1 }}</td>
                        <td>{{ idol.name }}</td>
                        <td>
                            <div class="top-rating-row">
                                <span class="top-rating-val">{{ idol.rating ?? '—' }}</span>
                                <div class="top-rating-bar">
                                    <div class="top-rating-fill" :style="{ width: (idol.rating ?? 0) + '%' }"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.page-title { font-size: 1.4rem; color: #fff; margin: 0 0 1.75rem; }

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.metric-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.metric-value--accent { color: #9B6EE8; }

.metric-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.35);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.25rem;
}

.metric-link {
    font-size: 0.75rem;
    color: rgba(155,110,232,0.7);
    text-decoration: none;
    margin-top: 0.35rem;
}
.metric-link:hover { color: #9B6EE8; }

.tables-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.table-section {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.section-title { font-size: 0.88rem; color: rgba(255,255,255,0.7); margin: 0; font-weight: 600; }
.section-link { font-size: 0.78rem; color: rgba(155,110,232,0.65); text-decoration: none; }
.section-link:hover { color: #9B6EE8; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    text-align: left;
    padding: 0.5rem 1rem;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.data-table td {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 0.83rem;
    color: rgba(255,255,255,0.75);
}
.data-table tr:last-child td { border-bottom: none; }

.app-link { color: rgba(255,255,255,0.8); text-decoration: none; }
.app-link:hover { color: #9B6EE8; }

.status-badge {
    font-size: 0.68rem;
    padding: 0.12rem 0.45rem;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.status--pending  { background: rgba(251,183,64,0.1);  color: #fbb740; border: 1px solid rgba(251,183,64,0.25); }
.status--approved { background: rgba(74,222,128,0.1);  color: #4ade80; border: 1px solid rgba(74,222,128,0.25); }
.status--rejected { background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.25); }

.muted { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
.broadcast-title {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.empty-row { text-align: center; color: rgba(255,255,255,0.2); padding: 1.5rem; }

/* Analytics */
.analytics-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.analytics-card {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    padding: 1rem;
}

.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 100px;
    padding-top: 0.5rem;
}

.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    height: 100%;
}

.bar-fill {
    width: 100%;
    background: rgba(155,110,232,0.5);
    border-radius: 2px 2px 0 0;
    transition: height 0.3s;
    min-height: 4px;
}

.bar-label {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.25);
}

.empty-chart {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.2);
    padding: 2rem 0;
    text-align: center;
}

.quiz-stats {
    display: flex;
    gap: 1.5rem;
    margin: 0.75rem 0;
}

.quiz-stat { display: flex; flex-direction: column; align-items: center; gap: 0.2rem; }
.quiz-stat__value { font-size: 1.8rem; font-weight: 700; color: rgba(255,255,255,0.9); font-variant-numeric: tabular-nums; }
.quiz-stat__value--pass { color: rgba(74,222,128,0.9); }
.quiz-stat__value--fail { color: rgba(239,68,68,0.85); }
.quiz-stat__label { font-size: 0.72rem; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.06em; }
.quiz-rate { font-size: 0.82rem; color: rgba(255,255,255,0.45); margin-top: 0.5rem; }

.top-rating-row { display: flex; align-items: center; gap: 0.75rem; }
.top-rating-val { font-size: 0.9rem; font-weight: 700; color: rgba(190,145,255,0.85); min-width: 30px; }
.top-rating-bar { flex: 1; height: 4px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; max-width: 120px; }
.top-rating-fill { height: 100%; background: rgba(155,110,232,0.6); border-radius: 99px; }

@media (max-width: 900px) {
    .metrics-grid { grid-template-columns: repeat(2, 1fr); }
    .tables-row { grid-template-columns: 1fr; }
    .analytics-row { grid-template-columns: 1fr; }
}
</style>
