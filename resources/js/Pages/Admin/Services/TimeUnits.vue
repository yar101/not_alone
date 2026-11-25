<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminTable from '@/Components/Admin/UI/AdminTable.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import ServicesNavTabs from '@/Components/Admin/ServicesNavTabs.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    timeUnits: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    name_ru:    '',
    name_en:    '',
    sort_order: 0,
    is_active:  true,
});

function openAdd() {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    showForm.value = true;
}

function openEdit(unit) {
    editingId.value = unit.id;
    form.name_ru    = unit.name_ru ?? '';
    form.name_en    = unit.name_en ?? '';
    form.sort_order = unit.sort_order ?? 0;
    form.is_active  = Boolean(unit.is_active);
    showForm.value  = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.services.time-units.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.services.time-units.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroyUnit(id) {
    ElMessageBox.confirm('Удалить эту единицу времени?', 'Удаление', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.services.time-units.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ServicesNavTabs active="time-units" />

        <!-- Header -->
        <AdminPageHeader
            title="Единицы времени"
            subtitle="Справочник единиц тарификации услуг (минута, час, день, катка)"
            :count="timeUnits?.length"
        >
            <template #actions>
                <AdminButton variant="primary" @click="openAdd">
                    <template #icon><el-icon><Plus /></el-icon></template>
                    Добавить единицу
                </AdminButton>
            </template>
        </AdminPageHeader>

        <!-- Table -->
        <AdminTable :empty="!timeUnits?.length" empty-title="Единиц времени пока нет">
            <template #header>
                <tr>
                    <th class="py-3 px-4 w-20">Порядок</th>
                    <th class="py-3 px-4">Название (RU)</th>
                    <th class="py-3 px-4">Название (EN)</th>
                    <th class="py-3 px-4">Статус</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="unit in timeUnits"
                :key="unit.id"
                class="hover:bg-zinc-800/40 transition-colors"
            >
                <td class="py-3 px-4 text-xs font-mono text-zinc-500">
                    {{ unit.sort_order }}
                </td>

                <td class="py-3 px-4 font-semibold text-zinc-100">
                    {{ unit.name_ru }}
                </td>

                <td class="py-3 px-4 text-xs text-zinc-400">
                    {{ unit.name_en || '—' }}
                </td>

                <td class="py-3 px-4">
                    <AdminBadge :variant="unit.is_active ? 'success' : 'neutral'" size="sm">
                        {{ unit.is_active ? 'Активна' : 'Скрыта' }}
                    </AdminBadge>
                </td>

                <td class="py-3 px-4 text-right whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1.5">
                        <AdminButton variant="secondary" size="sm" @click="openEdit(unit)">
                            <template #icon><el-icon :size="12"><Edit /></el-icon></template>
                            Изменить
                        </AdminButton>
                        <AdminButton variant="ghost" size="sm" class="text-rose-400 hover:text-rose-300" @click="destroyUnit(unit.id)">
                            <template #icon><el-icon :size="12"><Delete /></el-icon></template>
                        </AdminButton>
                    </div>
                </td>
            </tr>
        </AdminTable>

        <!-- Modal -->
        <AdminModal
            :show="showForm"
            :title="editingId ? 'Изменить единицу времени' : 'Добавить единицу времени'"
            @close="closeForm"
        >
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Название (RU) *</label>
                    <AdminInput
                        v-model="form.name_ru"
                        placeholder="Например: 1 час, 30 минут, 1 игра..."
                        :error="form.errors.name_ru"
                        required
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Название (EN)</label>
                    <AdminInput
                        v-model="form.name_en"
                        placeholder="Например: 1 hour, 30 mins, 1 match..."
                        :error="form.errors.name_en"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Порядок сортировки</label>
                    <AdminInput
                        v-model="form.sort_order"
                        type="number"
                        placeholder="0"
                    />
                </div>

                <div>
                    <label class="flex items-center gap-2 text-xs text-zinc-300 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-violet-600 focus:ring-violet-500"
                        />
                        <span>Единица времени активна</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <AdminButton variant="ghost" @click="closeForm">Отмена</AdminButton>
                <AdminButton variant="primary" :loading="form.processing" @click="submit">
                    {{ editingId ? 'Сохранить изменения' : 'Создать' }}
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
