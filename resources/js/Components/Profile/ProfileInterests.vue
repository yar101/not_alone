<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { EditPen } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    interests:     { default: null },
    allCategories: { default: null },
    isOwner:       { type: Boolean, default: false },
});

const editModal = ref(false);
const openCat = ref(null);
const interestSearch = ref('');

const selected = ref(new Set(Array.isArray(props.interests) ? props.interests.map(i => i.id) : []));
const form = useForm({ interest_ids: [] });

const filteredCategories = computed(() => {
    if (!Array.isArray(props.allCategories)) return [];
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
    selected.value = new Set(Array.isArray(props.interests) ? props.interests.map(i => i.id) : []);
    interestSearch.value = '';
    openCat.value = null;
    editModal.value = true;
}
</script>

<template>
    <div id="tour-interests" class="block-section">
        <div class="section-header">
            <span class="section-title">Интересы</span>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><EditPen /></el-icon>
            </button>
        </div>

        <div v-if="interests?.length" class="tags-row">
            <span v-for="i in interests" :key="i.id" class="tag">{{ i.name_ru }}</span>
        </div>
        <p v-else-if="isOwner" class="empty">Добавь свои интересы</p>
        <p v-else class="empty">Не указано</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
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
.block-section {
    padding: 1.5rem 2rem;
    position: relative;
    cursor: default;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.9rem;
}

.section-title {
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #FE28A2;
}

.edit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 26px; height: 26px;
    padding: 0;
    border-radius: 3px;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    color: rgba(255,255,255,0.25);
    font-size: 0.95rem;
    opacity: 0;
    transition: opacity 0.18s, color 0.18s, border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.block-section:hover .edit-btn {
    opacity: 1;
    color: rgba(254, 40, 162, 0.8);
    border-color: rgba(254, 40, 162, 0.35);
    background: rgba(254, 40, 162, 0.08);
}
.edit-btn:hover {
    color: #fe28a2;
    border-color: rgba(254, 40, 162, 0.7);
    background: rgba(254, 40, 162, 0.16);
    box-shadow: 0 0 8px rgba(254, 40, 162, 0.35);
}

.tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.tag {
    padding: 0.28rem 0.65rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
}
.empty { color: rgba(255,255,255,0.25); font-size: 1rem; font-style: italic; margin: 0; }

/* ── Форма редактирования ─────────────────────────────────── */
.edit-form { padding: 0.5rem 0.25rem; }
.edit-title { font-size: 1.1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin: 0 0 0.25rem; }
.edit-hint { font-size: 0.82rem; color: rgba(255,255,255,0.35); margin: 0 0 0.75rem; }
.search-input {
    width: 100%; padding: 0.55rem 0.9rem; margin-bottom: 0.75rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 3px; color: rgba(255,255,255,0.88); font-size: 0.9rem; font-family: inherit;
    box-sizing: border-box; outline: none; transition: border-color 0.15s;
}
.search-input::placeholder { color: rgba(255,255,255,0.25); }
.search-input:focus { border-color: rgba(254,40,162,0.5); }
.categories { display: flex; flex-direction: column; gap: 0.2rem; margin-bottom: 1rem; }
.cat-block { border: 1px solid rgba(255,255,255,0.07); border-radius: 3px; }
.cat-header {
    width: 100%; display: flex; align-items: center;
    padding: 0.6rem 0.9rem;
    background: rgba(255,255,255,0.03); border: none; color: rgba(255,255,255,0.75);
    font-size: 0.9rem; cursor: pointer; font-family: inherit; text-align: left;
    border-radius: 3px;
}
.cat-count { color: rgba(254,40,162,0.75); font-size: 0.8rem; margin-left: 0.4rem; }
.cat-arrow { color: rgba(255,255,255,0.35); font-size: 1.1rem; transition: transform 0.2s; margin-left: auto; }
.cat-arrow.open { transform: rotate(90deg); }
.cat-interests { display: flex; flex-wrap: wrap; gap: 0.35rem; padding: 0.6rem 0.9rem; background: rgba(0,0,0,0.12); border-radius: 0 0 3px 3px; }
.interest-btn {
    padding: 0.25rem 0.65rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.interest-btn.active { border-color: rgba(254,40,162,0.55); background: rgba(254,40,162,0.1); color: #fff; }
.interest-btn:disabled:not(.active) { opacity: 0.3; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.9rem; text-align: center; padding: 1rem 0; margin: 0; }
.save-btn {
    width: 100%; padding: 0.75rem;
    border-radius: 3px; border: 1px solid rgba(254,40,162,0.4);
    background: rgba(254,40,162,0.1);
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.save-btn:hover:not(:disabled) { background: rgba(254,40,162,0.2); }
.save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
