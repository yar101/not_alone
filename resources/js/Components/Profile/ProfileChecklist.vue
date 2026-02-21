<script setup>
import { computed } from 'vue';
import { Check, Minus, Trophy } from '@element-plus/icons-vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const items = computed(() => [
    { key: 'about',     label: 'Заполни "Обо мне"',              done: !!props.user.about },
    { key: 'traits',    label: 'Добавь черты характера',          done: props.user.traits.length > 0 },
    { key: 'interests', label: 'Добавь интересы',                 done: props.user.interests.length > 0 },
    { key: 'voice',     label: 'Запиши голосовое приветствие',    done: !!props.user.voice_url },
    { key: 'languages', label: 'Укажи языки',                     done: props.user.languages.length > 0 },
    { key: 'timezone',  label: 'Укажи часовой пояс',              done: !!props.user.timezone },
]);

const doneCount = computed(() => items.value.filter(i => i.done).length);
const allDone = computed(() => doneCount.value === items.value.length);
const pct = computed(() => Math.round((doneCount.value / items.value.length) * 100));
</script>

<template>
    <div id="tour-checklist" class="checklist-card">
        <div class="checklist-header">
            <div class="checklist-title-row">
                <h2 class="checklist-title">Заполни профиль</h2>
                <span class="checklist-count">{{ doneCount }}/{{ items.length }}</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" :style="{ width: pct + '%' }" />
            </div>
        </div>

        <ul class="checklist-items">
            <li v-for="item in items" :key="item.key" class="checklist-item" :class="{ done: item.done }">
                <el-icon class="item-icon"><component :is="item.done ? Check : Minus" /></el-icon>
                <span class="item-label">{{ item.label }}</span>
            </li>
        </ul>

        <p v-if="allDone" class="checklist-complete"><el-icon class="trophy-icon"><Trophy /></el-icon> Профиль заполнен полностью!</p>
    </div>
</template>

<style scoped>
.checklist-card {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, rgba(200,70,126,0.08) 0%, rgba(140,60,180,0.04) 100%);
    border: 1px solid rgba(200,70,126,0.22);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    cursor: default;
}
.checklist-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(200,70,126,0.6) 50%, transparent 100%);
}
.checklist-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,70,126,0.35);
    box-shadow: 0 8px 28px rgba(0,0,0,0.35), 0 0 0 1px rgba(200,70,126,0.08);
}
.checklist-header { margin-bottom: 1rem; }
.checklist-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
.checklist-title { font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.85); margin: 0; }
.checklist-count { font-size: 0.8rem; color: rgba(200,70,126,0.7); }
.progress-bar { height: 5px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, rgba(200,70,126,0.9) 0%, rgba(140,60,200,0.75) 100%);
    box-shadow: 0 0 8px rgba(200,70,126,0.45);
    transition: width 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.checklist-items { list-style: none; padding: 0; margin: 0 0 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.checklist-item { display: flex; align-items: center; gap: 0.6rem; }
.item-icon { font-size: 0.85rem; width: 16px; flex-shrink: 0; transition: color 0.2s, filter 0.2s; color: rgba(255,255,255,0.25); }
.checklist-item.done .item-icon { color: rgba(200,70,126,0.8); filter: drop-shadow(0 0 4px rgba(200,70,126,0.5)); }
.trophy-icon { font-size: 1rem; vertical-align: middle; color: rgba(200,70,126,0.8); filter: drop-shadow(0 0 6px rgba(200,70,126,0.5)); }
.item-label { font-size: 0.88rem; color: rgba(255,255,255,0.55); transition: all 0.2s; }
.checklist-item.done .item-label { color: rgba(255,255,255,0.3); text-decoration: line-through; text-decoration-color: rgba(200,70,126,0.35); }

.checklist-complete { font-size: 0.9rem; color: rgba(200,70,126,0.7); margin: 0; text-align: center; display: flex; align-items: center; justify-content: center; gap: 0.35rem; }
</style>
