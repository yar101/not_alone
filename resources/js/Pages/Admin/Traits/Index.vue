<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { Plus, EditPen, Delete } from '@element-plus/icons-vue';
import { ElMessageBox } from 'element-plus';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    traits: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    name_ru:    '',
    name_en:    '',
    sort_order: 0,
});

function openAdd() {
    editingId.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(trait) {
    editingId.value    = trait.id;
    form.name_ru       = trait.name_ru;
    form.name_en       = trait.name_en ?? '';
    form.sort_order    = trait.sort_order;
    showForm.value     = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.traits.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.traits.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroy(id) {
    ElMessageBox.confirm('Удалить эту черту характера?', 'Удаление черты характера', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.traits.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-100">Черты характера</h1>
                <p class="text-xs text-zinc-400 mt-0.5">
                    Справочник черт характера для анкет айдолов и алгоритма матчинга. Всего: {{ traits.length }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('admin.traits.suggestions.index')"
                    class="px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-xs font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 transition"
                >
                    Предложения пользователей →
                </Link>
                <AdminButton variant="primary" size="sm" @click="openAdd">
                    <el-icon class="mr-1.5"><Plus /></el-icon>
                    Добавить черту
                </AdminButton>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-950/40 text-zinc-400">
                            <th class="py-3 px-4 font-medium w-16">Порядок</th>
                            <th class="py-3 px-4 font-medium">Название (RU)</th>
                            <th class="py-3 px-4 font-medium">Название (EN)</th>
                            <th class="py-3 px-4 font-medium text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                        <tr v-if="!traits.length">
                            <td colspan="4" class="py-12 text-center text-zinc-500">
                                Черты характера пока не добавлены
                            </td>
                        </tr>
                        <tr
                            v-for="t in traits"
                            :key="t.id"
                            class="hover:bg-zinc-800/30 transition"
                        >
                            <td class="py-3 px-4 font-mono text-zinc-500">{{ t.sort_order }}</td>
                            <td class="py-3 px-4 font-medium text-zinc-100">{{ t.name_ru }}</td>
                            <td class="py-3 px-4 text-zinc-400">{{ t.name_en || '—' }}</td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                                        title="Редактировать"
                                        @click="openEdit(t)"
                                    >
                                        <el-icon><EditPen /></el-icon>
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1.5 rounded-lg border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-rose-400 hover:bg-zinc-800 transition"
                                        title="Удалить"
                                        @click="destroy(t.id)"
                                    >
                                        <el-icon><Delete /></el-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <AdminModal
            :show="showForm"
            :title="editingId ? 'Редактировать черту' : 'Новая черта характера'"
            @close="closeForm"
        >
            <form @submit.prevent="submit" class="space-y-4 text-xs">
                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название на русском <span class="text-rose-400">*</span></label>
                    <input
                        v-model="form.name_ru"
                        type="text"
                        required
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                    <p v-if="form.errors.name_ru" class="text-rose-400 text-[11px]">{{ form.errors.name_ru }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Название на английском</label>
                    <input
                        v-model="form.name_en"
                        type="text"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="font-medium text-zinc-300">Порядок сортировки</label>
                    <input
                        v-model.number="form.sort_order"
                        type="number"
                        class="w-full h-9 px-3 rounded-lg border border-zinc-800 bg-zinc-950 text-xs font-mono text-zinc-100 focus:outline-none focus:border-violet-500"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-800">
                    <AdminButton variant="outline" size="sm" type="button" @click="closeForm">
                        Отмена
                    </AdminButton>
                    <AdminButton variant="primary" size="sm" type="submit" :loading="form.processing">
                        Сохранить
                    </AdminButton>
                </div>
            </form>
        </AdminModal>
    </div>
</template>
