<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Edit } from '@element-plus/icons-vue';
import SiteModal from '@/Components/Site/SiteModal.vue';

const props = defineProps({
    traits: { type: Array, default: () => [] },
    allTraits: { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
    gender: { type: String, default: 'male' },
});

const editModal = ref(false);
const traitSearch = ref('');

const selected = ref(new Set(props.traits.map(t => t.id)));

const form = useForm({ trait_ids: [] });

function applyGender(name, gender) {
    if (gender !== 'female') return name;
    if (name.endsWith('ый')) return name.slice(0, -2) + 'ая';
    if (name.endsWith('ий')) return name.slice(0, -2) + 'ая';
    return name;
}

const filteredTraits = computed(() => {
    if (!traitSearch.value.trim()) return props.allTraits;
    const q = traitSearch.value.toLowerCase();
    return props.allTraits.filter(t => t.name_ru.toLowerCase().includes(q));
});

function toggleTrait(id) {
    if (selected.value.has(id)) selected.value.delete(id);
    else selected.value.add(id);
}

function submit() {
    form.trait_ids = [...selected.value];
    form.patch(route('profile.update.traits'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => editModal.value = false,
    });
}

function openEdit() {
    selected.value = new Set(props.traits.map(t => t.id));
    traitSearch.value = '';
    editModal.value = true;
}
</script>

<template>
    <div id="tour-traits" class="block-card">
        <div class="block-header">
            <h2 class="block-title">Характер</h2>
            <button v-if="isOwner" class="edit-btn" @click="openEdit" title="Редактировать">
                <el-icon><Edit /></el-icon>
            </button>
        </div>

        <div v-if="traits.length" class="tags-row">
            <span v-for="t in traits" :key="t.id" class="tag">{{ applyGender(t.name_ru, gender) }}</span>
        </div>
        <p v-else-if="isOwner" class="empty">Добавь свои черты характера</p>
        <p v-else class="empty">Не указано</p>

        <SiteModal :show="editModal" variant="pink" :compact="true" @close="editModal = false">
            <div class="edit-form">
                <h3 class="edit-title">Характер</h3>
                <p class="edit-hint">Выбери подходящие (до 10)</p>
                <input
                    v-model="traitSearch"
                    type="text"
                    class="search-input"
                    placeholder="Поиск по чертам..."
                />
                <div class="trait-grid">
                    <button
                        v-for="t in filteredTraits"
                        :key="t.id"
                        type="button"
                        class="trait-btn"
                        :class="{ active: selected.has(t.id) }"
                        @click="toggleTrait(t.id)"
                        :disabled="!selected.has(t.id) && selected.size >= 10"
                    >{{ t.name_ru }}</button>
                </div>
                <p v-if="filteredTraits.length === 0" class="no-results">Ничего не найдено</p>
                <button class="save-btn" :disabled="form.processing" @click="submit">
                    Сохранить ({{ selected.size }})
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
.tags-row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tag {
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(200,70,126,0.15) 0%, rgba(140,60,200,0.1) 100%);
    border: 1px solid rgba(200,70,126,0.3);
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    transition: transform 0.15s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.tag:hover {
    transform: scale(1.06);
    background: linear-gradient(135deg, rgba(200,70,126,0.28) 0%, rgba(140,60,200,0.2) 100%);
    border-color: rgba(200,70,126,0.55);
    box-shadow: 0 2px 12px rgba(200,70,126,0.2);
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
.trait-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.trait-btn {
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.trait-btn.active { border-color: rgba(200,70,126,0.5); background: rgba(200,70,126,0.15); color: #fff; }
.trait-btn:disabled:not(.active) { opacity: 0.35; cursor: not-allowed; }
.no-results { color: rgba(255,255,255,0.3); font-size: 0.88rem; text-align: center; padding: 0.5rem 0 1rem; margin: 0; }
.save-btn {
    width: 100%; padding: 0.8rem;
    border-radius: 10px; border: 1px solid rgba(200,70,126,0.35);
    background: linear-gradient(135deg, rgba(200,70,126,0.25), rgba(200,70,126,0.1));
    color: #fff; font-size: 0.95rem; cursor: pointer; font-family: inherit; transition: all 0.2s;
}
.save-btn:hover:not(:disabled) { background: linear-gradient(135deg, rgba(200,70,126,0.38), rgba(200,70,126,0.18)); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
