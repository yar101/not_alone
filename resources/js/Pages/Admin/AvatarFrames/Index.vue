<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Edit, Plus } from '@element-plus/icons-vue';

const props = defineProps({
    frames: Array,
    conditions: Array,
});

const isDialogVisible = ref(false);
const editingFrame = ref(null);

const form = useForm({
    name: '',
    description: '',
    image: null,
    type: 'free',
    price: 0,
    condition_class: '',
    is_active: true,
});

const imagePreview = ref(null);

const handleImageChange = (uploadFile) => {
    form.image = uploadFile.raw;
    imagePreview.value = URL.createObjectURL(uploadFile.raw);
};

const openCreateDialog = () => {
    editingFrame.value = null;
    form.reset();
    imagePreview.value = null;
    isDialogVisible.value = true;
};

const openEditDialog = (frame) => {
    editingFrame.value = frame;
    form.name = frame.name;
    form.description = frame.description || '';
    form.type = frame.type;
    form.price = frame.price;
    form.condition_class = frame.condition_class || '';
    form.is_active = frame.is_active;
    form.image = null;
    imagePreview.value = frame.image_url;
    isDialogVisible.value = true;
};

const saveFrame = () => {
    if (editingFrame.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PATCH'
        })).post(route('admin.avatar-frames.update', editingFrame.value.id), {
            onSuccess: () => {
                isDialogVisible.value = false;
                ElMessage.success('Рамка обновлена');
            }
        });
    } else {
        form.post(route('admin.avatar-frames.store'), {
            onSuccess: () => {
                isDialogVisible.value = false;
                ElMessage.success('Рамка добавлена');
            }
        });
    }
};

const deleteFrame = (frame) => {
    ElMessageBox.confirm('Удалить эту рамку навсегда?', 'Внимание', {
        type: 'warning'
    }).then(() => {
        router.delete(route('admin.avatar-frames.destroy', frame.id), {
            onSuccess: () => ElMessage.success('Рамка удалена')
        });
    }).catch(() => {});
};
</script>

<template>
    <Head title="Рамки аватарок" />

    <AdminLayout>
        <div class="frames-page">
            <div class="header">
                <h2>Управление рамками</h2>
                <el-button type="primary" :icon="Plus" @click="openCreateDialog">
                    Добавить рамку
                </el-button>
            </div>

            <el-table :data="frames" border class="frames-table">
                <el-table-column label="Картинка" width="120" align="center">
                    <template #default="{ row }">
                        <div class="frame-preview">
                            <!-- Имитируем аватарку для наглядности -->
                            <div class="mock-avatar"></div>
                            <img :src="row.image_url" alt="" class="frame-img" />
                        </div>
                    </template>
                </el-table-column>
                
                <el-table-column prop="name" label="Название" />
                <el-table-column prop="type" label="Тип" width="150">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'free'" type="info">Базовая</el-tag>
                        <el-tag v-else-if="row.type === 'paid'" type="success">Платная</el-tag>
                        <el-tag v-else-if="row.type === 'achievement'" type="warning">Ачивка</el-tag>
                        <el-tag v-else>Промо</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Условие / Цена">
                    <template #default="{ row }">
                        <span v-if="row.type === 'paid'">{{ row.price }} ₽</span>
                        <span v-else-if="row.type === 'achievement'">
                            {{ conditions.find(c => c.key === row.condition_class)?.description || row.condition_class }}
                        </span>
                        <span v-else>—</span>
                    </template>
                </el-table-column>
                <el-table-column label="Статус" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'">
                            {{ row.is_active ? 'Активна' : 'Отключена' }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Действия" width="120" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button type="primary" size="small" :icon="Edit" @click="openEditDialog(row)" />
                            <el-button type="danger" size="small" :icon="Delete" @click="deleteFrame(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <el-dialog v-model="isDialogVisible" :title="editingFrame ? 'Редактировать рамку' : 'Новая рамка'" width="500px" append-to-body>
                <el-form :model="form" label-position="top">
                    <el-form-item label="Название" required>
                        <el-input v-model="form.name" />
                        <div v-if="form.errors.name" class="el-form-item__error">{{ form.errors.name }}</div>
                    </el-form-item>

                    <el-form-item label="Тип получения" required>
                        <el-select v-model="form.type" style="width: 100%;">
                            <el-option label="Базовая (Доступна всем)" value="free" />
                            <el-option label="Платная" value="paid" />
                            <el-option label="За достижение" value="achievement" />
                            <el-option label="Промо-код" value="promo" />
                        </el-select>
                    </el-form-item>

                    <el-form-item v-if="form.type === 'paid'" label="Цена (₽)">
                        <el-input-number v-model="form.price" :min="0" />
                    </el-form-item>

                    <el-form-item v-if="form.type === 'achievement'" label="Условие получения (Класс)">
                        <el-select v-model="form.condition_class" style="width: 100%;">
                            <el-option v-for="c in conditions" :key="c.key" :label="c.description" :value="c.key" />
                        </el-select>
                        <div v-if="form.errors.condition_class" class="el-form-item__error">{{ form.errors.condition_class }}</div>
                    </el-form-item>

                    <el-form-item label="Изображение рамки (PNG, прозрачный центр)" required>
                        <el-upload
                            class="frame-uploader"
                            :auto-upload="false"
                            :show-file-list="false"
                            accept=".png"
                            @change="handleImageChange"
                        >
                            <img v-if="imagePreview" :src="imagePreview" class="uploaded-preview" />
                            <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                        </el-upload>
                        <div v-if="form.errors.image" class="el-form-item__error">{{ form.errors.image }}</div>
                    </el-form-item>

                    <el-form-item label="Активность">
                        <el-switch v-model="form.is_active" active-text="Доступна для надевания" />
                    </el-form-item>
                </el-form>

                <template #footer>
                    <el-button @click="isDialogVisible = false">Отмена</el-button>
                    <el-button type="primary" :loading="form.processing" @click="saveFrame">
                        Сохранить
                    </el-button>
                </template>
            </el-dialog>
        </div>
    </AdminLayout>
</template>

<style scoped>
.frames-page {
    padding: 20px;
    background: var(--bg-color);
    min-height: 100vh;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.frames-table {
    width: 100%;
}
.frame-preview {
    width: 60px;
    height: 60px;
    margin: 0 auto;
    position: relative;
    border-radius: 50%; /* Для аватарки внутри */
}
.mock-avatar {
    position: absolute;
    top: 5px;
    left: 5px;
    right: 5px;
    bottom: 5px;
    background: #333;
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

.frame-uploader {
    border: 1px dashed var(--el-border-color);
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: var(--el-transition-duration-fast);
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #111;
}

.frame-uploader:hover {
    border-color: var(--el-color-primary);
}

.uploader-icon {
    font-size: 28px;
    color: #8c939d;
}
.uploaded-preview {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
