<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Edit } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    interests: { type: Array, default: () => [] },
    allCategories: { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
});

const editModal = ref(false);
const openCat = ref(null);
const interestSearch = ref('');

// Group current interests by category
const byCategory = computed(() => {
    const map = {};
    for (const i of props.interests) {
        const cat = i.category.name_ru;
        if (!map[cat]) map[cat] = [];
        map[cat].push(i);
    }
    return map;
});

const selected = ref(new Set(props.interests.map(i => i.id)));

const form = useForm({ interest_ids: [] });

const filteredCategories = computed(() => {
    const q = interestSearch.value.trim().toLowerCase();
    if (!q) return props.allCategories;
    return props.allCategories
        .map(cat => ({
            ...cat,
            interests: cat.interests.filter(i =>
                i.name_ru.toLowerCase().includes(q) ||
                cat.name_ru.toLowerCase().includes(q)
            )
        }))
        .filter(cat => cat.interests.length > 0);
});

const expandedCats = computed(() => {
    if (interestSearch.value.trim()) {
        return new Set(filteredCategories.value.map(c => c.id));
    }
    return openCat.value ? new Set([openCat.value]) : new Set();
});

function toggleInterest(id) {
    if (selected.value.has(id)) selected.value.delete(id);
    else selected.value.add(id);
}

function toggleCat(id) {
    openCat.value = openCat.value === id ? null : id;
}

function submit() {
    form.interest_ids = [...selected.value];
    form.patch(route('profile.update.interests'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function openEdit() {
    selected.value = new Set(props.interests.map(i => i.id));
    interestSearch.value = '';
    openCat.value = null;
    editModal.value = true;
}
</script>

<template>
    <div id="tour-interests" class="block-card">
        <div class="block-header">
            <h2 class="block-title">Интересы</h2>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
        </div>

        <div v-if="interests.length">
            <div v-for="(items, catName) in byCategory" :key="catName" class="cat-group">
                <div class="cat-name">{{ catName }}</div>
                <div class="tags-row">
                    <span v-for="i in items" :key="i.id" class="tag">{{ i.name_ru }}</span>
                </div>
            </div>
        </div>
        <p v-else-if="isOwner" class="empty">Добавь свои интересы</p>
        <p v-else class="empty">Не указано</p>

        <SiteModal :show="editModal" variant="pink" :compact="false" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Интересы</h3>
                <p class="edit-hint">Выбери по категориям (до 10)</p>

                <input
                    v-model="interestSearch"
                    type="text"
                    class="search-input"
                    placeholder="Поиск по интересам..."
                />

                <div class="categories">
                    <template v-if="filteredCategories.length">
                        <div v-for="cat in filteredCategories" :key="cat.id" class="cat-block">
                            <button type="button" class="cat-header" @click="toggleCat(cat.id)">
                                <span>{{ cat.name_ru }}</span>
                                <span class="cat-count" v-if="cat.interests.some(i => selected.has(i.id))">
                                    ({{ cat.interests.filter(i => selected.has(i.id)).length }})
                                </span>
                                <span class="cat-arrow" :class="{ open: expandedCats.has(cat.id) }">›</span>
                            </button>
                            <div v-if="expandedCats.has(cat.id)" class="cat-interests">
                                <button
                                    v-for="i in cat.interests"
                                    :key="i.id"
                                    type="button"
                                    class="interest-btn"
                                    :class="{ active: selected.has(i.id) }"
                                    @click="toggleInterest(i.id)"
                                    :disabled="!selected.has(i.id) && selected.size >= 10"
                                >{{ i.name_ru }}</button>
                            </div>
                        </div>
                    </template>
                    <p v-else class="no-results">Ничего не найдено</p>
                </div>

                <button class="save-btn" :disabled="form.processing" @click="submit">
                    Сохранить ({{ selected.size }}/10)
                </button>
            </div>
        </SiteModal>
    </div>
</template>

<style scoped>
.block-card {
    padding: 1.25rem 1.5rem;
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.2s ease;
    cursor: default;
}
.block-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.4) 40%, rgba(120,70,200,0.3) 70%, transparent 100%);
    border-radius: 16px 16px 0 0;
}
.block-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,70,126,0.2);
    box-shadow: 0 12px 36px rgba(0,0,0,0.4), 0 0 0 1px rgba(200,70,126,0.08), 0 0 40px rgba(200,70,126,0.06);
}
.block-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; min-height: 1.5rem; }
.block-title {
    font-size: 0.72rem; letter-spacing: 0.16em; text-transform: uppercase;
    color: rgba(200,70,126,0.8); margin: 0;
    display: flex; align-items: center; gap: 0.5rem;
}
.block-title::before {
    content: '';
    display: block; width: 3px; height: 12px; border-radius: 2px; flex-shrink: 0;
    background: linear-gradient(180deg, rgba(200,70,126,0.95) 0%, rgba(140,60,200,0.75) 100%);
}
.edit-btn {
    display: flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 7px;
    border: none; background: transparent; cursor: pointer;
    color: rgba(255,255,255,0.25); font-size: 0.95rem; padding: 0;
    opacity: 0;
    transition: opacity 0.2s ease, color 0.2s ease, background 0.2s ease;
}
.block-card:hover .edit-btn { opacity: 1; }
.edit-btn:hover { color: rgba(200,70,126,0.9); background: rgba(200,70,126,0.1); }
.cat-group { margin-bottom: 0.75rem; }
.cat-name { font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 0.35rem; }
.tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.tag {
    padding: 0.3rem 0.75rem; border-radius: 20px;
    background: linear-gradient(135deg, rgba(100,60,200,0.15) 0%, rgba(200,70,126,0.1) 100%);
    border: 1px solid rgba(120,70,200,0.3);
    color: rgba(255,255,255,0.8); font-size: 0.85rem;
    transition: transform 0.15s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.tag:hover {
    transform: scale(1.06);
    background: linear-gradient(135deg, rgba(100,60,200,0.28) 0%, rgba(200,70,126,0.18) 100%);
    border-color: rgba(140,70,220,0.55);
    box-shadow: 0 2px 12px rgba(120,60,200,0.22);
}
.empty { color: rgba(255,255,255,0.25); font-size: 0.9rem; font-style: italic; margin: 0; }

.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.25rem; }
.edit-hint { font-size: 0.8rem; color: rgba(255,255,255,0.35); margin: 0 0 0.75rem; }
.search-input {
    width: 100%; padding: 0.55rem 0.9rem; margin-bottom: 0.75rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px; color: rgba(255,255,255,0.88); font-size: 0.88rem; font-family: inherit;
    box-sizing: border-box; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(200,70,126,0.45); box-shadow: 0 0 0 3px rgba(200,70,126,0.08); }
.categories { display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem; }
.cat-block { border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; }
.cat-header {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    padding: 0.65rem 1rem;
    background: rgba(255,255,255,0.03); border: none; color: rgba(255,255,255,0.75);
    font-size: 0.9rem; cursor: pointer; font-family: inherit; text-align: left;
}
.cat-count { color: rgba(200,70,126,0.7); font-size: 0.8rem; margin-left: 0.5rem; }
.cat-arrow { color: rgba(255,255,255,0.4); font-size: 1.1rem; transition: transform 0.2s; margin-left: auto; }
.cat-arrow.open { transform: rotate(90deg); }
.cat-interests { display: flex; flex-wrap: wrap; gap: 0.4rem; padding: 0.65rem 1rem; background: rgba(0,0,0,0.15); }
.interest-btn {
    padding: 0.3rem 0.75rem; border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 0.83rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.interest-btn.active { border-color: rgba(200,70,126,0.5); background: rgba(200,70,126,0.15); color: #fff; }
.interest-btn:disabled:not(.active) { opacity: 0.3; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.88rem; text-align: center; padding: 1rem 0; margin: 0; }
.save-btn {
    width: 100%; padding: 0.8rem;
    border-radius: 10px; border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
