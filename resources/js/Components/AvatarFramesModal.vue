<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { usePage } from '@inertiajs/vue3';

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
        const response = await axios.get(route('api.avatar-frames.index'));
        frames.value = response.data.frames;
        unlockedIds.value = response.data.unlocked_ids;
        activeFramePath.value = response.data.active_frame_path;
    } catch (e) {
        ElMessage.error('Не удалось загрузить список рамок');
    } finally {
        loading.value = false;
    }
};

const isUnlocked = (frame) => {
    if (frame.type === 'free') return true;
    return unlockedIds.value.includes(frame.id);
};

const equip = async (frame) => {
    try {
        const res = await axios.post(route('api.avatar-frames.equip', frame.id));
        activeFramePath.value = res.data.active_frame_path;
        // Обновляем глобальный объект пользователя, чтобы рамка обновилась во всех компонентах реактивно
        usePage().props.auth.user.active_frame_path = res.data.active_frame_path;
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || 'Ошибка');
    }
};

const unequip = async () => {
    try {
        const res = await axios.post(route('api.avatar-frames.unequip'));
        activeFramePath.value = null;
        usePage().props.auth.user.active_frame_path = null;
        ElMessage.success(res.data.message);
    } catch (e) {
        ElMessage.error('Ошибка');
    }
};
</script>

<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        title="Мои рамки аватарок"
        width="750px"
        class="frames-modal"
        append-to-body
        @open="fetchFrames"
    >
        <div v-loading="loading" class="frames-container">
            <!-- Секция текущей рамки -->
            <div class="current-frame-section">
                <h3>Текущая рамка</h3>
                <div class="current-preview">
                    <div class="mock-avatar"></div>
                    <img v-if="activeFramePath" :src="'/storage/' + activeFramePath" alt="" class="frame-img" />
                </div>
                <el-button v-if="activeFramePath" type="danger" plain @click="unequip" size="small" style="margin-top: 10px;">
                    Снять рамку
                </el-button>
                <div v-else class="no-frame-text">Рамка не надета</div>
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
                            {{ frame.condition_description }}
                        </div>
                        <div v-if="frame.type === 'paid'" class="frame-desc">
                            {{ frame.price }} ₽
                        </div>
                        <div v-if="frame.type === 'free'" class="frame-desc">
                            Бесплатная
                        </div>
                    </div>

                    <div class="frame-actions">
                        <el-button 
                            v-if="isUnlocked(frame) && activeFramePath !== frame.image_path" 
                            type="primary" 
                            size="small"
                            @click="equip(frame)"
                        >
                            Надеть
                        </el-button>
                        <el-button 
                            v-else-if="activeFramePath === frame.image_path" 
                            type="success" 
                            size="small" 
                            disabled
                        >
                            Надета
                        </el-button>
                        <el-button 
                            v-else 
                            type="info" 
                            size="small" 
                            disabled
                        >
                            Заблокирована
                        </el-button>
                    </div>
                </div>
            </div>
        </div>
    </el-dialog>
</template>

<style scoped>
.frames-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.current-frame-section {
    text-align: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
}
.current-preview {
    width: 120px;
    height: 120px;
    margin: 10px auto 0;
    position: relative;
}
.mock-avatar {
    position: absolute;
    top: 5px;
    left: 5px;
    right: 5px;
    bottom: 5px;
    background: linear-gradient(135deg, #444, #222);
    border-radius: 50%;
    z-index: 1;
}
.mock-avatar-small {
    position: absolute;
    top: 4px;
    left: 4px;
    right: 4px;
    bottom: 4px;
    background: linear-gradient(135deg, #444, #222);
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
.no-frame-text {
    margin-top: 10px;
    font-size: 13px;
    color: #888;
}

.frames-grid-title {
    font-size: 18px;
    font-weight: bold;
    margin-top: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.frames-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}

.frame-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    border: 1px solid transparent;
    transition: 0.2s ease;
}
.frame-card.is-active {
    border-color: var(--el-color-primary);
    background: rgba(var(--el-color-primary-rgb), 0.1);
}
.frame-card.is-locked {
    opacity: 0.5;
    filter: grayscale(100%);
}

.frame-preview-box {
    width: 80px;
    height: 80px;
    position: relative;
    margin-bottom: 15px;
}

.frame-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 6px;
}
.frame-desc {
    font-size: 12px;
    color: #999;
    margin-bottom: 15px;
    line-height: 1.3;
}
.frame-actions {
    margin-top: auto;
}
</style>
