<script setup>
import { ref, computed } from 'vue';
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
import CoinIcon from '@/Components/Common/CoinIcon.vue';
import { formatMoney } from '@/Utils/money';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    limits:     Array,
    categories: Array,
});

const showForm  = ref(false);
const editingId = ref(null);

const form = useForm({
    category_id:          '',
    max_price:            '',
    low_rating_max_price: '',
});

function getCategoryName(cat) {
    if (!cat) return '—';
    if (typeof cat.name === 'object') return cat.name?.ru || cat.name?.en || '—';
    return cat.name_ru || cat.name || '—';
}

function openAdd() {
    editingId.value            = null;
    form.category_id          = '';
    form.max_price             = '';
    form.low_rating_max_price = '';
    showForm.value             = true;
}

function openEdit(limit) {
    editingId.value            = limit.id;
    form.category_id          = limit.category_id;
    form.max_price             = limit.max_price ?? '';
    form.low_rating_max_price = limit.low_rating_max_price ?? '';
    showForm.value             = true;
}

function closeForm() {
    showForm.value  = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingId.value) {
        form.patch(route('admin.services.price-limits.update', editingId.value), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    } else {
        form.post(route('admin.services.price-limits.store'), {
            preserveScroll: true,
            onSuccess: closeForm,
        });
    }
}

function destroyLimit(id) {
    ElMessageBox.confirm('Удалить этот лимит цены?', 'Удаление лимита', {
        confirmButtonText: 'Удалить',
        cancelButtonText: 'Отмена',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(route('admin.services.price-limits.destroy', id), { preserveScroll: true });
    }).catch(() => {});
}
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Sub-navigation Tabs -->
        <ServicesNavTabs active="price-limits" />

        <!-- Header -->
        <AdminPageHeader
            title="Лимиты цен на услуги"
            subtitle="Ограничения максимальной стоимости услуг по категориям для защиты клиентов и антифрода"
            :count="limits?.length"
        >
            <template #actions>
                <AdminButton variant="primary" @click="openAdd">
                    <template #icon><el-icon><Plus /></el-icon></template>
                    Добавить лимит
                </AdminButton>
            </template>
        </AdminPageHeader>

        <!-- Table -->
        <AdminTable :empty="!limits?.length" empty-title="Лимиты цен ещё не настроены">
            <template #header>
                <tr>
                    <th class="py-3 px-4">Категория</th>
                    <th class="py-3 px-4">Базовый лимит (рейтинг ≥ 30)</th>
                    <th class="py-3 px-4">Лимит для новичков (рейтинг &lt; 30)</th>
                    <th class="py-3 px-4 text-right">Действия</th>
                </tr>
            </template>

            <tr
                v-for="l in limits"
                :key="l.id"
                class="hover:bg-zinc-800/40 transition-colors"
            >
                <td class="py-3 px-4 font-semibold text-zinc-100">
                    {{ getCategoryName(l.category) }}
                </td>

                <td class="py-3 px-4">
                    <div class="flex items-center gap-1.5 font-bold text-zinc-100 text-xs">
                        <span>до {{ formatMoney(l.max_price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>
                </td>

                <td class="py-3 px-4">
                    <div class="flex items-center gap-1.5 font-bold text-amber-300 text-xs">
                        <span>до {{ formatMoney(l.low_rating_max_price) }}</span>
                        <CoinIcon color="blue" size="xs" />
                    </div>
                    <div class="text-[10px] text-zinc-500">При рейтинге айдола &lt; 30 баллов</div>
                </td>

                <td class="py-3 px-4 text-right whitespace-nowrap">
                    <div class="flex items-center justify-end gap-1.5">
                        <AdminButton variant="secondary" size="sm" @click="openEdit(l)">
                            <template #icon><el-icon :size="12"><Edit /></el-icon></template>
                            Изменить
                        </AdminButton>
                        <AdminButton variant="ghost" size="sm" class="text-rose-400 hover:text-rose-300" @click="destroyLimit(l.id)">
                            <template #icon><el-icon :size="12"><Delete /></el-icon></template>
                        </AdminButton>
                    </div>
                </td>
            </tr>
        </AdminTable>

        <!-- Modal -->
        <AdminModal
            :show="showForm"
            :title="editingId ? 'Изменить лимит цены' : 'Добавить лимит цены'"
            @close="closeForm"
        >
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Категория услуг *</label>
                    <select
                        v-model="form.category_id"
                        class="block w-full bg-zinc-950 border border-zinc-800 text-zinc-100 rounded-lg text-xs py-2.5 px-3 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        required
                    >
                        <option value="" disabled selected>Выберите категорию...</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">
                            {{ getCategoryName(c) }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Максимальная цена (монеты 🪙) *</label>
                    <AdminInput
                        v-model="form.max_price"
                        type="number"
                        placeholder="Например: 5000"
                        :error="form.errors.max_price"
                        required
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Лимит для айдолов с низким рейтингом (&lt; 30 баллов) *</label>
                    <AdminInput
                        v-model="form.low_rating_max_price"
                        type="number"
                        placeholder="Например: 1500"
                        :error="form.errors.low_rating_max_price"
                        required
                    />
                    <p class="text-[11px] text-zinc-500 mt-1">Ограничивает прайс для новичков и исполнителей со штрафами.</p>
                </div>
            </form>

            <template #footer>
                <AdminButton variant="ghost" @click="closeForm">Отмена</AdminButton>
                <AdminButton variant="primary" :loading="form.processing" @click="submit">
                    {{ editingId ? 'Сохранить изменения' : 'Создать лимит' }}
                </AdminButton>
            </template>
        </AdminModal>
    </div>
</template>
