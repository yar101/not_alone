<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { usePage, router } from '@inertiajs/vue3';
import SiteModal from '@/Components/Site/SiteModal.vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    modelValue: Boolean
});

const emit = defineEmits(['update:modelValue']);

const loading = ref(true);
const frames = ref([]);
const unlockedIds = ref([]);
const activeFramePath = ref(null);

const fetchFrames = async () => {
    try {
        loading.value = true;
        const response = await axios.get(route('avatar-frames.index'));
        frames.value = response.data.frames;
        unlockedIds.value = response.data.unlocked_ids;
        activeFramePath.value = response.data.active_frame_path;
    } catch (e) {
        ElMessage.error('Не удалось загрузить список рамок');
    } finally {
        loading.value = false;
    }
};

watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        fetchFrames();
    }
});

const isUnlocked = (frame) => {
    if (frame.type === 'free') return true;
    return unlockedIds.value.includes(frame.id);
};

const equip = async (frame) => {
    try {
        const res = await axios.post(route('avatar-frames.equip', frame.id));
        activeFramePath.value = res.data.active_frame_path;
        usePage().props.auth.user.active_frame_path = res.data.active_frame_path;
        router.reload({ only: ['user', 'auth'], preserveScroll: true });
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || 'Ошибка');
    }
};

const unequip = async () => {
    try {
        const res = await axios.post(route('avatar-frames.unequip'));
        activeFramePath.value = null;
        usePage().props.auth.user.active_frame_path = null;
        router.reload({ only: ['user', 'auth'], preserveScroll: true });
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error('Ошибка');
    }
};
</script>

<template>
    <SiteModal 
        :show="modelValue" 
        variant="pink" 
        :compact="true" 
        maxWidth="750px"
        @close="$emit('update:modelValue', false)"
    >
        <div class="frames-modal-content">
            <h3 class="frames-modal-title">Мои рамки аватарок</h3>

            <div v-if="loading" class="frames-loading">
                <span class="spinner"></span> Загрузка...
            </div>

            <div v-else class="frames-container">
                <!-- Секция текущей рамки -->
                <div class="current-frame-section">
                    <h4 class="section-subtitle">Текущая рамка</h4>
                    <div class="current-preview">
                        <div class="mock-avatar"></div>
                        <img v-if="activeFramePath" :src="'/storage/' + activeFramePath" alt="" class="frame-img" />
                    </div>
                    <div class="current-actions">
                        <button v-if="activeFramePath" class="btn-unequip" @click="unequip">Снять рамку</button>
                        <div v-else class="no-frame-text">Рамка не надета</div>
                    </div>
                </div>

                <div class="frames-grid-title">Коллекция рамок</div>
                
                <div class="frames-grid">
                    <div 
                        v-for="frame in frames" 
                        :key="frame.id" 
                        class="frame-card"
                        :class="{ 'is-locked': !isUnlocked(frame), 'is-active': activeFramePath === frame.image_path }"
                    >
                        <div class="frame-preview-box">
                            <div class="mock-avatar-small"></div>
                            <img :src="frame.image_url" alt="" class="frame-img" />
                        </div>
                        
                        <div class="frame-info">
                            <div class="frame-name">{{ frame.name }}</div>
                            <div v-if="frame.type === 'achievement'" class="frame-desc">
                                {{ frame.condition_description || 'За достижение' }}
                            </div>
                            <div v-else-if="frame.type === 'paid'" class="frame-desc">
                                {{ frame.price }} ₽
                            </div>
                            <div v-else class="frame-desc">
                                Бесплатная
                            </div>
                        </div>

                        <div class="frame-actions">
                            <button 
                                v-if="isUnlocked(frame) && activeFramePath !== frame.image_path" 
                                class="btn-equip"
                                @click="equip(frame)"
                            >
                                Надеть
                            </button>
                            <button 
                                v-else-if="activeFramePath === frame.image_path" 
                                class="btn-equip is-equipped" 
                                disabled
                            >
                                Надета
                            </button>
                            <button 
                                v-else 
                                class="btn-equip is-disabled" 
                                disabled
                            >
                                Заблокирована
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SiteModal>
</template>

<style scoped>
.frames-modal-content {
    padding: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.frames-modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 1.5rem 0;
    text-align: center;
}

.frames-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: rgba(255, 255, 255, 0.6);
    gap: 10px;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.frames-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.current-frame-section {
    text-align: center;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.section-subtitle {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    font-weight: 500;
}

.current-preview {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    position: relative;
}
.mock-avatar {
    position: absolute;
    top: 5px;
    left: 5px;
    right: 5px;
    bottom: 5px;
    background: linear-gradient(135deg, #3a2e4d, #1a1625);
    border-radius: 50%;
    z-index: 1;
}
.mock-avatar-small {
    position: absolute;
    top: 4px;
    left: 4px;
    right: 4px;
    bottom: 4px;
    background: linear-gradient(135deg, #3a2e4d, #1a1625);
    border-radius: 50%;
    z-index: 1;
}
.frame-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    z-index: 2;
    pointer-events: none;
}

.current-actions {
    margin-top: 1rem;
}

.btn-unequip {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-unequip:hover {
    background: rgba(239, 68, 68, 0.2);
}

.no-frame-text {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.4);
}

.frames-grid-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.frames-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
    max-height: 450px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom Scrollbar for the grid */
.frames-grid::-webkit-scrollbar {
    width: 6px;
}
.frames-grid::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 3px;
}
.frames-grid::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.frame-card {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: 0.2s ease;
}
.frame-card:hover {
    background: rgba(255, 255, 255, 0.06);
}
.frame-card.is-active {
    border-color: rgba(255, 178, 239, 0.5);
    background: rgba(255, 178, 239, 0.05);
}
.frame-card.is-locked {
    opacity: 0.4;
    filter: grayscale(100%);
}

.frame-preview-box {
    width: 80px;
    height: 80px;
    position: relative;
    margin-bottom: 1rem;
}

.frame-name {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}
.frame-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
    line-height: 1.3;
}
.frame-actions {
    margin-top: auto;
    width: 100%;
}

.btn-equip {
    width: 100%;
    background: rgba(255, 178, 239, 0.15);
    color: rgb(255, 178, 239);
    border: 1px solid rgba(255, 178, 239, 0.3);
    padding: 0.6rem 0;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-equip:hover {
    background: rgba(255, 178, 239, 0.25);
}

.btn-equip.is-equipped {
    background: rgba(16, 185, 129, 0.15);
    color: rgb(16, 185, 129);
    border-color: rgba(16, 185, 129, 0.3);
    cursor: default;
}

.btn-equip.is-disabled {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.3);
    border-color: transparent;
    cursor: default;
}
</style>
