<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserPickerModal from '@/Components/Admin/UserPickerModal.vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import AdminButton from '@/Components/Admin/UI/AdminButton.vue';
import AdminBadge from '@/Components/Admin/UI/AdminBadge.vue';
import AdminModal from '@/Components/Admin/UI/AdminModal.vue';
import AdminInput from '@/Components/Admin/UI/AdminInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { ElMessage } from 'element-plus';

const { transChoice } = useTranslations();

defineOptions({ layout: AdminLayout });

const props = defineProps({
    broadcasts: [Array, Object],
});

const broadcastList = computed(() => {
    return Array.isArray(props.broadcasts) ? props.broadcasts : (props.broadcasts?.data ?? []);
});

const form = useForm({
    title: { ru: '', en: '' },
    body: { ru: '', en: '' },
    target: 'all',
    target_user_id: '',
    target_filters: null,
});

const activeLang = ref('ru');

const targetOptions = [
    { value: 'all',      label: 'Все пользователи' },
    { value: 'user',     label: 'Конкретный пользователь' },
    { value: 'filtered', label: 'По фильтру аудитории' },
];

function submit() {
    form.post(route('admin.messages.store'), {
        onSuccess: () => {
            form.reset();
            selectedUser.value = null;
            selectedFilteredCount.value = null;
            ElMessage.success('Рассылка успешно отправлена');
        },
        onError: () => {
            ElMessage.error('Пожалуйста, заполните обязательные поля');
        }
    });
}

// ── Detail modal ──────────────────────────────────────────────────────────
const detailBroadcast = ref(null);

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('ru', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString('ru', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

// ── Picker state ──────────────────────────────────────────────────────────
const showPicker = ref(false);
const showFilteredPicker = ref(false);
const selectedUser = ref(null);
const selectedFilteredCount = ref(null);

function selectUser(user) {
    selectedUser.value = user;
    selectedFilteredCount.value = null;
    form.target_user_id = user.id;
    form.target_filters = null;
}

function selectAllFiltered({ filters, total }) {
    selectedFilteredCount.value = total;
    selectedUser.value = null;
    form.target_user_id = '';
    form.target_filters = Object.keys(filters).length ? filters : {};
}

function clearSelection() {
    selectedUser.value = null;
    selectedFilteredCount.value = null;
    form.target_user_id = '';
    form.target_filters = null;
}

function filterLabel(key, value) {
    const labels = {
        is_idol: { '1': 'Айдолы', '0': 'Не айдолы' },
        gender: { male: 'Мужчины', female: 'Женщины' },
    };
    if (labels[key]) return labels[key][value] ?? value;
    if (key === 'age_from') return `от ${value} ${transChoice('search.age.years', value)}`;
    if (key === 'age_to') return `до ${value} ${transChoice('search.age.years', value)}`;
    if (key === 'registered_from') return `рег. с ${value}`;
    if (key === 'registered_to') return `рег. до ${value}`;
    if (key === 'q') return `«${value}»`;
    return value;
}

function targetLabel(b) {
    if (b.target === 'all') return 'Все пользователи';
    if (b.target === 'user') {
        return b.target_user
            ? (b.target_user.name || b.target_user.email)
            : `#${b.target_user_id}`;
    }
    if (b.target === 'filtered') {
        const f = b.target_filters;
        if (!f || !Object.keys(f).length) return 'По фильтру (все)';
        return 'По фильтру';
    }
    return b.target;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <AdminPageHeader
            title="Системные рассылки"
            subtitle="Массовые и таргетированные системные уведомления для пользователей и айдолов"
        />

        <!-- 2-Column Split: Compose Form (Left 5 cols) + History (Right 7 cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- Left: Compose Form -->
            <div class="lg:col-span-5 bg-zinc-900/60 border border-zinc-800 rounded-xl p-5 shadow-sm space-y-4">
                <div class="border-b border-zinc-800 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100 uppercase tracking-wider">Новая рассылка</h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Отправка системного пуша и уведомления</p>
                    </div>

                    <!-- Lang switcher -->
                    <div class="flex items-center gap-1 bg-zinc-950 p-1 rounded-lg border border-zinc-800">
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded text-xs font-semibold transition-colors"
                            :class="activeLang === 'ru' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                            @click="activeLang = 'ru'"
                        >
                            RU
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded text-xs font-semibold transition-colors"
                            :class="activeLang === 'en' ? 'bg-violet-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200'"
                            @click="activeLang === 'en'"
                        >
                            EN
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- RU Fields -->
                    <div v-show="activeLang === 'ru'" class="space-y-4">
                        <AdminInput
                            v-model="form.title.ru"
                            label="Заголовок (RU)"
                            placeholder="Важное системное уведомление..."
                            :error="form.errors['title.ru']"
                            :required="activeLang === 'ru'"
                        />
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Текст сообщения (RU)</label>
                            <textarea
                                v-model="form.body.ru"
                                rows="5"
                                placeholder="Текст уведомления для пользователей..."
                                class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-200 outline-none focus:border-violet-500 resize-none leading-relaxed"
                                :required="activeLang === 'ru'"
                            ></textarea>
                            <p v-if="form.errors['body.ru']" class="text-xs text-rose-400 mt-1">{{ form.errors['body.ru'] }}</p>
                        </div>
                    </div>

                    <!-- EN Fields -->
                    <div v-show="activeLang === 'en'" class="space-y-4">
                        <AdminInput
                            v-model="form.title.en"
                            label="Заголовок (EN)"
                            placeholder="Important system update..."
                            :error="form.errors['title.en']"
                            :required="activeLang === 'en'"
                        />
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Текст сообщения (EN)</label>
                            <textarea
                                v-model="form.body.en"
                                rows="5"
                                placeholder="Notification message in English..."
                                class="w-full bg-zinc-950 border border-zinc-800 rounded-lg p-3 text-xs text-zinc-200 outline-none focus:border-violet-500 resize-none leading-relaxed"
                                :required="activeLang === 'en'"
                            ></textarea>
                            <p v-if="form.errors['body.en']" class="text-xs text-rose-400 mt-1">{{ form.errors['body.en'] }}</p>
                        </div>
                    </div>

                    <!-- Audience select -->
                    <div>
                        <label class="block text-xs font-semibold uppercase text-zinc-400 mb-1.5">Целевая аудитория</label>
                        <select
                            v-model="form.target"
                            @change="clearSelection"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 outline-none focus:border-violet-500"
                        >
                            <option v-for="opt in targetOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Single user picker -->
                    <div v-if="form.target === 'user'" class="p-3 bg-zinc-950/60 border border-zinc-800 rounded-lg">
                        <div v-if="selectedUser" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-violet-500/20 text-violet-300 font-bold flex items-center justify-center text-xs">
                                    {{ (selectedUser.name || selectedUser.email || '?')[0].toUpperCase() }}
                                </div>
                                <div class="text-xs">
                                    <div class="font-medium text-zinc-200">{{ selectedUser.name || '—' }}</div>
                                    <div class="text-zinc-500 text-[11px]">{{ selectedUser.email }}</div>
                                </div>
                            </div>
                            <button type="button" @click="showPicker = true" class="text-xs text-violet-400 hover:text-violet-300 font-medium">
                                Изменить
                            </button>
                        </div>
                        <div v-else>
                            <AdminButton type="button" variant="outline" size="sm" class="w-full" @click="showPicker = true">
                                Выбрать пользователя...
                            </AdminButton>
                        </div>
                        <p v-if="form.errors.target_user_id" class="text-xs text-rose-400 mt-1">{{ form.errors.target_user_id }}</p>
                    </div>

                    <!-- Filtered audience picker -->
                    <div v-if="form.target === 'filtered'" class="p-3 bg-zinc-950/60 border border-zinc-800 rounded-lg">
                        <div v-if="selectedFilteredCount !== null" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-emerald-400">{{ selectedFilteredCount.toLocaleString('ru') }} получателей</span>
                                <button type="button" @click="showFilteredPicker = true" class="text-xs text-violet-400 hover:text-violet-300 font-medium">
                                    Настроить фильтры
                                </button>
                            </div>
                            <div v-if="form.target_filters && Object.keys(form.target_filters).length" class="flex flex-wrap gap-1">
                                <span
                                    v-for="(val, key) in form.target_filters"
                                    :key="key"
                                    class="text-[10px] px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700"
                                >
                                    {{ filterLabel(key, val) }}
                                </span>
                            </div>
                            <div v-else class="text-[11px] text-zinc-500 italic">Фильтры не выбраны — все пользователи</div>
                        </div>
                        <div v-else>
                            <AdminButton type="button" variant="outline" size="sm" class="w-full" @click="showFilteredPicker = true">
                                Настроить фильтры аудитории...
                            </AdminButton>
                        </div>
                        <p v-if="form.errors.target_filters" class="text-xs text-rose-400 mt-1">{{ form.errors.target_filters }}</p>
                    </div>

                    <!-- Send button -->
                    <AdminButton
                        type="submit"
                        variant="primary"
                        size="md"
                        class="w-full"
                        :loading="form.processing"
                    >
                        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Отправить рассылку
                    </AdminButton>
                </form>
            </div>

            <!-- Right: Broadcasts History (7 cols) -->
            <div class="lg:col-span-7 bg-zinc-900/60 border border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-3.5 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">История рассылок</h3>
                        <p class="text-[11px] text-zinc-500">Ранее отправленные сообщения</p>
                    </div>
                    <span class="text-xs text-zinc-500 font-medium">Всего: {{ broadcastList.length }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-zinc-950/60 text-zinc-400 font-semibold uppercase tracking-wider border-b border-zinc-800">
                            <tr>
                                <th class="px-4 py-3 w-12">#</th>
                                <th class="px-4 py-3 w-28">Дата</th>
                                <th class="px-4 py-3">Заголовок</th>
                                <th class="px-4 py-3">Аудитория</th>
                                <th class="px-4 py-3">Отправил</th>
                                <th class="px-4 py-3 text-right w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <tr
                                v-for="b in broadcastList"
                                :key="b.id"
                                class="hover:bg-zinc-800/20 transition-colors cursor-pointer"
                                @click="detailBroadcast = b"
                            >
                                <td class="px-4 py-3 font-mono text-zinc-500">{{ b.id }}</td>

                                <td class="px-4 py-3 font-mono text-zinc-400 whitespace-nowrap">
                                    {{ formatDateShort(b.created_at) }}
                                </td>

                                <td class="px-4 py-3 max-w-xs">
                                    <div class="font-medium text-zinc-200 truncate">{{ b.title.ru }}</div>
                                    <div v-if="b.title.en" class="text-[11px] text-zinc-500 truncate mt-0.5">{{ b.title.en }}</div>
                                </td>

                                <td class="px-4 py-3">
                                    <AdminBadge
                                        :variant="b.target === 'all' ? 'info' : (b.target === 'user' ? 'warning' : 'secondary')"
                                        size="sm"
                                    >
                                        {{ targetLabel(b) }}
                                    </AdminBadge>
                                </td>

                                <td class="px-4 py-3 text-zinc-400 font-medium">
                                    {{ b.admin?.name ?? '—' }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <button
                                        class="p-1 rounded text-zinc-500 hover:text-zinc-200 transition-colors"
                                        title="Просмотреть детали"
                                    >
                                        →
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!broadcastList.length">
                                <td colspan="6" class="px-4 py-12 text-center text-xs text-zinc-500 italic">
                                    Рассылок пока нет.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="broadcasts?.links" class="p-3 border-t border-zinc-800">
                    <AdminPagination :pagination="broadcasts" />
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <AdminModal
            :open="Boolean(detailBroadcast)"
            :title="`Рассылка #${detailBroadcast?.id}`"
            description="Подробные параметры и содержимое отправленного сообщения"
            size="xl"
            @close="detailBroadcast = null"
        >
            <div v-if="detailBroadcast" class="space-y-4 py-2">
                <!-- 2-column language content -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-zinc-950/60 border border-zinc-800 rounded-xl space-y-2">
                        <div class="text-[10px] font-semibold uppercase text-violet-400 font-mono">Русская версия (RU)</div>
                        <h4 class="text-sm font-semibold text-zinc-100">{{ detailBroadcast.title.ru }}</h4>
                        <p class="text-xs text-zinc-300 leading-relaxed whitespace-pre-wrap">{{ detailBroadcast.body.ru }}</p>
                    </div>

                    <div class="p-4 bg-zinc-950/60 border border-zinc-800 rounded-xl space-y-2">
                        <div class="text-[10px] font-semibold uppercase text-violet-400 font-mono">English Version (EN)</div>
                        <h4 class="text-sm font-semibold text-zinc-100">{{ detailBroadcast.title.en || '—' }}</h4>
                        <p class="text-xs text-zinc-300 leading-relaxed whitespace-pre-wrap">{{ detailBroadcast.body.en || '—' }}</p>
                    </div>
                </div>

                <!-- Meta info -->
                <div class="p-3 bg-zinc-950/40 border border-zinc-800 rounded-lg grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <span class="text-zinc-500 block">Получатели:</span>
                        <span class="font-medium text-zinc-200">{{ targetLabel(detailBroadcast) }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Отправитель:</span>
                        <span class="font-medium text-zinc-200">{{ detailBroadcast.admin?.name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 block">Дата отправки:</span>
                        <span class="font-mono text-zinc-400">{{ formatDate(detailBroadcast.created_at) }}</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <AdminButton variant="outline" size="sm" @click="detailBroadcast = null">Закрыть</AdminButton>
                </div>
            </template>
        </AdminModal>

        <!-- User Picker: Single user -->
        <UserPickerModal
            v-model="showPicker"
            title="Выбор пользователя"
            @select="selectUser"
        />

        <!-- User Picker: Filtered audience -->
        <UserPickerModal
            v-model="showFilteredPicker"
            title="Выбор аудитории"
            :show-select-all="true"
            @select="selectUser"
            @select-all="selectAllFiltered"
        />
    </div>
</template>
