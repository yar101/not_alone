<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    idols:  Object,
    filter: Object,
});

const search = ref(props.filter?.q ?? '');
const ratingInputs = ref({});
let searchTimer = null;

function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.idols.index'), { q: search.value || undefined }, {
            preserveState: true,
            replace: true,
        });
    }, 300);
}

function adjustRating(userId, delta) {
    const note = ratingInputs.value[userId] || null;
    router.patch(route('admin.users.rating.update', userId), { delta, note }, {
        preserveScroll: true,
        onSuccess: () => { ratingInputs.value[userId] = ''; },
    });
}

function ratingColor(rating) {
    if (rating === null || rating === undefined) return 'rgba(255,255,255,0.3)';
    if (rating >= 60) return 'rgba(74,222,128,0.85)';
    if (rating >= 30) return 'rgba(251,183,64,0.85)';
    return 'rgba(239,68,68,0.85)';
}

function ratingBarColor(rating) {
    if (rating === null || rating === undefined) return 'rgba(255,255,255,0.1)';
    if (rating >= 60) return 'rgba(74,222,128,0.6)';
    if (rating >= 30) return 'rgba(251,183,64,0.6)';
    return 'rgba(239,68,68,0.6)';
}
</script>

<template>
    <div>
        <div class="page-header">
            <h1 class="page-title">Рейтинг айдолов</h1>
            <span class="page-count">{{ idols.total }} айдолов</span>
        </div>

        <div class="search-wrap">
            <input
                v-model="search"
                @input="onSearch"
                class="search-input"
                placeholder="Поиск по имени или email..."
            />
        </div>

        <div class="table-wrap">
            <table class="idols-table">
                <thead>
                    <tr>
                        <th>Айдол</th>
                        <th>Рейтинг</th>
                        <th>Изменить рейтинг</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="idol in idols.data" :key="idol.id">
                        <td>
                            <div class="user-cell">
                                <img v-if="idol.avatar_url" :src="idol.avatar_url" class="user-avatar" alt="" />
                                <div v-else class="user-avatar user-avatar--initials">{{ idol.name[0] }}</div>
                                <div>
                                    <div class="user-name">{{ idol.name }}</div>
                                    <div class="user-email">{{ idol.email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="rating-cell">
                                <span class="rating-num" :style="{ color: ratingColor(idol.rating) }">
                                    {{ idol.rating ?? '—' }}
                                </span>
                                <div class="rating-bar-track">
                                    <div
                                        class="rating-bar-fill"
                                        :style="{
                                            width: (idol.rating ?? 0) + '%',
                                            background: ratingBarColor(idol.rating),
                                        }"
                                    />
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <button @click="adjustRating(idol.id, 5)"   class="btn-add">+5</button>
                                <button @click="adjustRating(idol.id, 1)"   class="btn-add">+1</button>
                                <button @click="adjustRating(idol.id, -1)"  class="btn-sub">-1</button>
                                <button @click="adjustRating(idol.id, -5)"  class="btn-sub">-5</button>
                                <input
                                    v-model="ratingInputs[idol.id]"
                                    class="note-input"
                                    placeholder="Причина..."
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!idols.data.length">
                        <td colspan="3" class="empty-row">Айдолов не найдено</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" v-if="idols.last_page > 1">
            <a
                v-for="link in idols.links"
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
.page-header { display: flex; align-items: baseline; gap: 1rem; margin-bottom: 1.25rem; }
.page-title  { font-size: 1.4rem; color: #fff; margin: 0; }
.page-count  { font-size: 0.82rem; color: rgba(255,255,255,0.3); }

.search-wrap { margin-bottom: 1.25rem; }
.search-input {
    width: 100%; max-width: 380px;
    padding: 0.5rem 0.85rem;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: #fff; font-size: 0.875rem; outline: none; font-family: inherit;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(155,110,232,0.6); }

.table-wrap { overflow-x: auto; }
.idols-table { width: 100%; border-collapse: collapse; }
.idols-table th {
    text-align: left; padding: 0.6rem 1rem;
    font-size: 0.72rem; color: rgba(255,255,255,0.35);
    text-transform: uppercase; letter-spacing: 0.07em;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.025);
}
.idols-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    vertical-align: middle;
}
.idols-table tr:hover td { background: rgba(255,255,255,0.03); }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
    background: rgba(155,110,232,0.15); flex-shrink: 0;
}
.user-avatar--initials {
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 600; color: #9B6EE8;
    border: 1px solid rgba(155,110,232,0.35);
}
.user-name  { font-size: 0.9rem; color: rgba(255,255,255,0.88); font-weight: 500; }
.user-email { font-size: 0.78rem; color: rgba(255,255,255,0.35); }

.rating-cell { display: flex; flex-direction: column; gap: 0.35rem; min-width: 120px; }
.rating-num  { font-size: 1.4rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
.rating-bar-track {
    height: 3px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden;
}
.rating-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }

.actions { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
.btn-add, .btn-sub {
    padding: 0.25rem 0.6rem; font-size: 0.8rem;
    cursor: pointer; border: 1px solid; font-family: inherit; border-radius: 2px;
    transition: background 0.12s;
}
.btn-add { border-color: rgba(74,222,128,0.4); color: rgba(74,222,128,0.9); background: rgba(74,222,128,0.06); }
.btn-add:hover { background: rgba(74,222,128,0.15); }
.btn-sub { border-color: rgba(239,68,68,0.4); color: rgba(239,68,68,0.9); background: rgba(239,68,68,0.06); }
.btn-sub:hover { background: rgba(239,68,68,0.15); }

.note-input {
    padding: 0.25rem 0.5rem; border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.7);
    font-family: inherit; font-size: 0.78rem; outline: none; width: 130px; border-radius: 2px;
}
.note-input:focus { border-color: rgba(155,110,232,0.4); }

.empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 3rem; }

.pagination { display: flex; gap: 0.25rem; margin-top: 1.5rem; flex-wrap: wrap; }
.page-link {
    padding: 0.28rem 0.6rem; border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.5); font-size: 0.8rem; text-decoration: none; cursor: pointer;
}
.page-link--active  { border-color: rgba(155,110,232,0.6); color: #9B6EE8; background: rgba(155,110,232,0.1); }
.page-link--disabled { opacity: 0.3; pointer-events: none; }
</style>
